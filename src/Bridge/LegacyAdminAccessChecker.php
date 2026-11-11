<?php

declare(strict_types=1);

namespace Backoffice\Bridge;

use Admin\Administrator;
use Backoffice\Security\AccessChecker;

/**
 * Oprávnění ze starého adminu: tytéž role, tatáž matice admin_permission, tentýž přihlášený účet.
 *
 * ```neon
 * services:
 *     - Backoffice\Bridge\LegacyAdminAccessChecker
 * ```
 */
final class LegacyAdminAccessChecker implements AccessChecker
{
	public function __construct(private readonly Administrator $admin)
	{
	}

	public function isLoggedIn(): bool
	{
		return $this->admin->isLoggedIn();
	}

	public function isAllowed(string $link): bool
	{
		return $this->admin->isLoggedIn() && $this->admin->isAllowed(':' . \ltrim($link, ':'));
	}

	public function getDisplayName(): ?string
	{
		$identity = $this->admin->getIdentity();

		if ($identity === null) {
			return null;
		}

		$fullName = $identity->fullName ?? null;

		if (\is_string($fullName) && $fullName !== '') {
			return $fullName;
		}

		$login = $identity->login ?? null;

		return \is_string($login) && $login !== '' ? $login : null;
	}
}
