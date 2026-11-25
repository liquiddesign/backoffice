<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

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
	public const TYPE_RICH_TEXT = 'richText';
	public const TYPE_LOCALE_RICH_TEXT = 'localeRichText';
	public const TYPE_ROWS = 'rows';
	public const TYPE_DATETIME = 'datetime';
	public const TYPE_RELATIONS = 'relations';

	public const ROW_CHECKBOX = 'checkbox';
	public const ROW_INTEGER = 'integer';
	public const ROW_TEXT = 'text';

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

	/** @var array<string, mixed> */
	private array $editorOptions = [];

	/** @var array<string, (\Closure(\Nette\Utils\Image): void)|null> adresář v userfiles → úprava obrázku */
	private array $imageDirectories = [];

	private ?int $counter = null;

	private bool $code = false;

	/** @var array<string, string> jazyk ('' = pole bez jazyků) → text před polem */
	private array $prefixes = [];

	/** @var array<string, array{type: string, label: string, placeholder: string|null}> pole řádku vnořeného seznamu */
	private array $rowColumns = [];

	private ?string $rowSortColumn = null;

	private string $rowsEmptyText = 'Zatím tu nic není.';

	private ?string $richTextUploadDirectory = null;

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

	/**
	 * Formátovaný text: nastavení editoru jen pro tohle pole (např. ['height' => 300]). Přidá se
	 * k tomu, co vrátí RichTextEditor::config() — co editor s klíči udělá, je věc aplikace.
	 * @param array<string, mixed> $options
	 */
	public function editorOptions(array $options): self
	{
		if (!$this->isRichText()) {
			throw new \LogicException("Pole '{$this->name}' není formátovaný text.");
		}

		$this->editorOptions = $options + $this->editorOptions;

		return $this;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getEditorOptions(): array
	{
		return $this->editorOptions;
	}

	/**
	 * Počítadlo znaků pod polem („42 / 70“), přes doporučenou délku zčervená — např. titulek
	 * a popisek pro vyhledávače. Délku neomezuje, jen ukazuje.
	 */
	public function counter(int $recommended): self
	{
		if (!Arrays::contains([self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_LOCALE_TEXT, self::TYPE_LOCALE_TEXTAREA], $this->type)) {
			throw new \LogicException("Počítadlo znaků jde jen u textového pole, '{$this->name}' je {$this->type}.");
		}

		$this->counter = $recommended;

		return $this;
	}

	public function getCounter(): ?int
	{
		return $this->counter;
	}

	/**
	 * Víceřádkové pole na kód (HTML šablony, JSON): neproporcionální písmo, vyšší pole, bez
	 * kontroly pravopisu a bez automatických úprav textu prohlížečem.
	 */
	public function code(bool $code = true): self
	{
		if (!Arrays::contains([self::TYPE_TEXTAREA, self::TYPE_LOCALE_TEXTAREA], $this->type)) {
			throw new \LogicException("Pole na kód jde jen u textarea, '{$this->name}' je {$this->type}.");
		}

		$this->code = $code;

		return $this;
	}

	public function isCode(): bool
	{
		return $this->code;
	}

	/**
	 * Pevný text před polem, který se neukládá — typicky začátek adresy („pse.cz/en/“). U jazykového
	 * pole pole [jazyk => text], jazyk bez textu prefix nemá.
	 * @param string|array<string, string> $prefix
	 */
	public function prefix(string|array $prefix): self
	{
		if (!Arrays::contains([self::TYPE_TEXT, self::TYPE_LOCALE_TEXT], $this->type)) {
			throw new \LogicException("Text před polem jde jen u jednořádkového pole, '{$this->name}' je {$this->type}.");
		}

		$this->prefixes = \is_string($prefix) ? ['' => $prefix] : $prefix;

		return $this;
	}

	/**
	 * Text před polem pro jazyk (null = pole bez jazyků).
	 */
	public function getPrefix(?string $mutation = null): ?string
	{
		return $this->prefixes[$mutation ?? ''] ?? null;
	}

	/**
	 * Formátovaný text: obrázek vložený do editoru (přetažením, ze schránky) se nahraje do
	 * userfiles/<directory> a do textu se vloží jeho adresa bez domény (/userfiles/…).
	 */
	public function richTextUploads(string $directory): self
	{
		if (!$this->isRichText()) {
			throw new \LogicException("Nahrávání obrázků do textu má jen formátovaný text, ne '{$this->name}'.");
		}

		$this->richTextUploadDirectory = Strings::trim($directory, '/');

		return $this;
	}

	public function getRichTextUploadDirectory(): ?string
	{
		return $this->richTextUploadDirectory;
	}

	/**
	 * Vnořený seznam (FormBuilder::rows()): zaškrtávátko v každém řádku.
	 */
	public function rowCheckbox(string $name, string $label): self
	{
		return $this->addRowColumn($name, self::ROW_CHECKBOX, $label);
	}

	/**
	 * Vnořený seznam: celé číslo v každém řádku (pořadí…).
	 */
	public function rowInteger(string $name, string $label): self
	{
		return $this->addRowColumn($name, self::ROW_INTEGER, $label);
	}

	/**
	 * Vnořený seznam: text v každém řádku.
	 */
	public function rowText(string $name, string $label, ?string $placeholder = null): self
	{
		return $this->addRowColumn($name, self::ROW_TEXT, $label, $placeholder);
	}

	/**
	 * Vnořený seznam: řádky jdou přetáhnout za úchyt; číselné pole $name se přečísluje podle nového
	 * pořadí (hodnotami, které řádky už měly) a uloží se s formulářem.
	 */
	public function rowsSortableBy(string $name): self
	{
		if (($this->rowColumns[$name]['type'] ?? null) !== self::ROW_INTEGER) {
			throw new \LogicException("Řazení vnořeného seznamu '{$this->name}' potřebuje číselné pole řádku '$name' (rowInteger).");
		}

		$this->rowSortColumn = $name;

		return $this;
	}

	/**
	 * Text místo tabulky, když agenda žádné řádky nevrátí.
	 */
	public function rowsEmptyText(string $text): self
	{
		$this->rowsEmptyText = $text;

		return $this;
	}

	/**
	 * @return array<string, array{type: string, label: string, placeholder: string|null}>
	 */
	public function getRowColumns(): array
	{
		return $this->rowColumns;
	}

	public function getRowSortColumn(): ?string
	{
		return $this->rowSortColumn;
	}

	public function getRowsEmptyText(): string
	{
		return $this->rowsEmptyText;
	}

	public function isLocalized(): bool
	{
		return Arrays::contains([self::TYPE_LOCALE_TEXT, self::TYPE_LOCALE_TEXTAREA, self::TYPE_LOCALE_RICH_TEXT], $this->type);
	}

	public function isRichText(): bool
	{
		return $this->type === self::TYPE_RICH_TEXT || $this->type === self::TYPE_LOCALE_RICH_TEXT;
	}

	private function addRowColumn(string $name, string $type, string $label, ?string $placeholder = null): self
	{
		if ($this->type !== self::TYPE_ROWS) {
			throw new \LogicException("Pole řádku jde přidat jen k vnořenému seznamu (FormBuilder::rows()), ne k '{$this->name}'.");
		}

		$this->rowColumns[$name] = ['type' => $type, 'label' => $label, 'placeholder' => $placeholder];

		return $this;
	}
}
