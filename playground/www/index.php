<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Playground\Bootstrap::boot()
	->createContainer()
	->getByType(Nette\Application\Application::class)
	->run();
