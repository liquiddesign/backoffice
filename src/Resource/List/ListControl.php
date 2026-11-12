<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

use Backoffice\Security\ChangeLogger;
use Backoffice\UI\ResourcePresenter;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use StORM\Collection;
use StORM\Entity;

/**
 * Tabulka seznamu agendy podle návrhu: hledání a filtry s „Aktivními filtry“, řazení, stránkování,
 * výběr řádků a hromadné akce, úprava v řádku s „Uložit vše“ a pořadí přetažením.
 *
 * Stav (hledání, filtry, řazení, strana) je v adrese jako list-q, list-f[…], list-sort…, takže
 * odkaz na filtrovaný seznam jde poslat a „Zpět“ v prohlížeči funguje.
 */
final class ListControl extends Control
{
	#[Persistent]
	public string $q = '';

	/** @var array<string, string> */
	#[Persistent]
	public array $f = [];

	#[Persistent]
	public string $sort = '';

	#[Persistent]
	public string $dir = '';

	#[Persistent]
	public int $page = 1;

	#[Persistent]
	public int $perPage = 0;

	private ?ListBuilder $list = null;

	public function render(): void
	{
		$list = $this->getList();
		$collection = $this->filteredCollection();
		$pager = new Pager($collection->enum(), $this->getPerPage(), $this->page);
		[$sortKey, $direction] = $this->getSort();
		$this->applySort($collection, $sortKey, $direction);
		$collection->page($pager->page, $pager->perPage);

		$presenter = $this->presenter();
		$meta = $presenter->getDefinition()->getMeta();
		$canEdit = $presenter->isAllowedAction('edit');
		$priority = $list->getPriorityColumn();

		$template = $this->getTemplate();
		\assert($template instanceof Template);
		$rows = $collection->toArray();
		$usages = [];
		$locked = [];

		foreach ($rows as $row) {
			$locations = $presenter->getDefinition()->usages($row);
			$usages[(string) $row->getPK()] = $locations === null ? null : \count($locations);
			$locked[(string) $row->getPK()] = $presenter->getDefinition()->lockReason($row);
		}

		$template->list = $list;
		$template->rows = $rows;
		$template->usages = $usages;
		$template->locked = $locked;
		$template->pager = $pager;
		$template->sortKey = $sortKey;
		$template->direction = $direction;
		$template->mutations = $presenter->mutations();
		$template->localizedColumns = $this->localizedColumns();
		$template->filterValues = $this->filterValues();
		$template->activeFilters = $this->activeFilters();
		$template->perPageOptions = \array_values(\array_unique([...ListBuilder::PER_PAGE_OPTIONS, $list->getPerPage()]));
		$template->canEdit = $canEdit;
		$template->canDelete = $presenter->canDelete();
		$template->hasDetail = $meta->hasDetail() && $canEdit;
		$template->editable = $canEdit && $list->getEditableColumns() !== [];
		$template->selection = $canEdit && ($list->getBulkActions() !== [] || ($list->hasBulkDelete() && $presenter->canDelete()));
		$template->canReorder = $canEdit && $priority !== null && $sortKey === $priority->key && $direction === 'ASC';
		$template->pkName = $this->pkName();
		$template->setFile(__DIR__ . '/../templates/list.latte');
		$template->render();
	}

	/**
	 * Odeslání tabulky: „Uložit vše“ (úpravy v řádcích), hromadná akce nebo smazání řádku.
	 * Tlačítko posílá action = save | delete | bulk-<n> | delete-row:<id>.
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleSubmit(): void
	{
		$presenter = $this->presenter();
		$post = $presenter->getRequest()->getPost();
		$action = \is_string($post['action'] ?? null) ? $post['action'] : 'save';

		if (!$presenter->isAllowedAction('edit')) {
			$this->error('Na úpravy nemáte oprávnění.', 403);
		}

		$ids = \array_values(\array_filter(\is_array($post['ids'] ?? null) ? $post['ids'] : [], 'is_string'));

		match (true) {
			$action === 'save' => $this->saveRows(\is_array($post['row'] ?? null) ? $post['row'] : []),
			$action === 'delete' => $this->deleteRows($ids),
			Strings::startsWith($action, 'delete-row:') => $this->deleteRows([Strings::substring($action, 11)]),
			Strings::startsWith($action, 'bulk-') => $this->bulkSet((int) Strings::substring($action, 5), $ids),
			default => $this->error('Neznámá akce.'),
		};

		$this->redirect('this');
	}

	/**
	 * Odkaz na seznam bez jednoho filtru (štítek v „Aktivních filtrech“). '' = hledání, '*' = vše.
	 */
	public function withoutFilterLink(string $name): string
	{
		$filters = $this->f;

		if ($name === '*') {
			$filters = [];
		} else {
			unset($filters[$name]);
		}

		return $this->link('this', ['q' => $name === '' || $name === '*' ? '' : $this->q, 'f' => $filters, 'page' => 1]);
	}

	public function sortLink(Column $column): string
	{
		[$sortKey, $direction] = $this->getSort();
		$next = $sortKey === $column->key && $direction === 'ASC' ? 'DESC' : 'ASC';

		return $this->link('this', ['sort' => $column->key, 'dir' => $next, 'page' => 1]);
	}

	/**
	 * Hodnota buňky pro zobrazení (bez HTML).
	 */
	public function cellValue(Entity $row, Column $column): mixed
	{
		$callback = $column->getValueCallback();
		$value = $callback !== null ? $callback($row) : $row->getValue($column->name, $column->mutation);

		if ($column->getPattern() !== null && $value !== null && $value !== '') {
			$value = \sprintf($column->getPattern(), \is_scalar($value) ? (string) $value : '');
		}

		if ($column->type === Column::TYPE_DATE && $value !== null && $value !== '') {
			$value = (new \DateTimeImmutable((string) $value))->format($column->getFormat() ?? 'j. n. Y');
		}

		return $value;
	}

	/**
	 * Adresa odkazu ven ze sloupce s ->externalUrl(), nebo null (prázdná hodnota).
	 */
	public function cellUrl(Entity $row, Column $column): ?string
	{
		$pattern = $column->getExternalUrl();

		if ($pattern === null) {
			return null;
		}

		$value = $row->getValue($column->name, $column->mutation);

		if (!\is_scalar($value) || (string) $value === '') {
			return null;
		}

		return \sprintf($pattern, \rawurlencode((string) $value));
	}

	/**
	 * @param array<mixed> $posted pk → [klíč sloupce → hodnota]
	 */
	private function saveRows(array $posted): void
	{
		$presenter = $this->presenter();
		$columns = $this->getList()->getEditableColumns();
		$entities = $this->loadEntities(\array_map('strval', \array_keys($posted)));
		$structure = $presenter->getDefinition()->getMeta()->getRepository()->getStructure();
		$errors = [];
		$changes = [];

		foreach ($entities as $pk => $entity) {
			if ($presenter->getDefinition()->lockReason($entity) !== null) {
				continue;
			}

			$values = \is_array($posted[$pk] ?? null) ? $posted[$pk] : [];
			/** @var array<string, mixed> $new */
			$new = [];
			/** @var array<string, mixed> $old */
			$old = [];
			/** @var array<string, array<string, mixed>> $newLocalized */
			$newLocalized = [];
			/** @var array<string, array<string, mixed>> $oldLocalized */
			$oldLocalized = [];

			foreach ($columns as $column) {
				if (!\array_key_exists($column->key, $values) || !\is_scalar($values[$column->key])) {
					continue;
				}

				$raw = (string) $values[$column->key];

				if (Arrays::contains([Column::TYPE_INTEGER, Column::TYPE_PRIORITY], $column->type) && !Validators::isNumericInt(Strings::trim($raw))) {
					$errors[] = "{$column->label}: „{$raw}“ není celé číslo.";

					continue;
				}

				$nullable = (bool) $structure->getColumn($column->name)?->isNullable();
				$value = $this->normalize($column, $raw, $nullable);
				$current = $entity->getValue($column->name, $column->mutation);

				if ($this->same($column, $current, $value)) {
					continue;
				}

				if ($column->mutation !== null) {
					$newLocalized[$column->name][$column->mutation] = $value;
					$oldLocalized[$column->name][$column->mutation] = $current;
				} else {
					$new[$column->name] = $value;
					$old[$column->name] = $current;
				}
			}

			$new += $newLocalized;
			$old += $oldLocalized;

			if ($new === []) {
				continue;
			}

			$changes[$pk] = [$entity, $new, $old];
		}

		if ($errors !== []) {
			$presenter->flashMessage('Nic se neuložilo. ' . \implode(' ', \array_unique($errors)), 'error');

			return;
		}

		if ($changes === []) {
			$presenter->flashMessage('Žádné změny k uložení.', 'info');

			return;
		}

		$undo = [];

		foreach ($changes as $pk => [$entity, $new, $old]) {
			$entity->update($new);
			$presenter->logChange(ChangeLogger::TYPE_DETAIL, (string) $pk);
			$undo[] = ['update', (string) $pk, $old];
		}

		$presenter->getDefinition()->changed();
		$presenter->flashWithUndo('Uloženo: ' . self::records(\count($changes)) . '.', $undo);
	}

	/**
	 * @param list<string> $ids
	 */
	private function deleteRows(array $ids): void
	{
		$presenter = $this->presenter();

		if (!$presenter->canDelete()) {
			$this->error('Na mazání nemáte oprávnění.', 403);
		}

		$undo = [];
		$failed = 0;
		$skipped = [];

		foreach ($this->loadEntities($ids) as $pk => $entity) {
			$locations = $presenter->getDefinition()->usages($entity);

			if (($locations !== null && $locations !== []) || $presenter->getDefinition()->lockReason($entity) !== null) {
				$skipped[] = $presenter->entityTitle($entity);

				continue;
			}

			$data = $entity->toArray();

			try {
				$entity->delete();
			} catch (\PDOException) {
				$failed++;

				continue;
			}

			$data[ResourcePresenter::UNDO_FILES] = $presenter->trashFiles($entity);
			$presenter->logChange(ChangeLogger::TYPE_DELETE, (string) $pk);
			$undo[] = ['restore', (string) $pk, $data];
		}

		if ($undo !== []) {
			$presenter->getDefinition()->changed();
			$presenter->flashWithUndo('Smazáno: ' . self::records(\count($undo)) . '.', $undo);
		}

		if ($skipped !== []) {
			$presenter->flashMessage('Nesmazáno (používá se na stránkách, nebo nejde měnit): ' . \implode(', ', $skipped) . '.', 'warning');
		}

		if ($failed > 0) {
			$presenter->flashMessage('Nepodařilo se smazat ' . self::records($failed) . ' — nejspíš se na ně odkazují jiná data.', 'error');
		}

		if ($undo !== [] || $failed !== 0 || $skipped !== []) {
			return;
		}

		$presenter->flashMessage('Nevybrali jste žádný řádek.', 'warning');
	}

	/**
	 * @param list<string> $ids
	 */
	private function bulkSet(int $index, array $ids): void
	{
		$presenter = $this->presenter();
		$action = $this->getList()->getBulkActions()[$index] ?? $this->error('Neznámá hromadná akce.');
		$undo = [];

		foreach ($this->loadEntities($ids) as $pk => $entity) {
			if ($presenter->getDefinition()->lockReason($entity) !== null) {
				continue;
			}

			$current = $entity->getValue($action->column);

			$same = \is_bool($action->value)
				? (bool) $current === $action->value
				: ($current === null ? null : (string) $current) === ($action->value === null ? null : (string) $action->value);

			if ($same) {
				continue;
			}

			$entity->update([$action->column => $action->value]);
			$presenter->logChange(ChangeLogger::TYPE_DETAIL, (string) $pk);
			$undo[] = ['update', (string) $pk, [$action->column => $current]];
		}

		if ($ids === []) {
			$presenter->flashMessage('Nevybrali jste žádný řádek.', 'warning');

			return;
		}

		if ($undo === []) {
			$presenter->flashMessage('Vybrané řádky už ten stav mají.', 'info');

			return;
		}

		$presenter->getDefinition()->changed();
		$presenter->flashWithUndo($action->label . ': ' . self::records(\count($undo)) . '.', $undo);
	}

	private function normalize(Column $column, string $raw, bool $nullable): string|int|bool|null
	{
		return match ($column->type) {
			Column::TYPE_CHECKBOX => $raw === '1',
			Column::TYPE_INTEGER, Column::TYPE_PRIORITY => (int) Strings::trim($raw),
			default => $nullable && $raw === '' ? null : $raw,
		};
	}

	private function same(Column $column, mixed $current, string|int|bool|null $value): bool
	{
		return match ($column->type) {
			Column::TYPE_CHECKBOX => (bool) $current === $value,
			Column::TYPE_INTEGER, Column::TYPE_PRIORITY => $current !== null && (int) $current === $value,
			default => ($current === null ? null : (string) $current) === ($value === null ? null : (string) $value),
		};
	}

	/**
	 * @param list<string> $ids
	 * @return array<string, \StORM\Entity>
	 */
	private function loadEntities(array $ids): array
	{
		if ($ids === []) {
			return [];
		}

		$repository = $this->presenter()->getDefinition()->getMeta()->getRepository();
		$entities = [];

		foreach ($repository->many()->where('this.' . $this->pkName(), $ids)->toArray() as $entity) {
			$entities[(string) $entity->getPK()] = $entity;
		}

		return $entities;
	}

	/**
	 * Kolekce po hledání a filtrech (bez řazení a stránkování).
	 * @return \StORM\Collection<\StORM\Entity>
	 */
	private function filteredCollection(): Collection
	{
		$collection = $this->presenter()->getDefinition()->getMeta()->getRepository()->many();
		$query = Strings::trim($this->q);

		if ($query !== '') {
			$conditions = [];

			foreach ($this->getList()->getSearchableColumns() as $column) {
				foreach ($this->sqlColumns($column->name, $column->mutation) as $sqlColumn) {
					$conditions[] = "$sqlColumn LIKE :bo_q";
				}
			}

			if ($conditions !== []) {
				$collection->where('(' . \implode(' OR ', \array_unique($conditions)) . ')', ['bo_q' => '%' . \addcslashes($query, '%_\\') . '%']);
			}
		}

		foreach ($this->getList()->getFilters() as $filter) {
			$value = $this->filterValues()[$filter->name];

			if ($value === null) {
				continue;
			}

			$filter->apply($collection, $value, fn (): string => $this->sqlColumn($filter->name));
		}

		return $collection;
	}

	/**
	 * Platné hodnoty filtrů (neplatná hodnota z adresy = výchozí).
	 * @return array<string, string|null>
	 */
	private function filterValues(): array
	{
		$values = [];

		foreach ($this->getList()->getFilters() as $filter) {
			$value = $this->f[$filter->name] ?? null;
			$values[$filter->name] = \is_string($value) && $filter->hasItem($value) ? $value : $filter->getDefault();
		}

		return $values;
	}

	/**
	 * Štítky „Aktivní filtry“: klíč filtru ('' = hledání) → text.
	 * @return array<string, string>
	 */
	private function activeFilters(): array
	{
		$active = [];

		if (Strings::trim($this->q) !== '') {
			$active[''] = 'Hledat: ' . Strings::trim($this->q);
		}

		foreach ($this->getList()->getFilters() as $filter) {
			$value = $this->filterValues()[$filter->name];

			if ($value === null || $value === $filter->getDefault()) {
				continue;
			}

			$active[$filter->name] = $filter->label . ': ' . $filter->getItems()[$value];
		}

		return $active;
	}

	/**
	 * @return array{string|null, string} klíč sloupce (nebo null) a směr
	 */
	private function getSort(): array
	{
		$list = $this->getList();
		$column = $list->getColumn($this->sort);

		if ($column !== null && $column->isSortable()) {
			return [$column->key, $this->dir === 'DESC' ? 'DESC' : 'ASC'];
		}

		return [$list->getDefaultSort(), $list->getDefaultDirection()];
	}

	/**
	 * @param \StORM\Collection<\StORM\Entity> $collection
	 */
	private function applySort(Collection $collection, ?string $sortKey, string $direction): void
	{
		$order = [];
		$column = $sortKey !== null ? $this->getList()->getColumn($sortKey) : null;

		if ($column !== null) {
			$order[$this->sqlColumn($column->name, $column->mutation)] = $direction;
		} elseif ($sortKey !== null) {
			$order[$this->sqlColumn($sortKey)] = $direction;
		}

		// Stálé pořadí při shodných hodnotách (stránkování, přetahování).
		$order['this.' . $this->pkName()] = 'ASC';
		$collection->orderBy($order);
	}

	private function getPerPage(): int
	{
		$options = [...ListBuilder::PER_PAGE_OPTIONS, $this->getList()->getPerPage()];

		return Arrays::contains($options, $this->perPage) ? $this->perPage : $this->getList()->getPerPage();
	}

	/**
	 * SQL sloupec vlastnosti; u jazykového sloupce aktuální (nebo zadaný) jazyk.
	 */
	private function sqlColumn(string $property, ?string $mutation = null): string
	{
		$repository = $this->presenter()->getDefinition()->getMeta()->getRepository();
		$column = $repository->getStructure()->getColumn($property);

		if ($column === null) {
			throw new \LogicException("Sloupec '$property' v entitě agendy není.");
		}

		$name = 'this.' . $column->getName();

		if (!$column->hasMutations()) {
			return $name;
		}

		$connection = $repository->getConnection();

		return $name . ($connection->getAvailableMutations()[$mutation ?? $connection->getMutation()] ?? $connection->getMutationSuffix());
	}

	/**
	 * SQL sloupce pro hledání: jazykový sloupec bez pevného jazyka ve všech jazycích.
	 * @return list<string>
	 */
	private function sqlColumns(string $property, ?string $mutation): array
	{
		$repository = $this->presenter()->getDefinition()->getMeta()->getRepository();
		$column = $repository->getStructure()->getColumn($property);

		if ($column === null || !$column->hasMutations() || $mutation !== null) {
			return [$this->sqlColumn($property, $mutation)];
		}

		return \array_map(fn (string $mutation): string => $this->sqlColumn($property, $mutation), $this->presenter()->mutations());
	}

	/**
	 * Jazykové sloupce seznamu — pro sloupec „Jazyky“ bez vlastního sloupce.
	 * @return list<string>
	 */
	private function localizedColumns(): array
	{
		$structure = $this->presenter()->getDefinition()->getMeta()->getRepository()->getStructure();
		$names = [];

		foreach ($this->getList()->getColumns() as $column) {
			if ($column->type === Column::TYPE_MUTATIONS || !$structure->getColumn($column->name)?->hasMutations()) {
				continue;
			}

			$names[] = $column->name;
		}

		return \array_values(\array_unique($names));
	}

	private function pkName(): string
	{
		return $this->presenter()->getDefinition()->getMeta()->getRepository()->getStructure()->getPK()->getName();
	}

	private function getList(): ListBuilder
	{
		return $this->list ??= $this->presenter()->getDefinition()->buildList();
	}

	private function presenter(): ResourcePresenter
	{
		$presenter = $this->getPresenter();

		if (!$presenter instanceof ResourcePresenter) {
			throw new \LogicException('ListControl patří jen do ResourcePresenter.');
		}

		return $presenter;
	}

	private static function records(int $count): string
	{
		return $count . ' ' . match (true) {
			$count === 1 => 'záznam',
			$count >= 2 && $count <= 4 => 'záznamy',
			default => 'záznamů',
		};
	}
}
