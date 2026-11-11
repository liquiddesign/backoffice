<?php

declare(strict_types=1);

namespace Backoffice\Resource;

use Nette\Utils\Strings;

/**
 * Všechny agendy aplikace podle slugu. Naplní ji extension ze služeb typu ResourceDefinition.
 */
final class ResourceRegistry
{
	/** @var array<string, \Backoffice\Resource\ResourceDefinition>|null */
	private ?array $bySlug = null;

	/**
	 * @param list<\Backoffice\Resource\ResourceDefinition> $resources
	 * @param list<string> $switched slugy agend, které už nahradily svůj starý presenter
	 */
	public function __construct(private readonly array $resources, private readonly array $switched = [])
	{
	}

	public function get(string $slug): ?ResourceDefinition
	{
		return $this->all()[$slug] ?? null;
	}

	/**
	 * @return array<string, \Backoffice\Resource\ResourceDefinition>
	 */
	public function all(): array
	{
		if ($this->bySlug === null) {
			$this->bySlug = [];

			foreach ($this->resources as $resource) {
				$slug = $resource->getMeta()->getSlug();

				if (isset($this->bySlug[$slug])) {
					throw new \LogicException("Dvě agendy se stejným slugem '$slug'.");
				}

				$this->bySlug[$slug] = $resource;
			}
		}

		return $this->bySlug;
	}

	public function isSwitched(string $slug): bool
	{
		return \in_array($slug, $this->switched, true);
	}

	/**
	 * Přepnutá agenda, která nahrazuje daný starý presenter (':Stock:Admin:Holiday'), jinak null.
	 */
	public function findSwitchedReplacing(string $presenter): ?ResourceDefinition
	{
		$presenter = ':' . Strings::trim($presenter, ':');

		foreach ($this->all() as $slug => $resource) {
			if ($this->isSwitched($slug) && $resource->getMeta()->getReplaces() === $presenter) {
				return $resource;
			}
		}

		return null;
	}
}
