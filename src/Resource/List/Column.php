<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

/**
 * Sloupec seznamu. Fluent nastavení, vykreslení obstará ListControl podle typu.
 */
class Column
{
	public const TYPE_TEXT = 'text';
	public const TYPE_DATE = 'date';
	public const TYPE_MUTATIONS = 'mutations';

	private bool $sortable = false;

	private bool $searchable = false;

	private bool $link = false;

	private ?string $format = null;

	private ?string $defaultDirection = null;

	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly string $label,
	) {
	}

	public function sortable(bool $sortable = true): static
	{
		$this->sortable = $sortable;

		return $this;
	}

	/**
	 * Sloupec se prohledává polem „Hledat“ nad seznamem.
	 */
	public function searchable(bool $searchable = true): static
	{
		$this->searchable = $searchable;

		return $this;
	}

	/**
	 * Hodnota je odkaz na detail záznamu.
	 */
	public function link(bool $link = true): static
	{
		$this->link = $link;

		return $this;
	}

	/**
	 * Formát data (date()), u textu se nepoužije.
	 */
	public function format(string $format): static
	{
		$this->format = $format;

		return $this;
	}

	/**
	 * Seznam se ve výchozím stavu řadí podle tohoto sloupce (a sloupec je řaditelný).
	 */
	public function defaultSort(string $direction = 'ASC'): static
	{
		$direction = \Nette\Utils\Strings::upper($direction);

		if (!\in_array($direction, ['ASC', 'DESC'], true)) {
			throw new \InvalidArgumentException("Směr řazení musí být ASC nebo DESC, ne '$direction'.");
		}

		$this->sortable = true;
		$this->defaultDirection = $direction;

		return $this;
	}

	public function getDefaultDirection(): ?string
	{
		return $this->defaultDirection;
	}

	public function isSortable(): bool
	{
		return $this->sortable;
	}

	public function isSearchable(): bool
	{
		return $this->searchable;
	}

	public function isLink(): bool
	{
		return $this->link;
	}

	public function getFormat(): ?string
	{
		return $this->format;
	}
}
