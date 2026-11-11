<?php

declare(strict_types=1);

namespace Playground\Presenters;

use Backoffice\UI\BackofficePresenter;
use Nette\DI\Attributes\Inject;
use Playground\Model\HolidayRepository;

final class DashboardPresenter extends BackofficePresenter
{
	#[Inject]
	public HolidayRepository $holidays;

	public function renderDefault(): void
	{
		$this->title = 'Nástěnka';
		$this->buttons = [
			$this->button('Neobchodní dny', 'Holiday:default', variant: 'outline'),
			$this->button('Nový neobchodní den', 'Holiday:edit', icon: 'file'),
		];

		$today = \date('Y-m-d');
		$this->template->holidayCount = $this->holidays->many()->enum();
		$this->template->upcoming = $this->holidays->many()
			->where('this.date >= :today', ['today' => $today])
			->orderBy(['this.date' => 'ASC'])
			->setTake(5)
			->toArray();
	}
}
