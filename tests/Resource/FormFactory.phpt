<?php

declare(strict_types=1);

use Backoffice\Resource\Form\FormBuilder;
use Backoffice\Resource\Form\FormFactory;
use Nette\Forms\Container;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

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
});
