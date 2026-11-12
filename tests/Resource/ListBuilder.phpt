<?php

declare(strict_types=1);

use Backoffice\Resource\List\Column;
use Backoffice\Resource\List\ListBuilder;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('výchozí řazení ze sloupce', function (): void {
	$list = new ListBuilder();
	$list->text('name', 'Název')->searchable()->link();
	$list->date('date', 'Datum')->defaultSort('desc');

	Assert::same('date', $list->getDefaultSort());
	Assert::same('DESC', $list->getDefaultDirection());
	Assert::true($list->getColumns()['date']->isSortable());
	Assert::same(['name'], array_map(fn (Column $column) => $column->key, $list->getSearchableColumns()));
	Assert::same('j. n. Y', $list->getColumns()['date']->getFormat());
});

test('výslovné řazení má přednost', function (): void {
	$list = new ListBuilder();
	$list->date('date', 'Datum')->defaultSort('desc');
	$list->defaultSort('name');

	Assert::same('name', $list->getDefaultSort());
	Assert::same('ASC', $list->getDefaultDirection());
});

test('sloupec dvakrát je chyba', function (): void {
	$list = new ListBuilder();
	$list->text('name', 'Název');

	Assert::exception(fn () => $list->text('name', 'Jiný'), LogicException::class);
});

test('jazyky jako sloupec', function (): void {
	$list = new ListBuilder();
	$list->mutations();

	Assert::same(Column::TYPE_MUTATIONS, $list->getColumns()['_mutations']->type);
});

test('pořadí se řadí samo, je upravitelné; jazykový sloupec s pevným jazykem má vlastní klíč', function (): void {
	$list = new ListBuilder();
	$list->priority();
	$list->text('text', 'Text CS', 'cs')->editable();
	$list->text('text', 'Text EN', 'en')->editable();

	Assert::same('priority', $list->getDefaultSort());
	Assert::same(['priority', 'text-cs', 'text-en'], array_keys($list->getEditableColumns()));
	Assert::same('priority', $list->getPriorityColumn()?->key);
});

test('upravit v řádku jde jen text, číslo a zaškrtávátko', function (): void {
	$list = new ListBuilder();

	Assert::exception(fn () => $list->date('date', 'Datum')->editable(), LogicException::class);
	Assert::exception(fn () => $list->status('state', 'Stav', [])->editable(), LogicException::class);
	Assert::true($list->checkbox('hidden', 'Skryto')->editable()->isEditable());
});

test('štítky stavů a filtry', function (): void {
	$list = new ListBuilder();
	$column = $list->status('hidden', 'Stav', [1 => ['Skryto', 'mute']]);
	$filter = $list->filter('hidden', 'Stav', [0 => 'Zobrazené', 1 => 'Skryté']);
	$area = $list->filter('area', 'Oblast', ['web' => 'Web', 'admin' => 'Administrace'])->default('web');

	Assert::same(['Skryto', 'mute'], $column->getState(true));
	Assert::null($column->getState(false));
	Assert::true($filter->hasItem('1'));
	Assert::false($filter->hasItem('2'));
	Assert::same('Všechny', $filter->getPrompt());
	Assert::null($area->getPrompt());
	Assert::same('web', $area->getDefault());
	Assert::exception(fn () => $list->filter('area', 'Znovu', []), LogicException::class);
});

test('výběr řádků jen s hromadnou akcí', function (): void {
	$list = new ListBuilder();
	Assert::false($list->hasSelection());
	$list->bulkSet('hidden', true, 'Skrýt');
	Assert::true($list->hasSelection());
	Assert::same('hidden', $list->getBulkActions()[0]->column);
});
