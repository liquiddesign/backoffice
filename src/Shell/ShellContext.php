<?php

declare(strict_types=1);

namespace Backoffice\Shell;

use Backoffice\Security\AccessChecker;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\Presenter;

/**
 * Skládá ShellView pro jednu stránku — pro nativní presentery balíku i pro staré presentery přes most.
 */
final class ShellContext
{
	public const LAYOUT = __DIR__ . '/templates/@layout.latte';

	public const ASSETS_DIR = __DIR__ . '/../../dist';

	public function __construct(
		public readonly ShellConfig $config,
		private readonly Navigation $navigation,
		public readonly AccessChecker $access,
	) {
	}

	/**
	 * @param array<mixed> $breadcrumbs položky ve formátu headerTree ze starého adminu: [popisek, odkaz?, parametry?]
	 * @param array<mixed> $buttons HTML řetězce nebo Nette\Utils\Html
	 */
	public function createView(Presenter $presenter, string $title, array $breadcrumbs = [], array $buttons = [], bool $legacy = false): ShellView
	{
		return new ShellView(
			config: $this->config,
			navigation: $this->navigation->getGroups($presenter->getName(), $presenter->getParameters()),
			title: $title,
			breadcrumbs: $this->resolveBreadcrumbs($presenter, $breadcrumbs),
			buttons: \array_values($buttons),
			userName: $this->access->getDisplayName(),
			cssHref: $this->assetHref($presenter, 'backoffice.css'),
			jsHref: $this->assetHref($presenter, 'backoffice.js'),
			signOutHref: $this->safeLink($presenter, $this->config->signOutLink),
			dashboardHref: $this->safeLink($presenter, $this->config->dashboardLink),
			legacy: $legacy,
		);
	}

	private function assetHref(Presenter $presenter, string $file): string
	{
		$path = self::ASSETS_DIR . '/' . $file;
		$version = \is_file($path) ? (string) \filemtime($path) : '0';

		return $presenter->link(':Backoffice:Asset:default', ['file' => $file, 'v' => $version]);
	}

	/**
	 * @param array<mixed> $items
	 * @return list<array{label: string, href: string|null}>
	 */
	private function resolveBreadcrumbs(Presenter $presenter, array $items): array
	{
		$result = [];

		foreach ($items as $item) {
			if (\is_string($item)) {
				$result[] = ['label' => $item, 'href' => null];

				continue;
			}

			if (!\is_array($item) || !isset($item[0])) {
				continue;
			}

			$link = isset($item[1]) && \is_string($item[1]) ? $item[1] : null;
			$args = isset($item[2]) ? (\is_array($item[2]) ? $item[2] : [$item[2]]) : [];
			$result[] = ['label' => (string) $item[0], 'href' => $this->safeLink($presenter, $link, $args)];
		}

		return $result;
	}

	/**
	 * @param array<mixed> $args
	 */
	private function safeLink(Presenter $presenter, ?string $link, array $args = []): ?string
	{
		if ($link === null) {
			return null;
		}

		try {
			return $presenter->link($link, $args);
		} catch (InvalidLinkException) {
			return null;
		}
	}
}
