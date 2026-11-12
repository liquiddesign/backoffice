<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

/**
 * Popis formuláře agendy. Sekce řadí pole do skupin s nadpisem (návrh: „Základní údaje“).
 */
final class FormBuilder
{
	/** @var array<string, \Backoffice\Resource\Form\Field> */
	private array $fields = [];

	/** @var array<string, string> jméno pole → sekce */
	private array $sectionOf = [];

	private string $section = 'Základní údaje';

	public function section(string $title): self
	{
		$this->section = $title;

		return $this;
	}

	public function text(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_TEXT, $name, $label);
	}

	public function textarea(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_TEXTAREA, $name, $label);
	}

	public function integer(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_INTEGER, $name, $label);
	}

	public function checkbox(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_CHECKBOX, $name, $label);
	}

	/**
	 * @param array<string|int, string> $items
	 */
	public function select(string $name, string $label, array $items): Field
	{
		return $this->add(Field::TYPE_SELECT, $name, $label)->items($items);
	}

	public function date(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_DATE, $name, $label);
	}

	/**
	 * Text ve všech jazycích (sloupce name_cs, name_en…) — v návrhu vedle sebe.
	 */
	public function localeText(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_LOCALE_TEXT, $name, $label);
	}

	public function localeTextarea(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_LOCALE_TEXTAREA, $name, $label);
	}

	/**
	 * Obrázek nahraný do userfiles; adresáře a úpravy přes ->imageDirectory(). Ve sloupci je jméno souboru.
	 */
	public function image(string $name, string $label): Field
	{
		return $this->add(Field::TYPE_IMAGE, $name, $label);
	}

	/**
	 * @return array<string, \Backoffice\Resource\Form\Field>
	 */
	public function getFields(): array
	{
		return $this->fields;
	}

	/**
	 * @return array<string, list<\Backoffice\Resource\Form\Field>> sekce → pole
	 */
	public function getSections(): array
	{
		$sections = [];

		foreach ($this->fields as $name => $field) {
			$sections[$this->sectionOf[$name]][] = $field;
		}

		return $sections;
	}

	private function add(string $type, string $name, string $label): Field
	{
		if (isset($this->fields[$name])) {
			throw new \LogicException("Pole '$name' je ve formuláři dvakrát.");
		}

		$this->sectionOf[$name] = $this->section;

		return $this->fields[$name] = new Field($type, $name, $label);
	}
}
