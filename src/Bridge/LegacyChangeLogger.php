<?php

declare(strict_types=1);

namespace Backoffice\Bridge;

use Admin\Administrator;
use Admin\DB\ChangelogRepository;
use Backoffice\Security\ChangeLogger;

/**
 * Zápis do admin_changelog starého adminu — stejná pole, jaká plní AdminFormFactory a AdminGridFactory.
 */
final class LegacyChangeLogger implements ChangeLogger
{
	public function __construct(
		private readonly Administrator $admin,
		private readonly ChangelogRepository $changelog,
	) {
	}

	public function log(string $agenda, string $type, ?string $objectId): void
	{
		$identity = $this->admin->getIdentity();

		if (!$identity instanceof \Admin\DB\Administrator || $identity->getAccount() === null) {
			return;
		}

		$this->changelog->createOne([
			'user' => $identity->getAccount()->login,
			'entity' => $agenda,
			'objectId' => $objectId,
			'type' => $type,
		]);
	}
}
