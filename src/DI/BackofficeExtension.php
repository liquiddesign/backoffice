<?php

declare(strict_types=1);

namespace Backoffice\DI;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Vstupní bod balíku. Zatím jen schéma konfigurace — služby přibývají s jednotlivými částmi
 * (shell, agendy, asistent…), viz docs/architektura.md.
 */
final class BackofficeExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'url' => Expect::string('administrace'),
			'mutations' => Expect::listOf('string')->default(['cs']),
		]);
	}
}
