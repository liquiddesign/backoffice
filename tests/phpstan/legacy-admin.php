<?php

// Jen pro PHPStan (scanFiles): tvar tříd z liquiddesign/admin, se kterými pracuje most.
// Balík na liquiddesign/admin nezávisí, při běhu se tenhle soubor nenačítá.

namespace Admin;

class Administrator extends \Nette\Security\User
{
}

abstract class BackendPresenter extends \Nette\Application\UI\Presenter
{
	public string $layoutTemplate;

	public string $contentTemplate;
}

namespace Admin\DB;

class Administrator implements \Nette\Security\IIdentity
{
	public ?string $fullName = null;

	public ?string $login = null;

	public function getId(): mixed
	{
		return null;
	}

	/**
	 * @return array<string>
	 */
	public function getRoles(): array
	{
		return [];
	}
}
