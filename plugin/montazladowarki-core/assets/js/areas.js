/* Montażładowarki — edytor obszarów działania. */
(function () {
	'use strict';

	function reindex(rows) {
		rows.querySelectorAll('[data-mlc-area]').forEach(function (row, i) {
			row.querySelectorAll('[name^="mlc_areas["]').forEach(function (f) {
				f.name = f.name.replace(/mlc_areas\[\d+\]/, 'mlc_areas[' + i + ']');
			});
		});
	}

	function init(box) {
		var rows = box.querySelector('[data-mlc-area-rows]');
		var add = box.querySelector('[data-mlc-area-add]');

		add.addEventListener('click', function () {
			var all = rows.querySelectorAll('[data-mlc-area]');
			if (all.length >= 10) { return; }
			var clone = all[all.length - 1].cloneNode(true);
			var input = clone.querySelector('[data-mlc-ac]');
			input.value = '';
			delete input.dataset.mlcAcReady;
			clone.querySelector('[data-mlc-ac-id]').value = '';
			clone.querySelectorAll('.mlc-ac').forEach(function (l) { l.remove(); });
			rows.appendChild(clone);
			reindex(rows);
			if (window.MLCAutocomplete) { window.MLCAutocomplete.attach(input); }
			input.focus();
		});

		rows.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-mlc-area-remove]');
			if (!btn) { return; }
			var all = rows.querySelectorAll('[data-mlc-area]');
			var row = btn.closest('[data-mlc-area]');
			if (all.length === 1) {
				row.querySelector('[data-mlc-ac]').value = '';
				row.querySelector('[data-mlc-ac-id]').value = '';
				return;
			}
			row.remove();
			reindex(rows);
		});
	}

	function ready() { document.querySelectorAll('[data-mlc-areas]').forEach(init); }
	if (document.readyState !== 'loading') { ready(); } else { document.addEventListener('DOMContentLoaded', ready); }
})();
