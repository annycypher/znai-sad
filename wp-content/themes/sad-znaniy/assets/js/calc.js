/**
 * Калькуляторы «Сад знаний»: регион из календаря + пересчёт при смене поля.
 *
 * Общий скрипт для всех калькуляторов. Регион подставляется, если в форме
 * есть селект с data-region="1" и регион ещё не пришёл в адресе страницы
 * (атрибут data-has-region="1" на селекте означает, что он уже задан).
 * Ключ localStorage календаря можно переопределить атрибутом data-ls-key на форме.
 * Формулу считает сервер: одна формула на весь проект, без дублей в JS.
 */
( function () {
	'use strict';

	var forms = document.querySelectorAll( '.calc-form' );
	if ( ! forms.length ) {
		return;
	}

	Array.prototype.forEach.call( forms, function ( form ) {
		var lsKey  = form.getAttribute( 'data-ls-key' ) || 'sad_znaniy_cal';
		var select = form.querySelector( 'select[data-region="1"]' );

		// 1. Регион из выбора в календаре, если он не задан в адресе.
		if ( select && '1' !== select.getAttribute( 'data-has-region' ) ) {
			try {
				var saved = JSON.parse( localStorage.getItem( lsKey ) || '{}' ) || {};
				if ( saved.region && saved.region !== select.value ) {
					select.value = saved.region;
					form.submit();
					return;
				}
			} catch ( err ) {
				// localStorage недоступен — считаем без автоподстановки.
			}
		}

		// 2. Пересчёт сразу при смене любого параметра.
		form.addEventListener( 'change', function () {
			form.submit();
		} );
	} );

	// 3. После пересчёта возвращаем пользователя к результату.
	if ( window.location.search.indexOf( 'sz' ) !== -1 ) {
		var calc = document.querySelector( '.calc' );
		if ( calc && calc.scrollIntoView ) {
			calc.scrollIntoView( { block: 'center' } );
		}
	}
} )();
