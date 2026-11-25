<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Nette bere render*/action*/handle* jako pohledy, akce a signály a chce je veřejné — jinak spadne
// kompilace DI kontejneru celé aplikace (stalo se: ResourcePresenter::renderForm byla private).
test('presentery balíku nemají neveřejné metody s vyhrazenými prefixy', function (): void {
	$presenters = [
		Backoffice\UI\BackofficePresenter::class,
		Backoffice\UI\ResourcePresenter::class,
	];

	foreach ($presenters as $class) {
		foreach ((new ReflectionClass($class))->getMethods() as $method) {
			if ($method->getDeclaringClass()->getName() !== $class || $method->isPublic() || !preg_match('~^(render|action|handle)[A-Z]~', $method->getName())) {
				continue;
			}

			Assert::fail("$class::{$method->getName()}() musí být veřejná, nebo se jmenovat jinak.");
		}
	}

	Assert::true(true);
});

// Název záznamu ve zprávách („Skryto: ano — …“) z formátovaného textu: bez HTML, slova oddělená.
test('plainTitle odstraní HTML a zkrátí text', function (): void {
	Assert::same('Nadpis Text s odkazem', Backoffice\UI\ResourcePresenter::plainTitle("<h1><span style=\"color: #fff;\">Nadpis</span></h1>\n<p>Text&nbsp;s <a href=\"/x\">odkazem</a></p>"));
	Assert::same('Kontakty', Backoffice\UI\ResourcePresenter::plainTitle('  Kontakty '));
	Assert::true(mb_strlen(Backoffice\UI\ResourcePresenter::plainTitle(str_repeat('slovo ', 40))) <= 80);
});
