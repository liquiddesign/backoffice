<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

/**
 * Popis seznamu agendy: sloupce, hledání, filtry, výchozí řazení, hromadné akce.
 */
final class ListBuilder
{
	public const PER_PAGE_OPTIONS = [20, 50, 100, 200];

	/** @var array<string, \Backoffice\Resource\List\Column> */
	private array $columns = [];

	/** @var array<string, \Backoffice\Resource\List\Filter> */
	private array $filters = [];

	/** @var list<\Backoffice\Resource\List\BulkAction> */
	private array $bulkActions = [];

	private ?string $defaultSort = null;

	private string $defaultDirection = 'ASC';

	private bool $bulkDelete = false;

	private int $perPage = 50;

	private ?string $searchPlaceholder = null;

	/**
	 * @param string|null $mutation u jazykového sloupce pevný jazyk (dva sloupce „Text CS“ a „Text EN“)
	 */
	public function text(string $name, string $label, ?string $mutation = null): Column
	{
		return $this->add(new Column(Column::TYPE_TEXT, $name, $label, $mutation));
	}

	public function date(string $name, string $label): Column
	{
		return $this->add((new Column(Column::TYPE_DATE, $name, $label))->format('j. n. Y'));
	}

	public function integer(string $name, string $label): Column
	{
		return $this->add(new Column(Column::TYPE_INTEGER, $name, $label));
	}

	/**
	 * Ano/ne. S ->editable() přepínač v řádku.
	 */
	public function checkbox(string $name, string $label): Column
	{
		return $this->add(new Column(Column::TYPE_CHECKBOX, $name, $label));
	}

	/**
	 * Štítek stavu podle hodnoty, např. [1 => ['Skryto', 'mute']].
	 * @param array<string|int, array{string, string}> $states
	 */
	public function status(string $name, string $label, array $states): Column
	{
		return $this->add((new Column(Column::TYPE_STATUS, $name, $label))->states($states));
	}

	/**
	 * Pořadí: číslo v řádku a přetahování za úchyt. Seznam se podle něj ve výchozím stavu řadí.
	 * Nové pořadí se ukládá s ostatními změnami tlačítkem „Uložit vše“.
	 */
	public function priority(string $name = 'priority', string $label = 'Pořadí'): Column
	{
		$column = $this->add(new Column(Column::TYPE_PRIORITY, $name, $label));

		if ($this->getDefaultSort() === null) {
			$column->defaultSort();
		}

		return $column;
	}

	/**
	 * Náhled obrázku z userfiles/<directory> (nejlépe adresář s malou kopií, např. 'quick_links/thumb').
	 */
	public function image(string $name, string $label, string $directory): Column
	{
		return $this->add((new Column(Column::TYPE_IMAGE, $name, $label))->directory($directory));
	}

	/**
	 * Počet míst, kde se záznam používá (ResourceDefinition::usages()); odkaz na jejich seznam.
	 */
	public function usage(string $label = 'Použito'): Column
	{
		return $this->add(new Column(Column::TYPE_USAGE, '_usage', $label));
	}

	/**
	 * Štítky jazyků. Bez $column: jazyk je „vyplněný“, když má záznam vyplněné jazykové sloupce
	 * seznamu; s $column podle booleovského jazykového sloupce (např. active).
	 */
	public function mutations(string $label = 'Jazyky', ?string $column = null): Column
	{
		return $this->add(new Column(Column::TYPE_MUTATIONS, '_mutations', $label, $column));
	}

	/**
	 * Text v poli „Hledat“; hledá se ve sloupcích s ->searchable().
	 */
	public function search(string $placeholder): self
	{
		$this->searchPlaceholder = $placeholder;

		return $this;
	}

	/**
	 * Filtr výběrem z hodnot. Bez ->condition() filtruje `sloupec = hodnota`.
	 * @param array<string|int, string> $items hodnota → štítek
	 */
	public function filter(string $name, string $label, array $items): Filter
	{
		if (isset($this->filters[$name])) {
			throw new \LogicException("Filtr '$name' je v seznamu dvakrát.");
		}

		$values = [];

		foreach ($items as $value => $itemLabel) {
			$values[(string) $value] = $itemLabel;
		}

		return $this->filters[$name] = new Filter($name, $label, $values);
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

	/**
	 * Výběr řádků + „Smazat“ v liště pod seznamem.
	 */
	public function bulkDelete(bool $enabled = true): self
	{
		$this->bulkDelete = $enabled;

		return $this;
	}

	/**
	 * Hromadná akce: vybraným řádkům nastaví sloupci hodnotu (např. bulkSet('hidden', true, 'Skrýt')).
	 */
	public function bulkSet(string $column, string|int|bool|null $value, string $label): self
	{
		$this->bulkActions[] = new BulkAction($column, $value, $label);

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

	public function getColumn(string $key): ?Column
	{
		return $this->columns[$key] ?? null;
	}

	/**
	 * @return array<string, \Backoffice\Resource\List\Column>
	 */
	public function getEditableColumns(): array
	{
		return \array_filter($this->columns, static fn (Column $column): bool => $column->isEditable());
	}

	public function getPriorityColumn(): ?Column
	{
		foreach ($this->columns as $column) {
			if ($column->type === Column::TYPE_PRIORITY) {
				return $column;
			}
		}

		return null;
	}

	/**
	 * @return array<string, \Backoffice\Resource\List\Filter>
	 */
	public function getFilters(): array
	{
		return $this->filters;
	}

	/**
	 * @return list<\Backoffice\Resource\List\BulkAction>
	 */
	public function getBulkActions(): array
	{
		return $this->bulkActions;
	}

	public function getDefaultSort(): ?string
	{
		if ($this->defaultSort !== null) {
			return $this->defaultSort;
		}

		foreach ($this->columns as $column) {
			if ($column->getDefaultDirection() !== null) {
				return $column->key;
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

	/**
	 * Má seznam výběr řádků (zaškrtávátka)?
	 */
	public function hasSelection(): bool
	{
		return $this->bulkDelete || $this->bulkActions !== [];
	}

	public function getPerPage(): int
	{
		return $this->perPage;
	}

	/**
	 * Hledání je zapnuté, když má seznam aspoň jeden sloupec s ->searchable().
	 */
	public function hasSearch(): bool
	{
		return $this->getSearchableColumns() !== [];
	}

	public function getSearchPlaceholder(): string
	{
		if ($this->searchPlaceholder !== null) {
			return $this->searchPlaceholder;
		}

		$labels = \array_map(static fn (Column $column): string => Strings::lower($column->label), $this->getSearchableColumns());

		return 'Hledat: ' . \implode(', ', \array_unique($labels));
	}

	/**
	 * @return list<\Backoffice\Resource\List\Column>
	 */
	public function getSearchableColumns(): array
	{
		return \array_values(\array_filter($this->columns, static fn (Column $column): bool => $column->isSearchable()));
	}

	private function add(Column $column): Column
	{
		if (isset($this->columns[$column->key])) {
			throw new \LogicException("Sloupec '{$column->key}' je v seznamu dvakrát.");
		}

		$this->columns[$column->key] = $column;

		return $column;
	}
}
