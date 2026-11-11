<?php

declare(strict_types=1);

namespace Backoffice\Shell;

use Backoffice\Resource\ResourceRegistry;
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
 * Položky, na které uživatel nemá právo, vypadnou; prázdná skupina taky. Položka starého presenteru,
 * který už nahradila přepnutá agenda (backoffice: switched:), vede rovnou na agendu.
 */
final class Navigation
{
	/**
	 * @param array<string, mixed> $menu
	 */
	public function __construct(
		private readonly array $menu,
		private readonly AccessChecker $access,
		private readonly ?ResourceRegistry $resources = null,
	) {
	}

	/**
	 * @param string|null $currentPresenter název aktuálního presenteru ('Web:Admin:Menu') kvůli zvýraznění
	 * @param array<string, mixed> $currentParameters parametry aktuální stránky (agenda: resource)
	 * @return list<\Backoffice\Shell\NavGroup>
	 */
	public function getGroups(?string $currentPresenter = null, array $currentParameters = []): array
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
				$items[] = $this->createItem((string) $label, $link, $itemIcon, $currentPresenter, $currentParameters);
			}

			if ($items === []) {
				continue;
			}

			$groups[] = new NavGroup((string) $title, $items, $icon);
		}

		return $groups;
	}

	/**
	 * @param array<string, mixed> $currentParameters
	 */
	private function createItem(string $label, string $link, ?string $icon, ?string $currentPresenter, array $currentParameters): NavItem
	{
		$resource = $this->resources?->findSwitchedReplacing(NavItem::presenterOf($link));

		if ($resource !== null) {
			$slug = $resource->getMeta()->getSlug();
			$active = $currentPresenter === 'Backoffice:Resource' && ($currentParameters['resource'] ?? null) === $slug;

			return new NavItem($label, ':Backoffice:Resource:default', $icon, $active, ['resource' => $slug]);
		}

		$active = $currentPresenter !== null && NavItem::presenterOf($link) === $currentPresenter;

		return new NavItem($label, $link, $icon, $active);
	}
}
