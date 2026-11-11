<?php

declare(strict_types=1);

namespace Playground\Presenters;

use Backoffice\UI\BackofficePresenter;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Playground\Model\Holiday;
use Playground\Model\HolidayRepository;

/**
 * Agenda napsaná ručně nad shellem. Až vznikne deklarativní vrstva (Resource), přepíše se na ni
 * a tahle verze poslouží k porovnání.
 */
final class HolidayPresenter extends BackofficePresenter
{
	#[Inject]
	public HolidayRepository $holidays;

	private ?Holiday $holiday = null;

	public function renderDefault(): void
	{
		$this->title = 'Neobchodní dny';
		$this->breadcrumbs = [['Burza'], ['Neobchodní dny']];
		$this->buttons = [$this->button('Nový neobchodní den', 'edit', icon: 'file')];
		$this->template->items = $this->holidays->many()->orderBy(['this.date' => 'DESC'])->toArray();
	}

	public function actionEdit(?string $id = null): void
	{
		if ($id !== null) {
			$this->holiday = $this->holidays->one($id) ?? $this->error('Neobchodní den nenalezen.');
			$this['form']->setDefaults($this->holiday->toArray());
		}
	}

	public function renderEdit(): void
	{
		$this->title = $this->holiday !== null ? (string) $this->holiday->name : 'Nový neobchodní den';
		$this->breadcrumbs = [['Burza'], ['Neobchodní dny', 'default'], [$this->title]];
		$this->buttons = [$this->button('Zpět', 'default', variant: 'ghost')];
	}

	public function handleDelete(string $id): void
	{
		$holiday = $this->holidays->one($id);

		if ($holiday !== null) {
			$holiday->delete();
			$this->flashMessage('Neobchodní den smazán.', 'success');
		}

		$this->redirect('default');
	}

	protected function createComponentForm(): Form
	{
		$form = new Form();
		$form->addText('date', 'Datum')->setHtmlType('date')->setRequired('Vyplňte datum.');
		$name = $form->addContainer('name');
		$name->addText('cs', 'Název (CS)')->setRequired('Vyplňte český název.');
		$name->addText('en', 'Název (EN)');
		$form->addTextArea('note', 'Poznámka');
		$form->addCheckbox('active', 'Aktivní')->setDefaultValue(true);
		$form->addSubmit('save', 'Uložit');

		$form->onSuccess[] = function (Form $form, array $values): void {
			if ($this->holiday !== null) {
				$this->holiday->update($values);
			} else {
				$this->holidays->createOne($values);
			}

			$this->flashMessage('Uloženo.', 'success');
			$this->redirect('default');
		};

		return $form;
	}
}
