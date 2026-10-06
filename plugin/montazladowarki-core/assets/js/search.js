/* Montażładowarki — wyszukiwarka, filtry i wybór firm do zapytania. */
(function () {
	'use strict';
	var cfg = window.MLC || {};
	var t = cfg.i18n || {};

	function fmt(s, n) { return (s || '').replace('%d', n); }

	function initSearch(form) {
		var input = form.querySelector('[data-mlc-ac]');
		var place = form.querySelector('[data-mlc-ac-id]');
		var lat = form.querySelector('[data-mlc-lat]');
		var lng = form.querySelector('[data-mlc-lng]');
		var btn = form.querySelector('[data-mlc-locate]');

		// Wybór z listy = od razu szukaj.
		input.addEventListener('mlc:place', function () {
			lat.disabled = lng.disabled = true;
			form.submit();
		});

		form.addEventListener('submit', function () {
			// Jeśli nie wybrano podpowiedzi, a tekst to sama nazwa miejscowości — użyj pierwszej podpowiedzi.
			if (!place.value && lat.disabled && input.mlcFirst && !/\d|,/.test(input.value)) {
				var first = input.mlcFirst();
				if (first) { place.value = first.id; }
			}
			if (!place.value) { place.disabled = true; }
		});

		if (btn && 'geolocation' in navigator) {
			btn.addEventListener('click', function () {
				btn.classList.add('is-busy');
				input.value = t.locating || '…';
				navigator.geolocation.getCurrentPosition(function (pos) {
					lat.value = pos.coords.latitude.toFixed(5);
					lng.value = pos.coords.longitude.toFixed(5);
					lat.disabled = lng.disabled = false;
					place.value = '';
					place.disabled = true;
					input.value = '';
					input.removeAttribute('required');
					input.disabled = true;
					form.submit();
				}, function () {
					btn.classList.remove('is-busy');
					input.value = '';
					input.placeholder = t.locFail || '';
					input.focus();
				}, { enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 });
			});
		} else if (btn) {
			btn.hidden = true;
		}
	}

	function initFilters(form) {
		form.addEventListener('change', function () { form.submit(); });
	}

	function initPicks(form) {
		var boxes = form.querySelectorAll('[data-mlc-pick]');
		var bar = form.querySelector('[data-mlc-pickbar]');
		var count = form.querySelector('[data-mlc-pickcount]');
		var summary = form.querySelector('[data-mlc-picksummary]');
		var max = cfg.leadMax || 5;
		if (!boxes.length) { return; }

		function names() {
			var out = [];
			boxes.forEach(function (b) {
				if (b.checked) {
					var card = b.closest('[data-mlc-card]');
					var a = card && card.querySelector('.mlc-card__title a');
					out.push(a ? a.textContent.trim() : '');
				}
			});
			return out;
		}

		function update() {
			var picked = names();
			boxes.forEach(function (b) {
				b.closest('[data-mlc-card]').classList.toggle('is-picked', b.checked);
				b.disabled = !b.checked && picked.length >= max;
			});
			if (bar) {
				bar.hidden = picked.length === 0;
				count.textContent = fmt(t.selected, picked.length);
			}
			if (summary) {
				summary.textContent = picked.length ? fmt(t.selected, picked.length) + ': ' + picked.join(', ') : summary.dataset.empty || summary.textContent;
			}
		}
		if (summary) { summary.dataset.empty = summary.textContent; }
		boxes.forEach(function (b) { b.addEventListener('change', update); });

		// Gdy nic nie zaznaczono, a użytkownik wysyła formularz — zaznacz pierwsze firmy.
		form.addEventListener('submit', function (e) {
			if (!names().length) {
				var n = 0;
				boxes.forEach(function (b) { if (n < Math.min(3, max)) { b.checked = true; n++; } });
				update();
				e.preventDefault();
				var first = form.querySelector('.mlc-cards');
				if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			}
		});
		update();
	}

	function ready() {
		document.querySelectorAll('[data-mlc-search]').forEach(initSearch);
		document.querySelectorAll('[data-mlc-filters]').forEach(initFilters);
		document.querySelectorAll('[data-mlc-leadform]').forEach(initPicks);
	}
	if (document.readyState !== 'loading') { ready(); } else { document.addEventListener('DOMContentLoaded', ready); }
})();
