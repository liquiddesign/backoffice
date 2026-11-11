<?php

declare(strict_types=1);

namespace Backoffice\Bridge;

final class LegacyBridge
{
	/** Tělo stránky starého presenteru (záložky + displayControls) v novém vzhledu. */
	public const CONTENT_TEMPLATE = __DIR__ . '/templates/content.latte';
}
