/* Montażładowarki — dane z Google Maps (na żywo, bez zapisu) i wybór wizytówki. */
(function () {
	'use strict';
	var cfg = window.MLC || {};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}

	function stars(r) {
		var out = '';
		for (var i = 1; i <= 5; i++) {
			var fill = Math.max(0, Math.min(1, r - i + 1));
			out += '<span class="mlc-star" style="--f:' + Math.round(fill * 100) + '%">★</span>';
		}
		return out;
	}

	function render(box, d) {
		var html = '';
		if (d.status === 'CLOSED_PERMANENTLY') {
			html += '<p class="mlc-errors">Według Google firma jest zamknięta na stałe.</p>';
		}
		if (d.rating) {
			html += '<div class="mlc-g__rating"><strong>' + d.rating.toFixed(1).replace('.', ',') + '</strong>' +
				'<span class="mlc-g__stars" aria-hidden="true">' + stars(d.rating) + '</span>' +
				'<span class="mlc-muted">' + d.count + ' opinii</span></div>';
		}
		if (d.phone && box.dataset.hasPhone !== '1') {
			html += '<p><a href="tel:' + esc(d.phone.replace(/\s/g, '')) + '">' + esc(d.phone) + '</a></p>';
		}
		if (d.hours && d.hours.length) {
			html += '<details class="mlc-g__hours"><summary>Godziny otwarcia</summary><ul>' +
				d.hours.map(function (h) { return '<li>' + esc(h) + '</li>'; }).join('') + '</ul></details>';
		}
		if (d.url) {
			html += '<p><a href="' + esc(d.url) + '" target="_blank" rel="noopener">Zobacz opinie w Mapach Google ↗</a></p>';
		}
		if (!html) { box.hidden = true; return; }
		box.querySelector('[data-mlc-g-body]').innerHTML = html;
		box.hidden = false;
	}

	function load(box) {
		fetch(cfg.rest + 'google/' + box.dataset.mlcGoogle, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (d) { if (d) { render(box, d); } else { box.hidden = true; } })
			.catch(function () { box.hidden = true; });
	}

	function initBoxes() {
		var boxes = document.querySelectorAll('[data-mlc-google]');
		if (!boxes.length) { return; }
		if (!('IntersectionObserver' in window)) { boxes.forEach(load); return; }
		// Ukryty element nie jest obserwowalny — obserwujemy rodzica.
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) { if (e.isIntersecting) { load(e.target.mlcBox); io.unobserve(e.target); } });
		}, { rootMargin: '300px' });
		boxes.forEach(function (b) { var host = b.parentElement || b; host.mlcBox = b; io.observe(host); });
	}

	function initPicker(el) {
		var input = el.querySelector('[data-mlc-gq]');
		var btn = el.querySelector('[data-mlc-gbtn]');
		var list = el.querySelector('[data-mlc-glist]');
		var hidden = el.querySelector('[data-mlc-gid]');
		var current = el.querySelector('[data-mlc-gcurrent]');

		function search() {
			list.innerHTML = '<li class="mlc-muted">Szukam…</li>';
			fetch(cfg.rest + 'google-search?installer=' + el.dataset.installer + '&q=' + encodeURIComponent(input.value), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce || '' }
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok) { list.innerHTML = '<li class="mlc-errors">' + esc(res.j.message || 'Błąd') + '</li>'; return; }
					if (!res.j.length) { list.innerHTML = '<li class="mlc-muted">Brak wyników — spróbuj innej nazwy.</li>'; return; }
					list.innerHTML = res.j.map(function (p) {
						return '<li><button type="button" class="mlc-g__pick" data-id="' + esc(p.id) + '"><strong>' + esc(p.name) + '</strong><br><span class="mlc-muted">' + esc(p.address) + (p.website ? ' · ' + esc(p.website.replace(/^https?:\/\//, '')) : '') + '</span></button></li>';
					}).join('');
				})
				.catch(function () { list.innerHTML = '<li class="mlc-errors">Błąd połączenia.</li>'; });
		}

		btn.addEventListener('click', search);
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
		list.addEventListener('click', function (e) {
			var b = e.target.closest('.mlc-g__pick');
			if (!b) { return; }
			hidden.value = b.dataset.id;
			current.textContent = 'Wybrano: ' + b.querySelector('strong').textContent + ' — zapisz zmiany.';
			list.innerHTML = '';
		});
		var clear = el.querySelector('[data-mlc-gclear]');
		if (clear) {
			clear.addEventListener('click', function () { hidden.value = ''; current.textContent = 'Odłączono — zapisz zmiany.'; });
		}
	}

	function ready() {
		initBoxes();
		document.querySelectorAll('[data-mlc-gpicker]').forEach(initPicker);
	}
	if (document.readyState !== 'loading') { ready(); } else { document.addEventListener('DOMContentLoaded', ready); }
})();
