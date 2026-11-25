<?php

declare(strict_types=1);

use Backoffice\Resource\Form\FormBuilder;
use Backoffice\Resource\Form\FormFactory;
use Nette\Forms\Container;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * Repozitář jen pro FormBuilder::relations() — encodeDefaults()/normalize() do databáze nesahají.
 * @extends \StORM\Repository<\StORM\Entity>
 */
class FakeRepository extends StORM\Repository
{
}

test('jazykové pole: kontejner, povinný jen hlavní jazyk', function (): void {
	$builder = new FormBuilder();
	$builder->localeText('name', 'Název')->required();
	$builder->date('date', 'Datum')->required();
	$builder->textarea('note', 'Poznámka');
	$builder->select('type', 'Typ', ['a' => 'A', 'b' => 'B']);

	$form = (new FormFactory())->create($builder, ['cs', 'en']);

	Assert::type(Container::class, $form['name']);
	Assert::true($form['name']['cs']->isRequired());
	Assert::false($form['name']['en']->isRequired());
	Assert::true($form['date']->isRequired());
	Assert::same('date', $form['date']->getControl()->type);
	Assert::false($form['note']->isRequired());
	Assert::same('—', $form['type']->getPrompt());
	Assert::true(isset($form['save'], $form['saveClose']));
});

test('sekce řadí pole', function (): void {
	$builder = new FormBuilder();
	$builder->text('a', 'A');
	$builder->section('Publikace');
	$builder->checkbox('b', 'B');

	Assert::same(['Základní údaje', 'Publikace'], array_keys($builder->getSections()));
});

test('obrázek: upload + Odstranit, do hodnot pro uložení nejde', function (): void {
	$builder = new FormBuilder();
	$builder->text('name', 'Jméno');
	$builder->image('photo', 'Fotka')->imageDirectory('contact_persons', static fn ($image) => $image->resize(600, 600));
	$builder->integer('priority', 'Pořadí')->default(10);

	$form = (new FormFactory())->create($builder, ['cs']);

	Assert::type(Nette\Forms\Controls\UploadControl::class, $form['photo']);
	Assert::type(Nette\Forms\Controls\Checkbox::class, $form['photo' . FormFactory::REMOVE_SUFFIX]);
	Assert::same(10, $form['priority']->getValue());
	Assert::same(['contact_persons'], array_keys($builder->getFields()['photo']->getImageDirectories()));
	Assert::exception(fn () => $builder->getFields()['name']->imageDirectory('x'), LogicException::class);
	Assert::exception(fn () => $builder->getFields()['photo']->imageDirectory('../etc'), InvalidArgumentException::class);
});

test('formátovaný text: textarea, jazyky v kontejneru, nastavení editoru jen u něj', function (): void {
	$builder = new FormBuilder();
	$builder->richText('body', 'Obsah');
	$builder->localeRichText('text', 'Text')->required()->editorOptions(['height' => 300]);
	$builder->text('name', 'Název');

	$form = (new FormFactory())->create($builder, ['cs', 'en']);
	$fields = $builder->getFields();

	Assert::type(Nette\Forms\Controls\TextArea::class, $form['body']);
	Assert::type(Nette\Forms\Controls\TextArea::class, $form['text']['en']);
	Assert::true($form['text']['cs']->isRequired());
	Assert::false($form['text']['en']->isRequired());
	Assert::true($fields['text']->isLocalized());
	Assert::true($fields['text']->isRichText());
	Assert::false($fields['body']->isLocalized());
	Assert::same(['height' => 300], $fields['text']->getEditorOptions());
	Assert::same([], (new Backoffice\Resource\Form\PlainRichTextEditor())->config($fields['body'], null));
	Assert::exception(fn () => $fields['name']->editorOptions(['height' => 1]), LogicException::class);
});

test('formátovaný text: atributy pro editor, nastavení pole má přednost', function (): void {
	$builder = new FormBuilder();
	$field = $builder->richText('body', 'Obsah')->editorOptions(['height' => 300]);
	$editor = new class implements Backoffice\Resource\Form\RichTextEditor {
		public function scripts(): array
		{
			return ['/js/editor.js'];
		}

		public function config(Backoffice\Resource\Form\Field $field, ?string $mutation): array
		{
			return ['height' => 550, 'lang' => $mutation];
		}
	};

	$attributes = FormFactory::richTextAttributes($field, 'en', $editor, true);

	Assert::same('bo-input bo-richtext is-invalid', $attributes['class']);
	Assert::same(['height' => 300, 'lang' => 'en'], json_decode($attributes['data-bo-richtext'], true));
	Assert::same(['/js/editor.js'], json_decode($attributes['data-bo-richtext-scripts'], true));
	Assert::same('{}', FormFactory::richTextAttributes($builder->richText('x', 'X'), null, null, false)['data-bo-richtext']);
});

test('počítadlo znaků a text před polem jen u textových polí', function (): void {
	$builder = new FormBuilder();
	$title = $builder->localeText('title', 'Titulek')->counter(70);
	$url = $builder->localeText('url', 'Adresa')->prefix(['cs' => 'pse.cz/', 'en' => 'pse.cz/en/']);
	$path = $builder->text('path', 'Cesta')->prefix('/');
	$description = $builder->textarea('description', 'Popisek')->counter(160);

	Assert::same(70, $title->getCounter());
	Assert::same(160, $description->getCounter());
	Assert::null($url->getCounter());
	Assert::same('pse.cz/en/', $url->getPrefix('en'));
	Assert::null($url->getPrefix('de'));
	Assert::same('/', $path->getPrefix());
	Assert::exception(fn () => $builder->checkbox('x', 'X')->counter(10), LogicException::class);
	Assert::exception(fn () => $builder->textarea('y', 'Y')->prefix('/'), LogicException::class);
	Assert::exception(fn () => $builder->localeRichText('z', 'Z')->counter(10), LogicException::class);
	Assert::true($builder->textarea('html', 'HTML')->code()->isCode());
	Assert::false($description->isCode());
	Assert::exception(fn () => $builder->text('w', 'W')->code(), LogicException::class);
});

test('vnořený seznam: kontejner na řádek, výchozí hodnoty z řádku', function (): void {
	$builder = new FormBuilder();
	$builder->text('name', 'Název');
	$builder->rows('members', 'Členové')
		->rowCheckbox('member', 'Ve skupině')
		->rowInteger('priority', 'Pořadí')
		->rowText('isins', 'ISIN', 'oddělené čárkou')
		->rowsSortableBy('priority');

	$rows = [
		new Backoffice\Resource\Form\FormRow('a1', 'Alfa', ['member' => 1, 'priority' => 3, 'isins' => 'CZ1']),
		new Backoffice\Resource\Form\FormRow('b2', 'Beta'),
	];
	$factory = new FormFactory();
	$form = $factory->create($builder, ['cs'], fn (string $field): array => $field === 'members' ? $rows : []);

	Assert::true($form['members']['a1']['member']->getValue());
	Assert::same(3, $form['members']['a1']['priority']->getValue());
	Assert::same('CZ1', $form['members']['a1']['isins']->getValue());
	Assert::true($form['members']['b2']['priority']->isRequired());
	Assert::null($form['members']['b2']['isins']->getValue());

});

test('vnořený seznam: pole řádku jen u rows(), řazení jen podle čísla', function (): void {
	$builder = new FormBuilder();
	Assert::exception(fn () => $builder->text('a', 'A')->rowText('x', 'X'), LogicException::class);
	Assert::exception(fn () => $builder->rows('b', 'B')->rowText('x', 'X')->rowsSortableBy('x'), LogicException::class);
});

test('datum a čas: input datetime-local, do databáze s vteřinami, prázdné = NULL', function (): void {
	$builder = new FormBuilder();
	$builder->datetime('publishedAt', 'Publikováno');
	$factory = new FormFactory();
	$form = $factory->create($builder, ['cs']);

	Assert::same('datetime-local', $form['publishedAt']->getControl()->type);
	Assert::same(['publishedAt' => '2026-11-25T09:30'], $factory->encodeDefaults($builder, ['publishedAt' => '2026-11-25 09:30:00']));
	Assert::same(['publishedAt' => '2026-11-25 09:30:00'], $factory->normalize($builder, ['publishedAt' => '2026-11-25T09:30']));
	Assert::same(['publishedAt' => null], $factory->normalize($builder, ['publishedAt' => '']));
});

test('víc vazeb: klíče čárkami, bez opakování a prázdných', function (): void {
	Assert::same(['CZ1', 'CZ2'], FormFactory::relationKeys('CZ1, CZ2,,CZ1'));
	Assert::same([], FormFactory::relationKeys(null));
	Assert::same(['a', 'b'], FormFactory::relationKeys(['a', 'b', 'a']));

	$builder = new FormBuilder();
	$builder->text('name', 'Název');
	$builder->relations('isins', 'Emise', (new ReflectionClass(FakeRepository::class))->newInstanceWithoutConstructor());
	$factory = new FormFactory();

	Assert::same(['isins' => 'CZ1,CZ2'], $factory->encodeDefaults($builder, ['isins' => ['CZ1', 'CZ2']]));
	Assert::same(['name' => 'x', 'isins' => ['CZ1']], $factory->normalize($builder, ['name' => 'x', 'isins' => 'CZ1']));
});

test('formátovaný text: nahrávání obrázků jen u formátovaného textu', function (): void {
	$builder = new FormBuilder();
	Assert::same('news-images', $builder->localeRichText('content', 'Obsah')->richTextUploads('/news-images/')->getRichTextUploadDirectory());
	Assert::exception(fn () => $builder->text('a', 'A')->richTextUploads('x'), LogicException::class);
});
