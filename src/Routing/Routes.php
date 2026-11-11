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
		$router->addRoute(self::resourceMask($prefix), 'Backoffice:Resource:default');
	}

	/**
	 * Deklarativní agendy: <prefix>/r/<agenda>[/<akce>][/<id>].
	 */
	public static function resourceMask(string $prefix): string
	{
		return Strings::trim($prefix, '/') . '/r/<resource [a-z][a-z0-9-]*>[/<action edit|new>][/<id>]';
	}

	public static function mask(string $prefix): string
	{
		// Soubor jde v query (?file=backoffice.css): projekty mají v .htaccess pravidlo, které
		// adresy s příponou .css/.js obslouží rovnou z disku a do index.php je nepustí.
		return Strings::trim($prefix, '/') . '/_backoffice';
	}
}
