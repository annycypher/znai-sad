/* ==========================================================
   ГЛАВНАЯ — сайдбар календаря (Этап 5.5, Партия 4)
   Показывает 3 ближайших события по региону из localStorage
   (ключ sad_znaniy_cal). Регион не выбран — блок скрыт.
   ========================================================== */
(function () {
	'use strict';

	var block = document.getElementById( 'calendar' );
	if ( ! block ) {
		return;
	}

	var events = Array.prototype.slice.call( block.querySelectorAll( '.event' ) );
	if ( ! events.length ) {
		return;
	}

	var region = window.szRegion ? window.szRegion.getRegion() : '';

	function apply( value ) {
		if ( ! value ) {
			block.style.display = 'none';
			return;
		}
		block.style.display = '';

		var shown = 0;
		events.forEach( function ( ev ) {
			var regions = ( ev.dataset.regions || '' ).split( ',' );
			var match = regions.indexOf( 'all' ) !== -1 || regions.indexOf( value ) !== -1;
			if ( match && shown < 3 ) {
				ev.classList.remove( 'is-extra' );
				shown++;
			} else {
				ev.classList.add( 'is-extra' );
			}
		} );
	}

	apply( region );

	if ( window.szRegion ) {
		window.szRegion.onChange( function ( next ) {
			region = next;
			apply( region );
		} );
	}
} )();
