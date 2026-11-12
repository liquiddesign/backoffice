<?php

declare(strict_types=1);

namespace Backoffice\Resource\List;

/**
 * Stránkování seznamu: počet stran a čísla stran k zobrazení (1 … 4 5 6 … 26).
 */
final class Pager
{
	public readonly int $pages;

	public readonly int $page;

	public function __construct(public readonly int $total, public readonly int $perPage, int $page)
	{
		$this->pages = \max(1, (int) \ceil($total / \max(1, $perPage)));
		$this->page = \min(\max(1, $page), $this->pages);
	}

	/**
	 * Čísla stran; null = vynechané strany („…“). Vždy první, poslední a okolí aktuální.
	 * @return list<int|null>
	 */
	public function numbers(int $around = 1): array
	{
		$numbers = [];
		$previous = 0;

		for ($number = 1; $number <= $this->pages; $number++) {
			if ($number !== 1 && $number !== $this->pages && \abs($number - $this->page) > $around) {
				continue;
			}

			if ($number - $previous > 1) {
				$numbers[] = $number - $previous === 2 ? $number - 1 : null;
			}

			$numbers[] = $number;
			$previous = $number;
		}

		return $numbers;
	}

	public function firstItem(): int
	{
		return $this->total === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
	}

	public function lastItem(): int
	{
		return \min($this->total, $this->page * $this->perPage);
	}
}
