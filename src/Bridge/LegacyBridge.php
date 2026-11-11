<?php

declare(strict_types=1);

namespace Backoffice\Bridge;

use Backoffice\Resource\ResourceRegistry;
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

	public function __construct(private readonly ShellContext $shell, private readonly ResourceRegistry $resources)
	{
	}

	public function onPresenter(Application $application, IPresenter $presenter): void
	{
		unset($application);

		if (!$presenter instanceof Presenter || !\is_a($presenter, self::LEGACY_PRESENTER)) {
			return;
		}

		$presenter->onStartup[] = function (Presenter $presenter): void {
			$this->redirectToResource($presenter);
			$this->apply($presenter);
		};
	}

	/**
	 * Starý presenter, který už nahradila přepnutá agenda, přesměruje na ni (záložky dál fungují).
	 * Mapují se akce default → seznam, new → nový, detail → úprava (id ze starého parametru).
	 */
	private function redirectToResource(Presenter $presenter): void
	{
		$resource = $this->resources->findSwitchedReplacing((string) $presenter->getName());

		if ($resource === null || $presenter->getSignal() !== null) {
			return;
		}

		$meta = $resource->getMeta();
		$args = ['resource' => $meta->getSlug()];
		$action = match ($presenter->getAction()) {
			'new' => 'new',
			'detail' => 'edit',
			default => 'default',
		};

		if ($action === 'edit') {
			$id = $meta->getLegacyIdParameter() !== null ? $presenter->getParameter($meta->getLegacyIdParameter()) : null;

			// Starý admin mapuje parametr na entitu ještě před startupem (renderDetail(Holiday $holiday)).
			if ($id instanceof \StORM\Entity) {
				$id = $id->getPK();
			}

			if (!\is_scalar($id) || (string) $id === '') {
				$action = 'default';
			} else {
				$args['id'] = (string) $id;
			}
		}

		$presenter->redirectPermanent(':Backoffice:Resource:' . $action, $args);
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
