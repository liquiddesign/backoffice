# Deklarativní agendy — API

Stav: etapa 0 (kostra). Plán etap: [agendy-plan.md](agendy-plan.md).

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
`localeText`, `localeTextarea`. Pole: `->required()`, `->description()`, `->items()`.
V etapě 0 jen popis — detail ukazuje náhled hodnot; formulář a ukládání přijdou v etapě 1.
