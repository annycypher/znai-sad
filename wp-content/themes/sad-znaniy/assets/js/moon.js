/* eslint-disable */
/**
 * Лунный календарь — клиентские улучшения (Этап 5.6).
 *
 * Список дней и фазы рендерятся на сервере (SSR). Здесь только мгновенное
 * усиление: фильтр «дни для культуры» подсвечивает подходящие строки,
 * не перезагружая страницу.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var sel = document.querySelector('[data-moon-crop]');
		if (!sel) {
			return;
		}
		var rows = Array.prototype.slice.call(document.querySelectorAll('[data-moon-good]'));

		function apply() {
			var cat = sel.value;
			rows.forEach(function (row) {
				var good = (row.getAttribute('data-moon-good') || '').split(',');
				var on = cat !== '' && good.indexOf(cat) !== -1;
				row.classList.toggle('moon-hilite', on);
			});
		}

		sel.addEventListener('change', apply);
		apply();
	});
})();
