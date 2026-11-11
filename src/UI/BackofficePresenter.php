<?php

declare(strict_types=1);

namespace Backoffice\UI;

use Backoffice\Shell\Icons;
use Backoffice\Shell\ShellContext;
use Nette\Application\UI\Presenter;
use Nette\DI\Attributes\Inject;
use Nette\Utils\Html;

/**
 * Předek nativních stránek administrace. Hlídá přihlášení a oprávnění, vykresluje shell.
 *
 * V render*() stačí nastavit $this->title, případně $this->breadcrumbs a $this->buttons.
 */
abstract class BackofficePresenter extends Presenter
{
	#[Inject]
	public ShellContext $shell;

	protected string $title = '';

	/** @var list<array<mixed>|string> položky [popisek, odkaz?, parametry?] */
	protected array $breadcrumbs = [];

	/** @var list<\Nette\Utils\Html|string> */
	protected array $buttons = [];

	public function checkRequirements(mixed $element): void
	{
		parent::checkRequirements($element);

		if (!$this->shell->access->isLoggedIn()) {
			$this->redirect($this->shell->config->signInLink, ['backlink' => $this->storeRequest()]);
		}

		if ($this->shell->access->isAllowed(':' . $this->getName() . ':' . $this->getAction())) {
			return;
		}

		$this->error('Na tuto stránku nemáte oprávnění.', 403);
	}

	/**
	 * @return array<string>
	 */
	public function formatLayoutTemplateFiles(): array
	{
		return [ShellContext::LAYOUT];
	}

	public function handleLogout(): void
	{
		$this->getUser()->logout(true);
		$this->redirect($this->shell->config->signInLink);
	}

	protected function beforeRender(): void
	{
		parent::beforeRender();

		$this->template->legacyHead = null;
		$this->template->legacyFoot = null;
		$this->template->shellFactory = fn () => $this->shell->createView($this, $this->title, $this->breadcrumbs, $this->buttons);
	}

	/**
	 * @param array<mixed> $args
	 */
	protected function button(string $label, string $link, array $args = [], string $variant = 'primary', ?string $icon = null): Html
	{
		$a = Html::el('a', ['class' => 'bo-btn bo-btn-' . $variant, 'href' => $this->link($link, $args)]);

		if ($icon !== null) {
			$a->addHtml(Icons::get($icon, 16));
		}

		return $a->addText($label);
	}
}
