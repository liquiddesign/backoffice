<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

/**
 * Výchozí „editor“, když aplikace žádný nedodá: formátovaný text zůstane textarea s HTML.
 */
final class PlainRichTextEditor implements RichTextEditor
{
	/**
	 * @return list<string>
	 */
	public function scripts(): array
	{
		return [];
	}

	/**
	 * @return array<string, mixed>
	 * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
	 */
	public function config(Field $field, ?string $mutation): array
	{
		return [];
	}
}
