<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Arrays;
use Nette\Utils\Json;
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
	 * @param (\Closure(string): list<\Backoffice\Resource\Form\FormRow>)|null $rows řádky vnořeného seznamu podle jména pole
	 */
	public function create(FormBuilder $builder, array $mutations, ?\Closure $rows = null): Form
	{
		$form = new Form();
		$form->addProtection('Platnost formuláře vypršela, odešlete ho prosím znovu.');

		foreach ($builder->getFields() as $field) {
			if ($field->type === Field::TYPE_ROWS) {
				$this->addRows($form, $field, $rows !== null ? $rows($field->name) : []);

				continue;
			}

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
			// Vnořený seznam má výchozí hodnoty z řádků (FormRow::$values), ne ze sloupce.
			if (self::isUpload($field) || $field->type === Field::TYPE_ROWS || $field->type === Field::TYPE_RELATIONS) {
				continue;
			}

			$value = $data[$name] ?? null;

			if ($field->type === Field::TYPE_DATETIME) {
				$defaults[$name] = $this->encodeDefaults($builder, [$name => $value])[$name];

				continue;
			}

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
			if (!\array_key_exists($name, $values) || self::isUpload($field) || $field->type === Field::TYPE_ROWS || $field->type === Field::TYPE_RELATIONS) {
				continue;
			}

			if ($field->type === Field::TYPE_DATETIME) {
				$result[$name] = self::datetimeValue($values[$name]);

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
	 * Hodnoty pro uložení po kontrole: víc vazeb jako list klíčů, datum a čas jako 'RRRR-MM-DD HH:MM:SS'.
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function normalize(FormBuilder $builder, array $values): array
	{
		foreach ($builder->getFields() as $name => $field) {
			if (!\array_key_exists($name, $values)) {
				continue;
			}

			if ($field->type === Field::TYPE_RELATIONS) {
				$values[$name] = self::relationKeys($values[$name]);
			} elseif ($field->type === Field::TYPE_DATETIME) {
				$values[$name] = self::datetimeValue($values[$name]);
			}
		}

		return $values;
	}

	/**
	 * Výchozí hodnoty z agendy pro formulář: list klíčů vazeb na text pole, datum a čas na tvar inputu.
	 * @param array<string, mixed> $defaults
	 * @return array<string, mixed>
	 */
	public function encodeDefaults(FormBuilder $builder, array $defaults): array
	{
		foreach ($builder->getFields() as $name => $field) {
			if (!\array_key_exists($name, $defaults)) {
				continue;
			}

			if ($field->type === Field::TYPE_RELATIONS) {
				$defaults[$name] = \implode(',', self::relationKeys($defaults[$name] ?? []));
			} elseif ($field->type === Field::TYPE_DATETIME) {
				$value = $defaults[$name];
				$defaults[$name] = $value instanceof \DateTimeInterface
					? $value->format('Y-m-d\\TH:i')
					: (\is_string($value) && $value !== '' ? Strings::substring(\str_replace(' ', 'T', $value), 0, 16) : null);
			}
		}

		return $defaults;
	}

	/**
	 * Obrázek nebo soubor — ukládá je presenter zvlášť, do hodnot formuláře nepatří.
	 */
	public static function isUpload(Field $field): bool
	{
		return $field->type === Field::TYPE_IMAGE || $field->type === Field::TYPE_FILE;
	}

	/**
	 * Klíče víc vazeb z hodnoty pole (čárkami oddělené, jak je drží skryté pole formuláře).
	 * @return list<string>
	 */
	public static function relationKeys(mixed $value): array
	{
		if (\is_array($value)) {
			return \array_values(\array_unique(\array_map('strval', $value)));
		}

		$keys = [];

		foreach (\explode(',', (string) $value) as $key) {
			$key = Strings::trim($key);

			if ($key === '' || Arrays::contains($keys, $key)) {
				continue;
			}

			$keys[] = $key;
		}

		return $keys;
	}

	/**
	 * Atributy textarea formátovaného textu: nastavení editoru (RichTextEditor::config() + pole,
	 * s Field::richTextUploads() i uploadUrl) a skripty, které balíkový JS načte (viz RichTextEditor).
	 * @return array<string, string>
	 */
	public static function richTextAttributes(Field $field, ?string $mutation, ?RichTextEditor $editor, bool $invalid, ?string $uploadUrl = null): array
	{
		$config = $field->getEditorOptions() + ($editor?->config($field, $mutation) ?? []);

		if ($uploadUrl !== null) {
			$config['uploadUrl'] = $uploadUrl;
		}

		return [
			'class' => 'bo-input bo-richtext' . ($invalid ? ' is-invalid' : ''),
			'data-bo-richtext' => Json::encode((object) $config),
			'data-bo-richtext-scripts' => Json::encode($editor?->scripts() ?? []),
		];
	}

	/**
	 * Vnořený seznam: kontejner pole → kontejner řádku (klíč) → pole řádku, s výchozími hodnotami řádku.
	 * @param list<\Backoffice\Resource\Form\FormRow> $rows
	 */
	private function addRows(Form $form, Field $field, array $rows): void
	{
		$container = $form->addContainer($field->name);

		foreach ($rows as $row) {
			$rowContainer = $container->addContainer($row->key);

			foreach ($field->getRowColumns() as $name => $column) {
				$control = match ($column['type']) {
					Field::ROW_CHECKBOX => $rowContainer->addCheckbox($name, $column['label']),
					Field::ROW_INTEGER => $rowContainer->addInteger($name, $column['label']),
					default => $rowContainer->addText($name, $column['label'])->setNullable(),
				};

				if ($column['type'] === Field::ROW_INTEGER) {
					$control->setRequired('Vyplňte ' . Strings::lower($column['label']) . ' u „' . $row->label . '“.');
				}

				if (!\array_key_exists($name, $row->values)) {
					continue;
				}

				$control->setDefaultValue($column['type'] === Field::ROW_CHECKBOX ? (bool) $row->values[$name] : $row->values[$name]);
			}
		}
	}

	/**
	 * @param list<string> $mutations
	 */
	private function addLocalized(Form $form, Field $field, array $mutations): void
	{
		$container = $form->addContainer($field->name);

		foreach ($mutations as $index => $mutation) {
			$control = $field->type === Field::TYPE_LOCALE_TEXTAREA || $field->type === Field::TYPE_LOCALE_RICH_TEXT
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
			Field::TYPE_TEXTAREA, Field::TYPE_RICH_TEXT => $form->addTextArea($field->name, $field->label),
			Field::TYPE_INTEGER => $form->addInteger($field->name, $field->label),
			Field::TYPE_CHECKBOX => $form->addCheckbox($field->name, $field->label),
			Field::TYPE_SELECT => $this->createSelect($form, $field),
			Field::TYPE_DATE => $this->createDate($form, $field),
			Field::TYPE_RELATION => $this->createRelation($form, $field),
			Field::TYPE_RELATIONS => $this->createRelations($form, $field),
			Field::TYPE_DATETIME => $this->createDatetime($form, $field),
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

	private function createDatetime(Form $form, Field $field): BaseControl
	{
		$input = $form->addText($field->name, $field->label)->setHtmlType('datetime-local')->setNullable();
		$input->addCondition(Form::Filled)
			->addRule(Form::Pattern, 'Datum a čas zadejte ve tvaru RRRR-MM-DD HH:MM.', '\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}(:\\d{2})?');

		return $input;
	}

	private function createRelations(Form $form, Field $field): BaseControl
	{
		$repository = $field->getRelationRepository() ?? throw new \LogicException("Vazby '{$field->name}' nemají repozitář.");
		$pk = 'this.' . $repository->getStructure()->getPK()->getName();
		$input = $form->addText($field->name, $field->label)->setNullable();
		$input->addCondition(Form::Filled)
			->addRule(static function ($control) use ($repository, $pk): bool {
				$keys = self::relationKeys($control->getValue());

				return $keys === [] || $repository->many()->where($pk, $keys)->count() === \count($keys);
			}, 'Vyberte položky ze seznamu.');

		return $input;
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

	private static function datetimeValue(mixed $value): ?string
	{
		if (!\is_string($value) || Strings::trim($value) === '') {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', Strings::substring(Strings::trim($value), 0, 16));

		return $date !== false ? $date->format('Y-m-d H:i:s') : null;
	}
}
