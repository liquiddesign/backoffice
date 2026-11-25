<?php

declare(strict_types=1);

namespace Backoffice\Resource\Csv;

use Backoffice\Resource\ResourceDefinition;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use StORM\Entity;

/**
 * Import CSV do agendy (ListBuilder::csv() s klíčem). Sloupce se poznají podle záhlaví nebo jména
 * sloupce (bez ohledu na velikost písmen), neznámé sloupce se přeskočí. Sloupec klíče může
 * v souboru chybět, když ho doplní ResourceDefinition::importRow(). Řádek se stejnými
 * hodnotami klíče jako existující záznam ho upraví, jinak se založí nový. Celý import běží
 * v transakci: první chybný řádek vrátí všechno a hlášení řekne, na kterém řádku to je.
 */
final class CsvImporter
{
	public function __construct(private readonly ResourceDefinition $definition)
	{
	}

	/**
	 * @return array{created: list<string>, updated: list<string>, unchanged: int}
	 * @throws \Backoffice\Resource\Csv\CsvRowException
	 */
	public function import(string $content): array
	{
		$list = $this->definition->buildList();
		$columns = $list->getCsvColumns();
		$key = $list->getCsvImportKey() ?? throw new \LogicException('Agenda nemá import CSV (ListBuilder::csv() bez klíče).');
		[$header, $rows] = CsvFile::read($content);
		$map = self::mapHeader($header, $columns);

		if ($map === []) {
			throw new CsvRowException('Záhlaví souboru nemá žádný známý sloupec. Očekávané sloupce: ' . \implode(', ', $columns) . '.');
		}

		$repository = $this->definition->getMeta()->getRepository();
		$link = $repository->getConnection()->getLink();
		$result = ['created' => [], 'updated' => [], 'unchanged' => 0];
		$seen = [];
		$link->beginTransaction();

		try {
			foreach ($rows as $line => $record) {
				$row = [];

				foreach ($map as $index => $column) {
					$row[$column] = $record[$index] ?? '';
				}

				try {
					$row = $this->definition->importRow($row);
				} catch (CsvRowException $e) {
					throw new CsvRowException('Řádek ' . $line . ': ' . $e->getMessage(), 0, $e);
				}

				// Sloupec klíče, který soubor nemá, smí doplnit importRow() (např. „všechny jazyky“).
				foreach ($key as $keyColumn) {
					if (!\array_key_exists($keyColumn, $row)) {
						throw new CsvRowException('V souboru chybí sloupec „' . $columns[$keyColumn] . '“. Očekávané sloupce: ' . \implode(', ', $columns) . '.');
					}
				}

				$keyValues = \array_map(static fn (string $column): mixed => $row[$column], $key);
				$keyHash = \serialize($keyValues);

				if (isset($seen[$keyHash])) {
					$keyText = \implode(', ', \array_map(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '', $keyValues));

					throw new CsvRowException('Řádek ' . $line . ' má stejný klíč (' . $keyText . ') jako řádek ' . $seen[$keyHash] . '.');
				}

				$seen[$keyHash] = $line;
				$existing = $this->findExisting($key, $row);

				if ($existing === null) {
					$result['created'][] = (string) $repository->createOne($row)->getPK();

					continue;
				}

				if (!self::differs($existing, $row)) {
					$result['unchanged']++;

					continue;
				}

				$existing->update($row);
				$result['updated'][] = (string) $existing->getPK();
			}

			$link->commit();
		} catch (\Throwable $e) {
			$link->rollBack();

			throw $e;
		}

		return $result;
	}

	/**
	 * @param list<string> $key
	 * @param array<string, mixed> $row
	 */
	private function findExisting(array $key, array $row): ?Entity
	{
		$collection = $this->definition->getMeta()->getRepository()->many();

		foreach ($key as $column) {
			$value = $row[$column] ?? null;
			$value === null
				? $collection->where('this.' . $column . ' IS NULL')
				: $collection->where('this.' . $column, $value);
		}

		return $collection->first();
	}

	/**
	 * Index sloupce v souboru → sloupec entity.
	 * @param list<string> $header
	 * @param array<string, string> $columns
	 * @return array<int, string>
	 */
	private static function mapHeader(array $header, array $columns): array
	{
		$lookup = [];

		foreach ($columns as $column => $label) {
			$lookup[Strings::lower($column)] = $column;
			$lookup[Strings::lower($label)] = $column;
		}

		$map = [];

		foreach ($header as $index => $title) {
			$column = $lookup[Strings::lower(Strings::trim($title))] ?? null;

			if ($column === null || Arrays::contains($map, $column)) {
				continue;
			}

			$map[$index] = $column;
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function differs(Entity $entity, array $row): bool
	{
		foreach ($row as $column => $value) {
			$current = $entity->getValue($column);
			$current = \is_bool($current) ? (int) $current : $current;
			$value = \is_bool($value) ? (int) $value : $value;

			if (($current === null ? null : (string) $current) !== ($value === null ? null : (string) $value)) {
				return true;
			}
		}

		return false;
	}
}
