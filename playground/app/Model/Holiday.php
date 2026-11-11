<?php

declare(strict_types=1);

namespace Playground\Model;

use StORM\Entity;

/**
 * Neobchodní den — zjednodušená obdoba pse_holiday.
 * @table{"name":"demo_holiday"}
 */
class Holiday extends Entity
{
	/**
	 * @column{"type":"date"}
	 */
	public string $date;

	/**
	 * @column{"mutations":true}
	 */
	public ?string $name = null;

	/**
	 * @column{"type":"text"}
	 */
	public ?string $note = null;

	/**
	 * @column
	 */
	public bool $active = true;
}
