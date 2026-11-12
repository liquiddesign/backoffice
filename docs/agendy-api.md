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

| Metoda | Co dělá |
|---|---|
| `text(name, label)` | textový sloupec (jazykový sloupec ukáže aktuální jazyk) |
| `date(name, label)` | datum, formát `j. n. Y` (`->format()`) |
| `mutations(label = 'Jazyky')` | štítky jazyků, ve kterých má záznam vyplněné jazykové sloupce seznamu |
| `defaultSort(column, dir)` / `Column::defaultSort(dir)` | výchozí řazení |
| `bulkDelete()` | výběr řádků + hromadné smazání (etapa 2) |
| `perPage(n)` | počet řádků na stránku (výchozí 50) |

Sloupec: `->sortable()`, `->searchable()` (etapa 2), `->link()` (odkaz na detail), `->format()`.

## Formulář (`FormBuilder`)

`section(title)`, `text`, `textarea`, `integer`, `checkbox`, `select(items)`, `date`,
`localeText`, `localeTextarea`. Pole: `->required()`, `->description()`, `->items()`,
`->unique(zpráva)` (hodnota se v agendě nesmí opakovat; kontrola při uložení, chyba u pole).

- jazyková pole: kontejner `name[cs]`, `name[en]` vedle sebe; povinný je jen hlavní (první) jazyk
- datum: `<input type="date">`, ukládá se `RRRR-MM-DD`
- prázdný text u sloupce, který smí být `NULL`, se uloží jako `NULL`
- tlačítka „Uložit“ (zůstane na detailu) a „Uložit a zavřít“; Ctrl S = Uložit
- lišta dole hlásí neuložené změny, při odchodu se stránkou se změnami se prohlížeč zeptá
- chyby se ukazují u polí, nahoře souhrnná zpráva

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
| `prepareFormTemplate()` | do šablony `$fieldsTemplate`, `$fields`, `$mutations`, `$newForm` — pole se vykreslí `{include $fieldsTemplate, form: $form, fields: $fields, mutations: $mutations}` |
| `canCreate()`, `canEdit()`, `editLink(Entity)`, `logChange(type, id)` | práva, odkaz na detail, zápis do logu, když obrazovka ukládá sama (signál) |

Stav obrazovky patří do `#[Persistent]` vlastností — v adrese jsou jako `view-<jméno>` (`?view-year=2027`)
a drží se v odkazech na seznam z detailu, v drobečcích i po uložení a smazání.

Komponenty pro obrazovky: `Backoffice\Resource\View\YearCalendar` (roční kalendář po měsících,
šablona `YearCalendar::TEMPLATE`; klik na volný den zapíše datum do pole `pickTarget`) a třídy CSS
`bo-split`, `bo-notice`, `bo-stepper`, `bo-rows`, `bo-form-stack`.

Příklad: `App\Stock\Backoffice\HolidayCalendar` v PSE (neobchodní dny podle návrhu).

## Mazání

Tlačítko „Smazat“ v řádku seznamu posílá POST (signál `delete!` s `#[Requires(methods: 'POST', sameOrigin: true)]`)
a ptá se na potvrzení. Totéž tlačítko je vlevo v liště stránky záznamu. Oprávnění jako pro úpravu (`…:detail`).

## Log změn

`Backoffice\Security\ChangeLogger`. S mostem `LegacyChangeLogger` zapisuje do `admin_changelog` stejně
jako starý admin: `entity` = název agendy, `type` = `new` / `detail` / `delete`, `objectId`, `user` = login.
Bez mostu `NullChangeLogger`; projekt může dodat vlastní službu.
