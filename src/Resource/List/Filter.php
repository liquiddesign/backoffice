<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

use StORM\ICollection;

/**
 * Filtr nad seznamem: výběr z hodnot. Bez vlastní podmínky filtruje `sloupec = hodnota`.
 */
final class Filter
{
	/** @var (callable(\StORM\ICollection<\StORM\Entity>, string): void)|null */
	private $condition = null;

	private ?string $default = null;

	private ?string $prompt = 'Všechny';

	/**
	 * @param array<string, string> $items hodnota → štítek
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		private readonly array $items,
	) {
	}

	/**
	 * Vlastní podmínka: fn (ICollection $collection, string $value) => $collection->where(…).
	 * @param callable(\StORM\ICollection<\StORM\Entity>, string): void $condition
	 */
	public function condition(callable $condition): self
	{
		$this->condition = $condition;

		return $this;
	}

	/**
	 * Výchozí hodnota. Filtr s výchozí hodnotou nemá volbu „Všechny“ a „Zrušit vše“ ho vrátí sem.
	 */
	public function default(string $value): self
	{
		$this->default = $value;
		$this->prompt = null;

		return $this;
	}

	public function getDefault(): ?string
	{
		return $this->default;
	}

	public function getPrompt(): ?string
	{
		return $this->prompt;
	}

	/**
	 * @return array<string, string>
	 */
	public function getItems(): array
	{
		$items = [];

		foreach ($this->items as $value => $label) {
			$items[(string) $value] = $label;
		}

		return $items;
	}

	public function hasItem(string $value): bool
	{
		return \array_key_exists($value, $this->getItems());
	}

	/**
	 * @param \StORM\ICollection<\StORM\Entity> $collection
	 * @param \Closure(): string $sqlColumn SQL sloupec filtru — jen pro filtr bez vlastní podmínky
	 */
	public function apply(ICollection $collection, string $value, \Closure $sqlColumn): void
	{
		if ($this->condition !== null) {
			($this->condition)($collection, $value);

			return;
		}

		$collection->where($sqlColumn(), $value);
	}
}
