<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

use Nette\Utils\Arrays;

/**
 * Pole formuláře agendy. Nette formulář z něj sestaví FormControl (etapa 1).
 */
final class Field
{
	/** Šablona s poli formuláře, sdílí ji stránka záznamu i vlastní obrazovky (ListView). */
	public const TEMPLATE = __DIR__ . '/../templates/fields.latte';

	public const TYPE_TEXT = 'text';
	public const TYPE_TEXTAREA = 'textarea';
	public const TYPE_INTEGER = 'integer';
	public const TYPE_CHECKBOX = 'checkbox';
	public const TYPE_SELECT = 'select';
	public const TYPE_DATE = 'date';
	public const TYPE_LOCALE_TEXT = 'localeText';
	public const TYPE_LOCALE_TEXTAREA = 'localeTextarea';
	public const TYPE_IMAGE = 'image';
	public const TYPE_FILE = 'file';
	public const TYPE_RELATION = 'relation';

	private bool $required = false;

	private ?string $description = null;

	/** @var array<string|int, string> */
	private array $items = [];

	private ?string $uniqueMessage = null;

	private mixed $default = null;

	private ?string $fileDirectory = null;

	private ?string $prompt = null;

	private ?string $visibleWhenField = null;

	/** @var list<string> */
	private array $visibleWhenValues = [];

	/** @var \StORM\Repository<covariant \StORM\Entity>|null */
	private ?\StORM\Repository $relationRepository = null;

	private ?string $relationLabel = null;

	/** @var list<string> */
	private array $relationSearch = [];

	/** @var list<string> */
	private array $acceptTypes = [];

	private ?string $acceptMessage = null;

	private ?string $sizeColumn = null;

	private ?string $extensionColumn = null;

	/** @var array<string, (\Closure(\Nette\Utils\Image): void)|null> adresář v userfiles → úprava obrázku */
	private array $imageDirectories = [];

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
	 * Výchozí hodnota u nového záznamu.
	 */
	public function default(mixed $value): self
	{
		$this->default = $value;

		return $this;
	}

	public function getDefault(): mixed
	{
		return $this->default;
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

	/**
	 * Hodnota se v agendě nesmí opakovat (např. datum neobchodního dne). Hlídá se při uložení.
	 */
	public function unique(string $message = 'Záznam s touto hodnotou už existuje.'): self
	{
		$this->uniqueMessage = $message;

		return $this;
	}

	/**
	 * Obrázek: adresář v userfiles (např. 'contact_persons' nebo 'quick_links/thumb'), kam se uloží
	 * kopie, a volitelně úprava (fn (Image $image) => $image->resize(600, 600)). Víc adresářů = víc
	 * velikostí; náhled ve formuláři je z prvního. Soubor se jmenuje <ID záznamu>.<přípona>.
	 * @param (\Closure(\Nette\Utils\Image): void)|null $transform
	 */
	public function imageDirectory(string $directory, ?\Closure $transform = null): self
	{
		if ($this->type !== self::TYPE_IMAGE) {
			throw new \LogicException("Pole '{$this->name}' není obrázek.");
		}

		if (!\preg_match('~^[a-z0-9_-]+(/[a-z0-9_-]+)*$~i', $directory)) {
			throw new \InvalidArgumentException("Neplatný adresář obrázku '$directory'.");
		}

		$this->imageDirectories[$directory] = $transform;

		return $this;
	}

	/**
	 * @return array<string, (\Closure(\Nette\Utils\Image): void)|null>
	 */
	public function getImageDirectories(): array
	{
		return $this->imageDirectories;
	}

	/**
	 * Soubor: adresář v userfiles, kam se uloží (jméno = očištěné jméno nahraného souboru).
	 */
	public function fileDirectory(string $directory): self
	{
		if ($this->type !== self::TYPE_FILE) {
			throw new \LogicException("Pole '{$this->name}' není soubor.");
		}

		if (!\preg_match('~^[a-z0-9_-]+(/[a-z0-9_-]+)*$~i', $directory)) {
			throw new \InvalidArgumentException("Neplatný adresář souboru '$directory'.");
		}

		$this->fileDirectory = $directory;

		return $this;
	}

	public function getFileDirectory(): ?string
	{
		return $this->fileDirectory;
	}

	/**
	 * Povolené typy souboru (MIME), např. ['application/pdf'].
	 * @param list<string> $mimeTypes
	 */
	public function accept(array $mimeTypes, string $message): self
	{
		$this->acceptTypes = $mimeTypes;
		$this->acceptMessage = $message;

		return $this;
	}

	/**
	 * @return list<string>
	 */
	public function getAcceptTypes(): array
	{
		return $this->acceptTypes;
	}

	public function getAcceptMessage(): ?string
	{
		return $this->acceptMessage;
	}

	/**
	 * Sloupce, kam se při nahrání zapíše velikost (bajty) a přípona souboru.
	 */
	public function fileColumns(?string $size = null, ?string $extension = null): self
	{
		$this->sizeColumn = $size;
		$this->extensionColumn = $extension;

		return $this;
	}

	public function getSizeColumn(): ?string
	{
		return $this->sizeColumn;
	}

	public function getExtensionColumn(): ?string
	{
		return $this->extensionColumn;
	}

	/**
	 * Text prázdné volby výběru (např. „pro všechny jazyky“). Bez něj nepovinný výběr ukáže „—“.
	 */
	public function prompt(string $prompt): self
	{
		$this->prompt = $prompt;

		return $this;
	}

	public function getPrompt(): ?string
	{
		return $this->prompt;
	}

	/**
	 * Pole je vidět, jen když má jiné pole (výběr) jednu z hodnot. Skryté pole se odešle taky —
	 * co s ním, rozhodne ResourceDefinition::beforeSave() a validate().
	 * @param string|list<string> $values
	 */
	public function visibleWhen(string $field, string|array $values): self
	{
		$this->visibleWhenField = $field;
		$this->visibleWhenValues = \is_array($values) ? \array_values($values) : [$values];

		return $this;
	}

	public function getVisibleWhenField(): ?string
	{
		return $this->visibleWhenField;
	}

	/**
	 * @return list<string>
	 */
	public function getVisibleWhenValues(): array
	{
		return $this->visibleWhenValues;
	}

	/**
	 * Vazba: popisek položky ve výběru (SQL výraz, např. "CONCAT(this.name, ' – ', this.isin)")
	 * a sloupce, ve kterých se hledá.
	 * @param list<string> $search
	 */
	public function relationLabel(string $expression, array $search): self
	{
		$this->relationLabel = $expression;
		$this->relationSearch = $search;

		return $this;
	}

	/**
	 * @param \StORM\Repository<covariant \StORM\Entity> $repository
	 */
	public function setRelationRepository(\StORM\Repository $repository): self
	{
		$this->relationRepository = $repository;

		return $this;
	}

	/**
	 * @return \StORM\Repository<covariant \StORM\Entity>|null
	 */
	public function getRelationRepository(): ?\StORM\Repository
	{
		return $this->relationRepository;
	}

	public function getRelationLabel(): string
	{
		return $this->relationLabel ?? 'this.' . ($this->relationRepository?->getStructure()->getPK()->getName() ?? 'uuid');
	}

	/**
	 * @return list<string>
	 */
	public function getRelationSearch(): array
	{
		return $this->relationSearch !== [] ? $this->relationSearch : [$this->getRelationLabel()];
	}

	public function getUniqueMessage(): ?string
	{
		return $this->uniqueMessage;
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
