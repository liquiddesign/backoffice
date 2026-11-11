<?php

declare(strict_types=1);

namespace Playground\Presenters;

use Backoffice\Shell\ShellContext;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;
use Nette\Security\AuthenticationException;

final class SignPresenter extends Presenter
{
	#[Inject]
	public ShellContext $shell;

	public string $backlink = '';

	public function actionIn(string $backlink = ''): void
	{
		$this->backlink = $backlink;

		if ($this->getUser()->isLoggedIn()) {
			$this->redirect('Dashboard:default');
		}
	}

	public function renderIn(): void
	{
		$this->template->title = $this->shell->config->title;
		$this->template->cssHref = $this->link(':Backoffice:Asset:default', ['file' => 'backoffice.css']);
	}

	protected function createComponentSignInForm(): Form
	{
		$form = new Form();
		$form->addText('login', 'Přihlašovací jméno')->setRequired('Vyplňte přihlašovací jméno.')->setHtmlAttribute('autocomplete', 'username');
		$form->addPassword('password', 'Heslo')->setRequired('Vyplňte heslo.')->setHtmlAttribute('autocomplete', 'current-password');
		$form->addSubmit('send', 'Přihlásit');

		$form->onSuccess[] = function (Form $form, \stdClass $values): void {
			try {
				$this->getUser()->login($values->login, $values->password);
			} catch (AuthenticationException) {
				$form->addError('Špatné jméno nebo heslo.');

				return;
			}

			if ($this->backlink !== '') {
				$this->restoreRequest($this->backlink);
			}

			$this->redirect('Dashboard:default');
		};

		return $form;
	}
}
