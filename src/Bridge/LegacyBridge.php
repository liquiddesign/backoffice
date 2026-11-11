<?php

declare(strict_types=1);

namespace Backoffice\Bridge;

use Backoffice\Shell\ShellContext;
use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\UI\Presenter;

/**
 * Most: presentery liquiddesign/admin (Admin\BackendPresenter) se vykreslí v novém shellu.
 *
 * Zapíná se v konfiguraci (`backoffice: bridge: enabled: true`) a projekt kvůli tomu nemusí sahat
 * na jediný presenter — extension se pověsí na Application::$onPresenter a každému starému
 * presenteru přepne layout a tělo stránky. Obsah agendy (AdminGrid, AdminForm) zůstává, shell
 * převezme sloty starého layoutu: headerLabel → nadpis, headerTree → drobečky,
 * displayButtons → tlačítka v hlavičce, tabs + displayControls → karta s obsahem.
 */
final class LegacyBridge
{
	/** Tělo stránky starého presenteru (záložky + displayControls) v novém vzhledu. */
	public const CONTENT_TEMPLATE = __DIR__ . '/templates/content.latte';

	private const LEGACY_PRESENTER = 'Admin\BackendPresenter';

	public function __construct(private readonly ShellContext $shell)
	{
	}

	public function onPresenter(Application $application, IPresenter $presenter): void
	{
		unset($application);

		if (!$presenter instanceof Presenter || !\is_a($presenter, self::LEGACY_PRESENTER)) {
			return;
		}

		$presenter->onStartup[] = function (Presenter $presenter): void {
			$this->apply($presenter);
		};
	}

	private function apply(Presenter $presenter): void
	{
		/** @var \Admin\BackendPresenter $legacy */
		$legacy = $presenter;
		$legacy->layoutTemplate = ShellContext::LAYOUT;
		$legacy->contentTemplate = self::CONTENT_TEMPLATE;

		/** @var \Nette\Bridges\ApplicationLatte\Template $template */
		$template = $presenter->getTemplate();
		$template->legacyHead = $this->shell->config->legacyHead;
		$template->legacyFoot = $this->shell->config->legacyFoot;

		// Až při vykreslení layoutu: headerLabel a spol. plní render*() metody, které běží později.
		$template->shellFactory = function () use ($presenter, $template) {
			$vars = $template->getParameters();

			return $this->shell->createView(
				$presenter,
				isset($vars['headerLabel']) && \is_string($vars['headerLabel']) ? $vars['headerLabel'] : '',
				isset($vars['headerTree']) && \is_array($vars['headerTree']) ? $vars['headerTree'] : [],
				isset($vars['displayButtons']) && \is_array($vars['displayButtons']) ? $vars['displayButtons'] : [],
				true,
			);
		};
	}
}
