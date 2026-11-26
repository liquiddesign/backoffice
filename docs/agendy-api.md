# Deklarativní agendy — API

Stav: etapa 1 (seznam, formulář, ukládání, mazání) + vlastní obrazovka seznamu (`ListView`). Plán etap: [agendy-plan.md](agendy-plan.md).

## Agenda

Agenda je služba v DI, potomek `Backoffice\Resource\ResourceDefinition`. Závislosti dostane
konstruktorem, popis dodá třemi metodami:

```php
final class HolidayResource extends ResourceDefinition
{
	public function __construct(private readonly HolidayRepository $holidays)
	{
	}

	public function meta(Meta $meta): void
	{
		$meta->slug('holiday')                       // adresa: <prefix>/r/holiday
			->title('Neobchodní dny')                // nadpis seznamu a položka drobečků
			->itemTitle('Neobchodní den')            // „Nový neobchodní den“
			->repository($this->holidays)            // StORM repozitář
			->replaces(':Stock:Admin:Holiday', 'holiday'); // starý presenter + jméno parametru detailu
	}

	public function list(ListBuilder $list): void
	{
		$list->date('date', 'Datum')->defaultSort('desc');
		$list->text('name', 'Název')->searchable()->link();
		$list->mutations();
		$list->bulkDelete();
	}

	public function form(FormBuilder $form): void
	{
		$form->date('date', 'Datum')->required();
		$form->localeText('name', 'Název')->required();
		$form->textarea('note', 'Poznámka');
	}
}
```

Registrace v projektu:

```neon
services:
	- App\Stock\Backoffice\HolidayResource
```

Extension najde všechny služby typu `ResourceDefinition` sama (`typed()`).

## Adresy

`<routePrefix>/r/<slug>` seznam · `/r/<slug>/edit/<id>` detail · `/r/<slug>/new` nový záznam.
Routy přidá extension s `backoffice: routePrefix:`, jinak `Backoffice\Routing\Routes::add()`.

## Oprávnění

`replaces(':Stock:Admin:Holiday')` → agenda se ptá na práva starého presenteru:
seznam `:Stock:Admin:Holiday:default`, úprava `…:detail`, nový `…:new`. Záznamy v `admin_permission`
mají tvar `:Stock:Admin:Holiday:*`, takže role vidí totéž co dnes. Bez `replaces` se ptá na
`:Backoffice:Resource:<akce>`.

## Přepnutí agendy

```neon
backoffice:
	switched: [holiday]
```

- položka menu se starým presenterem vede na `/r/holiday`
- most přesměruje starou adresu (301): `default` → seznam, `new` → nový, `detail?holiday=<id>` → úprava
- vrácení: slug z `switched` smazat

## Seznam (`ListBuilder`)

Tabulka podle návrhu „Seznam“: filtr nahoře, tabulka, lišta dole (výběr, Uložit vše, počet,
„Na stránce“, stránky). Vykresluje ji `ListControl` (komponenta `list` na `ResourcePresenter`).

| Metoda | Co dělá |
|---|---|
| `text(name, label, mutation = null)` | text; jazykový sloupec v aktuálním jazyce, s `mutation` v pevném (`Text CS`, `Text EN`) |
| `date(name, label)` | datum, formát `j. n. Y` (`->format()`) |
| `integer(name, label)` | číslo |
| `checkbox(name, label)` | ano/ne; s `->editable()` přepínač v řádku |
| `status(name, label, [hodnota => [štítek, varianta]])` | štítek stavu (ok, info, warn, danger, brand, mute) |
| `priority(name = 'priority')` | pořadí: číslo v řádku + přetahování za úchyt, výchozí řazení |
| `image(name, label, directory)` | náhled obrázku z `userfiles/<directory>` (40 × 40) |
| `file(name, label, directory)` | jméno souboru jako odkaz ke stažení |
| `computed(key, label, fn)` | spočítaný sloupec bez sloupce v tabulce (počet fotek…); jen zobrazení |
| `tree(parent, siblingOrder)` | záznamy ve stromu (vazba `parent` na stejnou tabulku): výchozí řazení stromem, podřízené odsazené pod nadřazeným, sourozenci podle `siblingOrder` (`['this.priority' => 'ASC']`) |
| `usage(label = 'Použito')` | počet míst, kde se záznam používá (`ResourceDefinition::usages()`), odkaz na jejich seznam |
| `mutations(label = 'Jazyky', column = null)` | štítky jazyků: vyplněné jazykové sloupce, nebo podle booleovského sloupce (`active`) |
| `search(placeholder)` | text v poli „Hledat“; hledá se ve sloupcích s `->searchable()` |
| `filter(name, label, items)` | výběr; bez `->condition()` filtruje `sloupec = hodnota`; `->default()` = bez „Všechny“ |
| `bulkSet(column, value, label)` | hromadná akce nad vybranými řádky (Skrýt, Zobrazit) |
| `bulkDelete()` | výběr řádků + „Smazat“ v liště |
| `defaultSort(column, dir)` / `Column::defaultSort(dir)` | výchozí řazení |
| `perPage(n)` | počet řádků na stránku (výchozí 50; v liště jde přepnout 20/50/100/200) |

Sloupec: `->sortable()` (klik na hlavičku), `->searchable()`, `->link()` (odkaz na stránku záznamu),
`->editable()` (text, číslo, zaškrtávátko), `->mono()`, `->format()`, `->pattern('{control box-%s}')`
(hodnota podle vzoru sprintf), `->externalUrl('https://www.youtube.com/playlist?list=%s')` (hodnota
jako odkaz ven v novém okně), `->value(fn (Entity $row) => …)` (spočítaná hodnota, např. počet
podřízených záznamů — jen zobrazení, nejde řadit, hledat ani upravovat).

- **Stav v adrese:** `list-q`, `list-f[jméno]`, `list-sort`, `list-dir`, `list-page`, `list-perPage`.
  Filtrovaný seznam jde poslat odkazem. „Aktivní filtry“ ukazují hledání a filtry mimo výchozí
  hodnotu; × jeden odebere, „Zrušit vše“ vrátí výchozí stav.
- **Hledání:** `LIKE %text%` přes hledané sloupce, jazykový sloupec ve všech jazycích.
- **Úpravy v řádku:** jeden formulář přes celou tabulku, „Uložit vše“ (nebo Enter, Ctrl S) uloží jen
  změněné hodnoty. Neplatné číslo = neuloží se nic. Změněné řádky jsou zvýrazněné, odchod ze stránky
  s neuloženými změnami se zeptá.
- **Pořadí:** přetažení (nebo šipky na úchytu) jen při řazení podle pořadí vzestupně. Řádky dostanou
  čísla, která na stránce už měly (při shodných hodnotách od nejmenšího po jedné), ukládá se s „Uložit vše“.
- **Vrátit:** po uložení v řádcích, hromadné akci a smazání (i ze stránky záznamu) má flash zpráva
  „Vrátit“. Upravené hodnoty se vrátí, smazané záznamy se založí znovu se stejným ID. Pamatuje se
  posledních 10 akcí v session. Smazání, které odmítne databáze (cizí klíče), se nahlásí a nic nesmaže.
  Vrácení obnoví jen řádek agendy, ne data, která databáze smazala kaskádou.
- **Práva:** úpravy v řádku, hromadné akce a mazání = právo na detail (`:…:detail`).

### Export a import CSV

```php
$list->csv(['fromUrl' => 'Z URL', 'toUrl' => 'Do URL', 'fromMutation' => 'Z mutace'], ['fromUrl', 'fromMutation']);
```

- Nad seznamem přibude **Export CSV** — výsledek hledání a filtrů v aktuálním řazení, bez stránkování
  (signál `list-export!`, soubor `<agenda>-<datum>.csv`, UTF-8 s BOM a středníkem, aby ho otevřel
  český Excel; hodnoty začínající `=`, `+`, `@` dostanou apostrof proti vzorcům).
- S druhým parametrem (klíč) i **Import CSV** (`/r/<agenda>/import`, právo jako na úpravu záznamu):
  sloupce se poznají podle záhlaví i jména sloupce, oddělovač (`;` `,` tab `|`) ze záhlaví, soubor
  v UTF-8 i ve Windows-1250 (Excel). Řádek se stejnými hodnotami klíče upraví existující záznam,
  ostatní se založí, nic se nemaže. Každý řádek projde `importRow()`. Celý import je v transakci:
  první chyba vrátí všechno a hlášení řekne číslo řádku. Výsledek: „X nových, Y upravených, Z beze
  změny“, změny jdou do logu jednotlivě.
- Sloupce jsou sloupce entity bez jazyků.

### Agenda bez zakládání, mazání nebo stránky záznamu

Číselníky z importu (Fáze obchodování, Typy události, Překlady):

```php
$meta->withoutCreate()->withoutDelete()->withoutDetail();
```

Bez stránky záznamu agenda nepotřebuje `form()` (má výchozí prázdnou implementaci).

### Po změně dat

`ResourceDefinition::changed()` se volá po každém uložení, úpravě v řádku, hromadné akci, smazání
a vrácení — např. pro smazání cache, ze které čte web (Překlady).

## Formulář (`FormBuilder`)

`section(title)`, `text`, `textarea`, `integer`, `checkbox`, `select(items)`, `date`,
`localeText`, `localeTextarea`, `richText`, `localeRichText`. Pole: `->required()`, `->description()`, `->items()`, `->default()`,
`->unique(zpráva)` (hodnota se v agendě nesmí opakovat; kontrola při uložení, chyba u pole).

- jazyková pole: kontejner `name[cs]`, `name[en]` vedle sebe; povinný je jen hlavní (první) jazyk
- datum: `<input type="date">`, ukládá se `RRRR-MM-DD`
- prázdný text u sloupce, který smí být `NULL`, se uloží jako `NULL`
- tlačítka „Uložit“ (zůstane na detailu) a „Uložit a zavřít“; Ctrl S = Uložit
- lišta dole hlásí neuložené změny, při odchodu se stránkou se změnami se prohlížeč zeptá
- chyby se ukazují u polí, nahoře souhrnná zpráva


### Obrázek

```php
$form->image('photo', 'Fotka')
	->imageDirectory('contact_persons', static fn (Image $image) => $image->resize(600, 600));
```

- Soubor se uloží jako `<ID záznamu>.<přípona>` do `userfiles/<adresář>` — stejně jako starý admin
  (`addImagePicker`), takže web ho najde beze změny. Víc `imageDirectory()` = víc kopií (např.
  `quick_links/origin` bez úpravy a `quick_links/thumb` zmenšená); náhled ve formuláři je z prvního.
- Ve formuláři náhled, přetažení nebo výběr souboru, „Odstranit obrázek“. Nový soubor i odstranění se
  projeví až uložením. Obrázek s jinou příponou nahradí starý (starý soubor se smaže), starší `.webp`
  vedle se smaže vždy.
- Povinný obrázek (`->required()`) stačí mít u záznamu z dřívějška.
- Smazání záznamu soubor nechá (kvůli „Vrátit“) — jako starý admin.
- Adresáře: `backoffice: userDir:` (výchozí `%wwwDir%/userfiles`) a `userUrl:` (výchozí `/userfiles`).
- Po uložení každé kopie volá `Backoffice\Media\ImageStorage::$onSave` — projekt tak přidá WebP:

```neon
decorator:
	Backoffice\Media\ImageStorage:
		setup:
			- '$onSave[]' = [App\WebpConverter, convert]
```


### Soubor

```php
$form->file('storageName', 'PDF dokument')
	->fileDirectory('annual_reports')
	->accept(['application/pdf'], 'Povolené jsou jen soubory PDF.')
	->fileColumns(size: 'fileSize', extension: 'fileExtension')
	->required();
```

- Uloží se do `userfiles/<adresář>` pod očištěným jménem nahraného souboru
  (`FileUpload::getSanitizedName()`, jako starý admin). Patří-li jméno jinému záznamu, dostane
  `-2`, `-3`… (starý admin cizí soubor přepsal).
- Ve sloupci pole je jméno souboru; `fileColumns()` doplní velikost a příponu.
- Povinný soubor stačí mít u záznamu z dřívějška. Nový soubor nahradí starý (ten se po uložení smaže).
- Při smazání záznamu se soubor přesune do koše mimo web (`%tempDir%/backoffice-trash`), „Vrátit“ ho
  vrátí; koš se čistí po dni.

### Formátovaný text

`richText('body', 'Obsah')`, `localeRichText('text', 'Obsah')` — HTML v editoru; jazyky jsou pod sebou.
Editor dodává aplikace službou `Backoffice\Resource\Form\RichTextEditor`:

- `scripts()` — adresy skriptů; balík je načte (jednou, v pořadí), až je na stránce takové pole;
- `config(field, mutation)` — nastavení editoru pro pole (JSON v `data-bo-richtext`); navrch se přidá
  `->editorOptions([...])` pole;
- poslední skript nastaví `window.BackofficeRichText = { init(textarea, config, changed), save() }`:
  `init` spustí editor nad textarea a při každé úpravě zavolá `changed()` (lišta „Neuložené změny“),
  `save` před odesláním formuláře přepíše obsah editorů do textarea.

Bez služby (výchozí `PlainRichTextEditor`) zůstane textarea s HTML. Balík HTML nečistí — co se smí
uložit, rozhoduje editor a `beforeSave()` agendy. PSE: TinyMCE 4 starého adminu (`App\Web\Backoffice\TinyMceEditor`).

### Adresa a SEO: text před polem, počítadlo znaků

- `text('fromUrl', 'Z adresy')->prefix('/')`, u jazykového pole `->prefix(['cs' => '/', 'en' => '/en/'])`
  — pevný text před polem, neukládá se (jen jednořádková pole).
- `localeText('title', 'Titulek')->counter(70)` — pod polem „42 / 70“, přes doporučenou délku
  červeně; délku neomezuje (textová pole i textarea).
- `textarea('html', 'Obsah (HTML)')->code()` — pole na kód: neproporcionální písmo, vyšší, bez
  kontroly pravopisu a bez zalamování řádků.
- Pole, které není sloupcem (zaškrtávátko „přesměrovat starou adresu“), se do dat k uložení dostane,
  zpracuje ho `beforeSave()` (v `$values`) a samo se neuloží. Výchozí hodnotu mu dá `formDefaults()`.

### Výběr, viditelnost, vazba

- `select(...)->prompt('na obou webech')` — text prázdné volby.
- `->visibleWhen('type', 'pdf')` — pole je vidět jen při dané hodnotě jiného pole (skryté se odešle
  taky; co s ním, řeší `beforeSave()`/`validate()`).
- `relation('isin', 'Instrument', $repo)->relationLabel("CONCAT(this.name, ' – ', this.isin)", ['this.name', 'this.isin'])`
  — pole s hledáním: píše se část názvu, nabídne 20 položek (signál `lookup!`, JSON), uloží se klíč.
  Neexistující klíč = chyba u pole.

### Datum a čas, víc vazeb, obrázky v textu

- `datetime('publishedAt', 'Publikováno')` — `<input type="datetime-local">`; ze sloupce
  `RRRR-MM-DD HH:MM:SS` do pole a zpět, prázdné = `NULL`. Výchozí hodnota ve tvaru `RRRR-MM-DDTHH:MM`.
- `relations('isins', 'Emise', $repo)->relationLabel(…)` — víc vazeb N:N: štítky vybraných s ×, pole
  s hledáním (signál `lookup!` jako u `relation()`) přidá další. Do sloupců nejde: výchozí list klíčů
  vrátí `formDefaults()` (`$defaults['isins'] = ['CZ…', …]`), uloží `afterSave()` — v `$values['isins']`
  je list klíčů. Neznámý klíč = chyba u pole.
- `localeRichText('content', 'Obsah')->richTextUploads('news-images')` — obrázek přetažený nebo vložený
  do editoru se nahraje (signál `richTextUpload!`, do 5 MB, WebP vedle přes `ImageStorage::$onSave`)
  a do textu jde adresa bez domény `/userfiles/news-images/…`.

V hácích `beforeSave()` / `afterSave()` jsou hodnoty už převedené: víc vazeb jako list klíčů, datum
a čas jako `RRRR-MM-DD HH:MM:SS`.

### Velké seznamy

Stránkování a počet jdou v SQL (`LIMIT`, `COUNT`), takže seznam o desítkách tisíc řádků se načte
stejně rychle jako malý (Novinky v PSE: 12 400 řádků, ~350 ms i s hledáním). Hledání mimo sloupce
seznamu: `$list->searchAlso(fn (string $text) => ['EXISTS (… LIKE :bo_x)', ['bo_x' => …]])` —
podmínka se přidá k hledání přes OR (vazba přes jinou tabulku, přesné ID); null = na tenhle text
se nehodí. Spočítaný odkaz ven: `$list->text('slug', 'Na webu')->value(…)->href(fn (Entity $row) => $url)`.

### Vnořený seznam (`rows`)

Podřízené záznamy přímo ve formuláři — konzultanti ve skupině, skupiny konzultanta (vazba N:N
s vlastními hodnotami v propojovací tabulce):

```php
$form->rows('members', 'Konzultanti')
	->rowInteger('priority', 'Pořadí')
	->rowText('isins', 'ISIN emisí', 'oddělené čárkou')
	->rowCheckbox('remove', 'Odebrat')
	->rowsSortableBy('priority')          // přetažení za úchyt / šipky, přečísluje pořadí
	->rowsEmptyText('Ve skupině zatím nikdo není.');

public function formRows(string $field, ?Entity $entity): array   // list<FormRow>
{
	return [new FormRow($id, 'Jméno', ['priority' => 3, 'isins' => 'CZ…'], 'pozice', [$thumbDir, $file], ':Backoffice:Resource:edit', ['resource' => 'consultants', 'id' => $id])];
}

public function afterSave(Entity $entity, array $values, bool $created): void
{
	// $values['members'][$id]['priority'] … uložit do propojovací tabulky
}
```

Řádek = `FormRow` (klíč, popisek, výchozí hodnoty polí, poznámka pod popiskem, náhled
`[adresář, soubor]`, odkaz). Hodnoty přijdou v `$values[pole][klíč][pole řádku]`, do sloupců záznamu
nejdou; uloží je `afterSave()` (volá se po uložení nového i upraveného záznamu, záznam už má ID).
Chyba u pole řádku: `$form['members'][$id]['isins']->addError()`. Přetažení jen přečísluje pole
a označí formulář za změněný — uloží se s ním.

`->rowImage('preview', 'Náhled')` dá do řádku nahrání obrázku (náhled souboru, nová dlaždice):
v `afterSave()` je to `Nette\Http\FileUpload` (prázdný, když se nic nevybralo) a agenda ho uloží sama.

### Víc souborů najednou (`uploads`)

```php
$form->uploads('newPhotos', 'Přidat fotky')
	->accept(['image/jpeg', 'image/png'], 'Nahrajte JPG nebo PNG.');
```

Pole s přetažením a `multiple`; do sloupců nejde, v `afterSave()` je `$values['newPhotos']` list
`FileUpload`. Kolik souborů projde jedním odesláním, určuje PHP `max_file_uploads` (pole ho ukáže
a víc nepustí už v prohlížeči — PHP by zbytek tiše zahodilo). Vzor: Galerie v PSE (fotky a soubory
jako vnořené seznamy, nové přes `uploads`).

### Dvě agendy z jednoho starého presenteru

`replaces()` přebírá oprávnění, položku menu i staré adresy. Druhá agenda téhož presenteru vezme jen
oprávnění přes `$meta->permissionsFrom(':Web:Admin:Consultant')`. Tlačítko mezi nimi v hlavičce
seznamu: `$meta->related('consultant-groups', 'Skupiny')` (ukáže se jen s právem na cílovou agendu).

### Háčky agendy

| Metoda | K čemu |
|---|---|
| `formDefaults(defaults, entity)` | výchozí hodnoty polí, která nejsou sloupcem (typ odvozený z dat) |
| `validate(form, entity)` | vlastní kontrola (chyby přes `$form['pole']->addError()`) |
| `beforeSave(data, values, entity)` | úprava dat před uložením (pole mimo sloupce, odvozené sloupce) |
| `lockReason(entity)` | záznam jen ke čtení (z importu): důvod se ukáže nahoře, formulář je zamčený, nejde smazat, hromadné akce ho přeskočí |
| `deleteBlockReason(entity)` | záznam jde upravit, ale ne smazat (systémový): koš je šedý s důvodem v popisku, hromadné mazání ho přeskočí |
| `importRow(row)` | řádek importu CSV před uložením (sloupec → text): vrátí data k uložení, nepoužitelný řádek ohlásí `CsvRowException` s důvodem; smí doplnit sloupec klíče, který soubor nemá |
| `formRows(field, entity)` / `afterSave(entity, values, created)` | řádky vnořeného seznamu (viz výše) / uložení dat mimo sloupce záznamu po jeho uložení |
| `beforeDelete(entity)` | těsně před smazáním (stránka záznamu i hromadně): úklid mimo tabulku — soubory, závislé řádky. Vrátí `false`, když smazal něco nevratně; zpráva pak „Vrátit“ nemá |
| `formNotices(entity)` | upozornění nad formulářem záznamu: `[[FormNotice::WARN, 'text'], [FormNotice::INFO, 'text']]` (žlutě / modře) — např. kde se záznam na webu ukáže, kontrola odkazů |

## Stránka nastavení (`Meta::settings()`)

Agenda bez seznamu a bez záznamů — jeden formulář (např. přepínače webu v tabulce klíč → hodnota):

```php
public function meta(Meta $meta): void
{
	$meta->slug('settings')->title('Nastavení webu')->replaces(':Web:Admin:Setting')->settings();
}

public function loadSettings(): array   // pole → hodnota
public function saveSettings(array $values): void
public function settingsNotices(): array // upozornění nad formulářem, jako formNotices()
```

Adresa je `/r/<agenda>`, pole a sekce z `form()`, tlačítko jen „Uložit“ (Ctrl S), po uložení flash
„Uloženo“ a záznam v logu změn (`objectId` = slug agendy). Uložit smí právo na úpravu (`…:detail`);
kdo má jen prohlížení, vidí formulář zamčený. Zakládání, mazání, stránka záznamu ani signály seznamu
nejsou; repozitář není potřeba.

## Vlastní obrazovka seznamu (`ListView`)

Když návrh agendy není tabulka (kalendář, nástěnka), agenda vrátí vlastní komponentu:

```php
public function createListView(): ListView
{
	return new HolidayCalendar($this->holidays);
}
```

`ListView` je Nette `Control`, presenter ho vykreslí místo tabulky jako komponentu `view`.
Ukládání, mazání, práva a log zůstávají na presenteru. Obrazovka má k dispozici:

| Metoda | Co dělá |
|---|---|
| `render()` | vykreslí obrazovku (povinné) |
| `headerButtons()` | prvky v hlavičce vpravo (přepínač roku); výchozí „Nový: …“ podle práva |
| `focus(Entity)` | nastaví stav tak, aby byl záznam vidět (rok podle data) — volá se po uložení, před smazáním a na detailu |
| `newForm()` | formulář nového záznamu k vložení do obrazovky (null bez práva); uloží se jako stránka „Nový“ a vrátí na seznam |
| `prepareFormTemplate()` | do šablony `$fieldsTemplate`, `$fields`, `$mutations`, `$newForm` a k rozbalenému záznamu `$imageUrls`, `$fileInfo`, `$relationLabels` — pole se vykreslí `{include $fieldsTemplate, form: $form, fields: $fields, mutations: $mutations}` |
| `editedId()`, `closeEdit()`, `editForm()` | úprava v řádku: obrazovka vrátí id rozbaleného záznamu (nebo `ListView::EDIT_NEW`), presenter na něj naváže formulář `form` a uloží ho jako stránka záznamu; po uložení zavolá `closeEdit()` a vrátí se na seznam |
| signál `toggle!` (`id`, `column`, POST `value=1\|0`) | přepínač v řádku, uloží se hned; jen sloupec seznamu `checkbox()->editable()` bez jazyka |
| signál `reorder!` (POST `ids[]`) | nové pořadí po přetažení, uloží se hned do sloupce `priority()` seznamu |
| `canCreate()`, `canEdit()`, `editLink(Entity)`, `logChange(type, id)` | práva, odkaz na detail, zápis do logu, když obrazovka ukládá sama (signál) |

Stav obrazovky patří do `#[Persistent]` vlastností — v adrese jsou jako `view-<jméno>` (`?view-year=2027`)
a drží se v odkazech na seznam z detailu, v drobečcích i po uložení a smazání.

Komponenty pro obrazovky: `Backoffice\Resource\View\YearCalendar` (roční kalendář po měsících,
šablona `YearCalendar::TEMPLATE`; klik na volný den zapíše datum do pole `pickTarget`) a třídy CSS
`bo-split`, `bo-notice`, `bo-stepper`, `bo-rows`, `bo-form-stack`.

Příklad: `App\Stock\Backoffice\HolidayCalendar` v PSE (neobchodní dny podle návrhu).

Řádkový seznam s úpravou v řádku (třídy `bo-rowlist*`, náhled `bo-preview*`): kontejner
`data-bo-reorder="<id formuláře se signálem reorder!>"`, položky `data-bo-reorder-item="<id>"`
a úchyt `data-bo-grip` — pořadí přetažením nebo šipkami se odešle hned. Přepínač je malý formulář
na `toggle!` se skrytým `value` a zaškrtávátkem `data-bo-autosubmit`. Mazání z rozbaleného řádku
musí mít vlastní formulář (tlačítko s `form="…"`), jinak by se poslalo `_do` editačního formuláře.
Příklad: `App\Web\Backoffice\QuickLinksView` v PSE (rychlé odkazy podle návrhu).
Další třídy: `bo-rowlist-thumb is-wide` (široký náhled 132 × 40, např. slide), `bo-rowlist-num`
(pořadové číslo), `bo-rowlist-add` (čárkované „Přidat…“ pod seznamem), `bo-btn-group` (přepínač
v hlavičce). Příklad: `App\Web\Backoffice\SliderView` v PSE (slider podle návrhu, přepínač CS/EN webu).

Název záznamu ve zprávách („Uloženo: …“, „Skryto: ano — …“) se bere ze sloupce s `->link()`;
je-li to formátovaný text, zbaví se HTML a zkrátí na 80 znaků.

## Mazání použitých záznamů

Agenda, jejíž záznamy se vkládají do stránek, vrátí z `ResourceDefinition::usages(Entity)` seznam
míst (`UsageLocation`: název, odkaz, poznámka, vnořená místa); `null` = nesleduje. Použitý záznam:

- koš v řádku a „Smazat“ na stránce záznamu vedou na stránku **Smazat** (`/r/<agenda>/usage/<id>`)
  se seznamem míst a volbami **Skrýt místo smazání** (má-li entita sloupec `hidden`), **Přesto smazat**
  a Zpět; obojí s „Vrátit“
- hromadné smazání ho přeskočí a vypíše
- sloupec `usage()` ukáže počet míst

## Mazání

Ikona koše v řádku seznamu a hromadné „Smazat“ jdou přes formulář seznamu (signál `list-submit!`),
„Smazat“ v liště stránky záznamu přes signál `delete!`. Obojí jen POST ze stejného webu
(`#[Requires(methods: 'POST', sameOrigin: true)]`), s potvrzením a s „Vrátit“ ve flash zprávě.
Oprávnění jako pro úpravu (`…:detail`); `Meta::withoutDelete()` mazání vypne.

## Log změn

`Backoffice\Security\ChangeLogger`. S mostem `LegacyChangeLogger` zapisuje do `admin_changelog` stejně
jako starý admin: `entity` = název agendy, `type` = `new` / `detail` / `delete`, `objectId`, `user` = login.
Bez mostu `NullChangeLogger`; projekt může dodat vlastní službu.
