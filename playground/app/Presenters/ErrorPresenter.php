<?php

declare(strict_types=1);

namespace Playground\Presenters;

use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\TextResponse;
use Tracy\ILogger;

final class ErrorPresenter implements IPresenter
{
	public function __construct(private readonly ILogger $logger)
	{
	}

	public function run(Request $request): Response
	{
		$exception = $request->getParameter('exception');

		if ($exception instanceof BadRequestException) {
			return new TextResponse('Stránka nenalezena (' . $exception->getHttpCode() . ').');
		}

		if ($exception instanceof \Throwable) {
			$this->logger->log($exception, ILogger::EXCEPTION);
		}

		return new TextResponse('Chyba serveru.');
	}
}
