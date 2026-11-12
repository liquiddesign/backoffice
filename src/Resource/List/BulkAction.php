<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

/**
 * Hromadná akce nad vybranými řádky: nastaví sloupci hodnotu (Skrýt, Zobrazit, Zveřejnit…).
 * Mazání je zvlášť (ListBuilder::bulkDelete()).
 */
final class BulkAction
{
	public function __construct(
		public readonly string $column,
		public readonly string|int|bool|null $value,
		public readonly string $label,
	) {
	}
}
