<?php

declare(strict_types=1);

namespace Backoffice\Resource;

use Backoffice\Resource\Form\FormBuilder;
use Backoffice\Resource\List\ListBuilder;

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

	abstract public function form(FormBuilder $form): void;

	/**
	 * Vlastní obrazovka místo tabulky seznamu (např. kalendář). Výchozí null = tabulka podle list().
	 */
	public function createListView(): ?ListView
	{
		return null;
	}
}
