<?php

declare(strict_types=1);

namespace Backoffice\Resource\Csv;

use Nette\Utils\Strings;

/**
 * Čtení a zápis CSV pro export a import seznamu. Zapisuje se tak, aby soubor rovnou otevřel český
 * Excel (UTF-8 s BOM, středník). Čte se, co lidé reálně pošlou: středník, čárka, tabulátor nebo
 * svislítko (pozná se ze záhlaví), UTF-8 s BOM i bez, a soubor uložený Excelem ve Windows-1250.
 */
final class CsvFile
{
	public const DELIMITER = ';';

	private const BOM = "\u{FEFF}";

	private const DELIMITERS = [';', ',', "\t", '|'];

	private const FORMULA_CHARS = '=+@';

	/**
	 * @param list<string> $header
	 * @param iterable<list<string>> $rows
	 */
	public static function write(array $header, iterable $rows): string
	{
		$handle = \fopen('php://temp', 'r+') ?: throw new \RuntimeException('Nejde otevřít dočasný soubor.');
		\fputcsv($handle, $header, self::DELIMITER, '"', '');

		foreach ($rows as $row) {
			\fputcsv($handle, \array_map(self::protectFormula(...), $row), self::DELIMITER, '"', '');
		}

		\rewind($handle);
		$content = (string) \stream_get_contents($handle);
		\fclose($handle);

		return self::BOM . $content;
	}

	/**
	 * Záhlaví a řádky. Prázdné řádky vynechá; řádek kratší než záhlaví doplní prázdnými hodnotami.
	 * Klíč řádku je číslo řádku v souboru (záhlaví = 1), ať chyba ukáže, kde hledat.
	 * @return array{list<string>, array<int, list<string>>}
	 */
	public static function read(string $content): array
	{
		if (\str_starts_with($content, self::BOM)) {
			$content = Strings::substring($content, 1);
		} elseif (!\mb_check_encoding($content, 'UTF-8')) {
			// Excel ve Windows ukládá „CSV (oddělený středníkem)“ v kódování Windows-1250.
			$converted = \iconv('Windows-1250', 'UTF-8//IGNORE', $content);
			$content = $converted !== false ? $converted : $content;
		}

		$content = Strings::normalizeNewLines($content);
		$delimiter = self::detectDelimiter((string) Strings::before($content . "\n", "\n"));
		$handle = \fopen('php://temp', 'r+') ?: throw new \RuntimeException('Nejde otevřít dočasný soubor.');
		\fwrite($handle, $content);
		\rewind($handle);

		$header = null;
		$rows = [];
		$line = 0;

		while (($record = \fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
			$line++;
			$record = \array_map(static fn (?string $value): string => self::unprotectFormula(Strings::trim((string) $value)), $record);

			if (\implode('', $record) === '') {
				continue;
			}

			if ($header === null) {
				$header = $record;

				continue;
			}

			$rows[$line] = \array_pad($record, \count($header), '');
		}

		\fclose($handle);

		return [$header ?? [], $rows];
	}

	/**
	 * Hodnota začínající =, + nebo @ by se v Excelu spustila jako vzorec — dostane apostrof, který
	 * read() zase odebere. Minus se nechává: záporná čísla (pořadí) musí zůstat číslem.
	 */
	private static function protectFormula(string $value): string
	{
		return $value !== '' && \str_contains(self::FORMULA_CHARS, $value[0]) ? "'" . $value : $value;
	}

	private static function unprotectFormula(string $value): string
	{
		return Strings::length($value) > 1 && $value[0] === "'" && \str_contains(self::FORMULA_CHARS, $value[1]) ? Strings::substring($value, 1) : $value;
	}

	private static function detectDelimiter(string $headerLine): string
	{
		$best = self::DELIMITER;
		$bestCount = 0;

		foreach (self::DELIMITERS as $delimiter) {
			$count = \substr_count($headerLine, $delimiter);

			if ($count <= $bestCount) {
				continue;
			}

			$best = $delimiter;
			$bestCount = $count;
		}

		return $best;
	}
}
