/**
 * Калькулятор полива: регион из календаря + мгновенный пересчёт.
 *
 * Формулу считает сервер (одна формула на весь проект): при смене любого
 * параметра форма отправляется заново, а расчёт приходит уже готовым.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.calc-form' );
	if ( ! form ) {
		return;
	}

	var cfg = window.sadZnaniyWater || {};

	// Если регион не задан в адресе — подставляем выбор из календаря.
	if ( ! cfg.hasRegion ) {
		var regionSelect = form.querySelector( '[name="sz_region"]' );
		if ( regionSelect ) {
			try {
				var saved = JSON.parse( localStorage.getItem( cfg.lsKey || 'sad_znaniy_cal' ) || '{}' ) || {};
				if ( saved.region && saved.region !== regionSelect.value ) {
					regionSelect.value = saved.region;
					form.submit();
					return;
				}
			} catch ( err ) {
				// localStorage недоступен — продолжаем без автоподстановки.
			}
		}
	}

	// Пересчёт сразу при смене параметра.
	form.addEventListener( 'change', function () {
		form.submit();
	} );

	// После пересчёта возвращаем пользователя к результату.
	if ( window.location.search.indexOf( 'sz_' ) !== -1 ) {
		var calc = document.querySelector( '.calc' );
		if ( calc && calc.scrollIntoView ) {
			calc.scrollIntoView( { block: 'center' } );
		}
	}
} )();
