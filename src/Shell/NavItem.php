<?php

declare(strict_types=1);

namespace Backoffice\Shell;

final class NavItem
{
	public function __construct(
		public readonly string $label,
		public readonly string $link,
		public readonly ?string $icon = null,
		public readonly bool $active = false,
	) {
	}

	/**
	 * Část odkazu s presenterem: ':Web:Admin:Menu:default' → 'Web:Admin:Menu'.
	 */
	public static function presenterOf(string $link): string
	{
		$link = \ltrim($link, ':');
		$parts = \explode(':', $link);

		if (\count($parts) > 1) {
			\array_pop($parts);
		}

		return \implode(':', $parts);
	}
}
