<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Media\FileStorage;
use Backoffice\Media\ImageStorage;
use Backoffice\Resource\Csv\CsvImporter;
use Backoffice\Resource\Csv\CsvRowException;
use Backoffice\Resource\Form\Field;
use Backoffice\Resource\Form\FormFactory;
use Backoffice\Resource\Form\RichTextEditor;
use Backoffice\Resource\List\ListControl;
use Backoffice\Resource\ListView;
use Backoffice\Resource\ResourceDefinition;
use Backoffice\Resource\ResourceRegistry;
use Backoffice\Security\ChangeLogger;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Nette\Http\FileUpload;
use Nette\Utils\Html;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use StORM\DIConnection;
use StORM\Entity;

/**
 * Jeden presenter pro všechny deklarativní agendy: <prefix>/r/<agenda>[/edit|new|usage|import][/<id>].
 */
final class ResourcePresenter extends BackofficePresenter
{
	/** Klíč v datech smazaného záznamu (operace „restore“) se soubory přesunutými do koše. */
	public const UNDO_FILES = '__files';

	/** Kolik posledních vratných změn si session pamatuje (tlačítko „Vrátit“). */
	private const UNDO_LIMIT = 10;

	private const RICH_TEXT_UPLOAD_LIMIT = 5 * 1024 * 1024;

	#[Persistent]
	public string $resource = '';

	#[Inject]
	public ResourceRegistry $registry;

	#[Inject]
	public FormFactory $formFactory;

	#[Inject]
	public ChangeLogger $changeLogger;

	/** Jazyky webu (i pro stránku nastavení, která nemá repozitář). */
	#[Inject]
	public DIConnection $connection;

	#[Inject]
	public ImageStorage $images;

	#[Inject]
	public FileStorage $files;

	#[Inject]
	public RichTextEditor $richText;

	private ?ResourceDefinition $definition = null;

	private ?Entity $entity = null;

	private ?bool $hasListView = null;

	/** @var array<string, list<\Backoffice\Resource\Form\FormRow>> */
	private array $formRows = [];

	public function checkRequirements(mixed $element): void
	{
		$this->definition = $this->registry->get($this->resource);

		if ($this->definition === null) {
			$this->error("Agenda '{$this->resource}' neexistuje.");
		}

		parent::checkRequirements($element);
	}

	public function renderDefault(): void
	{
		$meta = $this->getDefinition()->getMeta();

		$this->title = $meta->getTitle();
		$this->breadcrumbs = [[$meta->getTitle()]];

		if ($meta->isSettings()) {
			$this->prepareForm();
			$this->buttons = [];

			return;
		}

		if ($this->hasListView()) {
			$this->buttons = $this->getListView()->headerButtons();
			$this->template->setFile(__DIR__ . '/../Resource/templates/view.latte');

			return;
		}

		$this->buttons = $this->defaultButtons();
		$this->template->setFile(__DIR__ . '/../Resource/templates/default.latte');
	}

	/**
	 * Vlastní obrazovka seznamu s rozbaleným záznamem (ListView::editedId()): formulář se naváže
	 * na záznam stejně jako na stránce záznamu.
	 */
	public function actionDefault(): void
	{
		$definition = $this->getDefinition();

		// Stránka nastavení: formulář bez záznamu, hodnoty dodá agenda.
		if ($definition->getMeta()->isSettings()) {
			if (!$this['form']->isSubmitted()) {
				$this['form']->setDefaults($definition->loadSettings());
			}

			return;
		}

		if (!$this->hasListView()) {
			return;
		}

		$view = $this->getListView();
		$id = $view->editedId();

		if ($id === null || $id === ListView::EDIT_NEW) {
			return;
		}

		$entity = $definition->getMeta()->getRepository()->one($id);

		if ($entity === null || !$this->isAllowedAction('edit') || $definition->lockReason($entity) !== null) {
			$view->closeEdit();

			return;
		}

		$this->entity = $entity;

		if ($this['form']->isSubmitted()) {
			return;
		}

		$this['form']->setDefaults($this->formFactory->encodeDefaults($definition->buildForm(), $definition->formDefaults($this->formFactory->defaults($definition->buildForm(), $entity), $entity)));
	}

	public function actionNew(): void
	{
		if (!$this->getDefinition()->getMeta()->hasCreate()) {
			$this->error('Agenda nemá zakládání záznamů.');
		}
	}

	public function actionEdit(string $id): void
	{
		if (!$this->getDefinition()->getMeta()->hasDetail()) {
			$this->error('Agenda nemá stránku záznamu.');
		}

		$this->entity = $this->getDefinition()->getMeta()->getRepository()->one($id) ?? $this->error('Záznam nenalezen.');

		if ($this['form']->isSubmitted()) {
			return;
		}

		$definition = $this->getDefinition();
		$builder = $definition->buildForm();
		$defaults = $definition->formDefaults($this->formFactory->defaults($builder, $this->entity), $this->entity);
		$this['form']->setDefaults($this->formFactory->encodeDefaults($builder, $defaults));
	}

	public function renderEdit(): void
	{
		$meta = $this->getDefinition()->getMeta();

		// Až při vykreslení (ne v action), ať kanonizace adresy nepřesměruje na ?view-…
		if ($this->hasListView()) {
			$this->getListView()->focus($this->getEntity());
		}

		$this->title = $this->entityTitle($this->getEntity());
		$this->breadcrumbs = [[$meta->getTitle(), 'default', $this->listParameters()], [$this->title]];
		$this->prepareForm();
	}

	public function renderNew(): void
	{
		$meta = $this->getDefinition()->getMeta();

		$this->title = 'Nový: ' . $meta->getItemTitle();
		$this->breadcrumbs = [[$meta->getTitle(), 'default', $this->listParameters()], ['Nový záznam']];
		$this->prepareForm();
	}

	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleDelete(string $id, bool $force = false): void
	{
		$meta = $this->getDefinition()->getMeta();

		if (!$this->canDelete()) {
			$this->error('Na mazání nemáte oprávnění.', 403);
		}

		$entity = $meta->getRepository()->one($id);

		if ($entity !== null && $this->getDefinition()->lockReason($entity) !== null) {
			$this->flashMessage((string) $this->getDefinition()->lockReason($entity), 'error');
			$this->redirect('default', $this->listParameters());
		}

		$blocked = $entity !== null ? $this->getDefinition()->deleteBlockReason($entity) : null;

		if ($blocked !== null) {
			$this->flashMessage($blocked, 'error');
			$this->redirect('default', $this->listParameters());
		}

		if ($entity !== null && !$force && ($this->getDefinition()->usages($entity) ?? []) !== []) {
			// Používá se na stránkách — rozhodne se na stránce se seznamem míst.
			$this->redirect('usage', ['id' => $id]);
		}

		if ($entity !== null) {
			$title = $this->entityTitle($entity);
			$data = $entity->toArray();

			if ($this->hasListView()) {
				$this->getListView()->focus($entity);
			}

			try {
				$entity->delete();
			} catch (\PDOException) {
				$this->flashMessage("Záznam „{$title}“ nejde smazat — nejspíš se na něj odkazují jiná data.", 'error');
				$this->redirect('default', $this->listParameters());
			}

			$data[self::UNDO_FILES] = $this->trashFiles($entity);
			$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DELETE, $id);
			$this->getDefinition()->changed();
			$this->flashWithUndo("Smazáno: $title", [['restore', $id, $data]]);
		}

		$this->redirect('default', $this->listParameters());
	}

	public function actionUsage(string $id): void
	{
		$entity = $this->getDefinition()->getMeta()->getRepository()->one($id);

		if ($entity === null) {
			$this->flashMessage('Záznam už neexistuje.', 'warning');
			$this->redirect('default', $this->listParameters());
		}

		$this->entity = $entity;
	}

	public function renderUsage(): void
	{
		$meta = $this->getDefinition()->getMeta();
		$entity = $this->getEntity();
		$title = $this->entityTitle($entity);

		$this->title = 'Smazat: ' . $title;
		$this->breadcrumbs = [[$meta->getTitle(), 'default', $this->listParameters()], ['Smazat']];
		$this->buttons = [$this->button('Zpět na seznam', 'default', $this->listParameters(), variant: 'ghost')];
		$this->template->entity = $entity;
		$this->template->entityTitle = $title;
		$this->template->locations = $this->getDefinition()->usages($entity) ?? [];
		$this->template->canHide = $meta->getRepository()->getStructure()->getColumn('hidden') !== null;
		$this->template->hidden = (bool) ($meta->getRepository()->getStructure()->getColumn('hidden') !== null ? $entity->getValue('hidden') : false);
		$this->template->canDelete = $this->canDelete() && $this->getDefinition()->deleteBlockReason($entity) === null;
		$this->template->setFile(__DIR__ . '/../Resource/templates/usage.latte');
	}

	/**
	 * Skrýt místo smazání (stránka „Smazat“ u použitého záznamu).
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleHide(string $id): void
	{
		$meta = $this->getDefinition()->getMeta();

		if (!$this->isAllowedAction('edit')) {
			$this->error('Na úpravy nemáte oprávnění.', 403);
		}

		$entity = $meta->getRepository()->one($id);

		if ($entity !== null && $meta->getRepository()->getStructure()->getColumn('hidden') !== null && !$entity->getValue('hidden')) {
			$entity->update(['hidden' => true]);
			$this->logChange(ChangeLogger::TYPE_DETAIL, $id);
			$this->getDefinition()->changed();
			$this->flashWithUndo('Skryto: ' . $this->entityTitle($entity) . '. Na stránkách se už nezobrazí, v agendě zůstává.', [['update', $id, ['hidden' => false]]]);
		}

		$this->redirect('default', $this->listParameters());
	}

	/**
	 * Soubory smazaného záznamu do koše (FileStorage::trash()), ať je „Vrátit“ může vrátit.
	 * @return list<array{string, string, string}> [klíč v koši, adresář, jméno]
	 */
	public function trashFiles(Entity $entity): array
	{
		$trashed = [];

		foreach ($this->getDefinition()->buildForm()->getFields() as $field) {
			$directory = $field->getFileDirectory();

			if ($field->type !== Field::TYPE_FILE || $directory === null) {
				continue;
			}

			$filename = $entity->getValue($field->name);

			if (!\is_string($filename) || $filename === '') {
				continue;
			}

			$key = $this->files->trash($directory, $filename);

			if ($key === null) {
				continue;
			}

			$trashed[] = [$key, $directory, $filename];
		}

		return $trashed;
	}

	/**
	 * Adresa souboru ke stažení z userfiles, nebo null, když soubor není.
	 */
	public function fileUrl(?string $directory, mixed $filename): ?string
	{
		return $directory !== null && \is_string($filename) && $filename !== '' ? $this->files->url($directory, $filename) : null;
	}

	/**
	 * Hledání pro pole vazby (FormBuilder::relation()): JSON [{id, label}], nejvýš 20 položek.
	 */
	public function handleLookup(string $field, string $q = ''): void
	{
		if (!$this->isAllowedAction('edit') && !$this->isAllowedAction('new')) {
			$this->error('Nemáte oprávnění.', 403);
		}

		$definition = $this->getDefinition()->buildForm()->getFields()[$field] ?? null;
		$repository = $definition?->getRelationRepository();

		if ($definition === null || $repository === null) {
			$this->error('Pole vazby neexistuje.');
		}

		$collection = $repository->many()->select(['bo_label' => $definition->getRelationLabel()]);
		$q = Strings::trim($q);

		if ($q !== '') {
			$conditions = \array_map(static fn (string $column): string => "$column LIKE :bo_q", $definition->getRelationSearch());
			$collection->where('(' . \implode(' OR ', $conditions) . ')', ['bo_q' => '%' . \addcslashes($q, '%_\\') . '%']);
		}

		$items = [];

		foreach ($collection->setOrderBy(['bo_label' => 'ASC'])->setTake(20)->toArrayOf('bo_label') as $id => $label) {
			$items[] = ['id' => (string) $id, 'label' => (string) $label];
		}

		$this->sendJson($items);
	}

	/**
	 * Adresa náhledu obrázku z userfiles, nebo null, když soubor není.
	 */
	/**
	 * Obrázek vložený do formátovaného textu (Field::richTextUploads()): uloží ho do userfiles a vrátí
	 * JSON pro TinyMCE {location} s adresou bez domény, nebo {error}. Do 5 MB, jen obrázky.
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleRichTextUpload(string $field): void
	{
		if (!$this->isAllowedAction('edit') && !$this->isAllowedAction('new')) {
			$this->error('Nemáte oprávnění.', 403);
		}

		$directory = ($this->getDefinition()->buildForm()->getFields()[$field] ?? null)?->getRichTextUploadDirectory();

		if ($directory === null) {
			$this->error('Pole nemá nahrávání obrázků.');
		}

		$file = $this->getHttpRequest()->getFile('file');

		if (!$file instanceof FileUpload || !$file->isOk() || !$file->isImage()) {
			$this->sendJson(['error' => 'Soubor se nenahrál, nebo to není obrázek (JPG, PNG, GIF, WebP).']);
		}

		if ($file->getSize() > self::RICH_TEXT_UPLOAD_LIMIT) {
			$this->sendJson(['error' => 'Obrázek je větší než 5 MB.']);
		}

		$basename = Random::generate(8) . '-' . Strings::webalize((string) \pathinfo($file->getUntrustedName(), \PATHINFO_FILENAME));
		$filename = $this->images->save($file, [$directory => null], $basename);
		$url = (string) $this->images->url($directory, $filename);

		$this->sendJson(['location' => (string) Strings::before($url . '?', '?')]);
	}

	public function imageUrl(?string $directory, mixed $filename): ?string
	{
		return $directory !== null && \is_string($filename) && $filename !== '' ? $this->images->url($directory, $filename) : null;
	}

	/**
	 * Vrátí změnu z flash zprávy („Vrátit“): smazané záznamy založí znovu, upravené vrátí na původní
	 * hodnoty. Změna se pamatuje v session jen pro tuto agendu a posledních pár akcí.
	 */
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function handleUndo(string $token): void
	{
		$meta = $this->getDefinition()->getMeta();
		$section = $this->getSession('backoffice.undo');
		/** @var array<string, array{resource: string, ops: list<array{string, string, array<string, mixed>}>}> $stack */
		$stack = $section->get('stack') ?? [];
		$entry = $stack[$token] ?? null;

		if ($entry === null || $entry['resource'] !== $meta->getSlug()) {
			$this->flashMessage('Tuhle změnu už vrátit nejde.', 'warning');
			$this->redirect('default');
		}

		unset($stack[$token]);
		$section->set('stack', $stack);
		$repository = $meta->getRepository();
		$restored = 0;

		foreach ($entry['ops'] as [$type, $id, $data]) {
			if ($type === 'restore') {
				if ($repository->one($id) !== null || !$this->isAllowedAction('edit')) {
					continue;
				}

				$files = \is_array($data[self::UNDO_FILES] ?? null) ? $data[self::UNDO_FILES] : [];
				unset($data[self::UNDO_FILES]);
				$repository->createOne($data);

				foreach ($files as $file) {
					if (!\is_array($file) || \count($file) !== 3) {
						continue;
					}

					[$key, $directory, $filename] = $file;
					$this->files->restore((string) $key, (string) $directory, (string) $filename);
				}

				$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_NEW, $id);
				$restored++;

				continue;
			}

			$entity = $repository->one($id);

			if ($entity === null || !$this->isAllowedAction('edit')) {
				continue;
			}

			$entity->update($data);
			$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DETAIL, $id);
			$restored++;
		}

		$this->getDefinition()->changed();
		$this->flashMessage($restored > 0 ? 'Vráceno.' : 'Nebylo co vracet.', $restored > 0 ? 'success' : 'warning');
		$this->redirect('default', $this->listParameters());
	}

	/**
	 * Má uživatel právo na akci agendy (default, edit, new)? Ptá se stejně jako při vstupu na stránku.
	 * Zakládání vypnuté v Meta (withoutCreate) se bere jako bez práva.
	 */
	public function isAllowedAction(string $action): bool
	{
		$meta = $this->getDefinition()->getMeta();

		if ($action === 'new' && !$meta->hasCreate()) {
			return false;
		}

		return $this->shell->access->isAllowed($meta->permissionLink($action));
	}

	/**
	 * Mazání: právo na úpravu a agenda mazání nevypnula (Meta::withoutDelete()).
	 */
	public function canDelete(): bool
	{
		return $this->getDefinition()->getMeta()->hasDelete() && $this->isAllowedAction('edit');
	}

	/**
	 * Zápis do logu změn pod názvem agendy (ChangeLogger::TYPE_*).
	 */
	public function logChange(string $type, string $id): void
	{
		$this->changeLogger->log($this->getDefinition()->getMeta()->getTitle(), $type, $id);
	}

	/**
	 * Flash zpráva s tlačítkem „Vrátit“. Operace: ['update', id, původní hodnoty] nebo
	 * ['restore', id, Entity::toArray() smazaného záznamu].
	 * @param list<array{string, string, array<string, mixed>}> $ops
	 */
	public function flashWithUndo(string $message, array $ops): void
	{
		$flash = $this->flashMessage($message, 'success');

		if ($ops === []) {
			return;
		}

		$section = $this->getSession('backoffice.undo');
		/** @var array<string, array{resource: string, ops: list<array{string, string, array<string, mixed>}>}> $stack */
		$stack = $section->get('stack') ?? [];
		$token = Random::generate(12);
		$stack[$token] = ['resource' => $this->getDefinition()->getMeta()->getSlug(), 'ops' => $ops];
		$section->set('stack', \array_slice($stack, -self::UNDO_LIMIT, null, true));
		// Na seznam, ne na „this“: po smazání ze stránky záznamu by adresa vedla na smazaný záznam.
		$flash->undo = $this->link('default', [self::SignalKey => 'undo', 'token' => $token] + $this->listParameters());
	}

	/**
	 * Tlačítka hlavičky seznamu: „Nový záznam“, když na něj má uživatel právo.
	 * @return list<\Nette\Utils\Html>
	 */
	public function defaultButtons(): array
	{
		$list = $this->getDefinition()->buildList();
		$buttons = [];

		foreach ($this->getDefinition()->getMeta()->getRelated() as $slug => $label) {
			$related = $this->registry->get($slug);

			if ($related === null || !$this->shell->access->isAllowed($related->getMeta()->permissionLink('default'))) {
				continue;
			}

			$buttons[] = $this->button($label, 'default', ['resource' => $slug], variant: 'ghost');
		}

		if ($list->hasCsvExport() && !$this->hasListView()) {
			// Odkaz z komponenty seznamu nese jeho stav (hledání, filtry, řazení).
			$buttons[] = Html::el('a', ['class' => 'bo-btn bo-btn-ghost', 'href' => $this['list']->link('export!')])
				->addText('Export CSV');
		}

		if ($list->hasCsvImport() && $this->isAllowedAction('import')) {
			$buttons[] = $this->button('Import CSV', 'import', variant: 'outline');
		}

		if ($this->isAllowedAction('new')) {
			$buttons[] = $this->button('Nový: ' . $this->getDefinition()->getMeta()->getItemTitle(), 'new', icon: 'file');
		}

		return $buttons;
	}

	public function actionImport(): void
	{
		if (!$this->getDefinition()->buildList()->hasCsvImport()) {
			$this->error('Agenda nemá import CSV.');
		}
	}

	public function renderImport(): void
	{
		$meta = $this->getDefinition()->getMeta();
		$list = $this->getDefinition()->buildList();
		$this->title = 'Import CSV';
		$this->breadcrumbs = [[$meta->getTitle(), 'default'], ['Import CSV']];
		$this->buttons = [$this->button('Zpět na seznam', 'default', variant: 'ghost')];
		$this->template->csvColumns = $list->getCsvColumns();
		$this->template->csvKey = $list->getCsvImportKey() ?? [];
		$this->template->exportLink = $list->hasCsvExport() ? $this['list']->link('export!') : null;
		$template = $this->getTemplate();
		\assert($template instanceof \Nette\Bridges\ApplicationLatte\Template);
		$template->setFile(__DIR__ . '/../Resource/templates/import.latte');
	}

	public function getDefinition(): ResourceDefinition
	{
		return $this->definition ?? throw new \LogicException('Agenda není načtená.');
	}

	/**
	 * Jazyky v pořadí, první je hlavní.
	 * @return list<string>
	 */
	public function mutations(): array
	{
		return \array_map('strval', \array_keys($this->connection->getAvailableMutations()));
	}

	/**
	 * Data pro šablonu polí formuláře k upravovanému záznamu: náhledy obrázků, soubory, popisky vazeb.
	 * @return array{
	 *     imageUrls: array<string, string>,
	 *     fileInfo: array<string, array{string, string|null, int|null}>,
	 *     relationLabels: array<string, string|array<string, string>>,
	 *     formRows: array<string, list<\Backoffice\Resource\Form\FormRow>>,
	 *     entity: ?\StORM\Entity,
	 *     richText: ?\Backoffice\Resource\Form\RichTextEditor
	 * }
	 */
	public function formContext(): array
	{
		return [
			'imageUrls' => $this->imageUrls(),
			'fileInfo' => $this->fileInfo(),
			'relationLabels' => $this->relationLabels(),
			'formRows' => $this->allFormRows(),
			'entity' => $this->entity,
			'richText' => $this->richText,
		];
	}

	public function entityTitle(Entity $entity): string
	{
		foreach ($this->getDefinition()->buildList()->getColumns() as $column) {
			if ($column->isLink()) {
				$value = $entity->getValue($column->name);
				$title = \is_scalar($value) ? self::plainTitle((string) $value) : '';

				return $title !== '' ? $title : (string) $entity->getPK();
			}
		}

		return (string) $entity->getPK();
	}

	/**
	 * Název záznamu z textu, který může být HTML (formátovaný text): bez značek, na jeden řádek, zkrácený.
	 */
	public static function plainTitle(string $value): string
	{
		if (\str_contains($value, '<')) {
			$value = \html_entity_decode(\strip_tags((string) \preg_replace('~<(br|/p|/h[1-6]|/div|/li)\b[^>]*>~i', ' $0', $value)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
		}

		return \Nette\Utils\Strings::truncate(\Nette\Utils\Strings::trim((string) \preg_replace('~[\s\x{A0}]+~u', ' ', $value)), 80);
	}

	protected function createComponentForm(): Form
	{
		$definition = $this->getDefinition();
		$form = $this->formFactory->create($definition->buildForm(), $this->mutations(), $this->formRows(...));

		$form->onValidate[] = function (Form $form) use ($definition): void {
			$this->validateUnique($form, $definition);
			$this->validateImages($form, $definition);
			$definition->validate($form, $this->entity);
		};

		$form->onSuccess[] = function (Form $form, array $values) use ($definition): void {
			$meta = $definition->getMeta();

			if ($meta->isSettings()) {
				$this->saveSettings($definition, $values);
			}

			if (!$this->isAllowedAction($this->entity !== null ? 'edit' : 'new')) {
				$this->error('Na uložení nemáte oprávnění.', 403);
			}

			if ($this->entity !== null && $definition->lockReason($this->entity) !== null) {
				$this->error('Záznam nejde upravit.', 403);
			}

			$repository = $meta->getRepository();
			$data = $this->formFactory->values($definition->buildForm(), $values, $repository->getStructure());
			// Háčkům agendy víc vazeb jako list klíčů, datum a čas ve tvaru pro databázi.
			$values = $this->formFactory->normalize($definition->buildForm(), $values);
			[$data, $obsoleteFiles] = $this->prepareFiles($form, $definition, $data);
			$data = $definition->beforeSave($data, $values, $this->entity);

			if ($this->entity !== null) {
				$this->entity->update($data);
				$entity = $this->entity;
				$type = ChangeLogger::TYPE_DETAIL;
			} else {
				// Znovu načíst: nová entita z createOne() nemá jazykové sloupce v podobě pro getValue().
				$created = $repository->createOne($data, true);
				$entity = $repository->one($created->getPK()) ?? $created;
				$type = ChangeLogger::TYPE_NEW;
			}

			$this->saveImages($form, $definition, $entity);
			$definition->afterSave($entity, $values, $type === ChangeLogger::TYPE_NEW);

			foreach ($obsoleteFiles as [$directory, $filename]) {
				$this->files->delete($directory, $filename);
			}

			$this->changeLogger->log($meta->getTitle(), $type, (string) $entity->getPK());
			$definition->changed();

			if ($this->hasListView()) {
				$this->getListView()->focus($entity);
			}

			if ($this->getAction() === 'default') {
				// Formulář vložený do vlastní obrazovky seznamu (ListView::newForm(), editForm()).
				$this->getListView()->closeEdit();
				$this->flashMessage(($type === ChangeLogger::TYPE_NEW ? 'Přidáno: ' : 'Uloženo: ') . $this->entityTitle($entity), 'success');
				$this->redirect('default', $this->listParameters());
			}

			$this->flashMessage('Uloženo', 'success');

			if ($form->isSubmitted() === $form['saveClose']) {
				$this->redirect('default', $this->listParameters());
			}

			$this->redirect('edit', ['id' => (string) $entity->getPK()]);
		};

		$form->onError[] = function (): void {
			$this->flashMessage('Formulář obsahuje chyby — jsou vyznačené u polí.', 'error');
		};

		return $form;
	}

	protected function createComponentImportForm(): Form
	{
		$form = new Form();
		$form->addProtection('Platnost formuláře vypršela, odešlete ho prosím znovu.');
		$form->addUpload('file', 'Soubor CSV')
			->setRequired('Vyberte soubor CSV.')
			->setHtmlAttribute('accept', '.csv,text/csv,text/plain');
		$form->addSubmit('import', 'Importovat');

		$form->onSuccess[] = function (Form $form, array $values): void {
			$definition = $this->getDefinition();
			$meta = $definition->getMeta();
			$upload = $values['file'];
			\assert($upload instanceof FileUpload);

			if (!$upload->isOk()) {
				$control = $form->getComponent('file');
				\assert($control instanceof \Nette\Forms\Controls\UploadControl);
				$control->addError('Soubor se nenahrál, zkuste to znovu.');

				return;
			}

			try {
				$result = (new CsvImporter($definition))->import((string) $upload->getContents());
			} catch (CsvRowException $e) {
				$form->addError('Nic se neuložilo. ' . $e->getMessage());

				return;
			} catch (\PDOException $e) {
				$form->addError('Nic se neuložilo — databáze řádek odmítla (' . $e->getMessage() . ').');

				return;
			}

			foreach ($result['created'] as $id) {
				$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_NEW, $id);
			}

			foreach ($result['updated'] as $id) {
				$this->changeLogger->log($meta->getTitle(), ChangeLogger::TYPE_DETAIL, $id);
			}

			if ($result['created'] !== [] || $result['updated'] !== []) {
				$definition->changed();
			}

			$this->flashMessage(\sprintf(
				'Import hotový: %d nových, %d upravených, %d beze změny.',
				\count($result['created']),
				\count($result['updated']),
				$result['unchanged'],
			), 'success');
			$this->redirect('default');
		};

		return $form;
	}

	protected function createComponentList(): ListControl
	{
		if ($this->getDefinition()->getMeta()->isSettings()) {
			$this->error('Stránka nastavení nemá seznam.');
		}

		return new ListControl();
	}

	protected function createComponentView(): ListView
	{
		return $this->getDefinition()->createListView() ?? $this->error('Agenda nemá vlastní obrazovku seznamu.');
	}

	protected function permissionLink(): string
	{
		return $this->getDefinition()->getMeta()->permissionLink($this->getAction());
	}

	private function prepareForm(): void
	{
		$this->buttons = [$this->button('Zpět na seznam', 'default', $this->listParameters(), variant: 'ghost')];
		$this->template->sections = $this->getDefinition()->buildForm()->getSections();
		$this->template->mutations = $this->mutations();
		$this->template->entity = $this->entity;
		$this->template->fieldsTemplate = Field::TEMPLATE;
		$this->template->canDelete = $this->canDelete();
		$this->template->imageUrls = $this->imageUrls();
		$this->template->fileInfo = $this->fileInfo();
		$this->template->relationLabels = $this->relationLabels();
		$this->template->formRows = $this->allFormRows();
		$this->template->richText = $this->richText;
		$this->template->settings = $this->getDefinition()->getMeta()->isSettings();

		if ($this->entity !== null && $this->getDefinition()->deleteBlockReason($this->entity) !== null) {
			$this->template->canDelete = false;
		}

		$lock = $this->entity !== null ? $this->getDefinition()->lockReason($this->entity) : null;
		$notices = $this->entity !== null ? $this->getDefinition()->formNotices($this->entity) : [];

		if ($this->getDefinition()->getMeta()->isSettings()) {
			$lock = $this->isAllowedAction('edit') ? null : 'Nastavení můžete jen prohlížet — na změny nemáte oprávnění.';
			$notices = $this->getDefinition()->settingsNotices();
		}

		$this->template->lockReason = $lock;
		$this->template->notices = $notices;

		if ($lock !== null) {
			$this->template->canDelete = false;

			foreach ($this['form']->getControls() as $control) {
				if (!$control instanceof \Nette\Forms\Controls\BaseControl) {
					continue;
				}

				$control->setDisabled();
			}
		}

		$this->template->setFile(__DIR__ . '/../Resource/templates/form.latte');
	}

	/**
	 * Řádky vnořeného seznamu z agendy, jednou za požadavek (formulář i šablona je chtějí stejné).
	 * @return list<\Backoffice\Resource\Form\FormRow>
	 */
	private function formRows(string $field): array
	{
		return $this->formRows[$field] ??= $this->getDefinition()->formRows($field, $this->entity);
	}

	/**
	 * @return array<string, list<\Backoffice\Resource\Form\FormRow>>
	 */
	private function allFormRows(): array
	{
		$rows = [];

		foreach ($this->getDefinition()->buildForm()->getFields() as $name => $field) {
			if ($field->type !== Field::TYPE_ROWS) {
				continue;
			}

			$rows[$name] = $this->formRows($name);
		}

		return $rows;
	}

	/**
	 * Uložení stránky nastavení (Meta::settings()): hodnoty jdou agendě, do logu pod jménem agendy.
	 * @param array<string, mixed> $values
	 */
	private function saveSettings(ResourceDefinition $definition, array $values): never
	{
		if (!$this->isAllowedAction('edit')) {
			$this->error('Na změnu nastavení nemáte oprávnění.', 403);
		}

		$definition->saveSettings($values);
		$this->changeLogger->log($definition->getMeta()->getTitle(), ChangeLogger::TYPE_DETAIL, $definition->getMeta()->getSlug());
		$definition->changed();
		$this->flashMessage('Uloženo', 'success');
		$this->redirect('default');
	}

	/**
	 * Povinný obrázek: buď už je u záznamu, nebo se právě nahrává.
	 */
	private function validateImages(Form $form, ResourceDefinition $definition): void
	{
		foreach ($definition->buildForm()->getFields() as $field) {
			if (!FormFactory::isUpload($field) || !$field->isRequired()) {
				continue;
			}

			$control = $form->getComponent($field->name);
			\assert($control instanceof \Nette\Forms\Controls\UploadControl);
			$upload = $control->getValue();
			$current = $this->entity?->getValue($field->name);
			$remove = (bool) $form->getComponent($field->name . FormFactory::REMOVE_SUFFIX)->getValue();

			if (($upload instanceof \Nette\Http\FileUpload && $upload->isOk()) || (\is_string($current) && $current !== '' && !$remove)) {
				continue;
			}

			$control->addError(($field->type === Field::TYPE_IMAGE ? 'Nahrajte obrázek ' : 'Nahrajte soubor ') . $field->label . '.');
		}
	}

	/**
	 * Nahrané obrázky do userfiles (jméno = ID záznamu), odstraněné smazat. Starý soubor s jinou
	 * příponou se po nahrání nového smaže.
	 */
	private function saveImages(Form $form, ResourceDefinition $definition, Entity $entity): void
	{
		foreach ($definition->buildForm()->getFields() as $field) {
			if ($field->type !== Field::TYPE_IMAGE) {
				continue;
			}

			$control = $form->getComponent($field->name);
			\assert($control instanceof \Nette\Forms\Controls\UploadControl);
			$upload = $control->getValue();
			$current = $entity->getValue($field->name);
			$current = \is_string($current) && $current !== '' ? $current : null;
			$remove = (bool) $form->getComponent($field->name . FormFactory::REMOVE_SUFFIX)->getValue();

			if ($upload instanceof \Nette\Http\FileUpload && $upload->isOk()) {
				$filename = $this->images->save($upload, $field->getImageDirectories(), (string) $entity->getPK());

				if ($current !== null && $current !== $filename) {
					$this->images->delete($field->getImageDirectories(), $current);
				}

				$entity->update([$field->name => $filename]);
			} elseif ($remove && $current !== null) {
				$this->images->delete($field->getImageDirectories(), $current);
				$entity->update([$field->name => null]);
			}
		}
	}

	/**
	 * Popisky vybraných položek vazeb: jméno pole → popisek (pole s hledáním ukazuje popisek, ne klíč);
	 * u víc vazeb jméno pole → klíč → popisek.
	 * @return array<string, string|array<string, string>>
	 */
	private function relationLabels(): array
	{
		$labels = [];
		$form = $this['form'];

		foreach ($this->getDefinition()->buildForm()->getFields() as $field) {
			$repository = $field->getRelationRepository();

			if ($repository === null) {
				continue;
			}

			$control = $form->getComponent($field->name);
			$value = $control instanceof \Nette\Forms\Controls\BaseControl ? $control->getValue() : null;
			$pk = 'this.' . $repository->getStructure()->getPK()->getName();

			if ($field->type === Field::TYPE_RELATIONS) {
				// Štítky vybraných v pořadí výběru; neznámý klíč zůstane jako klíč (chybu hlásí pravidlo pole).
				$keys = FormFactory::relationKeys($value);
				$found = $keys === [] ? [] : $repository->many()->where($pk, $keys)->select(['bo_label' => $field->getRelationLabel()])->toArrayOf('bo_label');
				$chips = [];

				foreach ($keys as $key) {
					$chips[$key] = \is_scalar($found[$key] ?? null) ? (string) $found[$key] : $key;
				}

				$labels[$field->name] = $chips;

				continue;
			}

			if (!\is_scalar($value) || (string) $value === '') {
				continue;
			}

			$found = $repository->many()->where($pk, (string) $value)->select(['bo_label' => $field->getRelationLabel()])->toArrayOf('bo_label');
			$label = \reset($found);
			$labels[$field->name] = \is_scalar($label) ? (string) $label : (string) $value;
		}

		return $labels;
	}

	/**
	 * Soubory záznamu pro formulář: jméno pole → [jméno souboru, adresa nebo null, velikost nebo null].
	 * @return array<string, array{string, string|null, int|null}>
	 */
	private function fileInfo(): array
	{
		$info = [];

		foreach ($this->getDefinition()->buildForm()->getFields() as $field) {
			$directory = $field->getFileDirectory();

			if ($field->type !== Field::TYPE_FILE || $directory === null || $this->entity === null) {
				continue;
			}

			$filename = $this->entity->getValue($field->name);

			if (!\is_string($filename) || $filename === '' || !FileStorage::isSafeFilename($filename)) {
				continue;
			}

			$info[$field->name] = [$filename, $this->files->url($directory, $filename), $this->files->size($directory, $filename)];
		}

		return $info;
	}

	/**
	 * Náhledy obrázků záznamu pro formulář: jméno pole → adresa (z prvního adresáře pole).
	 * @return array<string, string>
	 */
	private function imageUrls(): array
	{
		$urls = [];

		foreach ($this->getDefinition()->buildForm()->getFields() as $field) {
			$directory = \array_key_first($field->getImageDirectories());

			if ($field->type !== Field::TYPE_IMAGE || $directory === null || $this->entity === null) {
				continue;
			}

			$url = $this->imageUrl($directory, $this->entity->getValue($field->name));

			if ($url === null) {
				continue;
			}

			$urls[$field->name] = $url;
		}

		return $urls;
	}

	/**
	 * Nahrané soubory do userfiles a jejich jména (velikost, přípona) do dat záznamu. Vrátí data
	 * a soubory, které po uložení přestanou patřit záznamu (smažou se, až se záznam uloží).
	 * @param array<string, mixed> $data
	 * @return array{array<string, mixed>, list<array{string, string}>}
	 */
	private function prepareFiles(Form $form, ResourceDefinition $definition, array $data): array
	{
		$obsolete = [];

		foreach ($definition->buildForm()->getFields() as $field) {
			$directory = $field->getFileDirectory();

			if ($field->type !== Field::TYPE_FILE || $directory === null) {
				continue;
			}

			$control = $form->getComponent($field->name);
			\assert($control instanceof \Nette\Forms\Controls\UploadControl);
			$upload = $control->getValue();
			$current = $this->entity?->getValue($field->name);
			$current = \is_string($current) && $current !== '' ? $current : null;
			$remove = (bool) $form->getComponent($field->name . FormFactory::REMOVE_SUFFIX)->getValue();

			if ($upload instanceof \Nette\Http\FileUpload && $upload->isOk()) {
				$filename = $this->files->save($upload, $directory, $current);
				$data[$field->name] = $filename;

				if ($field->getSizeColumn() !== null) {
					$data[$field->getSizeColumn()] = $upload->getSize();
				}

				if ($field->getExtensionColumn() !== null) {
					$data[$field->getExtensionColumn()] = Strings::lower((string) \pathinfo($filename, \PATHINFO_EXTENSION));
				}

				if ($current !== null && $current !== $filename) {
					$obsolete[] = [$directory, $current];
				}
			} elseif ($remove && $current !== null && !$field->isRequired()) {
				$data[$field->name] = null;
				$obsolete[] = [$directory, $current];
			}
		}

		return [$data, $obsolete];
	}

	private function hasListView(): bool
	{
		return $this->hasListView ??= $this->getDefinition()->createListView() !== null;
	}

	/**
	 * Stav vlastní obrazovky seznamu (rok, filtr) jako parametry odkazu na seznam: view-<jméno>.
	 * Po uložení nebo smazání se tak uživatel vrátí tam, kde záznam je (ListView::focus()).
	 * @return array<string, mixed>
	 */
	private function listParameters(): array
	{
		if (!$this->hasListView()) {
			return [];
		}

		$view = $this->getListView();
		$state = [];
		$view->saveState($state);
		$parameters = [];

		foreach ($state as $name => $value) {
			$parameters[$view->getUniqueId() . '-' . $name] = $value;
		}

		return $parameters;
	}

	private function getListView(): ListView
	{
		$view = $this->getComponent('view');
		\assert($view instanceof ListView);

		return $view;
	}

	/**
	 * Pole s ->unique(): hodnota se v agendě nesmí opakovat (kromě upravovaného záznamu).
	 */
	private function validateUnique(Form $form, ResourceDefinition $definition): void
	{
		foreach ($definition->buildForm()->getFields() as $field) {
			$message = $field->getUniqueMessage();

			if ($message === null || $field->isLocalized()) {
				continue;
			}

			$control = $form->getComponent($field->name, false);

			if (!$control instanceof \Nette\Forms\Controls\BaseControl) {
				continue;
			}

			$value = $control->getValue();

			if ($value === null || $value === '') {
				continue;
			}

			$collection = $definition->getMeta()->getRepository()->many()->where('this.' . $field->name, $value);

			if ($this->entity !== null) {
				$collection->whereNot('this.' . $definition->getMeta()->getRepository()->getStructure()->getPK()->getName(), $this->entity->getPK());
			}

			if ($collection->enum() === 0) {
				continue;
			}

			$control->addError($message);
		}
	}

	private function getEntity(): Entity
	{
		return $this->entity ?? throw new \LogicException('Záznam není načtený.');
	}
}
