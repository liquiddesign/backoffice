# Architektura liquiddesign/backoffice (koncept)

Koncept k diskusi, 11. 11. 2026. Navazuje na inventuru adminu PSE (`docs/administrace/01-inventura.md` v repu `liquiddesign/pse`). Rozhodnuto: MySQL + StORM
beze změny schématu, Nette + Latte s Vue 3 ostrůvky, převod po agendách vedle starého adminu,
„Pomoc“ do Taktu přes vyměnitelný kanál (projekt v Taktu zatím není).

## 1. Balík a jmenný prostor

**Nový balík, ne `liquiddesign/admin` 3.0.** Během převodu musí v jedné aplikaci běžet starý i nový
admin současně. Composer nainstaluje z jednoho balíku jen jednu verzi, takže major verze téhož balíku by
znamenala převést všech 34 agend naráz. Nový název a nový namespace to obchází a starý balík
zůstane beze změny pro ostatní projekty.

- `liquiddesign/backoffice`, namespace `Backoffice\` (jako `Admin\`, `Web\` u ostatních balíků),
  veřejné repo na GitHubu, Packagist, MIT
- vlastní repo od začátku; PSE ho dostane přes `composer require liquiddesign/backoffice:dev-main`,
  později tagy `0.x`
- PHP 8.2+, Nette 3.2, Latte 3, StORM 2
- rozhodnuto 11. 11. 2026: vlastní balík na Nette, ne LiquidAdmin (Nuxt + Laravel) — PSE, eshop a další
  weby stojí na Nette a StORM a REST pro každou agendu by práci zdvojil

```
backoffice/
  src/
    Shell/          layout, navigace, drobečky, Ctrl K, notifikace, štítek prostředí
    Security/       rozhraní AccessChecker (přihlášení, oprávnění) — ve PSE most na starý admin
    Resource/       deklarativní agendy: ResourceDefinition, ListBuilder, FormBuilder, akce
    Form/           pole (jazykové varianty, rich text, výběr souboru, URL+SEO…)
    Files/          knihovna souborů (náhrada Responsive FileManageru)
    Bridge/         vykreslení starých presenterů do nového shellu
    Assistant/      asistent (jádro, rozhraní pro znalosti a nástroje)
    Help/           „Pomoc“ — požadavek na LQD
    ApiConsumers/   odběratelé API
    DI/BackofficeExtension.php
  assets/           zdroje Vue/TS/SCSS
  dist/             zkompilované assety — součást balíku
  migrations/       migrace vlastních tabulek adm_*
  playground/       ukázková Nette aplikace (viz níže)
```

**Playground** — minimální Nette aplikace s MySQL a ukázkovými agendami podobnými skutečným
(Neobchodní dny, Novinky s jazyky, Slider s obrázky) a se starým `liquiddesign/admin` kvůli mostu.
Má vlastní dev instanci na ide1. Core se vyvíjí a testuje tady, bez PSE — to hlídá, že do něj nic
z PSE nepronikne. Cyklus: změna → playground → push → v PSE `composer update liquiddesign/backoffice`
→ ověření na dev instanci PSE se skutečnými daty.

**Assety se kompilují v balíku a do projektu jdou hotové** (`dist/`). PSE staví frontend pod Node 16
(viz CLAUDE.md), eshop a další weby mají jiné verze — admin tak na build projektu nezávisí. Projekt si
mění jen vzhled přes CSS proměnné.

## 2. Databáze

Stávající tabulky se nemění. Core je čte:

| Tabulka | Použití |
|---|---|
| `admin_administrator`, `admin_role`, `admin_permission`, `admin_administrator_nxn_security_account`, `security_account` | přihlášení, role, oprávnění — **stejná data jako starý admin**, takže jedno přihlášení platí pro oba |
| `admin_changelog` | log změn, zapisují oba adminy |

Nové tabulky jen s prefixem `adm_`, migrace v balíku, projekt je pouští stávajícím `liquiddesign/migrator`:

| Tabulka | Obsah |
|---|---|
| `adm_user_pref` | uživatelské nastavení (počet řádků, sloupce, sbalené menu) |
| `adm_saved_filter` | uložené filtry seznamů |
| `adm_assistant_query`, `adm_assistant_feedback` | log asistenta a hodnocení odpovědí (§ 6) |
| `adm_help_request` | odeslané požadavky „Pomoc“ (§ 7) |
| `adm_api_consumer`, `adm_api_key`, `adm_api_consumer_ip`, `adm_api_usage_daily` | odběratelé API (§ 8) |
| `adm_file`, `adm_file_folder` | knihovna souborů — evidence nad stávajícími soubory v `userfiles/` |

## 3. Shell

- **Navigace** z konfigurace. Čte stávající formát `admin: menu:` z `config/admin.neon` (aby most fungoval
  bez úprav) a přidává klíče `badge` (služba vracející číslo), `permission`, `hidden`.
- **Drobečky, tlačítka, záložky** jako sloty layoutu — přesně ty, které dnes plní `headerLabel`,
  `headerTree`, `displayButtons`, `displayControls`, `tabs`.
- **Ctrl K** — `SearchProvider` rozhraní; každá agenda může přidat zdroj (emise, novinky, stránky).
- **Notifikace** — `NotificationProvider` (např. „Import selhal“, „Stránka čeká na publikaci“).
- **Štítek prostředí** z `config/environments.neon` (DEV / TEST / STAGE; na produkci nic).
- **Přihlášení a oprávnění** přes rozhraní `Backoffice\Security\AccessChecker` (přihlášen?, smí na
  odkaz?, jméno do hlavičky). Výchozí `UserAccessChecker` pustí každého přihlášeného Nette uživatele
  (playground). Projekt se starým adminem zaregistruje `Backoffice\Bridge\LegacyAdminAccessChecker`
  — ten se ptá `Admin\Administrator::isAllowed`, takže platí tytéž účty, role, `admin_permission`
  a Google 2FA jako dnes. Vlastní přihlašovací obrazovka v balíku přijde až s odchodem od starého adminu.

### Most pro staré presentery (hotovo v 0.x)

Společný předek adminových presenterů v projektu dostane trait `Backoffice\Bridge\LegacyShell`.
Ten v `$onStartup` přepne `layoutTemplate` na layout shellu a `contentTemplate` na
`Bridge/templates/content.latte`; shell pak vezme `headerLabel` jako nadpis, `headerTree` jako
drobečky, `displayButtons` do hlavičky a `tabs` + `displayControls` vykreslí v kartě. Obsah agendy
(AdminGrid, AdminForm) zůstává beze změny. Assety starého adminu (jQuery, Bootstrap 4, polyfilly
formulářů) si projekt vytáhne do souboru, který shell vloží do `<head>` (`bridge: head:`); skripty
z konce starého layoutu do `bridge: foot:`. Třídy shellu mají prefix `bo-`, takže se s Bootstrapem nepřou.

Převedená agenda se pak přepne v navigaci na novou routu, stará URL přesměruje.

Assety balíku (`dist/`) vydává `Backoffice:Asset` přímo z vendoru — projekt přidá routu
`Backoffice\Routing\Routes::add($router, '<prefix administrace>')` a nic nekopíruje.

## 4. Deklarativní agenda (Resource)

Pokryje skupinu A z inventury (21 agend) a základ skupiny B. Agenda = jedna třída:

```php
final class HolidayResource extends ResourceDefinition
{
	public function __construct(private HolidayRepository $holidays) {}

	public function meta(Meta $m): void
	{
		$m->title('Neobchodní dny')->repository($this->holidays)->permission('stock.holiday');
	}

	public function list(ListBuilder $l): void
	{
		$l->date('date', 'Datum')->sortable()->defaultSort('desc');
		$l->text('name', 'Název')->localized()->searchable();
		$l->status('active', 'Stav');
		$l->bulkDelete();
	}

	public function form(FormBuilder $f): void
	{
		$f->section('Základní údaje');
		$f->date('date', 'Datum')->required();
		$f->localeText('name', 'Název')->required();   // CS a EN vedle sebe, „Zkopírovat z češtiny“
		$f->checkbox('active', 'Aktivní');
	}
}
```

Registrace v konfiguraci (`backoffice: resources:`) nebo automaticky hledáním `*Resource` jako dnes repozitáře.

**Seznam** — sloupce `text`, `date`, `link`, `image`, `status`, `mutations`, `priority` (přetahováním);
inline editace `checkbox` / `integer` / `text` s „Uložit vše“; výběr + hromadné akce; hledání, filtry
s pruhem „Aktivní filtry“, uložené filtry; stránkování jen nad limit; „Vrátit“ po smazání/skrytí.

**Formulář** — sekce a záložky; boční panely (`Publikace`, `SEO`, `Kontrola odkazů`); Uložit / Uložit
a zavřít / Ctrl S; hlídání neuložených změn; chyby u pole; zápis do `admin_changelog` přes
`getChangedProperties`.

**Vlastní obrazovky** (skupina C a D) jsou běžné Nette presentery v shellu, které používají stejné
komponenty (`ListControl`, `FormControl`, panely) bez deklarativní třídy.

### Pole: starý → nový

| Dnes (počet použití) | Nové pole |
|---|---|
| `addText` (114), `addTextArea` (18), `addInteger` (34), `addCheckbox` (75), `addSelect` (19), `addRadioList` (6) | `text`, `textarea`, `integer`, `checkbox`/`toggle`, `select`, `radio` |
| `addLocaleText` (74), `addLocaleTextArea` (10), `addLocaleCheckbox` (2) | `localeText` … (jazyky vedle sebe) |
| `addLocaleRichEdit` (26), `addLocalePerexEdit` (7) — TinyMCE 4.9 | `localeRichText` — Tiptap, stejné rozšíření jako editor bloků |
| `addImagePicker` (32), `addFilePicker` (3), `addUpload` / `addMultiUpload` (~7) | `image`, `file`, `files` — nad knihovnou souborů |
| `addDataSelect` / `addDataMultiSelect` (9) | `relation`, `relations` (hledání, AJAX) |
| `addPolyfillDate` / `Datetime` (3) | `date`, `datetime` |
| `addPageContainer` (12) | `pageBlock` — URL v jazycích + SEO, přesměrování při změně adresy |

## 5. Frontend

- Server-rendered Latte, částečné překreslení přes Naja (Nette AJAX) — žádné vlastní REST API pro CRUD.
- Vue 3 ostrůvky: strom menu, editor bloků (už existuje — `public/js/pageBuilder`), Ctrl K, asistent,
  knihovna souborů, rich text (Tiptap).
- Build: Vite v balíku → `dist/`. Editor bloků se do balíku nestěhuje hned; zůstává v PSE a později se
  přesune jako samostatný modul.
- Tokeny `--adm-*` z artboardu „Komponenty a tokeny“; světlý i tmavý režim; ikony jedné sady.

## 6. Asistent

Sloučení `app/Assistant` + `app/Feedback` (PSE, větev `feature/page-builder`) a `app/AI` (DN). Obě
implementace mají stejnou kostru — stabilní prefix s `cache_control`, smyčka tool use, vynucený tvar
odpovědi přes `output_config.format`, uzavřený seznam odkazů — liší se jen znalostmi a nástroji.

**V core:**
- `AssistantService` — smyčka nástrojů, prompt caching, structured output, limit kol, ořez odkazů na
  povolené, logování. Postaveno na oficiálním PHP SDK `anthropic-ai/sdk` místo dnešních ručních volání
  přes Guzzle (obě implementace). Ověřit požadavky SDK na verzi PHP.
- Model z konfigurace, výchozí `claude-opus-5-5`, adaptivní thinking (u Opus 5.5 nejde vypnout),
  `effort` nastavený výslovně (výchozí je u tohoto modelu `medium`), u modelů, které to umí, serverový
  `fallbacks` pro odmítnuté dotazy. Nucený `tool_choice` model nepodporuje — zůstává `auto`, jak to
  dnes dělají obě implementace.
- Streamování odpovědi (SSE) podle DN `SseResponse`.
- Widget (Vue): dialog z PSE + bublina a kontext stránky z DN (`TheAssistant.vue`,
  `assistantContextStore`). Kontext — agendu a otevřený záznam — doplní shell sám.
- Log `adm_assistant_query` (sjednocená pole PSE a DN: dotaz, odpověď, místo, nástroje, tokeny, cache,
  doba, chyba, administrátor) a hodnocení `adm_assistant_feedback` + agenda pro jejich procházení.
- Sada evaluačních otázek (vzor DN `eval/questions.json`) — spouští se před změnou promptu nebo modelu.

**Dodává projekt** (rozhraní):
- `KnowledgeSource` — index a obsah dokumentů (PSE: `docs/prirucka`, DN: `knowledge/cards`)
- `AssistantTool` — název, popis, JSON schéma, `run(input, identity)`. **Běží pod právy přihlášeného
  uživatele**; core odfiltruje nástroje, na které role nemá oprávnění.
- `LinkCatalog` — povolené odkazy/akce (DN: `app-map.json`, `ui-actions.json`)
- přepínač zapnutí po prostředí — v PSE asistent potřebuje souhlas PSE

## 7. Pomoc

Tlačítko „Pomoc“ v shellu → formulář: popis, automatický kontext (URL, agenda, záznam, uživatel,
prohlížeč, prostředí, verze), snímek obrazovky, výběr prvku na stránce, až 3 upřesňující otázky od AI
(`FeedbackAssistant::clarifyingQuestions`). Z asistenta: „Nepomohlo — předat Liquid Designu“ přiloží
konverzaci.

- `HelpChannel` rozhraní: `send(HelpRequest): HelpReceipt`
- `EmailHelpChannel` — teď (přenos `FeedbackService`); `TaktHelpChannel` — až vznikne projekt v Taktu;
  `AsanaHelpChannel` z PSE `AsanaClient` jen pokud ho někdo bude chtít
- víc kanálů najednou: první je zdroj pravdy, ostatní best-effort (jako dnes e-mail + Asana)
- `adm_help_request` — co a kdy kdo poslal a kam to došlo; administrátor vidí své požadavky

## 8. Odběratelé API

Dnes: whitelist IP v `%access.trusted%` (sdílený s dalšími účely) obchází `RateLimiter` úplně, ostatní
dostanou anonymní limity. Kdo API používá, není nikde zapsané.

**Agenda „Odběratelé API“:** název, kontaktní osoba a e-mail, poznámka, úroveň limitu, povolené
skupiny endpointů (FrontApi, RSS, …), platnost od–do, aktivní; klíče (zobrazí se jednou, v DB jen hash
a prefix, poslední použití, zneplatnění); povolené IP / rozsahy; statistika volání po dnech.

**Za běhu:** `ApiConsumerResolver` určí odběratele podle klíče (`X-Api-Key` nebo `?key=`) a/nebo IP →
`RateLimiter` vezme limity jeho úrovně místo anonymních. **Bez klíče API funguje dál jako dnes**
(veřejné endpointy se nezamykají). Neznámý nebo zneplatněný klíč = anonymní limity, ne chyba, aby se
odběratel neodřízl překlepem — rozhodnutí k potvrzení. Počty volání se sčítají v cache a do
`adm_api_usage_daily` zapisují dávkově, ne každým požadavkem.

**Převod:** z `%access.trusted%` se do odběratelů přesunou jen adresy, které jsou tam kvůli API
(ČTK RSS a další) — VPN LQD a servery zůstanou v konfiguraci, protože slouží i pro sondy, importy a
servisní režim.

## 9. Testy a kvalita

- PHPStan 8 a PHPCS (liquiddesign/codestyle) pro balík; Nette Tester pro builder a resolver
- Playwright: průchod hlavními obrazovkami, vizuální snímky, přístupnost (klávesnice, kontrast)
- každá převedená agenda nejdřív na dev instanci, pak test.pse.cz

## 10. K rozhodnutí

1. Vlastník balíku (Oldřich/Radomír); sladění vzhledu s LiquidAdmin (tokeny, ikony Lucide)?
2. Neznámý klíč odběratele: anonymní limity, nebo odmítnout?
3. Tmavý režim v první verzi, nebo později?
4. Kdy převést editor bloků do balíku (po dokončení `feature/page-builder` v PSE).
