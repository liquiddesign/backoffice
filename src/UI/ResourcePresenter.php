<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Resource\Form\Field;
use Backoffice\Resource\Form\FormFactory;
use Backoffice\Resource\List\Column;
use Backoffice\Resource\ListView;
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

	private ?bool $hasListView = null;

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

		$this->title = $meta->getTitle();
		$this->breadcrumbs = [[$meta->getTitle()]];

		if ($this->hasListView()) {
			$this->buttons = $this->getListView()->headerButtons();
			$this->template->setFile(__DIR__ . '/../Resource/templates/view.latte');

			return;
		}

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

		$this->buttons = $this->defaultButtons();

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

		// Až při vykreslení (ne v action), ať kanonizace adresy nepřesměruje na ?view-…
		if ($this->hasListView()) {
			$this->getListView()->focus($this->getEntity());
		}

		$this->title = $this->entityTitle($this->getEntity());
		$this->breadcrumbs = [[$meta->getTitle(), 'default', $this->listParameters()], [$this->title]];
		$this->prepareForm();
	}

	public function renderNew(): void
	{
		$meta = $this->getDefinition()->getMeta();

		$this->title = 'Nový: ' . $meta->getItemTitle();
		$this->breadcrumbs = [[$meta->getTitle(), 'default', $this->listParameters()], ['Nový záznam']];
		$this->prepareForm();
	}

	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleDelete(string $id): void
	{
		$meta = $this->getDefinition()->getMeta();

		if (!$this->isAllowedAction('edit')) {
			$this->error('Na mazání nemáte oprávnění.', 403);
		}

		$entity = $meta->getRepository()->one($id);

		if ($entity !== null) {
			$title = $this->entityTitle($entity);

			if ($this->hasListView()) {
				$this->getListView()->focus($entity);
			}

			$entity->delete();
			$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DELETE, $id);
			$this->flashMessage("Smazáno: $title", 'success');
		}

		$this->redirect('default', $this->listParameters());
	}

	/**
	 * Má uživatel právo na akci agendy (default, edit, new)? Ptá se stejně jako při vstupu na stránku.
	 */
	public function isAllowedAction(string $action): bool
	{
		return $this->shell->access->isAllowed($this->getDefinition()->getMeta()->permissionLink($action));
	}

	/**
	 * Tlačítka hlavičky seznamu: „Nový záznam“, když na něj má uživatel právo.
	 * @return list<\Nette\Utils\Html>
	 */
	public function defaultButtons(): array
	{
		return $this->isAllowedAction('new')
			? [$this->button('Nový: ' . $this->getDefinition()->getMeta()->getItemTitle(), 'new', icon: 'file')]
			: [];
	}

	public function getDefinition(): ResourceDefinition
	{
		return $this->definition ?? throw new \LogicException('Agenda není načtená.');
	}

	/**
	 * Jazyky v pořadí, první je hlavní.
	 * @return list<string>
	 */
	public function mutations(): array
	{
		return \array_keys($this->getDefinition()->getMeta()->getRepository()->getConnection()->getAvailableMutations());
	}

	protected function createComponentForm(): Form
	{
		$definition = $this->getDefinition();
		$form = $this->formFactory->create($definition->buildForm(), $this->mutations());

		$form->onValidate[] = function (Form $form) use ($definition): void {
			$this->validateUnique($form, $definition);
		};

		$form->onSuccess[] = function (Form $form, array $values) use ($definition): void {
			$meta = $definition->getMeta();

			if (!$this->isAllowedAction($this->entity !== null ? 'edit' : 'new')) {
				$this->error('Na uložení nemáte oprávnění.', 403);
			}

			$repository = $meta->getRepository();
			$data = $this->formFactory->values($definition->buildForm(), $values, $repository->getStructure());

			if ($this->entity !== null) {
				$this->entity->update($data);
				$entity = $this->entity;
				$type = ChangeLogger::TYPE_DETAIL;
			} else {
				// Znovu načíst: nová entita z createOne() nemá jazykové sloupce v podobě pro getValue().
				$created = $repository->createOne($data);
				$entity = $repository->one($created->getPK()) ?? $created;
				$type = ChangeLogger::TYPE_NEW;
			}

			$this->changeLogger->log($meta->getTitle(), $type, (string) $entity->getPK());

			if ($this->hasListView()) {
				$this->getListView()->focus($entity);
			}

			if ($this->getAction() === 'default') {
				// Formulář vložený do vlastní obrazovky seznamu (ListView::newForm()).
				$this->flashMessage('Přidáno: ' . $this->entityTitle($entity), 'success');
				$this->redirect('default', $this->listParameters());
			}

			$this->flashMessage('Uloženo', 'success');

			if ($form->isSubmitted() === $form['saveClose']) {
				$this->redirect('default', $this->listParameters());
			}

			$this->redirect('edit', ['id' => (string) $entity->getPK()]);
		};

		$form->onError[] = function (): void {
			$this->flashMessage('Formulář obsahuje chyby — jsou vyznačené u polí.', 'error');
		};

		return $form;
	}

	protected function createComponentView(): ListView
	{
		return $this->getDefinition()->createListView() ?? $this->error('Agenda nemá vlastní obrazovku seznamu.');
	}

	protected function permissionLink(): string
	{
		return $this->getDefinition()->getMeta()->permissionLink($this->getAction());
	}

	private function prepareForm(): void
	{
		$this->buttons = [$this->button('Zpět na seznam', 'default', $this->listParameters(), variant: 'ghost')];
		$this->template->sections = $this->getDefinition()->buildForm()->getSections();
		$this->template->mutations = $this->mutations();
		$this->template->entity = $this->entity;
		$this->template->fieldsTemplate = Field::TEMPLATE;
		$this->template->canDelete = $this->isAllowedAction('edit');
		$this->template->setFile(__DIR__ . '/../Resource/templates/form.latte');
	}

	private function hasListView(): bool
	{
		return $this->hasListView ??= $this->getDefinition()->createListView() !== null;
	}

	/**
	 * Stav vlastní obrazovky seznamu (rok, filtr) jako parametry odkazu na seznam: view-<jméno>.
	 * Po uložení nebo smazání se tak uživatel vrátí tam, kde záznam je (ListView::focus()).
	 * @return array<string, mixed>
	 */
	private function listParameters(): array
	{
		if (!$this->hasListView()) {
			return [];
		}

		$view = $this->getListView();
		$state = [];
		$view->saveState($state);
		$parameters = [];

		foreach ($state as $name => $value) {
			$parameters[$view->getUniqueId() . '-' . $name] = $value;
		}

		return $parameters;
	}

	private function getListView(): ListView
	{
		$view = $this->getComponent('view');
		\assert($view instanceof ListView);

		return $view;
	}

	/**
	 * Pole s ->unique(): hodnota se v agendě nesmí opakovat (kromě upravovaného záznamu).
	 */
	private function validateUnique(Form $form, ResourceDefinition $definition): void
	{
		foreach ($definition->buildForm()->getFields() as $field) {
			$message = $field->getUniqueMessage();

			if ($message === null || $field->isLocalized()) {
				continue;
			}

			$control = $form->getComponent($field->name, false);

			if (!$control instanceof \Nette\Forms\Controls\BaseControl) {
				continue;
			}

			$value = $control->getValue();

			if ($value === null || $value === '') {
				continue;
			}

			$collection = $definition->getMeta()->getRepository()->many()->where('this.' . $field->name, $value);

			if ($this->entity !== null) {
				$collection->whereNot('this.' . $definition->getMeta()->getRepository()->getStructure()->getPK()->getName(), $this->entity->getPK());
			}

			if ($collection->enum() === 0) {
				continue;
			}

			$control->addError($message);
		}
	}

	private function getEntity(): Entity
	{
		return $this->entity ?? throw new \LogicException('Záznam není načtený.');
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
