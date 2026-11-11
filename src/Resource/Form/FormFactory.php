<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Strings;
use StORM\Entity;
use StORM\Meta\Structure;

/**
 * Sestaví Nette formulář z popisu agendy a převádí hodnoty mezi formulářem a entitou.
 *
 * Jazyková pole jsou kontejner s prvkem pro každý jazyk (name[cs], name[en]) — StORM tenhle tvar
 * přijímá u sloupců s mutacemi přímo. Povinný je jen první (hlavní) jazyk; ostatní se doplní,
 * až budou přeložené — stejně jako ve starém adminu.
 */
final class FormFactory
{
	private const DATE_PATTERN = '\d{4}-\d{2}-\d{2}';

	/**
	 * @param list<string> $mutations jazyky v pořadí, první je hlavní
	 */
	public function create(FormBuilder $builder, array $mutations): Form
	{
		$form = new Form();
		$form->addProtection('Platnost formuláře vypršela, odešlete ho prosím znovu.');

		foreach ($builder->getFields() as $field) {
			if ($field->isLocalized()) {
				$container = $form->addContainer($field->name);

				foreach ($mutations as $index => $mutation) {
					$control = $field->type === Field::TYPE_LOCALE_TEXTAREA
						? $container->addTextArea($mutation, $field->label)
						: $container->addText($mutation, $field->label);

					if ($field->isRequired() && $index === 0) {
						$control->setRequired('Vyplňte pole ' . $field->label . ' (' . Strings::upper($mutation) . ').');
					}
				}

				continue;
			}

			$control = $this->createControl($form, $field);

			if ($field->isRequired() && $field->type !== Field::TYPE_CHECKBOX) {
				$control->setRequired('Vyplňte pole ' . $field->label . '.');
			}
		}

		$form->addSubmit('save', 'Uložit');
		$form->addSubmit('saveClose', 'Uložit a zavřít');

		return $form;
	}

	/**
	 * Výchozí hodnoty formuláře ze záznamu.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(FormBuilder $builder, Entity $entity): array
	{
		$data = $entity->toArray();
		$defaults = [];

		foreach ($builder->getFields() as $name => $field) {
			$value = $data[$name] ?? null;

			if ($field->type === Field::TYPE_DATE && \is_string($value)) {
				$value = Strings::substring($value, 0, 10);
			}

			if ($field->type === Field::TYPE_CHECKBOX) {
				$value = (bool) $value;
			}

			$defaults[$name] = $value;
		}

		return $defaults;
	}

	/**
	 * Hodnoty z formuláře pro uložení: prázdný text u sloupce, který smí být NULL, se uloží jako NULL.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function values(FormBuilder $builder, array $values, Structure $structure): array
	{
		$result = [];

		foreach ($builder->getFields() as $name => $field) {
			if (!\array_key_exists($name, $values)) {
				continue;
			}

			$nullable = (bool) $structure->getColumn($name)?->isNullable();
			$value = $values[$name];

			if (\is_iterable($value)) {
				$localized = [];

				foreach ($value as $mutation => $text) {
					$localized[$mutation] = $nullable && $text === '' ? null : $text;
				}

				$result[$name] = $localized;

				continue;
			}

			$result[$name] = $nullable && $value === '' ? null : $value;
		}

		return $result;
	}

	private function createControl(Form $form, Field $field): BaseControl
	{
		switch ($field->type) {
			case Field::TYPE_TEXTAREA:
				return $form->addTextArea($field->name, $field->label);
			case Field::TYPE_INTEGER:
				return $form->addInteger($field->name, $field->label);
			case Field::TYPE_CHECKBOX:
				return $form->addCheckbox($field->name, $field->label);
			case Field::TYPE_SELECT:
				$select = $form->addSelect($field->name, $field->label, $field->getItems());

				if (!$field->isRequired()) {
					$select->setPrompt('—');
				}

				return $select;
			case Field::TYPE_DATE:
				$date = $form->addText($field->name, $field->label)->setHtmlType('date');
				$date->addCondition(Form::Filled)
					->addRule(Form::Pattern, 'Datum zadejte ve tvaru RRRR-MM-DD.', self::DATE_PATTERN);

				return $date;
			default:
				return $form->addText($field->name, $field->label);
		}
	}
}
