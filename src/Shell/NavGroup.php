<?php

declare(strict_types=1);

namespace Backoffice\Shell;

final class NavGroup
{
	/**
	 * @param list<\Backoffice\Shell\NavItem> $items
	 */
	public function __construct(
		public readonly string $title,
		public readonly array $items,
		public readonly ?string $icon = null,
	) {
	}
}
