<?php

declare(strict_types=1);

// Ukázková data pro playground. Spouští se při startu kontejneru; když data jsou, nedělá nic.

require __DIR__ . '/../vendor/autoload.php';

$container = Playground\Bootstrap::boot()->createContainer();
$holidays = $container->getByType(Playground\Model\HolidayRepository::class);

if ($holidays->many()->enum() > 0) {
	echo "seed: data už jsou\n";

	return;
}

$rows = [
	['2026-11-17', 'Den boje za svobodu a demokracii', 'Struggle for Freedom and Democracy Day'],
	['2026-12-24', 'Štědrý den', 'Christmas Eve'],
	['2026-12-25', '1. svátek vánoční', 'Christmas Day'],
	['2026-12-28', 'Burza zavřena', 'Exchange closed'],
	['2026-12-31', 'Silvestr', 'New Year\'s Eve'],
	['2027-01-01', 'Den obnovy samostatného českého státu', 'Restoration Day of the Independent Czech State'],
];

foreach ($rows as [$date, $cs, $en]) {
	$holidays->createOne(['date' => $date, 'name' => ['cs' => $cs, 'en' => $en], 'active' => true]);
}

echo 'seed: vloženo ' . \count($rows) . " neobchodních dnů\n";
