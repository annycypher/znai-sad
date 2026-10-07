/* ==========================================================
   КАРТОЧКА РАСТЕНИЯ — блок лунных дней по региону (Этап 5.7, B)
   SSR отдаёт блок для региона из cookie; при смене региона в
   шапке блок перезапрашивается по REST и заменяется без перезагрузки.
   ========================================================== */
(function () {
	'use strict';

	var cfg = window.szPlantMoon || {};
	if ( ! cfg.url || ! window.szRegion ) {
		return;
	}

	function load( region ) {
		var block = document.querySelector( '[data-sz-plant-moon]' );
		if ( ! block ) {
			return;
		}

		var plant = block.getAttribute( 'data-plant' ) || '';
		if ( ! plant ) {
			return;
		}

		var url = cfg.url + ( cfg.url.indexOf( '?' ) === -1 ? '?' : '&' ) +
			'plant=' + encodeURIComponent( plant ) +
			'&region=' + encodeURIComponent( region || '' );

		fetch( url, { headers: { Accept: 'application/json' } } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.html ) {
					return;
				}
				var tmp = document.createElement( 'div' );
				tmp.innerHTML = data.html;
				var fresh = tmp.firstElementChild;
				if ( fresh && block.parentNode ) {
					block.parentNode.replaceChild( fresh, block );
				}
			} )
			.catch( function () {} );
	}

	window.szRegion.onChange( function ( region ) {
		load( region );
	} );
})();
