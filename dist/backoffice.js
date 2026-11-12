/*
 * liquiddesign/backoffice — chování shellu bez závislostí. Ručně psané, dokud nepřijde build.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'backoffice.sideCollapsed';
	var root = document.documentElement;
	var mobile = window.matchMedia('(max-width: 960px)');

	function readCollapsed() {
		try {
			return window.localStorage.getItem(STORAGE_KEY) === '1';
		} catch (e) {
			return false;
		}
	}

	function writeCollapsed(value) {
		try {
			window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
		} catch (e) {
			// Bez úložiště se jen nepamatuje volba.
		}
	}

	if (readCollapsed()) {
		root.classList.add('bo-side-collapsed');
	}

	document.addEventListener('click', function (event) {
		var toggle = event.target.closest('[data-bo-toggle-side]');

		if (toggle) {
			if (mobile.matches) {
				root.classList.toggle('bo-side-open');
			} else {
				var collapsed = root.classList.toggle('bo-side-collapsed');
				writeCollapsed(collapsed);
				toggle.setAttribute('aria-label', collapsed ? 'Rozbalit menu' : 'Sbalit menu');
			}

			return;
		}

		var dismiss = event.target.closest('[data-bo-dismiss]');

		if (dismiss && dismiss.parentElement) {
			dismiss.parentElement.remove();

			return;
		}

		// Na mobilu zavřít vysunuté menu kliknutím mimo něj.
		if (root.classList.contains('bo-side-open') && !event.target.closest('.bo-side')) {
			root.classList.remove('bo-side-open');
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			root.classList.remove('bo-side-open');
		}
	});
	// Potvrzení před odesláním (mazání) — z tlačítka, nebo z formuláře.
	document.addEventListener('submit', function (event) {
		var form = event.target;
		var submitter = event.submitter;
		var message = (submitter && submitter.dataset.boConfirm) || (form instanceof HTMLFormElement && form.dataset.boConfirm);

		if (message && !window.confirm(message)) {
			event.preventDefault();
		}
	}, true);

	// Výběr ve filtru a „Na stránce“ odešle formulář hned.
	document.addEventListener('change', function (event) {
		var el = event.target;

		if (el.matches && el.matches('[data-bo-autosubmit]') && el.form) {
			el.form.requestSubmit();
		}
	});

	// Kalendář: klik na volný den zapíše datum do pole (data-bo-pick-target = id pole).
	// Pole může mít data-bo-weekend-warning — text, který se ukáže u víkendového dne.
	document.addEventListener('click', function (event) {
		var day = event.target.closest('[data-bo-pick]');

		if (!day) {
			return;
		}

		var input = document.getElementById(day.dataset.boPickTarget);

		if (!input) {
			return;
		}

		input.value = day.dataset.boPick;
		input.dispatchEvent(new Event('input', { bubbles: true }));
		input.dispatchEvent(new Event('change', { bubbles: true }));

		document.querySelectorAll('.bo-cal-day.is-picked').forEach(function (el) {
			el.classList.remove('is-picked');
		});
		day.classList.add('is-picked');

		var warn = document.getElementById(input.id + '-warn');

		if (warn) {
			warn.textContent = day.hasAttribute('data-bo-weekend') ? (input.dataset.boWeekendWarning || '') : '';
		}

		var next = input.form && Array.prototype.find.call(input.form.elements, function (el) {
			return el !== input && el.matches('input:not([type=hidden]), textarea') && el.value === '';
		});

		(next || input).focus();
	});

	// Formulář agendy: neuložené změny, Ctrl S, varování při odchodu.
	var form = document.querySelector('[data-bo-form]');

	if (form) {
		var savebar = form.querySelector('[data-bo-savebar]');
		var label = form.querySelector('[data-bo-dirty-label]');
		var dirty = false;
		var submitting = false;

		var markDirty = function () {
			if (dirty) {
				return;
			}

			dirty = true;

			if (savebar) {
				savebar.classList.add('is-dirty');
			}

			if (label) {
				label.textContent = 'Neuložené změny';
			}
		};

		form.addEventListener('input', markDirty);
		form.addEventListener('change', markDirty);
		// Odeslání kteréhokoli formuláře na stránce (uložení i smazání z lišty) nemá varovat.
		document.addEventListener('submit', function (event) {
			if (!event.defaultPrevented) {
				submitting = true;
			}
		});

		window.addEventListener('beforeunload', function (event) {
			if (dirty && !submitting) {
				event.preventDefault();
				event.returnValue = '';
			}
		});

		var discard = form.querySelector('[data-bo-discard]');

		if (discard) {
			discard.addEventListener('click', function () {
				dirty = false;
			});
		}

		document.addEventListener('keydown', function (event) {
			if ((event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S')) {
				event.preventDefault();
				var save = form.querySelector('[data-bo-save]');

				if (save) {
					form.requestSubmit(save);
				}
			}
		});
	}

	// Seznam agendy: výběr řádků, úpravy v řádku + „Uložit vše“, pořadí přetažením.
	document.querySelectorAll('[data-bo-list]').forEach(function (list) {
		var bar = list.querySelector('.bo-listbar');
		var selectAll = list.querySelector('[data-bo-select-all]');
		var selectLabel = list.querySelector('[data-bo-select-label]');
		var stateLabel = list.querySelector('[data-bo-list-state]');
		var saveButton = list.querySelector('[data-bo-list-save]');
		var dirty = false;
		var submitting = false;

		var boxes = function () {
			return Array.prototype.slice.call(list.querySelectorAll('[data-bo-select]'));
		};

		var updateSelection = function () {
			var all = boxes();
			var count = all.filter(function (box) { return box.checked; }).length;

			if (selectLabel) {
				selectLabel.textContent = count === 0 ? 'Nic nevybráno' : 'Vybráno: ' + count;
			}

			if (selectAll) {
				selectAll.checked = count > 0 && count === all.length;
				selectAll.indeterminate = count > 0 && count < all.length;
			}

			list.querySelectorAll('[data-bo-bulk]').forEach(function (button) {
				button.disabled = count === 0;
			});
		};

		var markDirty = function (row) {
			if (row) {
				row.classList.add('is-changed');
			}

			if (dirty) {
				return;
			}

			dirty = true;

			if (bar) {
				bar.classList.add('is-dirty');
			}

			if (stateLabel) {
				stateLabel.textContent = 'Neuložené změny';
			}

			if (saveButton) {
				saveButton.disabled = false;
			}
		};

		if (selectAll) {
			selectAll.addEventListener('change', function () {
				boxes().forEach(function (box) { box.checked = selectAll.checked; });
				updateSelection();
			});
		}

		list.addEventListener('change', function (event) {
			if (event.target.matches('[data-bo-select]')) {
				updateSelection();
			}
		});

		var onEdit = function (event) {
			if (event.target.matches('[data-bo-edit]')) {
				markDirty(event.target.closest('tr'));
			}
		};

		list.addEventListener('input', onEdit);
		list.addEventListener('change', onEdit);

		// Enter v poli řádku = Uložit vše (výchozí tlačítko formuláře by bylo mazání prvního řádku).
		list.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' || !event.target.matches('input[data-bo-edit]')) {
				return;
			}

			event.preventDefault();

			if (dirty && saveButton) {
				list.requestSubmit(saveButton);
			}
		});

		list.addEventListener('submit', function (event) {
			var submitter = event.submitter;

			// Hromadná akce nebo smazání by neuložené úpravy v řádcích zahodily.
			if (dirty && submitter && submitter.value !== 'save' && !window.confirm('Neuložené změny v řádcích se zahodí. Pokračovat?')) {
				event.preventDefault();

				return;
			}

			if (!event.defaultPrevented) {
				submitting = true;
			}
		});

		window.addEventListener('beforeunload', function (event) {
			if (dirty && !submitting) {
				event.preventDefault();
				event.returnValue = '';
			}
		});

		document.addEventListener('keydown', function (event) {
			if (dirty && saveButton && (event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S')) {
				event.preventDefault();
				list.requestSubmit(saveButton);
			}
		});

		updateSelection();

		// Pořadí: přetažení za úchyt (nebo šipky na úchytu) přeskládá řádky a přečísluje pole pořadí
		// hodnotami, které řádky na stránce už měly — ostatní stránky se tím nerozhodí.
		var body = list.querySelector('[data-bo-sortable]');

		if (!body) {
			return;
		}

		var rows = function () {
			return Array.prototype.slice.call(body.querySelectorAll('tr[data-bo-row]'));
		};

		var original = rows().map(function (row) {
			var input = row.querySelector('[data-bo-priority]');

			return input ? parseInt(input.value, 10) || 0 : 0;
		}).sort(function (a, b) { return a - b; });

		var unique = original.every(function (value, index) { return index === 0 || value !== original[index - 1]; });

		var renumber = function () {
			rows().forEach(function (row, index) {
				var input = row.querySelector('[data-bo-priority]');

				if (!input) {
					return;
				}

				var value = String(unique ? original[index] : original[0] + index);

				if (input.value !== value) {
					input.value = value;
					markDirty(row);
				}
			});
		};

		var dragged = null;

		body.addEventListener('pointerdown', function (event) {
			var grip = event.target.closest('[data-bo-grip]');

			if (grip) {
				grip.closest('tr').draggable = true;
			}
		});

		body.addEventListener('dragstart', function (event) {
			dragged = event.target.closest('tr');

			if (!dragged) {
				return;
			}

			dragged.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', '');
		});

		body.addEventListener('dragover', function (event) {
			if (!dragged) {
				return;
			}

			event.preventDefault();
			var target = event.target.closest('tr[data-bo-row]');

			if (!target || target === dragged) {
				return;
			}

			var rect = target.getBoundingClientRect();
			body.insertBefore(dragged, event.clientY > rect.top + rect.height / 2 ? target.nextSibling : target);
		});

		body.addEventListener('dragend', function () {
			if (!dragged) {
				return;
			}

			dragged.classList.remove('is-dragging');
			dragged.draggable = false;
			dragged = null;
			renumber();
		});

		body.addEventListener('keydown', function (event) {
			var grip = event.target.closest('[data-bo-grip]');

			if (!grip || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
				return;
			}

			event.preventDefault();
			var row = grip.closest('tr');
			var sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;

			if (!sibling || !sibling.matches('tr[data-bo-row]')) {
				return;
			}

			body.insertBefore(row, event.key === 'ArrowUp' ? sibling : sibling.nextSibling);
			grip.focus();
			renumber();
		});
	});
})();
