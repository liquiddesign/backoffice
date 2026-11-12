<?php

declare(strict_types=1);

use Backoffice\Resource\List\Pager;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('počet stran a meze', function (): void {
	$pager = new Pager(1284, 50, 99);

	Assert::same(26, $pager->pages);
	Assert::same(26, $pager->page);
	Assert::same(1251, $pager->firstItem());
	Assert::same(1284, $pager->lastItem());

	$empty = new Pager(0, 50, 3);
	Assert::same(1, $empty->pages);
	Assert::same(1, $empty->page);
	Assert::same(0, $empty->firstItem());
});

test('čísla stran s vynechávkou', function (): void {
	Assert::same([1, 2, null, 26], (new Pager(1284, 50, 1))->numbers());
	Assert::same([1, null, 9, 10, 11, null, 26], (new Pager(1284, 50, 10))->numbers());
	Assert::same([1, 2, 3, 4], (new Pager(200, 50, 2))->numbers());
	// Jedna vynechaná strana se ukáže číslem, ne „…“.
	Assert::same([1, 2, 3, 4, 5], (new Pager(250, 50, 4))->numbers());
});
