<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

/**
 * Popis seznamu agendy: sloupce, výchozí řazení, hromadné akce.
 */
final class ListBuilder
{
	/** @var array<string, \Backoffice\Resource\List\Column> */
	private array $columns = [];

	private ?string $defaultSort = null;

	private string $defaultDirection = 'ASC';

	private bool $bulkDelete = false;

	private int $perPage = 50;

	public function text(string $name, string $label): Column
	{
		return $this->add(new Column(Column::TYPE_TEXT, $name, $label));
	}

	public function date(string $name, string $label): Column
	{
		return $this->add((new Column(Column::TYPE_DATE, $name, $label))->format('j. n. Y'));
	}

	/**
	 * Sloupec s jazyky, ve kterých má záznam vyplněné jazykové sloupce.
	 */
	public function mutations(string $label = 'Jazyky'): Column
	{
		return $this->add(new Column(Column::TYPE_MUTATIONS, '_mutations', $label));
	}

	public function defaultSort(string $column, string $direction = 'ASC'): self
	{
		$direction = Strings::upper($direction);

		if (!Arrays::contains(['ASC', 'DESC'], $direction)) {
			throw new \InvalidArgumentException("Směr řazení musí být ASC nebo DESC, ne '$direction'.");
		}

		$this->defaultSort = $column;
		$this->defaultDirection = $direction;

		return $this;
	}

	public function bulkDelete(bool $enabled = true): self
	{
		$this->bulkDelete = $enabled;

		return $this;
	}

	public function perPage(int $perPage): self
	{
		$this->perPage = \max(1, $perPage);

		return $this;
	}

	/**
	 * @return array<string, \Backoffice\Resource\List\Column>
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	public function getDefaultSort(): ?string
	{
		if ($this->defaultSort !== null) {
			return $this->defaultSort;
		}

		foreach ($this->columns as $column) {
			if ($column->getDefaultDirection() !== null) {
				return $column->name;
			}
		}

		return null;
	}

	public function getDefaultDirection(): string
	{
		if ($this->defaultSort === null) {
			foreach ($this->columns as $column) {
				if ($column->getDefaultDirection() !== null) {
					return $column->getDefaultDirection();
				}
			}
		}

		return $this->defaultDirection;
	}

	public function hasBulkDelete(): bool
	{
		return $this->bulkDelete;
	}

	public function getPerPage(): int
	{
		return $this->perPage;
	}

	/**
	 * @return list<string>
	 */
	public function getSearchableColumns(): array
	{
		return \array_values(\array_map(
			static fn (Column $column): string => $column->name,
			\array_filter($this->columns, static fn (Column $column): bool => $column->isSearchable()),
		));
	}

	private function add(Column $column): Column
	{
		if (isset($this->columns[$column->name])) {
			throw new \LogicException("Sloupec '{$column->name}' je v seznamu dvakrát.");
		}

		$this->columns[$column->name] = $column;

		return $column;
	}
}
