<?php

declare(strict_types=1);

use Backoffice\Resource\Csv\CsvFile;
use Backoffice\Resource\List\ListBuilder;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('zápis: BOM, středník, uvozovky a ochrana vzorců; čtení to vrátí', function (): void {
	$csv = CsvFile::write(['Z URL', 'Do URL', 'Pořadí'], [['stara;adresa', 'nova "x"', '-1'], ['=SUM(A1)', '@a', '0']]);

	// fputcsv uzavírá do uvozovek i hodnoty s mezerou — Excel i read() to přečtou stejně.
	Assert::true(str_starts_with($csv, "\u{FEFF}\"Z URL\";\"Do URL\";Pořadí\n"));
	Assert::contains("'=SUM(A1)", $csv);

	[$header, $rows] = CsvFile::read($csv);
	Assert::same(['Z URL', 'Do URL', 'Pořadí'], $header);
	Assert::same([2 => ['stara;adresa', 'nova "x"', '-1'], 3 => ['=SUM(A1)', '@a', '0']], $rows);
});

test('čtení: oddělovač ze záhlaví, prázdné řádky, krátké řádky, Windows-1250', function (): void {
	[$header, $rows] = CsvFile::read("fromUrl,toUrl\r\n\r\na,b\r\nc\r\n");
	Assert::same(['fromUrl', 'toUrl'], $header);
	Assert::same([3 => ['a', 'b'], 4 => ['c', '']], $rows);

	[$header, $rows] = CsvFile::read("Z URL\tDo URL\nx\ty\n");
	Assert::same([2 => ['x', 'y']], $rows);

	$win = iconv('UTF-8', 'Windows-1250', "Název;Popis\nŽluťoučký;kůň\n");
	[$header, $rows] = CsvFile::read((string) $win);
	Assert::same(['Název', 'Popis'], $header);
	Assert::same([2 => ['Žluťoučký', 'kůň']], $rows);
	Assert::same([[], []], CsvFile::read(''));
});

test('ListBuilder::csv: klíč musí být mezi sloupci, bez klíče jen export', function (): void {
	$list = (new ListBuilder())->csv(['fromUrl' => 'Z URL', 'toUrl' => 'Do URL']);
	Assert::true($list->hasCsvExport());
	Assert::false($list->hasCsvImport());

	$list->csv(['fromUrl' => 'Z URL', 'fromMutation' => 'Z mutace'], ['fromUrl', 'fromMutation']);
	Assert::true($list->hasCsvImport());
	Assert::same(['fromUrl', 'fromMutation'], $list->getCsvImportKey());
	Assert::exception(fn () => $list->csv(['a' => 'A'], ['b']), InvalidArgumentException::class);
	Assert::exception(fn () => $list->csv([]), InvalidArgumentException::class);
});
