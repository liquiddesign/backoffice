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
	Assert::same(['name'], $list->getSearchableColumns());
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
