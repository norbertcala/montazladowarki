/* Motyw Montażładowarki — menu mobilne i nagłówek. */
(function () {
	'use strict';
	var burger = document.querySelector('[data-mlt-burger]');
	var header = document.querySelector('.mlt-header');
	if (burger) {
		burger.addEventListener('click', function () {
			var open = burger.getAttribute('aria-expanded') === 'true';
			burger.setAttribute('aria-expanded', String(!open));
			document.body.classList.toggle('mlt-menu-open', !open);
		});
	}
	if (header) {
		var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}
})();
