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
	// Potvrzení před odesláním (mazání).
	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (form instanceof HTMLFormElement && form.dataset.boConfirm && !window.confirm(form.dataset.boConfirm)) {
			event.preventDefault();
		}
	}, true);

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
		form.addEventListener('submit', function () {
			submitting = true;
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
})();
