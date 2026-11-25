<?php

declare(strict_types=1);

namespace Backoffice\Resource\Csv;

/**
 * Řádek importu, který nejde uložit. Vyhodí ho ResourceDefinition::importRow() s důvodem pro
 * člověka („Do URL musí být adresa na tomto webu.“); import se pak celý vrátí a hlášení ukáže
 * číslo řádku.
 */
final class CsvRowException extends \RuntimeException
{
}
