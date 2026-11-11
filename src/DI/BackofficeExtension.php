<?php

declare(strict_types=1);

namespace Backoffice\DI;

use Backoffice\Bridge\LegacyAdminAccessChecker;
use Backoffice\Bridge\LegacyBridge;
use Backoffice\Routing\Routes;
use Backoffice\Security\AccessChecker;
use Backoffice\Security\UserAccessChecker;
use Backoffice\Shell\Navigation;
use Backoffice\Shell\ShellConfig;
use Backoffice\Shell\ShellContext;
use Backoffice\UI\AssetPresenter;
use Nette\Application\Application;
use Nette\Application\IPresenterFactory;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Nette\Routing\Router;
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
 *     environment: dev # dev | test | stage | null (produkce = bez štítku)
 *     webUrl: https://www.pse.cz
 *     signInLink: ':Admin:Login:default'
 *     signOutLink: 'logout!'
 *     routePrefix: administrace-pse # routa pro assety balíku se přidá na začátek routeru sama
 *     navigation: %adminMenu% # stejný formát jako admin: menu: z liquiddesign/admin
 *     bridge:
 *         enabled: true # staré presentery liquiddesign/admin v novém shellu
 *         head: %appDir%/Admin/templates/legacyHead.latte
 *         foot: %appDir%/Admin/templates/legacyFoot.latte
 * ```
 *
 * Bez routePrefix si projekt přidá routy sám: Backoffice\Routing\Routes::add($router, 'administrace').
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
			'routePrefix' => Expect::string()->nullable(),
			'navigation' => Expect::array()->default([]),
			'bridge' => Expect::structure([
				'enabled' => Expect::bool(false),
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

		if (!$config->bridge->enabled) {
			return;
		}

		$builder->addDefinition($this->prefix('legacyBridge'))
			->setFactory(LegacyBridge::class);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		/** @var \stdClass $config */
		$config = $this->getConfig();

		$this->registerAccessChecker($config->bridge->enabled);

		if ($config->bridge->enabled) {
			$application = $builder->getDefinitionByType(Application::class);

			if ($application instanceof ServiceDefinition) {
				$application->addSetup('$onPresenter[]', [[$this->prefix('@legacyBridge'), 'onPresenter']]);
			}
		}

		if ($config->routePrefix !== null) {
			$this->prependAssetRoute($config->routePrefix);
		}

		$presenterFactory = $builder->getDefinitionByType(IPresenterFactory::class);

		if (!($presenterFactory instanceof ServiceDefinition)) {
			return;
		}

		$presenterFactory->addSetup('setMapping', [['Backoffice' => 'Backoffice\UI\*Presenter']]);
	}

	/**
	 * Oprávnění: vlastní služba projektu má přednost; s mostem se bere starý admin, jinak Nette User.
	 */
	private function registerAccessChecker(bool $bridge): void
	{
		$builder = $this->getContainerBuilder();

		if ($builder->findByType(AccessChecker::class) !== []) {
			return;
		}

		// Starý admin registruje Admin\Administrator jako admin.administrator bez autowiringu,
		// takže se službě předává jménem.
		/** @var class-string $legacyType */
		$legacyType = 'Admin\Administrator';
		$administrators = $bridge ? \array_keys($builder->findByType($legacyType)) : [];
		$definition = $builder->addDefinition($this->prefix('accessChecker'));

		if ($administrators === []) {
			$definition->setFactory(UserAccessChecker::class);

			return;
		}

		$definition->setFactory(LegacyAdminAccessChecker::class, ['@' . $administrators[0]]);
	}

	private function prependAssetRoute(string $prefix): void
	{
		$router = $this->getContainerBuilder()->getDefinitionByType(Router::class);

		if (!$router instanceof ServiceDefinition || !\is_a((string) $router->getType(), RouteList::class, true)) {
			throw new InvalidConfigurationException(
				'backoffice.routePrefix potřebuje router typu ' . RouteList::class . '; jinak routu přidejte sami přes ' . Routes::class . '::add().',
			);
		}

		$router->addSetup('prepend', [new Statement(Route::class, [Routes::mask($prefix), 'Backoffice:Asset:default'])]);
	}
}
