<?php

declare(strict_types=1);

use Backoffice\Resource\View\YearCalendar;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('rok má 12 měsíců, odsazení podle prvního dne v týdnu', function (): void {
	$months = (new YearCalendar(2026, [], '2026-11-12'))->months();

	Assert::count(12, $months);
	Assert::same('Leden', $months[0]['name']);
	Assert::same(3, $months[0]['offset']); // 1. 1. 2026 je čtvrtek
	Assert::count(31, $months[0]['days']);
	Assert::count(28, $months[1]['days']);
	Assert::same(6, $months[10]['offset']); // 1. 11. 2026 je neděle
});

test('víkend, dnešek a vyznačené dny', function (): void {
	$calendar = new YearCalendar(2026, ['2026-01-01' => ['title' => 'Nový rok', 'href' => '/x']], '2026-01-02');
	$days = $calendar->months()[0]['days'];

	Assert::same('2026-01-01', $days[0]['date']);
	Assert::same(['title' => 'Nový rok', 'href' => '/x'], $days[0]['marked']);
	Assert::same('1. 1. 2026 — Nový rok', $days[0]['label']);
	Assert::true($days[1]['today']);
	Assert::false($days[1]['weekend']); // pátek
	Assert::true($days[2]['weekend']); // sobota
	Assert::true($days[3]['weekend']); // neděle
	Assert::false($days[4]['weekend']);
});

test('přestupný rok a zkratka dne', function (): void {
	Assert::count(29, (new YearCalendar(2028))->months()[1]['days']);
	Assert::same('Čt', YearCalendar::weekday('2026-01-01'));
	Assert::same('Ne', YearCalendar::weekday('2026-11-01'));
});
