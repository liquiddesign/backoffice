<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Shell\ShellContext;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\CallbackResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

/**
 * Vydává zkompilované assety balíku (dist/) přímo z vendoru. Projekt tak nic nekopíruje do své
 * veřejné složky a nezáleží na tom, jestli má docroot v kořeni (PSE) nebo ve www/.
 * Odkazy nesou verzi (?v=mtime), takže se smí cachovat napořád.
 */
final class AssetPresenter implements IPresenter
{
	private const TYPES = [
		'css' => 'text/css; charset=utf-8',
		'js' => 'text/javascript; charset=utf-8',
		'svg' => 'image/svg+xml',
		'woff2' => 'font/woff2',
		'png' => 'image/png',
	];

	public function run(Request $request): Response
	{
		$file = $request->getParameter('file');

		if (!\is_string($file) || !\preg_match('~^[a-z0-9_-]+(?:/[a-z0-9_-]+)*\.[a-z0-9]+$~i', $file)) {
			throw new BadRequestException('Asset not found.');
		}

		$root = \realpath(ShellContext::ASSETS_DIR);
		$path = \realpath(ShellContext::ASSETS_DIR . '/' . $file);
		$type = self::TYPES[\strtolower(\pathinfo($file, \PATHINFO_EXTENSION))] ?? null;

		if ($root === false || $path === false || !\str_starts_with($path, $root . \DIRECTORY_SEPARATOR) || !\is_file($path) || $type === null) {
			throw new BadRequestException('Asset not found.');
		}

		return new CallbackResponse(static function (IRequest $httpRequest, IResponse $httpResponse) use ($path, $type): void {
			unset($httpRequest);
			$httpResponse->setContentType($type);
			$httpResponse->setHeader('Cache-Control', 'public, max-age=31536000, immutable');
			$httpResponse->setHeader('Content-Length', (string) \filesize($path));
			\readfile($path);
		});
	}
}
