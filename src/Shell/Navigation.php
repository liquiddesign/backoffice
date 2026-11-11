<?php

declare(strict_types=1);

namespace Backoffice\Shell;

use Backoffice\Security\AccessChecker;

/**
 * Menu shellu. Bere stejný formát jako `admin: menu:` z liquiddesign/admin, aby most šel zapnout
 * bez přepisování menu:
 *
 * ```neon
 * "Obsah":
 *     icon: newspaper
 *     items:
 *         "Menu a obsah":
 *             link: ':Web:Admin:Menu:default'
 *         "Rychlé odkazy": ':Web:Admin:QuickLinks:default'
 * ```
 *
 * Položky, na které uživatel nemá právo, vypadnou; prázdná skupina taky.
 */
final class Navigation
{
	/**
	 * @param array<string, mixed> $menu
	 */
	public function __construct(private readonly array $menu, private readonly AccessChecker $access)
	{
	}

	/**
	 * @param string|null $currentPresenter název aktuálního presenteru ('Web:Admin:Menu') kvůli zvýraznění
	 * @return list<\Backoffice\Shell\NavGroup>
	 */
	public function getGroups(?string $currentPresenter = null): array
	{
		$groups = [];

		foreach ($this->menu as $title => $group) {
			if (!\is_array($group)) {
				continue;
			}

			$icon = isset($group['icon']) && \is_string($group['icon']) ? $group['icon'] : null;
			$items = [];
			$definitions = isset($group['items']) && \is_array($group['items']) ? $group['items'] : [];

			// Skupina bez položek, jen s odkazem (staré „Články“) — jedna položka se jménem skupiny.
			if ($definitions === [] && isset($group['link']) && \is_string($group['link'])) {
				$definitions = [(string) $title => $group['link']];
			}

			foreach ($definitions as $label => $definition) {
				$link = \is_string($definition) ? $definition : (\is_array($definition) && isset($definition['link']) && \is_string($definition['link']) ? $definition['link'] : null);

				if ($link === null || !$this->access->isAllowed($link)) {
					continue;
				}

				$itemIcon = \is_array($definition) && isset($definition['icon']) && \is_string($definition['icon']) ? $definition['icon'] : null;
				$active = $currentPresenter !== null && NavItem::presenterOf($link) === $currentPresenter;
				$items[] = new NavItem((string) $label, $link, $itemIcon, $active);
			}

			if ($items === []) {
				continue;
			}

			$groups[] = new NavGroup((string) $title, $items, $icon);
		}

		return $groups;
	}
}
