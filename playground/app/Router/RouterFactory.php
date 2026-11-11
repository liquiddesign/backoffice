<?php

declare(strict_types=1);

namespace Playground\Router;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
	public static function createRouter(): RouteList
	{
		$router = new RouteList();
		$router->addRoute('admin/<presenter>/<action>[/<id>]', 'Dashboard:default');
		$router->addRoute('', 'Dashboard:default');

		return $router;
	}
}
