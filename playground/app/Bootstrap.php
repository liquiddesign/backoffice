<?php

declare(strict_types=1);

namespace Playground;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
	public static function boot(): Configurator
	{
		$root = \dirname(__DIR__);
		$configurator = new Configurator();

		$configurator->setDebugMode(\getenv('DEBUG_MODE') !== '0');
		$configurator->enableTracy($root . '/log');
		$configurator->setTempDirectory($root . '/temp');
		$configurator->addStaticParameters(['rootDir' => $root, 'wwwDir' => $root . '/www']);

		$configurator->addConfig($root . '/config/common.neon');

		if (\is_file($root . '/config/local.neon')) {
			$configurator->addConfig($root . '/config/local.neon');
		}

		return $configurator;
	}
}
