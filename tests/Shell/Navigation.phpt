<?php

declare(strict_types=1);

use Backoffice\Resource\Form\FormBuilder;
use Backoffice\Resource\List\ListBuilder;
use Backoffice\Resource\Meta;
use Backoffice\Resource\ResourceDefinition;
use Backoffice\Resource\ResourceRegistry;
use Backoffice\Security\AccessChecker;
use Backoffice\Shell\Navigation;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

final class AllowAll implements AccessChecker
{
	public function isLoggedIn(): bool
	{
		return true;
	}

	public function isAllowed(string $link): bool
	{
		return $link !== ':Web:Admin:Secret:default';
	}

	public function getDisplayName(): ?string
	{
		return 'Test';
	}
}

final class HolidayStub extends ResourceDefinition
{
	public function meta(Meta $meta): void
	{
		$meta->slug('holiday')->title('Neobchodní dny')->replaces(':Stock:Admin:Holiday');
	}

	public function list(ListBuilder $list): void
	{
	}

	public function form(FormBuilder $form): void
	{
	}
}

$menu = [
	'Obsah' => [
		'icon' => 'newspaper',
		'items' => [
			'Neobchodní dny' => ['link' => ':Stock:Admin:Holiday:default'],
			'Slider' => ':Web:Admin:Slider:default',
			'Tajné' => ':Web:Admin:Secret:default',
		],
	],
	'Prázdná' => ['items' => ['Tajné' => ':Web:Admin:Secret:default']],
];

test('položky bez práva a prázdné skupiny vypadnou', function () use ($menu): void {
	$groups = (new Navigation($menu, new AllowAll()))->getGroups('Web:Admin:Slider');

	Assert::count(1, $groups);
	Assert::same(['Neobchodní dny', 'Slider'], array_map(fn ($item) => $item->label, $groups[0]->items));
	Assert::true($groups[0]->items[1]->active);
	Assert::false($groups[0]->items[0]->active);
});

test('nepřepnutá agenda nechá starý odkaz', function () use ($menu): void {
	$registry = new ResourceRegistry([new HolidayStub()]);
	$item = (new Navigation($menu, new AllowAll(), $registry))->getGroups()[0]->items[0];

	Assert::same(':Stock:Admin:Holiday:default', $item->link);
});

test('přepnutá agenda vede na nový presenter', function () use ($menu): void {
	$registry = new ResourceRegistry([new HolidayStub()], ['holiday']);
	$item = (new Navigation($menu, new AllowAll(), $registry))
		->getGroups('Backoffice:Resource', ['resource' => 'holiday'])[0]->items[0];

	Assert::same(':Backoffice:Resource:default', $item->link);
	Assert::same(['resource' => 'holiday'], $item->args);
	Assert::true($item->active);
});
