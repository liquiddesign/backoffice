<?php

declare(strict_types=1);

namespace Backoffice\Shell;

/**
 * Všechno, co layout shellu potřebuje k vykreslení jedné stránky.
 */
final class ShellView
{
	/**
	 * @param list<\Backoffice\Shell\NavGroup> $navigation
	 * @param list<array{label: string, href: string|null}> $breadcrumbs
	 * @param list<mixed> $buttons HTML řetězce (starý admin) nebo Nette\Utils\Html
	 */
	public function __construct(
		public readonly ShellConfig $config,
		public readonly array $navigation,
		public readonly string $title,
		public readonly array $breadcrumbs,
		public readonly array $buttons,
		public readonly ?string $userName,
		public readonly string $cssHref,
		public readonly string $jsHref,
		public readonly ?string $signOutHref,
		public readonly ?string $dashboardHref,
		public readonly bool $legacy,
	) {
	}

	public function userInitials(): string
	{
		if ($this->userName === null || \trim($this->userName) === '') {
			return '?';
		}

		$words = \preg_split('/\s+/u', \trim($this->userName)) ?: [];
		$initials = '';

		foreach (\array_slice($words, 0, 2) as $word) {
			$initials .= \mb_strtoupper(\mb_substr($word, 0, 1));
		}

		return $initials;
	}
}
