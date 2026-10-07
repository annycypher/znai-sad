/* ==========================================================
   ЕДИНЫЙ РЕГИОНАЛЬНЫЙ КОНТУР (Этап 5.7, Партия A)
   Хранилище региона: localStorage (ключ sz_region) + cookie
   sz_region (для серверного рендера). Событие: sz:region-change.
   API: window.szRegion.getRegion() / setRegion() / regions / onChange()
   ========================================================== */
(function () {
	'use strict';

	var cfg = window.szRegionData || {};
	var LS_KEY = cfg.key || 'sz_region';
	var ASK_KEY = cfg.askedKey || 'sz_region_asked';
	var LEGACY_KEY = cfg.legacyKey || 'sad_znaniy_cal';
	var EMPTY = cfg.emptyLabel || 'Выберите регион';
	var regions = Array.isArray( cfg.regions ) ? cfg.regions : [];
	var current = '';

	function valid( key ) {
		return !! key && regions.some( function ( r ) { return r.key === key; } );
	}

	function label( key ) {
		var found = regions.filter( function ( r ) { return r.key === key; } )[0];
		return found ? found.label : '';
	}

	function readStored() {
		var val = '';
		try {
			val = localStorage.getItem( LS_KEY ) || '';
		} catch ( e ) {
			val = '';
		}

		// Миграция старого значения из календаря (sad_znaniy_cal.region).
		if ( ! valid( val ) ) {
			try {
				var legacy = JSON.parse( localStorage.getItem( LEGACY_KEY ) || '{}' ) || {};
				if ( valid( legacy.region ) ) {
					val = legacy.region;
					localStorage.setItem( LS_KEY, val );
				}
			} catch ( e ) {}
		}

		if ( ! valid( val ) ) {
			val = valid( cfg.current ) ? cfg.current : '';
		}
		return val;
	}

	function setCookie( key ) {
		var expires = new Date();
		expires.setTime( expires.getTime() + 365 * 24 * 60 * 60 * 1000 );
		document.cookie = LS_KEY + '=' + encodeURIComponent( key ) + ';expires=' +
			expires.toUTCString() + ';path=/;SameSite=Lax';
	}

	function getRegion() {
		return current;
	}

	function setRegion( key ) {
		key = valid( key ) ? key : '';
		if ( key === current ) {
			return current;
		}
		current = key;

		try {
			if ( key ) {
				localStorage.setItem( LS_KEY, key );
			} else {
				localStorage.removeItem( LS_KEY );
			}
		} catch ( e ) {}
		setCookie( key );

		syncUI();
		document.dispatchEvent( new CustomEvent( 'sz:region-change', { detail: { region: key, label: label( key ) } } ) );

		return current;
	}

	function onChange( cb ) {
		document.addEventListener( 'sz:region-change', function ( e ) {
			cb( e.detail.region, e.detail );
		} );
	}

	function syncUI() {
		var text = current ? label( current ) : EMPTY;
		document.querySelectorAll( '[data-sz-region-label]' ).forEach( function ( el ) {
			el.textContent = text;
		} );
		document.querySelectorAll( '[data-sz-region-option]' ).forEach( function ( el ) {
			el.classList.toggle( 'on', el.getAttribute( 'data-sz-region-option' ) === current );
		} );
	}

	function askWidget() {
		return document.querySelector( '[data-sz-region-ask]' );
	}

	function asked() {
		try {
			localStorage.setItem( ASK_KEY, '1' );
		} catch ( e ) {}
	}

	function alreadyAsked() {
		try {
			return !! localStorage.getItem( ASK_KEY );
		} catch ( e ) {
			return true;
		}
	}

	function showAsk() {
		var el = askWidget();
		if ( el && ! current ) {
			el.hidden = false;
		}
	}

	function hideAsk() {
		var el = askWidget();
		if ( el ) {
			el.hidden = true;
		}
	}

	// Делегирование кликов: выбор региона и «Позже».
	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		var option = target.closest( '[data-sz-region-option]' );
		if ( option ) {
			if ( 'A' === option.tagName ) {
				e.preventDefault(); // без перезагрузки — блоки перерисует JS
			}
			setRegion( option.getAttribute( 'data-sz-region-option' ) );
			var box = option.closest( 'details' );
			if ( box ) {
				box.removeAttribute( 'open' );
			}
			hideAsk();
			return;
		}

		if ( target.closest( '[data-sz-region-later]' ) ) {
			asked();
			hideAsk();
		}
	} );

	// Закрываем выпадающий список по клику вне его.
	document.addEventListener( 'click', function ( e ) {
		document.querySelectorAll( 'details.region-chip[open]' ).forEach( function ( d ) {
			if ( ! d.contains( e.target ) ) {
				d.removeAttribute( 'open' );
			}
		} );
	} );

	// Escape закрывает список.
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			document.querySelectorAll( 'details.region-chip[open]' ).forEach( function ( d ) {
				d.removeAttribute( 'open' );
			} );
		}
	} );

	window.szRegion = {
		getRegion: getRegion,
		setRegion: setRegion,
		regions: regions,
		label: label,
		onChange: onChange
	};

	current = readStored();
	if ( current ) {
		setCookie( current );
	}
	syncUI();

	// Виджет первого визита: один раз и только если регион не выбран.
	if ( ! current && ! alreadyAsked() ) {
		window.setTimeout( showAsk, 1200 );
	}
})();
