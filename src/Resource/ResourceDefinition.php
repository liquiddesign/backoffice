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

	/**
	 * Sloupce, hledání, filtry a akce seznamu. Stránka nastavení (Meta::settings()) seznam nemá.
	 */
	public function list(ListBuilder $list): void
	{
		unset($list);
	}

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
	 * Záznam, který jde upravit, ale ne smazat (např. systémový — web s ním počítá): vrátí důvod,
	 * jinak null. V seznamu pak chybí koš a hromadné mazání ho přeskočí.
	 */
	public function deleteBlockReason(Entity $entity): ?string
	{
		unset($entity);

		return null;
	}

	/**
	 * Upozornění nad formulářem záznamu (kde se záznam na webu ukáže, kontrola odkazů…):
	 * dvojice [úroveň, text], úroveň FormNotice::WARN (žlutě) nebo FormNotice::INFO (modře).
	 * @return list<array{string, string}>
	 */
	public function formNotices(Entity $entity): array
	{
		unset($entity);

		return [];
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
	 * Řádky vnořeného seznamu (FormBuilder::rows()) pro upravovaný záznam; $entity = null u nového.
	 * @return list<\Backoffice\Resource\Form\FormRow>
	 */
	public function formRows(string $field, ?Entity $entity): array
	{
		unset($field, $entity);

		return [];
	}

	/**
	 * Po uložení záznamu (nového i upraveného) — pro data mimo jeho sloupce, typicky vnořený
	 * seznam ($values[pole][klíč řádku][pole řádku]) v jiné tabulce. Záznam už má ID.
	 * @param array<string, mixed> $values všechny hodnoty formuláře
	 */
	public function afterSave(Entity $entity, array $values, bool $created): void
	{
		unset($entity, $values, $created);
	}

	/**
	 * Řádek importu CSV (ListBuilder::csv() s klíčem) před uložením: sloupec → text ze souboru,
	 * jen sloupce, které soubor má. Vrátí data k uložení (úprava, převod typů, výchozí hodnoty);
	 * řádek, který nejde uložit, ohlásí CsvRowException s důvodem — import se pak celý vrátí.
	 * @param array<string, string> $row
	 * @return array<string, mixed>
	 * @throws \Backoffice\Resource\Csv\CsvRowException
	 */
	public function importRow(array $row): array
	{
		return $row;
	}

	/**
	 * Hodnoty stránky nastavení (Meta::settings()): pole formuláře → hodnota.
	 * @return array<string, mixed>
	 */
	public function loadSettings(): array
	{
		throw new \LogicException('Agenda s Meta::settings() musí mít loadSettings().');
	}

	/**
	 * Uložení stránky nastavení (Meta::settings()): hodnoty polí formuláře po kontrole.
	 * @param array<string, mixed> $values
	 */
	public function saveSettings(array $values): void
	{
		unset($values);

		throw new \LogicException('Agenda s Meta::settings() musí mít saveSettings().');
	}

	/**
	 * Upozornění nad stránkou nastavení (Meta::settings()) — obdoba formNotices() bez záznamu.
	 * @return list<array{string, string}>
	 */
	public function settingsNotices(): array
	{
		return [];
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
