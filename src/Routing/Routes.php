<?php

declare(strict_types=1);

namespace Backoffice\Routing;

use Nette\Application\Routers\RouteList;
use Nette\Utils\Strings;

/**
 * Routy balíku. Volat dřív než obecné routy projektu:
 *
 * ```php
 * Backoffice\Routing\Routes::add($router, 'administrace-pse');
 * ```
 */
final class Routes
{
	public static function add(RouteList $router, string $prefix): void
	{
		$router->addRoute(self::mask($prefix), 'Backoffice:Asset:default');
	}

	public static function mask(string $prefix): string
	{
		return Strings::trim($prefix, '/') . '/_backoffice/<file .+>';
	}
}
