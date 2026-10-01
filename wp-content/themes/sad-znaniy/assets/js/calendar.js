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

	var cfg = window.sadZnaniyCal || {};
	var today = new Date();
	var doneSet = new Set();
	try {
		( JSON.parse( localStorage.getItem( 'sad_znaniy_cal_done' ) || '[]' ) || [] ).forEach( function ( id ) { doneSet.add( id ); } );
	} catch ( e ) {}
	if ( cfg.loggedIn && Array.isArray( cfg.done ) ) {
		cfg.done.forEach( function ( id ) { doneSet.add( id ); } );
	}

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

	function saveDone() {
		try {
			localStorage.setItem( 'sad_znaniy_cal_done', JSON.stringify( Array.from( doneSet ) ) );
		} catch ( e ) {}
		if ( cfg.loggedIn && cfg.restUrl ) {
			try {
				fetch( cfg.restUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify( { ids: Array.from( doneSet ) } )
				} );
			} catch ( e ) {}
		}
	}

	function apply() {
		cards.forEach( function ( card ) {
			card.classList.toggle( 'hidden', ! matches( card ) );
		} );
		var visible = visibleCards();
		var doneCount = visible.filter( function ( c ) {
			return doneSet.has( parseInt( c.dataset.id, 10 ) );
		} ).length;
		var pNum = document.getElementById( 'pNum' );
		if ( pNum ) { pNum.textContent = doneCount + ' из ' + visible.length; }
		var pFill = document.getElementById( 'pFill' );
		if ( pFill ) { pFill.style.width = visible.length ? ( doneCount / visible.length * 100 ) + '%' : '0'; }
		renderMissed();
		renderDayPanel();
	}

	function renderMissed() {
		var isCur = ( curMonth === today.getMonth() + 1 && curYear === today.getFullYear() );
		cards.forEach( function ( card ) {
			var id = parseInt( card.dataset.id, 10 );
			var done = doneSet.has( id );
			card.classList.toggle( 'done', done );
			var missed = isCur && parseInt( card.dataset.d2, 10 ) < today.getDate() && ! done && ! card.classList.contains( 'hidden' );
			card.classList.toggle( 'missed', missed );
			var badge = card.querySelector( '.miss-badge' );
			if ( missed && ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = 'miss-badge';
				badge.textContent = 'Пропущено — запланируйте на следующий сезон';
				card.querySelector( '.t-top' ).appendChild( badge );
			} else if ( ! missed && badge ) {
				badge.parentNode.removeChild( badge );
			}
		} );
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

	document.getElementById( 'listView' ).addEventListener( 'click', function ( e ) {
		var cb = e.target.closest ? e.target.closest( '.cb' ) : null;
		if ( ! cb ) {
			return;
		}
		var task = cb.closest( '.task' );
		var id = parseInt( task.dataset.id, 10 );
		if ( doneSet.has( id ) ) { doneSet.delete( id ); } else { doneSet.add( id ); }
		saveDone();
		apply();
	} );

	var btnPdf = document.getElementById( 'btnPdf' );
	if ( btnPdf ) { btnPdf.addEventListener( 'click', function () { window.print(); } ); }
	var btnJpg = document.getElementById( 'btnJpg' );
	if ( btnJpg ) { btnJpg.addEventListener( 'click', exportJpg ); }
	var btnShare = document.getElementById( 'btnShare' );
	if ( btnShare ) { btnShare.addEventListener( 'click', share ); }

	function regionLabel() {
		var el = document.querySelector( '.chip.region.on' );
		return el ? el.textContent.trim() : 'Все регионы';
	}

	function roundRect( ctx, x, y, w, h, r ) {
		if ( ctx.roundRect ) { ctx.beginPath(); ctx.roundRect( x, y, w, h, r ); return; }
		ctx.beginPath();
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r );
		ctx.arcTo( x + w, y + h, x, y + h, r );
		ctx.arcTo( x, y + h, x, y, r );
		ctx.arcTo( x, y, x + w, y, r );
		ctx.closePath();
	}

	function exportJpg() {
		var list = visibleCards();
		var c = document.createElement( 'canvas' );
		c.width = 900;
		c.height = 210 + list.length * 54 + 80;
		var x = c.getContext( '2d' );
		x.fillStyle = '#F4F5F7';
		x.fillRect( 0, 0, c.width, c.height );
		x.fillStyle = '#2E6B4F';
		x.font = 'bold 22px system-ui';
		x.fillText( 'Сад знаний', 50, 60 );
		x.fillStyle = '#20241F';
		x.font = 'bold 32px system-ui';
		x.fillText( 'Календарь дачника — ' + monthName + ' ' + curYear, 50, 110 );
		x.fillStyle = '#5C645D';
		x.font = '19px system-ui';
		x.fillText( 'Регион: ' + regionLabel() + ' · znai-sad.ru', 50, 145 );
		list.forEach( function ( card, i ) {
			var y = 180 + i * 54;
			var title = card.querySelector( '.t-title' ).textContent;
			var done = doneSet.has( parseInt( card.dataset.id, 10 ) );
			var type = card.dataset.type;
			x.fillStyle = '#fff';
			x.strokeStyle = '#E3E6EA';
			roundRect( x, 50, y, 800, 44, 12 );
			x.fill();
			x.stroke();
			var color = getComputedStyle( document.documentElement ).getPropertyValue( '--c-' + type ).trim() || '#3FA46F';
			x.fillStyle = color;
			x.beginPath();
			x.arc( 78, y + 22, 8, 0, 7 );
			x.fill();
			x.fillStyle = '#20241F';
			x.font = '18px system-ui';
			x.fillText( ( card.dataset.d1 + '–' + card.dataset.d2 + '. ' + title ).slice( 0, 58 ), 100, y + 29 );
			x.fillStyle = done ? '#3FA46F' : '#c9ced4';
			roundRect( x, 790, y + 12, 20, 20, 6 );
			x.fill();
			if ( done ) {
				x.strokeStyle = '#fff';
				x.lineWidth = 3;
				x.beginPath();
				x.moveTo( 794, y + 22 );
				x.lineTo( 799, y + 27 );
				x.lineTo( 807, y + 16 );
				x.stroke();
			}
		} );
		var a = document.createElement( 'a' );
		a.download = 'calendar-' + curYear + '-' + String( curMonth ).padStart( 2, '0' ) + '.jpg';
		a.href = c.toDataURL( 'image/jpeg', 0.9 );
		a.click();
	}

	function share() {
		var list = visibleCards();
		var text = monthName + ' ' + curYear + ', ' + regionLabel() + ': ' + list.length + ' задач на участке';
		var url = window.location.origin + buildUrl();
		var data = { title: 'Календарь дачника — Сад знаний', text: text, url: url };
		if ( navigator.share ) {
			navigator.share( data ).catch( function () {} );
			return;
		}
		var btnShare = document.getElementById( 'btnShare' );
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text + ' → ' + url ).then( function () {
				btnShare.textContent = '✓ Ссылка скопирована';
				setTimeout( function () { btnShare.textContent = '🔗 Поделиться'; }, 2000 );
			} ).catch( function () { window.prompt( 'Скопируйте ссылку:', url ); } );
		} else {
			window.prompt( 'Скопируйте ссылку:', url );
		}
	}

	syncChips();
	if ( 'list' === S.view ) {
		vGrid.classList.remove( 'on' );
		vList.classList.add( 'on' );
		gridView.hidden = true;
		listView.hidden = false;
	}
	apply();
} )();

