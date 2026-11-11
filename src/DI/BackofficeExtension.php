<?php

declare(strict_types=1);

namespace Backoffice\DI;

use Backoffice\Security\AccessChecker;
use Backoffice\Security\UserAccessChecker;
use Backoffice\Shell\Navigation;
use Backoffice\Shell\ShellConfig;
use Backoffice\Shell\ShellContext;
use Backoffice\UI\AssetPresenter;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Vstupní bod balíku.
 *
 * ```neon
 * extensions:
 *     backoffice: Backoffice\DI\BackofficeExtension
 *
 * backoffice:
 *     title: Administrace
 *     subtitle: Burza cenných papírů Praha
 *     environment: dev            # dev | test | stage | null (produkce = bez štítku)
 *     webUrl: https://www.pse.cz
 *     signInLink: ':Admin:Login:default'
 *     signOutLink: 'logout!'
 *     navigation: %adminMenu%     # stejný formát jako admin: menu: z liquiddesign/admin
 *     bridge:
 *         head: %appDir%/Admin/templates/legacyHead.latte
 *         foot: %appDir%/Admin/templates/legacyFoot.latte
 * ```
 *
 * Projekt si dál přidá routy: Backoffice\Routing\Routes::add($router, 'administrace').
 */
final class BackofficeExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'title' => Expect::string('Administrace'),
			'subtitle' => Expect::string()->nullable(),
			'environment' => Expect::anyOf('dev', 'test', 'stage', null)->default(null),
			'webUrl' => Expect::string()->nullable(),
			'signInLink' => Expect::string(':Admin:Login:default'),
			'signOutLink' => Expect::string()->nullable(),
			'dashboardLink' => Expect::string()->nullable(),
			'navigation' => Expect::array()->default([]),
			'bridge' => Expect::structure([
				'head' => Expect::string()->nullable(),
				'foot' => Expect::string()->nullable(),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var \stdClass $config */
		$config = $this->getConfig();

		$builder->addDefinition($this->prefix('config'))
			->setFactory(ShellConfig::class, [
				'title' => $config->title,
				'subtitle' => $config->subtitle,
				'environment' => $config->environment,
				'webUrl' => $config->webUrl,
				'signInLink' => $config->signInLink,
				'signOutLink' => $config->signOutLink,
				'dashboardLink' => $config->dashboardLink,
				'legacyHead' => $config->bridge->head,
				'legacyFoot' => $config->bridge->foot,
			]);

		$builder->addDefinition($this->prefix('navigation'))
			->setFactory(Navigation::class, ['menu' => $config->navigation]);

		$builder->addDefinition($this->prefix('shell'))
			->setFactory(ShellContext::class);

		$builder->addDefinition($this->prefix('assetPresenter'))
			->setFactory(AssetPresenter::class);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		// Výchozí oprávnění jen tehdy, když si projekt nedodal vlastní (PSE: most na Admin\Administrator).
		if ($builder->findByType(AccessChecker::class) === []) {
			$builder->addDefinition($this->prefix('accessChecker'))
				->setFactory(UserAccessChecker::class);
		}

		$presenterFactory = $builder->getDefinitionByType(IPresenterFactory::class);

		if ($presenterFactory instanceof ServiceDefinition) {
			$presenterFactory->addSetup('setMapping', [['Backoffice' => 'Backoffice\UI\*Presenter']]);
		}
	}
}
