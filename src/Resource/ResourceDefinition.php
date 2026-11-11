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

	abstract public function meta(Meta $meta): void;

	abstract public function list(ListBuilder $list): void;

	abstract public function form(FormBuilder $form): void;

	final public function getMeta(): Meta
	{
		if ($this->meta === null) {
			$this->meta = new Meta();
			$this->meta($this->meta);
		}

		return $this->meta;
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
}
