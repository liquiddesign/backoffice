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
	/** Zaškrtávátko „Odstranit obrázek“ vedle pole obrázku: <pole>__remove. */
	public const REMOVE_SUFFIX = '__remove';

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
				$this->addLocalized($form, $field, $mutations);

				continue;
			}

			if ($field->type === Field::TYPE_IMAGE) {
				$this->addImage($form, $field);

				continue;
			}

			if ($field->type === Field::TYPE_FILE) {
				$this->addFile($form, $field);

				continue;
			}

			$control = $this->createControl($form, $field);

			if ($field->getDefault() !== null) {
				$control->setDefaultValue($field->getDefault());
			}

			if (!$field->isRequired() || $field->type === Field::TYPE_CHECKBOX) {
				continue;
			}

			$control->setRequired('Vyplňte pole ' . $field->label . '.');
		}

		$form->addSubmit('save', 'Uložit');
		$form->addSubmit('saveClose', 'Uložit a zavřít');

		return $form;
	}

	/**
	 * Výchozí hodnoty formuláře ze záznamu.
	 * @return array<string, mixed>
	 */
	public function defaults(FormBuilder $builder, Entity $entity): array
	{
		$data = $entity->toArray();
		$defaults = [];

		foreach ($builder->getFields() as $name => $field) {
			if (self::isUpload($field)) {
				continue;
			}

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
	 * @param array<string, mixed> $values
	 * @param \StORM\Meta\Structure<\StORM\Entity> $structure
	 * @return array<string, mixed>
	 */
	public function values(FormBuilder $builder, array $values, Structure $structure): array
	{
		$result = [];

		foreach ($builder->getFields() as $name => $field) {
			// Obrázky ukládá presenter až po uložení záznamu (jméno souboru = ID záznamu).
			if (!\array_key_exists($name, $values) || self::isUpload($field)) {
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

	/**
	 * Obrázek nebo soubor — ukládá je presenter zvlášť, do hodnot formuláře nepatří.
	 */
	public static function isUpload(Field $field): bool
	{
		return $field->type === Field::TYPE_IMAGE || $field->type === Field::TYPE_FILE;
	}

	/**
	 * @param list<string> $mutations
	 */
	private function addLocalized(Form $form, Field $field, array $mutations): void
	{
		$container = $form->addContainer($field->name);

		foreach ($mutations as $index => $mutation) {
			$control = $field->type === Field::TYPE_LOCALE_TEXTAREA
				? $container->addTextArea($mutation, $field->label)
				: $container->addText($mutation, $field->label);

			if (!$field->isRequired() || $index !== 0) {
				continue;
			}

			$control->setRequired('Vyplňte pole ' . $field->label . ' (' . Strings::upper($mutation) . ').');
		}
	}

	private function addImage(Form $form, Field $field): void
	{
		$upload = $form->addUpload($field->name, $field->label);
		$upload->setHtmlAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
		$upload->addCondition(Form::Filled)
			->addRule(Form::Image, 'Nahrajte obrázek JPG, PNG, GIF nebo WebP.');
		$form->addCheckbox($field->name . self::REMOVE_SUFFIX, 'Odstranit obrázek');
	}

	private function addFile(Form $form, Field $field): void
	{
		$upload = $form->addUpload($field->name, $field->label);

		if ($field->getAcceptTypes() !== []) {
			$upload->setHtmlAttribute('accept', \implode(',', $field->getAcceptTypes()));
			$upload->addCondition(Form::Filled)
				->addRule(Form::MimeType, $field->getAcceptMessage() ?? 'Nepovolený typ souboru.', $field->getAcceptTypes());
		}

		$form->addCheckbox($field->name . self::REMOVE_SUFFIX, 'Odstranit soubor');
	}

	private function createControl(Form $form, Field $field): BaseControl
	{
		return match ($field->type) {
			Field::TYPE_TEXTAREA => $form->addTextArea($field->name, $field->label),
			Field::TYPE_INTEGER => $form->addInteger($field->name, $field->label),
			Field::TYPE_CHECKBOX => $form->addCheckbox($field->name, $field->label),
			Field::TYPE_SELECT => $this->createSelect($form, $field),
			Field::TYPE_DATE => $this->createDate($form, $field),
			Field::TYPE_RELATION => $this->createRelation($form, $field),
			default => $form->addText($field->name, $field->label),
		};
	}

	private function createSelect(Form $form, Field $field): BaseControl
	{
		$select = $form->addSelect($field->name, $field->label, $field->getItems());

		if ($field->getPrompt() !== null || !$field->isRequired()) {
			$select->setPrompt($field->getPrompt() ?? '—');
		}

		return $select;
	}

	private function createRelation(Form $form, Field $field): BaseControl
	{
		$repository = $field->getRelationRepository() ?? throw new \LogicException("Vazba '{$field->name}' nemá repozitář.");
		$input = $form->addText($field->name, $field->label)->setNullable();
		$input->addCondition(Form::Filled)
			->addRule(static fn ($control): bool => $repository->one((string) $control->getValue()) !== null, 'Vyberte položku ze seznamu.');

		return $input;
	}

	private function createDate(Form $form, Field $field): BaseControl
	{
		$date = $form->addText($field->name, $field->label)->setHtmlType('date');
		$date->addCondition(Form::Filled)
			->addRule(Form::Pattern, 'Datum zadejte ve tvaru RRRR-MM-DD.', self::DATE_PATTERN);

		return $date;
	}
}
