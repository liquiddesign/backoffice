<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

use Nette\Utils\Arrays;

/**
 * Pole formuláře agendy. Nette formulář z něj sestaví FormControl (etapa 1).
 */
final class Field
{
	public const TYPE_TEXT = 'text';
	public const TYPE_TEXTAREA = 'textarea';
	public const TYPE_INTEGER = 'integer';
	public const TYPE_CHECKBOX = 'checkbox';
	public const TYPE_SELECT = 'select';
	public const TYPE_DATE = 'date';
	public const TYPE_LOCALE_TEXT = 'localeText';
	public const TYPE_LOCALE_TEXTAREA = 'localeTextarea';

	private bool $required = false;

	private ?string $description = null;

	/** @var array<string|int, string> */
	private array $items = [];

	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly string $label,
	) {
	}

	public function required(bool $required = true): self
	{
		$this->required = $required;

		return $this;
	}

	/**
	 * Nápověda pod polem.
	 */
	public function description(string $description): self
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * Volby u výběru.
	 * @param array<string|int, string> $items
	 */
	public function items(array $items): self
	{
		$this->items = $items;

		return $this;
	}

	public function isRequired(): bool
	{
		return $this->required;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	/**
	 * @return array<string|int, string>
	 */
	public function getItems(): array
	{
		return $this->items;
	}

	public function isLocalized(): bool
	{
		return Arrays::contains([self::TYPE_LOCALE_TEXT, self::TYPE_LOCALE_TEXTAREA], $this->type);
	}
}
