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
