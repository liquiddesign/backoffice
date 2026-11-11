<?php

declare(strict_types=1);

namespace Backoffice\Resource;

use Nette\Utils\Strings;
use StORM\Repository;

/**
 * Základní údaje agendy: adresa, název, data a starý presenter, který agenda nahrazuje.
 */
final class Meta
{
	private ?string $slug = null;

	private string $title = '';

	private ?string $itemTitle = null;

	/** @var \StORM\Repository<\StORM\Entity>|null */
	private ?Repository $repository = null;

	private ?string $replaces = null;

	private ?string $legacyIdParameter = null;

	private ?string $icon = null;

	public function slug(string $slug): self
	{
		if (!\preg_match('~^[a-z][a-z0-9-]*$~', $slug)) {
			throw new \InvalidArgumentException("Neplatný slug agendy '$slug' (malá písmena, číslice, pomlčky).");
		}

		$this->slug = $slug;

		return $this;
	}

	public function title(string $title): self
	{
		$this->title = $title;

		return $this;
	}

	/**
	 * Název jedné položky do nadpisu nového záznamu („Nový neobchodní den“).
	 */
	public function itemTitle(string $itemTitle): self
	{
		$this->itemTitle = $itemTitle;

		return $this;
	}

	/**
	 * @param \StORM\Repository<\StORM\Entity> $repository
	 */
	public function repository(Repository $repository): self
	{
		$this->repository = $repository;

		return $this;
	}

	/**
	 * Starý presenter, který agenda nahrazuje (':Stock:Admin:Holiday'). Agenda od něj přebírá
	 * oprávnění z admin_permission a po přepnutí (backoffice: switched:) i položku menu a adresu.
	 *
	 * @param string|null $idParameter jméno parametru se záznamem ve staré adrese detailu (?holiday=…)
	 */
	public function replaces(string $presenter, ?string $idParameter = null): self
	{
		$this->replaces = ':' . Strings::trim($presenter, ':');
		$this->legacyIdParameter = $idParameter;

		return $this;
	}

	public function icon(string $icon): self
	{
		$this->icon = $icon;

		return $this;
	}

	public function getSlug(): string
	{
		return $this->slug ?? throw new \LogicException('Agenda nemá slug — doplňte $meta->slug().');
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getItemTitle(): string
	{
		return $this->itemTitle ?? $this->title;
	}

	/**
	 * @return \StORM\Repository<\StORM\Entity>
	 */
	public function getRepository(): Repository
	{
		return $this->repository ?? throw new \LogicException("Agenda '{$this->slug}' nemá repozitář — doplňte \$meta->repository().");
	}

	public function getReplaces(): ?string
	{
		return $this->replaces;
	}

	public function getLegacyIdParameter(): ?string
	{
		return $this->legacyIdParameter;
	}

	public function getIcon(): ?string
	{
		return $this->icon;
	}

	/**
	 * Odkaz, podle kterého se ptáme na oprávnění. S `replaces` je to starý presenter, takže platí
	 * stávající role; bez něj nový presenter s parametrem agendy.
	 */
	public function permissionLink(string $action): string
	{
		$legacyAction = match ($action) {
			'edit' => 'detail',
			default => $action,
		};

		return $this->replaces !== null
			? $this->replaces . ':' . $legacyAction
			: ':Backoffice:Resource:' . $action;
	}
}
