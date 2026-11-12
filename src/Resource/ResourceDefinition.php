<?php

declare(strict_types=1);

namespace Backoffice\Resource;

use Backoffice\Resource\Form\FormBuilder;
use Backoffice\Resource\List\ListBuilder;
use Nette\Application\UI\Form;
use StORM\Entity;

/**
 * Předek deklarativní agendy. Projekt popíše data, seznam a formulář, zbytek obstará balík.
 * Agenda je služba v DI — závislosti (repozitáře) dostane konstruktorem.
 */
abstract class ResourceDefinition
{
	private ?Meta $meta = null;

	final public function getMeta(): Meta
	{
		if ($this->meta !== null) {
			return $this->meta;
		}

		$meta = new Meta();
		$this->meta($meta);

		return $this->meta = $meta;
	}

	final public function buildList(): ListBuilder
	{
		$builder = new ListBuilder();
		$this->list($builder);

		return $builder;
	}

	final public function buildForm(): FormBuilder
	{
		$builder = new FormBuilder();
		$this->form($builder);

		return $builder;
	}

	abstract public function meta(Meta $meta): void;

	abstract public function list(ListBuilder $list): void;

	/**
	 * Formulář záznamu. Agenda bez stránky záznamu (Meta::withoutDetail()) ho nepotřebuje.
	 */
	public function form(FormBuilder $form): void
	{
		unset($form);
	}

	/**
	 * Vlastní obrazovka místo tabulky seznamu (např. kalendář). Výchozí null = tabulka podle list().
	 */
	public function createListView(): ?ListView
	{
		return null;
	}

	/**
	 * Kde se záznam používá. Agenda, která to sleduje, vrátí seznam (i prázdný); null = nesleduje.
	 * Použitý záznam se nesmaže rovnou: koš vede na stránku se seznamem míst, kde jde záznam
	 * skrýt (má-li sloupec hidden), nebo přesto smazat. Hromadné mazání použité záznamy přeskočí.
	 * @return list<\Backoffice\Resource\UsageLocation>|null
	 */
	public function usages(Entity $entity): ?array
	{
		unset($entity);

		return null;
	}

	/**
	 * Záznam, který nejde upravit ani smazat (např. přišel importem): vrátí důvod, jinak null.
	 * Stránka záznamu je pak jen ke čtení, v seznamu chybí koš a hromadné akce ho přeskočí.
	 */
	public function lockReason(Entity $entity): ?string
	{
		unset($entity);

		return null;
	}

	/**
	 * Výchozí hodnoty formuláře ze záznamu — pro pole, která nejsou sloupcem (typ, odvozené hodnoty).
	 * @param array<string, mixed> $defaults
	 * @return array<string, mixed>
	 */
	public function formDefaults(array $defaults, Entity $entity): array
	{
		unset($entity);

		return $defaults;
	}

	/**
	 * Vlastní kontrola formuláře (chyby přes $form['pole']->addError()). $entity = upravovaný záznam.
	 */
	public function validate(Form $form, ?Entity $entity): void
	{
		unset($form, $entity);
	}

	/**
	 * Úprava dat těsně před uložením (pole, která nejsou sloupcem, odvozené sloupce). V datech jsou
	 * i jména nahraných souborů. Co v datech zůstane a není sloupcem, se neuloží.
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $values všechny hodnoty formuláře
	 * @return array<string, mixed>
	 */
	public function beforeSave(array $data, array $values, ?Entity $entity): array
	{
		unset($values, $entity);

		return $data;
	}

	/**
	 * Zavolá se po každé změně dat agendy (uložení, úprava v seznamu, hromadná akce, smazání, vrácení).
	 * Např. smazání cache, ze které čte web.
	 */
	public function changed(): void
	{
		// Výchozí agenda nic navíc nedělá.
	}
}
