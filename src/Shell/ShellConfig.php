<?php

declare(strict_types=1);

namespace Backoffice\Shell;

/**
 * Nastavení shellu z konfigurace `backoffice:` (viz BackofficeExtension).
 */
final class ShellConfig
{
	public function __construct(
		public readonly string $title,
		public readonly ?string $subtitle = null,
		public readonly ?string $environment = null,
		public readonly ?string $webUrl = null,
		public readonly string $signInLink = ':Admin:Login:default',
		public readonly ?string $signOutLink = null,
		public readonly ?string $dashboardLink = null,
		public readonly ?string $legacyHead = null,
		public readonly ?string $legacyFoot = null,
	) {
	}

	public function environmentLabel(): ?string
	{
		return match ($this->environment) {
			'dev' => 'DEV',
			'test' => 'TEST',
			'stage' => 'STAGE',
			default => null,
		};
	}
}
