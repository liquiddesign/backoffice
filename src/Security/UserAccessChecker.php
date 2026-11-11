<?php

declare(strict_types=1);

namespace Backoffice\Security;

use Nette\Security\User;

final class UserAccessChecker implements AccessChecker
{
	public function __construct(private readonly User $user)
	{
	}

	public function isLoggedIn(): bool
	{
		return $this->user->isLoggedIn();
	}

	public function isAllowed(string $link): bool
	{
		unset($link);

		return $this->user->isLoggedIn();
	}

	public function getDisplayName(): ?string
	{
		$identity = $this->user->getIdentity();

		if ($identity === null) {
			return null;
		}

		$data = $identity->getData();

		foreach (['fullName', 'name', 'login'] as $key) {
			if (isset($data[$key]) && \is_string($data[$key]) && $data[$key] !== '') {
				return $data[$key];
			}
		}

		return (string) $identity->getId();
	}
}
