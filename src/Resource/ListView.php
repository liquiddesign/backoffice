<?php

declare(strict_types=1);

namespace Backoffice\Resource;

use Backoffice\Resource\Form\Field;
use Backoffice\UI\ResourcePresenter;
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
