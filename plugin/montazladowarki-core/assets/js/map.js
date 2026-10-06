/* Montażładowarki — mapy Leaflet (lista firm, profil). */
(function () {
	'use strict';
	var cfg = window.MLC || {};

	function pin(cls, text) {
		return L.divIcon({
			className: 'mlc-pin ' + cls,
			html: '<span>' + (text || '') + '</span>',
			iconSize: [30, 30],
			iconAnchor: [15, 30],
			popupAnchor: [0, -28]
		});
	}

	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}

	function init(el) {
		if (el.dataset.ready || typeof L === 'undefined') { return; }
		el.dataset.ready = '1';
		var data = JSON.parse(el.dataset.mlcMap || '{}');
		var c = data.center || { lat: 52.07, lng: 19.48 };
		var map = L.map(el, { scrollWheelZoom: false, attributionControl: true }).setView([c.lat, c.lng], data.zoom || 10);
		L.tileLayer(cfg.tiles || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 18,
			attribution: cfg.attrib || ''
		}).addTo(map);

		var bounds = [];
		if (c.label) {
			L.marker([c.lat, c.lng], { icon: pin('mlc-pin--you', '●'), zIndexOffset: 1000 })
				.addTo(map).bindPopup(esc((cfg.i18n && cfg.i18n.you) || '') + ': <strong>' + esc(c.label) + '</strong>');
			bounds.push([c.lat, c.lng]);
		}

		(data.circles || []).forEach(function (ci) {
			var circle = L.circle([ci.lat, ci.lng], { radius: ci.radius * 1000, className: 'mlc-circle' }).addTo(map);
			if (ci.label) { circle.bindTooltip(esc(ci.label) + ' +' + ci.radius + ' km'); }
			var b = circle.getBounds();
			bounds.push(b.getNorthEast(), b.getSouthWest());
		});

		var byId = {};
		(data.markers || []).forEach(function (m) {
			var mk = L.marker([m.lat, m.lng], { icon: pin(m.promoted ? 'mlc-pin--promo' : '', m.promoted ? '★' : '⚡') })
				.addTo(map)
				.bindPopup('<a href="' + esc(m.url) + '"><strong>' + esc(m.name) + '</strong></a>');
			mk.on('click', function () {
				var card = document.querySelector('[data-mlc-card="' + m.id + '"]');
				if (card) {
					card.classList.add('is-flash');
					card.scrollIntoView({ behavior: 'smooth', block: 'center' });
					setTimeout(function () { card.classList.remove('is-flash'); }, 1600);
				}
			});
			byId[m.id] = mk;
			bounds.push([m.lat, m.lng]);
		});

		// Najechanie na kartę podświetla firmę na mapie.
		document.querySelectorAll('[data-mlc-card]').forEach(function (card) {
			var mk = byId[card.dataset.mlcCard];
			if (!mk) { return; }
			card.addEventListener('mouseenter', function () { mk.openPopup(); });
		});

		if (bounds.length > 1 && !data.zoom) {
			map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
		}
		// Kliknięcie w mapę włącza zoom kółkiem.
		map.once('focus', function () { map.scrollWheelZoom.enable(); });
		setTimeout(function () { map.invalidateSize(); }, 200);
	}

	function ready() {
		var maps = document.querySelectorAll('[data-mlc-map]');
		if (!('IntersectionObserver' in window)) { maps.forEach(init); return; }
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) { if (e.isIntersecting) { init(e.target); io.unobserve(e.target); } });
		}, { rootMargin: '200px' });
		maps.forEach(function (m) { io.observe(m); });
	}
	if (document.readyState !== 'loading') { ready(); } else { document.addEventListener('DOMContentLoaded', ready); }
})();
