<?php

declare(strict_types=1);

namespace Backoffice\Security;

/**
 * Smí přihlášený uživatel na danou agendu? Odkaz je ve tvaru Nette linku (':Web:Admin:Menu:default').
 *
 * Výchozí UserAccessChecker pustí každého přihlášeného. Projekt se starým adminem dodá
 * Backoffice\Bridge\LegacyAdminAccessChecker, který se ptá Admin\Administrator::isAllowed —
 * tedy stejné role a oprávnění jako dnes.
 */
interface AccessChecker
{
	public function isLoggedIn(): bool;

	public function isAllowed(string $link): bool;

	/**
	 * Jméno do hlavičky (celé jméno, jinak login).
	 */
	public function getDisplayName(): ?string;
}
