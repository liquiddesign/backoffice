<?php

declare(strict_types=1);

namespace Backoffice\Resource;

use Backoffice\Resource\Form\Field;
use Backoffice\Resource\List\Column;
use Backoffice\Security\ChangeLogger;
use Backoffice\UI\ResourcePresenter;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use StORM\Entity;

/**
 * Vlastní obrazovka seznamu místo tabulky (kalendář, nástěnka agendy…). Agenda ji vrátí
 * z ResourceDefinition::createListView(); presenter ji vykreslí jako komponentu „view“.
 *
 * Ukládání, mazání, oprávnění a log změn zůstávají na presenteru — obrazovka jen vykresluje
 * a může vložit formulář nového záznamu (newForm()), který se uloží stejně jako na stránce „Nový“.
 * Stav obrazovky (rok, filtr) patří do #[Persistent] vlastností; v adrese jsou jako view-<jméno>
 * a drží se i na detailu záznamu a po uložení.
 */
abstract class ListView extends Control
{
	/** editedId() pro nový záznam rozbalený v obrazovce. */
	public const EDIT_NEW = 'new';

	abstract public function render(): void;

	final protected function presenter(): ResourcePresenter
	{
		$presenter = $this->getPresenter();

		if (!$presenter instanceof ResourcePresenter) {
			throw new \LogicException('ListView patří jen do ResourcePresenter.');
		}

		return $presenter;
	}

	/**
	 * Formulář rozbaleného záznamu (editedId()) — úprava, nebo nový záznam. Null, když není co
	 * upravovat nebo na to uživatel nemá právo.
	 */
	final protected function editForm(): ?Form
	{
		$id = $this->editedId();

		if ($id === null) {
			return null;
		}

		if ($id === self::EDIT_NEW ? !$this->canCreate() : !$this->canEdit()) {
			return null;
		}

		$form = $this->presenter()->getComponent('form');
		\assert($form instanceof Form);

		return $form;
	}

	/**
	 * Formulář nového záznamu, nebo null, když na založení uživatel nemá právo.
	 */
	final protected function newForm(): ?Form
	{
		if (!$this->canCreate()) {
			return null;
		}

		$form = $this->presenter()->getComponent('form');
		\assert($form instanceof Form);

		return $form;
	}

	final protected function canCreate(): bool
	{
		return $this->presenter()->isAllowedAction('new');
	}

	final protected function canEdit(): bool
	{
		return $this->presenter()->isAllowedAction('edit');
	}

	final protected function editLink(Entity $entity): string
	{
		return $this->presenter()->link('edit', ['id' => (string) $entity->getPK()]);
	}

	/**
	 * Zápis do logu změn pod názvem agendy (ChangeLogger::TYPE_*), když obrazovka ukládá sama.
	 */
	final protected function logChange(string $type, string $id): void
	{
		$this->presenter()->changeLogger->log($this->presenter()->getDefinition()->getMeta()->getTitle(), $type, $id);
	}

	/**
	 * @return list<\Backoffice\Resource\Form\Field>
	 */
	final protected function formFields(): array
	{
		return \array_values($this->presenter()->getDefinition()->buildForm()->getFields());
	}

	/**
	 * @return list<string>
	 */
	final protected function mutations(): array
	{
		return $this->presenter()->mutations();
	}

	/**
	 * Proměnné pro šablonu pole formuláře (Field::TEMPLATE): {include $fieldsTemplate, fields: …}.
	 */
	final protected function prepareFormTemplate(): void
	{
		$this->template->fieldsTemplate = Field::TEMPLATE;
		$this->template->fields = $this->formFields();
		$this->template->mutations = $this->mutations();
		$this->template->newForm = $this->newForm();

		// Náhledy obrázků, soubory a popisky vazeb rozbaleného záznamu (pro šablonu polí).
		foreach ($this->presenter()->formContext() as $name => $value) {
			$this->template->{$name} = $value;
		}
	}

	/**
	 * Záznam, který obrazovka ukazuje rozbalený k úpravě (id, EDIT_NEW), nebo null.
	 * Presenter pak formulář „form“ naváže na tento záznam a uloží ho stejně jako stránka záznamu.
	 */
	public function editedId(): ?string
	{
		return null;
	}

	/**
	 * Zavře rozbalený záznam (po uložení, nebo když záznam už neexistuje).
	 */
	public function closeEdit(): void
	{
		// Obrazovka bez úprav v řádku nic nezavírá.
	}

	/**
	 * Přepínač v řádku (sloupec seznamu checkbox s ->editable()), uloží se hned. POST value=1|0.
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleToggle(string $id, string $column): void
	{
		$presenter = $this->presenter();
		$definition = $presenter->getDefinition();
		$listColumn = $definition->buildList()->getColumn($column);

		if ($listColumn === null || $listColumn->type !== Column::TYPE_CHECKBOX || !$listColumn->isEditable() || $listColumn->mutation !== null) {
			$this->error('Sloupec nejde přepnout.');
		}

		if (!$this->canEdit()) {
			$this->error('Na úpravy nemáte oprávnění.', 403);
		}

		$entity = $definition->getMeta()->getRepository()->one($id);

		if ($entity === null || $definition->lockReason($entity) !== null) {
			$presenter->flashMessage('Záznam nejde upravit.', 'error');
			$this->redirect('this');
		}

		$value = $presenter->getRequest()->getPost('value') === '1';

		if ((bool) $entity->getValue($column) !== $value) {
			$entity->update([$column => $value]);
			$this->logChange(ChangeLogger::TYPE_DETAIL, $id);
			$definition->changed();
		}

		$presenter->flashMessage($listColumn->label . ': ' . ($value ? 'ano' : 'ne') . ' — ' . $presenter->entityTitle($entity), 'success');
		$this->redirect('this');
	}

	/**
	 * Nové pořadí po přetažení (POST ids[] v novém pořadí), uloží se hned do sloupce priority() seznamu.
	 * Záznamy dostanou hodnoty pořadí, které už měly, jen přeskládané (nebo řadu od nejmenší, když se opakovaly).
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleReorder(): void
	{
		$presenter = $this->presenter();
		$definition = $presenter->getDefinition();
		$priority = $definition->buildList()->getPriorityColumn();

		if ($priority === null) {
			$this->error('Seznam nemá pořadí.');
		}

		if (!$this->canEdit()) {
			$this->error('Na úpravy nemáte oprávnění.', 403);
		}

		$posted = $presenter->getRequest()->getPost('ids');
		$ids = \array_values(\array_unique(\array_filter(\is_array($posted) ? $posted : [], 'is_string')));
		$repository = $definition->getMeta()->getRepository();
		$pk = $repository->getStructure()->getPK()->getName();
		$entities = [];

		foreach ($repository->many()->where('this.' . $pk, $ids)->toArray() as $entity) {
			$entities[(string) $entity->getPK()] = $entity;
		}

		$ids = \array_values(\array_filter($ids, static fn (string $id): bool => isset($entities[$id])));
		$values = \array_map(static fn (string $id): int => (int) $entities[$id]->getValue($priority->name), $ids);
		\sort($values);
		$unique = \count(\array_unique($values)) === \count($values);
		$changed = 0;

		foreach ($ids as $index => $id) {
			$entity = $entities[$id];
			$value = $unique ? $values[$index] : ($values[0] ?? 0) + $index;

			if ((int) $entity->getValue($priority->name) === $value || $definition->lockReason($entity) !== null) {
				continue;
			}

			$entity->update([$priority->name => $value]);
			$this->logChange(ChangeLogger::TYPE_DETAIL, $id);
			$changed++;
		}

		if ($changed > 0) {
			$definition->changed();
			$presenter->flashMessage('Pořadí uloženo.', 'success');
		}

		$this->redirect('this');
	}

	/**
	 * Ovládací prvky v hlavičce stránky vpravo (přepínač roku, tlačítka).
	 * Výchozí: tlačítko „Nový záznam“, když na něj má uživatel právo.
	 * @return list<\Nette\Utils\Html|string>
	 */
	public function headerButtons(): array
	{
		return $this->presenter()->defaultButtons();
	}

	/**
	 * Nastaví stav obrazovky tak, aby byl záznam vidět (např. rok podle data). Volá se po uložení
	 * a před smazáním, aby se uživatel vrátil tam, kde byl záznam.
	 */
	public function focus(Entity $entity): void
	{
		unset($entity);
	}
}
