/* ==========================================================
   ПОДСВЕТКА РЕГИОНА В ТАБЛИЦАХ (Этап 5.7, Партия C)
   Находит в контенте таблицы со сроками по регионам и подсвечивает
   строку своего региона или колонку с ним. Работает в статьях,
   карточках растений; обновляется при смене региона в шапке.
   ========================================================== */
(function () {
	'use strict';

	var data = window.szRegionData || {};
	var ALIASES = data.aliases || {};
	if ( ! window.szRegion ) {
		return;
	}

	function norm( s ) {
		return ( s || '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();
	}

	function matchesRegion( text, region ) {
		var list = ALIASES[ region ] || [];
		text = norm( text );
		for ( var i = 0; i < list.length; i++ ) {
			if ( text.indexOf( list[ i ] ) !== -1 ) {
				return true;
			}
		}
		return false;
	}

	function anyRegion( text ) {
		for ( var key in ALIASES ) {
			if ( ALIASES.hasOwnProperty( key ) && matchesRegion( text, key ) ) {
				return true;
			}
		}
		return false;
	}

	function cells( tr ) {
		return Array.prototype.slice.call( tr.children ).filter( function ( c ) {
			return 'TD' === c.tagName || 'TH' === c.tagName;
		} );
	}

	function clear( table ) {
		Array.prototype.forEach.call( table.querySelectorAll( '.is-current-region' ), function ( el ) {
			el.classList.remove( 'is-current-region' );
		} );
	}

	function isRegionTable( table, rows ) {
		var head = '';
		rows.forEach( function ( tr ) {
			cells( tr ).forEach( function ( c ) {
				if ( 'TH' === c.tagName ) {
					head += ' ' + norm( c.textContent );
				}
			} );
		} );

		if ( /регион|зона|зоны|зоне/.test( head ) ) {
			return true;
		}

		// Заголовок колонки сам называет регион (например «Срок (средняя полоса)»).
		if ( anyRegion( head ) ) {
			return true;
		}

		// Первый столбец перечисляет регионы построчно.
		var hits = 0;
		rows.forEach( function ( tr ) {
			var first = cells( tr )[ 0 ];
			if ( first && anyRegion( first.textContent ) ) {
				hits++;
			}
		} );
		return hits >= 2;
	}

	function markTable( table, region ) {
		clear( table );

		var rows = Array.prototype.slice.call( table.querySelectorAll( 'tr' ) );
		if ( ! rows.length || ! isRegionTable( table, rows ) ) {
			return;
		}

		// Строка региона (первый столбец).
		rows.forEach( function ( tr ) {
			var first = cells( tr )[ 0 ];
			if ( first && matchesRegion( first.textContent, region ) ) {
				tr.classList.add( 'is-current-region' );
			}
		} );

		// Колонка региона (по заголовку, кроме первого столбца).
		var headCells = cells( rows[ 0 ] );
		headCells.forEach( function ( th, i ) {
			if ( 0 === i || 'TH' !== th.tagName ) {
				return;
			}
			if ( ! matchesRegion( th.textContent, region ) ) {
				return;
			}
			th.classList.add( 'is-current-region' );
			rows.forEach( function ( tr ) {
				var cs = cells( tr );
				if ( cs[ i ] ) {
					cs[ i ].classList.add( 'is-current-region' );
				}
			} );
		} );
	}

	function mark( region ) {
		if ( ! region ) {
			return;
		}
		Array.prototype.forEach.call(
			document.querySelectorAll( '.article-body table, .plant-content table, [data-sz-region-tables] table' ),
			function ( table ) {
				markTable( table, region );
			}
		);
	}

	mark( window.szRegion.getRegion() );
	window.szRegion.onChange( function ( region ) {
		mark( region );
	} );
})();
