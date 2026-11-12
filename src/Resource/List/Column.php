<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

/**
 * Sloupec seznamu. Fluent nastavení, vykreslení obstará ListControl podle typu.
 */
class Column
{
	public const TYPE_TEXT = 'text';
	public const TYPE_DATE = 'date';
	public const TYPE_INTEGER = 'integer';
	public const TYPE_CHECKBOX = 'checkbox';
	public const TYPE_STATUS = 'status';
	public const TYPE_PRIORITY = 'priority';
	public const TYPE_MUTATIONS = 'mutations';

	/** Typy, které jde upravit přímo v řádku (->editable()). */
	public const EDITABLE_TYPES = [self::TYPE_TEXT, self::TYPE_INTEGER, self::TYPE_CHECKBOX, self::TYPE_PRIORITY];

	/** Klíč sloupce v seznamu: jméno, u jazykového sloupce s pevným jazykem jméno-jazyk. */
	public readonly string $key;

	private bool $sortable;

	private bool $searchable = false;

	private bool $link = false;

	private bool $editable;

	private bool $mono = false;

	private ?string $format = null;

	private ?string $defaultDirection = null;

	/** @var array<string, array{string, string}> hodnota → [štítek, varianta] */
	private array $states = [];

	/**
	 * @param string|null $mutation u jazykového sloupce pevný jazyk (jinak aktuální jazyk administrace);
	 *   u sloupce Jazyky booleovský jazykový sloupec, podle kterého se štítky barví
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly string $label,
		public readonly ?string $mutation = null,
	) {
		$this->key = $mutation !== null && $type !== self::TYPE_MUTATIONS ? $name . '-' . $mutation : $name;
		$this->sortable = $type === self::TYPE_PRIORITY;
		$this->editable = $type === self::TYPE_PRIORITY;
	}

	public function sortable(bool $sortable = true): static
	{
		$this->sortable = $sortable;

		return $this;
	}

	/**
	 * Sloupec se prohledává polem „Hledat“ nad seznamem (jazykový sloupec ve všech jazycích).
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
	 * Hodnota se upravuje přímo v řádku a ukládá tlačítkem „Uložit vše“ (text, číslo, zaškrtávátko).
	 */
	public function editable(bool $editable = true): static
	{
		if ($editable && !Arrays::contains(self::EDITABLE_TYPES, $this->type)) {
			throw new \LogicException("Sloupec '{$this->name}' typu {$this->type} nejde upravovat v řádku.");
		}

		$this->editable = $editable;

		return $this;
	}

	/**
	 * Neproporcionální písmo (kódy, ID).
	 */
	public function mono(bool $mono = true): static
	{
		$this->mono = $mono;

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
	 * Štítky stavů: hodnota → [štítek, varianta]. Varianta: ok, info, warn, danger, brand, mute.
	 * Hodnota bez štítku se nezobrazí; true/false se zapisují jako 1/0.
	 * @param array<string|int, array{string, string}> $states
	 */
	public function states(array $states): static
	{
		$this->states = [];

		foreach ($states as $value => $state) {
			$this->states[(string) $value] = $state;
		}

		return $this;
	}

	/**
	 * Seznam se ve výchozím stavu řadí podle tohoto sloupce (a sloupec je řaditelný).
	 */
	public function defaultSort(string $direction = 'ASC'): static
	{
		$direction = Strings::upper($direction);

		if (!Arrays::contains(['ASC', 'DESC'], $direction)) {
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

	public function isEditable(): bool
	{
		return $this->editable;
	}

	public function isMono(): bool
	{
		return $this->mono;
	}

	public function getFormat(): ?string
	{
		return $this->format;
	}

	/**
	 * Štítek stavu pro hodnotu, nebo null.
	 * @return array{string, string}|null
	 */
	public function getState(mixed $value): ?array
	{
		$key = \is_bool($value) ? (string) (int) $value : (\is_scalar($value) ? (string) $value : '');

		return $this->states[$key] ?? null;
	}
}
