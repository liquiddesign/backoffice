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
			if ($method->isPublic() || !preg_match('~^(render|action|handle)[A-Z]~', $method->getName())) {
				continue;
			}

			Assert::fail("$class::{$method->getName()}() musí být veřejná, nebo se jmenovat jinak.");
		}
	}

	Assert::true(true);
});
