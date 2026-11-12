<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Resource\Form\Field;
use Backoffice\Resource\Form\FormFactory;
use Backoffice\Resource\List\ListControl;
use Backoffice\Resource\ListView;
use Backoffice\Resource\ResourceDefinition;
use Backoffice\Resource\ResourceRegistry;
use Backoffice\Security\ChangeLogger;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Nette\Utils\Random;
use StORM\Entity;

/**
 * Jeden presenter pro všechny deklarativní agendy: <prefix>/r/<agenda>[/edit|new][/<id>].
 */
final class ResourcePresenter extends BackofficePresenter
{
	/** Kolik posledních vratných změn si session pamatuje (tlačítko „Vrátit“). */
	private const UNDO_LIMIT = 10;

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

	public function renderDefault(): void
	{
		$meta = $this->getDefinition()->getMeta();

		$this->title = $meta->getTitle();
		$this->breadcrumbs = [[$meta->getTitle()]];

		if ($this->hasListView()) {
			$this->buttons = $this->getListView()->headerButtons();
			$this->template->setFile(__DIR__ . '/../Resource/templates/view.latte');

			return;
		}

		$this->buttons = $this->defaultButtons();
		$this->template->setFile(__DIR__ . '/../Resource/templates/default.latte');
	}

	public function actionNew(): void
	{
		if (!$this->getDefinition()->getMeta()->hasCreate()) {
			$this->error('Agenda nemá zakládání záznamů.');
		}
	}

	public function actionEdit(string $id): void
	{
		if (!$this->getDefinition()->getMeta()->hasDetail()) {
			$this->error('Agenda nemá stránku záznamu.');
		}

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

		if (!$this->canDelete()) {
			$this->error('Na mazání nemáte oprávnění.', 403);
		}

		$entity = $meta->getRepository()->one($id);

		if ($entity !== null) {
			$title = $this->entityTitle($entity);
			$data = $entity->toArray();

			if ($this->hasListView()) {
				$this->getListView()->focus($entity);
			}

			try {
				$entity->delete();
			} catch (\PDOException) {
				$this->flashMessage("Záznam „{$title}“ nejde smazat — nejspíš se na něj odkazují jiná data.", 'error');
				$this->redirect('default', $this->listParameters());
			}

			$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DELETE, $id);
			$this->getDefinition()->changed();
			$this->flashWithUndo("Smazáno: $title", [['restore', $id, $data]]);
		}

		$this->redirect('default', $this->listParameters());
	}

	/**
	 * Vrátí změnu z flash zprávy („Vrátit“): smazané záznamy založí znovu, upravené vrátí na původní
	 * hodnoty. Změna se pamatuje v session jen pro tuto agendu a posledních pár akcí.
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleUndo(string $token): void
	{
		$meta = $this->getDefinition()->getMeta();
		$section = $this->getSession('backoffice.undo');
		/** @var array<string, array{resource: string, ops: list<array{string, string, array<string, mixed>}>}> $stack */
		$stack = $section->get('stack') ?? [];
		$entry = $stack[$token] ?? null;

		if ($entry === null || $entry['resource'] !== $meta->getSlug()) {
			$this->flashMessage('Tuhle změnu už vrátit nejde.', 'warning');
			$this->redirect('default');
		}

		unset($stack[$token]);
		$section->set('stack', $stack);
		$repository = $meta->getRepository();
		$restored = 0;

		foreach ($entry['ops'] as [$type, $id, $data]) {
			if ($type === 'restore') {
				if ($repository->one($id) !== null || !$this->isAllowedAction('edit')) {
					continue;
				}

				$repository->createOne($data);
				$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_NEW, $id);
				$restored++;

				continue;
			}

			$entity = $repository->one($id);

			if ($entity === null || !$this->isAllowedAction('edit')) {
				continue;
			}

			$entity->update($data);
			$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DETAIL, $id);
			$restored++;
		}

		$this->getDefinition()->changed();
		$this->flashMessage($restored > 0 ? 'Vráceno.' : 'Nebylo co vracet.', $restored > 0 ? 'success' : 'warning');
		$this->redirect('default', $this->listParameters());
	}

	/**
	 * Má uživatel právo na akci agendy (default, edit, new)? Ptá se stejně jako při vstupu na stránku.
	 * Zakládání vypnuté v Meta (withoutCreate) se bere jako bez práva.
	 */
	public function isAllowedAction(string $action): bool
	{
		$meta = $this->getDefinition()->getMeta();

		if ($action === 'new' && !$meta->hasCreate()) {
			return false;
		}

		return $this->shell->access->isAllowed($meta->permissionLink($action));
	}

	/**
	 * Mazání: právo na úpravu a agenda mazání nevypnula (Meta::withoutDelete()).
	 */
	public function canDelete(): bool
	{
		return $this->getDefinition()->getMeta()->hasDelete() && $this->isAllowedAction('edit');
	}

	/**
	 * Zápis do logu změn pod názvem agendy (ChangeLogger::TYPE_*).
	 */
	public function logChange(string $type, string $id): void
	{
		$this->changeLogger->log($this->getDefinition()->getMeta()->getTitle(), $type, $id);
	}

	/**
	 * Flash zpráva s tlačítkem „Vrátit“. Operace: ['update', id, původní hodnoty] nebo
	 * ['restore', id, Entity::toArray() smazaného záznamu].
	 * @param list<array{string, string, array<string, mixed>}> $ops
	 */
	public function flashWithUndo(string $message, array $ops): void
	{
		$flash = $this->flashMessage($message, 'success');

		if ($ops === []) {
			return;
		}

		$section = $this->getSession('backoffice.undo');
		/** @var array<string, array{resource: string, ops: list<array{string, string, array<string, mixed>}>}> $stack */
		$stack = $section->get('stack') ?? [];
		$token = Random::generate(12);
		$stack[$token] = ['resource' => $this->getDefinition()->getMeta()->getSlug(), 'ops' => $ops];
		$section->set('stack', \array_slice($stack, -self::UNDO_LIMIT, null, true));
		// Na seznam, ne na „this“: po smazání ze stránky záznamu by adresa vedla na smazaný záznam.
		$flash->undo = $this->link('default', [self::SignalKey => 'undo', 'token' => $token] + $this->listParameters());
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
			$definition->changed();

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

	protected function createComponentList(): ListControl
	{
		return new ListControl();
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
		$this->template->canDelete = $this->canDelete();
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
