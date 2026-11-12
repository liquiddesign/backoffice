<?php

declare(strict_types=1);

namespace Backoffice\Resource;

/**
 * Místo, kde se záznam agendy používá (stránka, textový box…). Vrací ho ResourceDefinition::usages();
 * záznam, který se někde používá, se z agendy rovnou nesmaže.
 */
final class UsageLocation
{
	/**
	 * @param string|null $link Nette cíl odkazu (':Web:Admin:PageEditor:detail'), otevře se v novém okně
	 * @param array<int|string, mixed> $linkArgs
	 * @param string|null $note doplněk za názvem (adresa, „na webu, v konceptu“)
	 * @param list<\Backoffice\Resource\UsageLocation> $children vnořená místa (stránky, kde je textový box)
	 */
	public function __construct(
		public readonly string $label,
		public readonly ?string $link = null,
		public readonly array $linkArgs = [],
		public readonly ?string $note = null,
		public readonly array $children = [],
	) {
	}
}
