/* Montażładowarki — podpowiedzi miejscowości (bez zależności). */
(function () {
	'use strict';
	var cfg = window.MLC || {};
	var cache = {};
	var uid = 0;

	function fold(s) {
		return (s || '').toLowerCase()
			.replace(/ą/g, 'a').replace(/ć/g, 'c').replace(/ę/g, 'e').replace(/ł/g, 'l')
			.replace(/ń/g, 'n').replace(/ó/g, 'o').replace(/ś/g, 's').replace(/[źż]/g, 'z');
	}

	function fetchPlaces(q, cb) {
		var key = fold(q).trim();
		if (cache[key]) { cb(cache[key]); return; }
		fetch(cfg.rest + 'places?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : []; })
			.then(function (data) { cache[key] = data; cb(data); })
			.catch(function () { cb([]); });
	}

	function highlight(label, q) {
		var esc = function (s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
		var name = label.split(/[,(]/)[0];
		var rest = label.slice(name.length);
		var f = fold(name), fq = fold(q.split(',').pop().trim());
		var i = fq ? f.indexOf(fq) : -1;
		var html = i === 0 ? '<strong>' + esc(name.slice(0, fq.length)) + '</strong>' + esc(name.slice(fq.length)) : esc(name);
		return html + '<span class="mlc-ac__rest">' + esc(rest) + '</span>';
	}

	function attach(input) {
		if (!input || input.dataset.mlcAcReady) { return; }
		input.dataset.mlcAcReady = '1';
		var wrap = input.parentElement;
		var hidden = wrap.querySelector('[data-mlc-ac-id]');
		var list = document.createElement('ul');
		var id = 'mlc-ac-' + (++uid);
		var items = [];
		var active = -1;
		var timer = null;

		list.className = 'mlc-ac';
		list.id = id;
		list.setAttribute('role', 'listbox');
		list.hidden = true;
		wrap.style.position = wrap.style.position || 'relative';
		wrap.appendChild(list);
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-controls', id);
		input.setAttribute('aria-expanded', 'false');

		function close() {
			list.hidden = true;
			active = -1;
			input.setAttribute('aria-expanded', 'false');
		}

		function render(q) {
			list.innerHTML = '';
			if (!items.length) {
				var li = document.createElement('li');
				li.className = 'mlc-ac__empty';
				li.textContent = (cfg.i18n && cfg.i18n.noResults) || '';
				list.appendChild(li);
			}
			items.forEach(function (it, i) {
				var li = document.createElement('li');
				li.id = id + '-' + i;
				li.setAttribute('role', 'option');
				li.className = 'mlc-ac__item mlc-ac__item--' + it.type;
				li.innerHTML = highlight(it.label, q);
				li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
				list.appendChild(li);
			});
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		function setActive(i) {
			var opts = list.querySelectorAll('[role=option]');
			if (!opts.length) { return; }
			active = (i + opts.length) % opts.length;
			opts.forEach(function (o, n) { o.classList.toggle('is-active', n === active); });
			input.setAttribute('aria-activedescendant', opts[active].id);
		}

		function choose(i) {
			var it = items[i];
			if (!it) { return; }
			input.value = it.type === 'city' ? it.name : it.label.replace(/\s*\([^)]*\)$/, '');
			if (hidden) { hidden.value = it.id; }
			close();
			input.dispatchEvent(new CustomEvent('mlc:place', { bubbles: true, detail: it }));
		}

		input.addEventListener('input', function () {
			if (hidden) { hidden.value = ''; }
			var q = input.value.trim();
			clearTimeout(timer);
			if (q.length < 2) { close(); return; }
			timer = setTimeout(function () {
				fetchPlaces(q, function (data) {
					if (input.value.trim() !== q) { return; }
					items = data || [];
					render(q);
				});
			}, 160);
		});

		input.addEventListener('keydown', function (e) {
			if (list.hidden) { return; }
			if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
			else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(active); }
			else if (e.key === 'Escape') { close(); }
		});

		input.addEventListener('blur', function () { setTimeout(close, 120); });

		// Pierwsza podpowiedź jako domyślna, gdy użytkownik nie wybrał nic z listy.
		input.mlcFirst = function () { return items[0] || null; };
	}

	function init(root) {
		(root || document).querySelectorAll('[data-mlc-ac]').forEach(attach);
	}

	window.MLCAutocomplete = { attach: attach, init: init };
	if (document.readyState !== 'loading') { init(); } else { document.addEventListener('DOMContentLoaded', function () { init(); }); }
})();
