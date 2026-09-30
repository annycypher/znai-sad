/**
 * Скрипты темы «Сад знаний».
 * Перенесено из макета _design/index.html: переключатель версии для слабовидящих.
 * Без внешних зависимостей.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var bviBtn = document.getElementById( 'bvi-toggle' );

		if ( ! bviBtn ) {
			return;
		}

		bviBtn.addEventListener( 'click', function () {
			var on = document.body.classList.toggle( 'bvi' );
			bviBtn.setAttribute( 'aria-pressed', String( on ) );
			bviBtn.textContent = on ? 'Обычная версия сайта' : 'Версия для слабовидящих';
		} );
	} );
} )();