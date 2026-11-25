<?php

declare(strict_types=1);

namespace Backoffice\Resource;

/**
 * Úrovně upozornění nad formulářem záznamu (ResourceDefinition::formNotices()).
 */
final class FormNotice
{
	/** Něco, co by redaktor měl opravit nebo vědět (žlutě). */
	public const WARN = 'warn';

	/** Doplňující informace (modře). */
	public const INFO = 'info';
}
