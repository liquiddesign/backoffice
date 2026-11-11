<?php

declare(strict_types=1);

namespace Backoffice\Security;

final class NullChangeLogger implements ChangeLogger
{
	public function log(string $agenda, string $type, ?string $objectId): void
	{
		unset($agenda, $type, $objectId);
	}
}
