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
		var rows = Array.prototype.slice.call(document.querySelectorAll('[data-moon-cat]'));

		function apply() {
			var cat = sel.value;
			rows.forEach(function (row) {
				var on = cat !== '' && row.getAttribute('data-moon-cat') === cat;
				row.classList.toggle('moon-hilite', on);
			});
		}

		sel.addEventListener('change', apply);
		apply();
	});
})();
