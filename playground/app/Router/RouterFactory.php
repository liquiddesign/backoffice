<?php

declare(strict_types=1);

namespace Playground\Router;

use Backoffice\Routing\Routes;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
	public static function createRouter(): RouteList
	{
		$router = new RouteList();
		Routes::add($router, 'admin');
		$router->addRoute('admin/<presenter>/<action>[/<id>]', 'Dashboard:default');
		$router->addRoute('', 'Dashboard:default');

		return $router;
	}
}
