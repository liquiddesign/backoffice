<?php

declare(strict_types=1);

namespace Backoffice\Resource\View;

/**
 * Roční kalendář po měsících (týden od pondělí) s vyznačenými dny — pro agendy nad daty
 * (neobchodní dny, akce). Vykresluje ho šablona YearCalendar::TEMPLATE.
 */
final class YearCalendar
{
	public const TEMPLATE = __DIR__ . '/templates/yearCalendar.latte';

	public const MONTHS = ['Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];

	public const WEEKDAYS = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];

	/**
	 * @param array<string, array{title: string, href: string|null}> $marked RRRR-MM-DD → popisek a odkaz
	 * @param string|null $today RRRR-MM-DD, null = dnešek podle serveru
	 */
	public function __construct(
		public readonly int $year,
		private readonly array $marked = [],
		private ?string $today = null,
	) {
		$this->today ??= (new \DateTimeImmutable())->format('Y-m-d');
	}

	/**
	 * @return list<array{name: string, offset: int, days: list<array{day: int, label: string, date: string, weekend: bool, today: bool, marked: array{title: string, href: string|null}|null}>}>
	 */
	public function months(): array
	{
		$months = [];

		foreach (self::MONTHS as $index => $name) {
			$first = new \DateTimeImmutable(\sprintf('%04d-%02d-01', $this->year, $index + 1));
			$days = [];

			for ($day = 1; $day <= (int) $first->format('t'); $day++) {
				$date = \sprintf('%04d-%02d-%02d', $this->year, $index + 1, $day);
				$weekday = ((int) $first->format('N') + $day - 2) % 7;

				$marked = $this->marked[$date] ?? null;
				$days[] = [
					'day' => $day,
					'label' => $day . '. ' . ($index + 1) . '. ' . $this->year . ($marked !== null ? ' — ' . $marked['title'] : ''),
					'date' => $date,
					'weekend' => $weekday >= 5,
					'today' => $date === $this->today,
					'marked' => $marked,
				];
			}

			$months[] = [
				'name' => $name,
				'offset' => (int) $first->format('N') - 1,
				'days' => $days,
			];
		}

		return $months;
	}

	/**
	 * Zkratka dne v týdnu („Po“ … „Ne“) pro datum RRRR-MM-DD.
	 */
	public static function weekday(string $date): string
	{
		return self::WEEKDAYS[(int) (new \DateTimeImmutable($date))->format('N') - 1];
	}
}
