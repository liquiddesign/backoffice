<?php

declare(strict_types=1);

namespace Backoffice\Routing;

use Nette\Application\Routers\RouteList;

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
		$prefix = \trim($prefix, '/');
		$router->addRoute($prefix . '/_backoffice/<file .+>', 'Backoffice:Asset:default');
	}
}
