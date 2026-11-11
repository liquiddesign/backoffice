<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Resource\Form\FormFactory;
use Backoffice\Resource\List\Column;
use Backoffice\Resource\ResourceDefinition;
use Backoffice\Resource\ResourceRegistry;
use Backoffice\Security\ChangeLogger;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use StORM\Entity;

/**
 * Jeden presenter pro všechny deklarativní agendy: <prefix>/r/<agenda>[/edit|new][/<id>].
 */
final class ResourcePresenter extends BackofficePresenter
{
	#[Persistent]
	public string $resource = '';

	#[Inject]
	public ResourceRegistry $registry;

	#[Inject]
	public FormFactory $formFactory;

	#[Inject]
	public ChangeLogger $changeLogger;

	private ?ResourceDefinition $definition = null;

	private ?Entity $entity = null;

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
		$this->buttons = [$this->button('Nový: ' . $meta->getItemTitle(), 'new', icon: 'file')];

		$this->template->list = $list;
		$this->template->rows = $collection->toArray();
		$this->template->total = $total;
		$this->template->page = $page;
		$this->template->pages = $pages;
		$this->template->mutations = $this->mutations();
		$this->template->localizedColumns = $this->localizedColumns($definition);
		$this->template->setFile(__DIR__ . '/../Resource/templates/default.latte');
	}

	public function actionEdit(string $id): void
	{
		$this->entity = $this->getDefinition()->getMeta()->getRepository()->one($id) ?? $this->error('Záznam nenalezen.');

		if ($this['form']->isSubmitted()) {
			return;
		}

		$this['form']->setDefaults($this->formFactory->defaults($this->getDefinition()->buildForm(), $this->entity));
	}

	public function renderEdit(): void
	{
		$meta = $this->getDefinition()->getMeta();

		$this->title = $this->entityTitle($this->getEntity());
		$this->breadcrumbs = [[$meta->getTitle(), 'default'], [$this->title]];
		$this->prepareForm();
	}

	public function renderNew(): void
	{
		$meta = $this->getDefinition()->getMeta();

		$this->title = 'Nový: ' . $meta->getItemTitle();
		$this->breadcrumbs = [[$meta->getTitle(), 'default'], ['Nový záznam']];
		$this->prepareForm();
	}

	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleDelete(string $id): void
	{
		$meta = $this->getDefinition()->getMeta();

		if (!$this->shell->access->isAllowed($meta->permissionLink('edit'))) {
			$this->error('Na mazání nemáte oprávnění.', 403);
		}

		$entity = $meta->getRepository()->one($id);

		if ($entity !== null) {
			$title = $this->entityTitle($entity);
			$entity->delete();
			$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DELETE, $id);
			$this->flashMessage("Smazáno: $title", 'success');
		}

		$this->redirect('default');
	}

	protected function createComponentForm(): Form
	{
		$definition = $this->getDefinition();
		$form = $this->formFactory->create($definition->buildForm(), $this->mutations());

		$form->onSuccess[] = function (Form $form, array $values) use ($definition): void {
			$meta = $definition->getMeta();
			$repository = $meta->getRepository();
			$data = $this->formFactory->values($definition->buildForm(), $values, $repository->getStructure());

			if ($this->entity !== null) {
				$this->entity->update($data);
				$entity = $this->entity;
				$type = ChangeLogger::TYPE_DETAIL;
			} else {
				$entity = $repository->createOne($data);
				$type = ChangeLogger::TYPE_NEW;
			}

			$this->changeLogger->log($meta->getTitle(), $type, (string) $entity->getPK());
			$this->flashMessage('Uloženo', 'success');

			if ($form->isSubmitted() === $form['saveClose']) {
				$this->redirect('default');
			}

			$this->redirect('edit', ['id' => (string) $entity->getPK()]);
		};

		$form->onError[] = function (): void {
			$this->flashMessage('Formulář obsahuje chyby — jsou vyznačené u polí.', 'error');
		};

		return $form;
	}

	protected function permissionLink(): string
	{
		return $this->getDefinition()->getMeta()->permissionLink($this->getAction());
	}

	private function prepareForm(): void
	{
		$this->buttons = [$this->button('Zpět na seznam', 'default', variant: 'ghost')];
		$this->template->sections = $this->getDefinition()->buildForm()->getSections();
		$this->template->mutations = $this->mutations();
		$this->template->entity = $this->entity;
		$this->template->setFile(__DIR__ . '/../Resource/templates/form.latte');
	}

	private function getDefinition(): ResourceDefinition
	{
		return $this->definition ?? throw new \LogicException('Agenda není načtená.');
	}

	private function getEntity(): Entity
	{
		return $this->entity ?? throw new \LogicException('Záznam není načtený.');
	}

	/**
	 * @return list<string>
	 */
	private function mutations(): array
	{
		return \array_keys($this->getDefinition()->getMeta()->getRepository()->getConnection()->getAvailableMutations());
	}

	/**
	 * Jazykové sloupce entity — pro sloupec „Jazyky“ (které jazyky má záznam vyplněné).
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

			if (!$structure->getColumn($column->name)?->hasMutations()) {
				continue;
			}

			$names[] = $column->name;
		}

		return $names;
	}

	private function entityTitle(Entity $entity): string
	{
		foreach ($this->getDefinition()->buildList()->getColumns() as $column) {
			if ($column->isLink()) {
				$value = $entity->getValue($column->name);

				return \is_scalar($value) && (string) $value !== '' ? (string) $value : (string) $entity->getPK();
			}
		}

		return (string) $entity->getPK();
	}
}
