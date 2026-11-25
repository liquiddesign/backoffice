<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

/**
 * Řádek vnořeného seznamu ve formuláři (FormBuilder::rows()): co je v řádku vidět a jaké hodnoty
 * mají jeho pole. Řádky dodává agenda z ResourceDefinition::formRows(), uloží je afterSave().
 */
final class FormRow
{
	/**
	 * @param string $key klíč řádku (ID podřízeného záznamu) — v hodnotách formuláře $values[pole][klíč]
	 * @param array<string, mixed> $values pole řádku → výchozí hodnota
	 * @param array{string, mixed}|null $image náhled před popiskem: [adresář v userfiles, jméno souboru]
	 * @param string|null $link cíl odkazu z popisku (':Backoffice:Resource:edit'), argumenty v $linkArgs
	 * @param array<string, mixed> $linkArgs
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly array $values = [],
		public readonly ?string $note = null,
		public readonly ?array $image = null,
		public readonly ?string $link = null,
		public readonly array $linkArgs = [],
	) {
	}
}
