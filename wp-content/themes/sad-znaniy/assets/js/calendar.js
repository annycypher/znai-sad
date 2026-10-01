/* ==========================================================
   УМНЫЙ КАЛЕНДАРЬ — клиентская фильтрация (Этап 5.5, Партия 2)
   SSR рендерит список на сервере; JS дублирует фильтры мгновенно
   и хранит выбор в localStorage (ключ sad_znaniy_cal).
   ========================================================== */
(function () {
	'use strict';

	var LS_KEY = 'sad_znaniy_cal';
	var wrap = document.querySelector( '.cal-wrap' );
	if ( ! wrap ) {
		return;
	}

	var curMonth = parseInt( wrap.dataset.month, 10 ) || new Date().getMonth() + 1;
	var curYear = parseInt( wrap.dataset.year, 10 ) || new Date().getFullYear();

	var params = new URLSearchParams( window.location.search );
	var saved = {};
	try {
		saved = JSON.parse( localStorage.getItem( LS_KEY ) || '{}' ) || {};
	} catch ( e ) {
		saved = {};
	}

	var S = {
		region: params.get( 'region' ) || saved.region || '',
		exp: params.get( 'exp' ) || saved.exp || '',
		types: new Set( Array.isArray( saved.types ) ? saved.types : [] ),
		view: saved.view === 'list' ? 'list' : 'grid',
		day: null
	};

	var cards = Array.prototype.slice.call( document.querySelectorAll( '#listView .task' ) );
	var monthName = ( ( document.querySelector( '.month-title' ) || {} ).textContent || '' ).replace( /\s*\d{4}$/, '' ).trim();

	function matches( card ) {
		var regions = ( card.dataset.regions || '' ).split( ',' );
		var exp = card.dataset.exp || 'all';
		var type = card.dataset.type || '';
		if ( S.region && regions.indexOf( 'all' ) === -1 && regions.indexOf( S.region ) === -1 ) {
			return false;
		}
		if ( 'new' === S.exp && 'exp' === exp ) {
			return false;
		}
		if ( S.types.size && ! S.types.has( type ) ) {
			return false;
		}
		return true;
	}

	function visibleCards() {
		return cards.filter( matches );
	}

	function save() {
		try {
			localStorage.setItem( LS_KEY, JSON.stringify( { region: S.region, exp: S.exp, types: Array.from( S.types ), view: S.view } ) );
		} catch ( e ) {}
	}

	function buildUrl() {
		var p = new URLSearchParams();
		p.set( 'year', curYear );
		p.set( 'month', curMonth );
		if ( S.region ) { p.set( 'region', S.region ); }
		if ( S.exp ) { p.set( 'exp', S.exp ); }
		return window.location.pathname + '?' + p.toString();
	}

	function apply() {
		cards.forEach( function ( card ) {
			card.classList.toggle( 'hidden', ! matches( card ) );
		} );
		var pNum = document.getElementById( 'pNum' );
		if ( pNum ) { pNum.textContent = '0 из ' + visibleCards().length; }
		renderDayPanel();
	}

	function syncChips() {
		document.querySelectorAll( '.chip.region' ).forEach( function ( x ) {
			x.classList.toggle( 'on', x.dataset.region === S.region );
		} );
		document.querySelectorAll( '.chip.exp' ).forEach( function ( x ) {
			x.classList.toggle( 'on', x.dataset.exp === S.exp );
		} );
		document.querySelectorAll( '.chip.wtype' ).forEach( function ( x ) {
			x.classList.toggle( 'on', S.types.has( x.dataset.type ) );
		} );
		document.querySelectorAll( '.mnav-btn' ).forEach( function ( a ) {
			var u = new URL( a.href, window.location.origin );
			if ( S.region ) { u.searchParams.set( 'region', S.region ); } else { u.searchParams.delete( 'region' ); }
			if ( S.exp ) { u.searchParams.set( 'exp', S.exp ); } else { u.searchParams.delete( 'exp' ); }
			a.href = u.pathname + u.search;
		} );
	}

	function updateUrl() {
		try { history.replaceState( null, '', buildUrl() ); } catch ( e ) {}
	}

	function renderDayPanel() {
		var dayPanel = document.getElementById( 'dayPanel' );
		var dpTitle = document.getElementById( 'dpTitle' );
		var dpTasks = document.getElementById( 'dpTasks' );
		document.querySelectorAll( '.day[data-day]' ).forEach( function ( el ) {
			el.classList.toggle( 'sel', parseInt( el.dataset.day, 10 ) === S.day );
		} );

		if ( null === S.day ) {
			dayPanel.hidden = true;
			return;
		}
		var dayTasks = visibleCards().filter( function ( card ) {
			var d1 = parseInt( card.dataset.d1, 10 );
			var d2 = parseInt( card.dataset.d2, 10 );
			return d1 <= S.day && S.day <= d2;
		} );
		dayPanel.hidden = false;
		dpTitle.textContent = S.day + ' ' + monthName + ' — ' + ( dayTasks.length ? dayTasks.length + ' задач' : 'задач нет' );
		dpTasks.innerHTML = dayTasks.length
			? dayTasks.map( function ( card ) {
				return '<div class="dp-task"><i class="dp-dot mk ' + card.dataset.type + '"></i>' + card.querySelector( '.t-title' ).textContent + '</div>';
			} ).join( '' )
			: '<div class="dp-task">Отдыхайте 🌿 Задач на этот день нет.</div>';
	}

	document.querySelectorAll( '.chip.region' ).forEach( function ( b ) {
		b.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			S.region = ( b.dataset.region === S.region ) ? '' : b.dataset.region;
			save();
			syncChips();
			apply();
			updateUrl();
		} );
	} );

	document.querySelectorAll( '.chip.exp' ).forEach( function ( b ) {
		b.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			S.exp = 'new' === b.dataset.exp ? 'new' : 'all';
			save();
			syncChips();
			apply();
			updateUrl();
		} );
	} );

	document.querySelectorAll( '.chip.wtype' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var t = b.dataset.type;
			if ( S.types.has( t ) ) { S.types.delete( t ); } else { S.types.add( t ); }
			save();
			syncChips();
			apply();
		} );
	} );

	var vGrid = document.getElementById( 'vGrid' );
	var vList = document.getElementById( 'vList' );
	var gridView = document.getElementById( 'gridView' );
	var listView = document.getElementById( 'listView' );
	vGrid.addEventListener( 'click', function () {
		S.view = 'grid';
		vGrid.classList.add( 'on' );
		vList.classList.remove( 'on' );
		gridView.hidden = false;
		listView.hidden = true;
		save();
	} );
	vList.addEventListener( 'click', function () {
		S.view = 'list';
		vList.classList.add( 'on' );
		vGrid.classList.remove( 'on' );
		gridView.hidden = true;
		listView.hidden = false;
		save();
	} );

	document.querySelectorAll( '.day[data-day]' ).forEach( function ( el ) {
		el.addEventListener( 'click', function () {
			var d = parseInt( el.dataset.day, 10 );
			S.day = ( S.day === d ) ? null : d;
			renderDayPanel();
		} );
		el.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				el.click();
			}
		} );
	} );

	syncChips();
	if ( 'list' === S.view ) {
		vGrid.classList.remove( 'on' );
		vList.classList.add( 'on' );
		gridView.hidden = true;
		listView.hidden = false;
	}
	apply();
} )();

