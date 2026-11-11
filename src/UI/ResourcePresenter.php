<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Resource\List\Column;
use Backoffice\Resource\ResourceDefinition;
use Backoffice\Resource\ResourceRegistry;
use Nette\Application\Attributes\Persistent;
use Nette\DI\Attributes\Inject;
use StORM\Entity;

/**
 * Jeden presenter pro všechny deklarativní agendy: <prefix>/r/<agenda>[/<akce>][/<id>].
 *
 * Etapa 0: seznam jen ke čtení. Formulář (edit, new) a mazání přijdou v etapě 1.
 */
final class ResourcePresenter extends BackofficePresenter
{
	#[Persistent]
	public string $resource = '';

	#[Inject]
	public ResourceRegistry $registry;

	private ?ResourceDefinition $definition = null;

	public function checkRequirements(mixed $element): void
	{
		$this->definition = $this->registry->get($this->resource);

		if ($this->definition === null) {
			$this->error("Agenda '{$this->resource}' neexistuje.");
		}

		parent::checkRequirements($element);
	}

	public function renderDefault(int $page = 1): void
	{
		$definition = $this->getDefinition();
		$meta = $definition->getMeta();
		$list = $definition->buildList();
		$repository = $meta->getRepository();

		$collection = $repository->many();
		$sort = $list->getDefaultSort();

		if ($sort !== null) {
			$collection->orderBy(['this.' . $sort => $list->getDefaultDirection()]);
		}

		$total = $collection->enum();
		$pages = \max(1, (int) \ceil($total / $list->getPerPage()));
		$page = \min(\max(1, $page), $pages);
		$collection->page($page, $list->getPerPage());

		$this->title = $meta->getTitle();
		$this->breadcrumbs = [[$meta->getTitle()]];

		$this->template->list = $list;
		$this->template->rows = $collection->toArray();
		$this->template->total = $total;
		$this->template->page = $page;
		$this->template->pages = $pages;
		$this->template->mutations = \array_keys($repository->getConnection()->getAvailableMutations());
		$this->template->localizedColumns = $this->localizedColumns($definition);
		$this->template->setFile(__DIR__ . '/../Resource/templates/default.latte');
	}

	public function renderEdit(string $id): void
	{
		$meta = $this->getDefinition()->getMeta();
		$entity = $meta->getRepository()->one($id) ?? $this->error('Záznam nenalezen.');

		$this->title = $this->entityTitle($entity);
		$this->breadcrumbs = [[$meta->getTitle(), 'default'], [$this->title]];
		$this->buttons = [$this->button('Zpět', 'default', variant: 'ghost')];
		$this->template->fields = $this->getDefinition()->buildForm()->getFields();
		$this->template->entity = $entity;
		$this->template->mutations = \array_keys($meta->getRepository()->getConnection()->getAvailableMutations());
		$this->template->setFile(__DIR__ . '/../Resource/templates/edit.latte');
	}

	protected function permissionLink(): string
	{
		return $this->getDefinition()->getMeta()->permissionLink($this->getAction());
	}

	private function getDefinition(): ResourceDefinition
	{
		return $this->definition ?? throw new \LogicException('Agenda není načtená.');
	}

	/**
	 * Jazykové sloupce entity — pro sloupec „Jazyky“ (které jazyky má záznam vyplněné).
	 *
	 * @return list<string>
	 */
	private function localizedColumns(ResourceDefinition $definition): array
	{
		$structure = $definition->getMeta()->getRepository()->getStructure();
		$names = [];

		foreach ($definition->buildList()->getColumns() as $column) {
			if ($column->type === Column::TYPE_MUTATIONS) {
				continue;
			}

			if ($structure->getColumn($column->name)?->hasMutations()) {
				$names[] = $column->name;
			}
		}

		return $names;
	}

	private function entityTitle(Entity $entity): string
	{
		foreach ($this->getDefinition()->buildList()->getColumns() as $column) {
			if ($column->isLink()) {
				$value = $entity->getValue($column->name);

				return \is_scalar($value) ? (string) $value : (string) $entity->getPK();
			}
		}

		return (string) $entity->getPK();
	}
}
