# Deklarativní vrstva agend — plán

Schváleno 11. 11. 2026. Navazuje na [architekturu](architektura.md) § 4.

## Cíl

Jednoduchá agenda = jedna třída: seznam, formulář, oprávnění. Zbytek — vzhled podle návrhu,
ukládání, validaci, log změn, oprávnění, navigaci — dělá balík. Podle inventury PSE tím padne 21
jednoduchých agend a základ dalších 5; Menu, Importy, Emise a Dokumentace budou vlastní obrazovky
ze stejných komponent.

## Jak to vypadá

V balíku (`Backoffice\Resource`):

- `ResourceDefinition` — předek agendy: `meta()`, `list()`, `form()`, volitelně `actions()`, `panels()`
- `ListBuilder`, `FormBuilder` — popis sloupců, filtrů a polí
- `ResourcePresenter` — jeden společný presenter pro všechny agendy (`<prefix>/r/<agenda>[/<akce>][/<id>]`)
- komponenty `ListControl`, `FormControl` — použijí je i vlastní obrazovky

V projektu jen popisy, např. `app/Stock/Backoffice/HolidayResource.php`:

```php
final class HolidayResource extends ResourceDefinition
{
	public function __construct(private readonly HolidayRepository $holidays)
	{
	}

	public function meta(Meta $m): void
	{
		$m->slug('holiday')->title('Neobchodní dny')->repository($this->holidays)
			->replaces(':Stock:Admin:Holiday');
	}

	public function list(ListBuilder $l): void
	{
		$l->date('date', 'Datum')->sortable()->defaultSort('desc');
		$l->text('name', 'Název')->searchable()->link();
		$l->mutations();
		$l->bulkDelete();
	}

	public function form(FormBuilder $f): void
	{
		$f->date('date', 'Datum')->required();
		$f->localeText('name', 'Název')->required();
		$f->textarea('note', 'Poznámka');
	}
}
```

## Klíčová rozhodnutí

1. **Oprávnění beze změny nastavení.** `replaces(':Stock:Admin:Holiday')` = agenda přebírá práva starého
   presenteru z `admin_permission`; role vidí totéž co dnes.
2. **Přepnutí agendy = jeden řádek konfigurace** (`backoffice: switched: [holiday]`). Položka menu pak
   vede na novou agendu a most přesměruje starou adresu. Vrácení = smazat z `switched`.
3. **Data a tabulky se nemění.** Stávající StORM repozitáře, jazyky z nastavení StORM (`_cs`/`_en`),
   log do `admin_changelog`.
4. **Formuláře = Nette Forms, bez liquiddesign/forms.** Vlastní pole a vzhled podle návrhu.
5. **Obrázky a soubory kompatibilní se starými** (`userfiles/<dir>/origin|detail|thumb`).
6. **Napřed klasické načtení stránky, Naja (AJAX) až tam, kde chybí** (inline editace, přetahování).

## Etapy

| Etapa | Co vrstva umí | Co se v PSE převede | Odhad |
|---|---|---|---|
| **0. Kostra a API** | třídy, společný presenter, registrace agend, práva přes `replaces`, přepnutí menu a přesměrování | — (popis API, Neobchodní dny jen ke čtení na vlastní adrese) | 2–3 dny |
| **1. Minimální CRUD** | seznam: text, datum, odkaz, jazyky; formulář: text, textarea, integer, checkbox, select, date, localeText; založení, úprava, smazání, log | **Neobchodní dny** | ~1 týden |
| **2. Seznamy jako v návrhu** | hledání, filtry + „Aktivní filtry“, řazení, stránkování, výběr + hromadné akce, štítky stavů, inline editace + „Uložit vše“, pořadí přetahováním, „Vrátit“ | Fáze obchodování, Typy události, Kontakty, Rozhodci, Překlady | ~1–1,5 týdne |
| **3. Média a vazby** | upload obrázků a souborů, výběr vazeb s hledáním, ochrana mazání použitých záznamů | Kontaktní osoby, Výroční zprávy, Prezentace, Videa, YouTube playlisty, Rychlé odkazy | ~1,5 týdne |
| **4. Rich text a SEO** | Tiptap místo TinyMCE, blok URL + SEO s přesměrováním | **Slider** (12. 11. 2026 přesunut z etapy 3 — text na slidu potřebuje editor), Textové boxy, Banery, Příběhy, Layouty, Vstupní stránky, Přesměrování | ~1,5 týdne |
| **5. Velké a složené** | rychlé seznamy nad tisíci záznamy, vnořený seznam v detailu | **Novinky** (9 900, ČTK, vazby na emise), Konzultanti, Galerie, Nastavení | ~2 týdny |

Mimo generátor (vlastní obrazovky později): Menu a editor stránek, Importy, Dokumentace, Emise,
Správce souborů, Administrátoři/Role/Log.

## Ověření u každé etapy

- Nette Tester na buildery a ukládání, PHPStan 8, PHPCS
- před přepnutím agendy přečíst její starý kód v PSE i v `liquiddesign/web`
- průchod na instanci pse-admin (seznam, založení, úprava, smazání, práva rolí Editor a Administrátor);
  teprve pak do `switched`

## Rizika

- **HTML z TinyMCE v Tiptapu** — před etapou 4 prohnat všechny texty Tiptapem a porovnat; při velkých
  ztrátách zatím TinyMCE v novém obalu
- **Zpracování obrázků** — starý admin dělá náhledy `detail`/`thumb`; zopakovat přesně
- **Logika schovaná ve vendoru** (`liquiddesign/web`) — synchronizace stránek, mazání souborů

## Otevřené otázky

- adresy: zatím `<prefix>/r/<agenda>`; hezčí (`/neobchodni-dny`) jde přidat později
- Tiptap hned v etapě 4, nebo nejdřív TinyMCE
- „slupka“ pro starý obsah v shellu — před etapou 1, nebo později
