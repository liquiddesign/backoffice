<?php

declare(strict_types=1);

namespace Backoffice\Bridge;

use Backoffice\Shell\ShellContext;

/**
 * Most: starý presenter z liquiddesign/admin (Admin\BackendPresenter) se vykreslí v novém shellu.
 *
 * Stačí trait přidat do společného předka adminových presenterů v projektu. Obsah agendy
 * (AdminGrid, AdminForm) zůstane, jak je; shell převezme sloty, které staré presentery plní:
 * headerLabel → nadpis, headerTree → drobečky, displayButtons → tlačítka v hlavičce,
 * tabs + displayControls → tělo stránky.
 *
 * Nastavuje se přes inject metodu a $onStartup, ne přepsáním startup()/beforeRender(), aby trait
 * nekolidoval s metodami, které presentery v projektu běžně mají.
 *
 * @property string $layoutTemplate
 * @property string $contentTemplate
 */
trait LegacyShell
{
	public function injectLegacyShell(ShellContext $backofficeShell): void
	{
		$this->onStartup[] = function () use ($backofficeShell): void {
			$this->layoutTemplate = ShellContext::LAYOUT;
			$this->contentTemplate = LegacyBridge::CONTENT_TEMPLATE;

			$template = $this->getTemplate();
			$template->legacyHead = $backofficeShell->config->legacyHead;
			$template->legacyFoot = $backofficeShell->config->legacyFoot;

			// Až při vykreslení layoutu: headerLabel a spol. plní render*() metody, které běží později.
			$template->shellFactory = function () use ($backofficeShell, $template) {
				/** @var \stdClass $vars */
				$vars = (object) $template->getParameters();

				return $backofficeShell->createView(
					$this,
					isset($vars->headerLabel) && \is_string($vars->headerLabel) ? $vars->headerLabel : '',
					isset($vars->headerTree) && \is_array($vars->headerTree) ? $vars->headerTree : [],
					isset($vars->displayButtons) && \is_array($vars->displayButtons) ? $vars->displayButtons : [],
					true,
				);
			};
		};
	}
}
