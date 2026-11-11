<?php

declare(strict_types=1);

use Backoffice\Resource\Meta;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('oprávnění podle starého presenteru', function (): void {
	$meta = (new Meta())->slug('holiday')->replaces('Stock:Admin:Holiday', 'holiday');

	Assert::same(':Stock:Admin:Holiday', $meta->getReplaces());
	Assert::same(':Stock:Admin:Holiday:default', $meta->permissionLink('default'));
	Assert::same(':Stock:Admin:Holiday:detail', $meta->permissionLink('edit'));
	Assert::same(':Stock:Admin:Holiday:new', $meta->permissionLink('new'));
	Assert::same('holiday', $meta->getLegacyIdParameter());
});

test('bez starého presenteru vlastní oprávnění', function (): void {
	$meta = (new Meta())->slug('api-consumers');

	Assert::same(':Backoffice:Resource:default', $meta->permissionLink('default'));
});

test('neplatný slug', function (): void {
	Assert::exception(fn () => (new Meta())->slug('Neobchodní dny'), InvalidArgumentException::class);
});
