<?php

declare(strict_types=1);

namespace Backoffice\Security;

/**
 * Log změn v administraci. S mostem zapisuje do admin_changelog ve stejném tvaru jako starý admin
 * (agenda, typ new/detail/update/delete, id záznamu, login), takže „Log změn“ ukazuje obojí.
 */
interface ChangeLogger
{
	public const TYPE_NEW = 'new';
	public const TYPE_DETAIL = 'detail';
	public const TYPE_DELETE = 'delete';

	public function log(string $agenda, string $type, ?string $objectId): void;
}
