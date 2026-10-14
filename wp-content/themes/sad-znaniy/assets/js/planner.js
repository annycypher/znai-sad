/**
 * Планировщик участка (Этап 6, Партия 1) — каркас интерфейса и canvas.
 *
 * Vanilla JS + Canvas без библиотек: rAF-батчинг отрисовки, пакетная сетка
 * (все линии группы — одним stroke), данные — из window.sadZnaniyPlan.data.
 * Разметка и стили — template-planner.php + assets/css/planner.css (#plnRoot).
 */
( function () {
	'use strict';

	var root = document.getElementById( 'plnRoot' );
	if ( ! root ) {
		return;
	}

	var CFG = ( window.sadZnaniyPlan && window.sadZnaniyPlan.data ) || {};
	var MIGR = window.szPlanMigrations || { current: 1, run: function ( p ) { return p; } };
	var $ = function ( id ) { return document.getElementById( id ); };

	/* ===== типы элементов — демо 1:1 (спекa, §2) ===== */
	var TYPES = {
		bed:   { name: 'Грядка',      w: 1,   h: 3,   crop: true },
		tree:  { name: 'Дерево',      w: 2.5, h: 2.5, crop: true, shadow: 2.5 },
		bush:  { name: 'Куст',        w: 1.5, h: 1.5, crop: true, shadow: 1 },
		house: { name: 'Дом',         w: 6,   h: 8,   shadow: 3 },
		build: { name: 'Постройка',   w: 2,   h: 3 },
		green: { name: 'Теплица',     w: 2,   h: 4,   crop: true },
		comp:  { name: 'Компост',     w: 1.5, h: 1.5 },
		path:  { name: 'Дорожка',     w: 1,   h: 4 },
		water: { name: 'Водоём',      w: 2,   h: 2 },
		rest:  { name: 'Зона отдыха', w: 3,   h: 3 }
	};

	var SNAP = 0.5, PMIN = 4, PMAX = 100;
	var MOBILE = 961; /* px — порог «шторки вместо колонок» (как в демо) */

	/* ===== культуры — из CPT plant (спекa, §0 и §7) ===== */
	var CROPS = ( CFG.plants || [] ).map( function ( p ) {
		return {
			id: String( p.id ),
			name: p.title || '',
			light: p.light || '',
			water: p.water || '',
			soil: p.soil || '',
			good: p.good || '',
			bad: p.bad || '',
			ph: p.ph || [ 0, 0 ],
			url: p.url || ''
		};
	} );
	function cropById( id ) {
		for ( var i = 0; i < CROPS.length; i++ ) {
			if ( CROPS[ i ].id === String( id ) ) {
				return CROPS[ i ];
			}
		}
		return null;
	}
	/* единый словарь полива: low/moderate/high → русские слова (из WP-словаря) */
	function waterLabel( key ) {
		var map = CFG.water || {};
		return map[ String( key ) ] || String( key || '' );
	}

	/* ===== состояние ===== */
	var planId = uid();
	var plot = { w: 20, h: 30 };
	var items = [], selId = null, tool = null, showDims = true;
	var history = [], future = [];
	var drag = null, pinch = null;
	var pointers = new Map();
	var view = { x: 20, y: 20, s: 12 };
	var VW = 100, VH = 100, dpr = 1, rafId = 0;
	var saveT = null;

	var shell = $( 'shell' ), cv = $( 'board' ), cx = cv.getContext( '2d' );

	function uid() {
		return Math.random().toString( 36 ).slice( 2, 9 );
	}

	/* ===== цвета из design-tokens.css ===== */
	var colorCache = null;
	function typeColor( t ) {
		if ( ! colorCache ) {
			var cs = getComputedStyle( document.documentElement );
			colorCache = {};
			Object.keys( TYPES ).forEach( function ( k ) {
				colorCache[ k ] = cs.getPropertyValue( '--pln-' + k ).trim() || '#999999';
			} );
			colorCache.handle = cs.getPropertyValue( '--pln-handle' ).trim() || 'rgba(46,107,79,.35)';
			colorCache.handleInk = cs.getPropertyValue( '--pln-handle-ink' ).trim() || '#2E6B4F';
		}
		return colorCache[ t ] || '#999999';
	}
	function handleInk() { typeColor( 'bed' ); return colorCache.handleInk; }
	function handleSoft() { typeColor( 'bed' ); return colorCache.handle; }

	/* ===== хранение (спекa, §1): sz_plan_{uuid} + sz_plan_index ===== */
	var LS_INDEX = 'sz_plan_index';
	function lsGet( k ) {
		try { return JSON.parse( localStorage.getItem( k ) ); } catch ( e ) { return null; }
	}
	function lsSet( k, v ) {
		try { localStorage.setItem( k, JSON.stringify( v ) ); return true; } catch ( e ) { return false; }
	}
	function planSnapshot() {
		return {
			version: MIGR.current,
			id: planId,
			meta: {
				title: $('planName').value || 'Мой участок',
				region: $('plnRegion').value || CFG.currentRegion || 'mid',
				updated: new Date().toISOString()
			},
			plot: { shape: 'rect', width: plot.w, height: plot.h, north: 'up' },
			elements: items.map( function ( it ) {
				return { id: it.id, type: it.type, x: it.x, y: it.y, width: it.w, height: it.h, rotation: 0, crop: it.crop || null, label: '' };
			} ),
			gridStep: SNAP
		};
	}
	function planLoad( id ) {
		var p = lsGet( 'sz_plan_' + id );
		if ( ! p ) {
			return false;
		}
		p = MIGR.run( p );
		planId = p.id || id;
		if ( p.meta ) {
			if ( p.meta.title ) { $('planName').value = p.meta.title; }
			if ( p.meta.region ) { $('plnRegion').value = p.meta.region; }
		}
		if ( p.plot ) {
			plot.w = Number( p.plot.width ) || 20;
			plot.h = Number( p.plot.height ) || 30;
		}
		items = ( p.elements || [] ).map( function ( e ) {
			return {
				id: e.id || uid(),
				type: TYPES[ e.type ] ? e.type : 'bed',
				x: Number( e.x ) || 0,
				y: Number( e.y ) || 0,
				w: Number( e.width ) || 1,
				h: Number( e.height ) || 1,
				crop: e.crop ? String( e.crop ) : null
			};
		} );
		return true;
	}
	function planIndex() {
		var idx = lsGet( LS_INDEX );
		return Array.isArray( idx ) ? idx : [];
	}
	function planIndexPut() {
		var idx = planIndex().filter( function ( r ) { return r.id !== planId; } );
		idx.unshift( {
			id: planId,
			title: $('planName').value || 'Мой участок',
			region: $('plnRegion').value || '',
			updated: new Date().toISOString(),
			elements: items.length
		} );
		lsSet( LS_INDEX, idx.slice( 0, 30 ) );
	}
	function savedMark( text ) {
		var mark = $('savedMark');
		if ( ! mark ) { return; }
		mark.textContent = text;
	}
	function doSave( silent ) {
		var ok = lsSet( 'sz_plan_' + planId, planSnapshot() );
		planIndexPut();
		savedMark( ok ? 'сохранено ✓' : 'не сохранено' );
		setTimeout( function () { savedMark( 'сохранено' ); }, 1500 );
		if ( ! ok ) {
			toast( 'red', 'Не удалось сохранить', 'Браузер не разрешил запись в localStorage — проверьте настройки приватности.' );
		} else if ( ! silent ) {
			toast( 'green', 'План сохранён', 'На этом устройстве. Войдите в личный кабинет — план будет доступен всегда.' );
		}
		return ok;
	}
	function saveDebounced() {
		clearTimeout( saveT );
		saveT = setTimeout( function () { doSave( true ); }, 800 );
	}

	/* ===== вид: размеры, зум, панорама ===== */
	function fitRoot() {
		var rect = root.getBoundingClientRect();
		var docTop = rect.top + ( window.pageYOffset || document.documentElement.scrollTop || 0 );
		var h = Math.round( window.innerHeight - docTop - 20 );
		root.style.height = Math.max( 480, h ) + 'px';
	}
	function resizeCanvas() {
		var r = shell.getBoundingClientRect();
		if ( r.width < 10 || r.height < 10 ) {
			requestAnimationFrame( resizeCanvas );
			return;
		}
		dpr = Math.min( window.devicePixelRatio || 1, 2 );
		VW = r.width;
		VH = r.height;
		cv.width = Math.round( VW * dpr );
		cv.height = Math.round( VH * dpr );
		cv.style.width = VW + 'px';
		cv.style.height = VH + 'px';
		cx.setTransform( dpr, 0, 0, dpr, 0, 0 );
		fitView();
		draw();
	}
	function bottomReserve() {
		var bl = $( 'bottomline' );
		return bl && ! bl.hidden ? bl.offsetHeight + 22 : 14;
	}
	function fitView() {
		var m = 24, reserve = bottomReserve();
		var sFit = Math.min( ( VW - 2 * m ) / plot.w, ( VH - reserve - 2 * m ) / plot.h );
		view.s = Math.max( 7, Math.min( sFit, 18 ) );
		view.x = ( VW - plot.w * view.s ) / 2;
		view.y = Math.max( m, ( VH - reserve - plot.h * view.s ) / 2 );
		clampView();
	}
	function clampView() {
		var w = plot.w * view.s, h = plot.h * view.s, pad = 60;
		view.x = Math.min( Math.max( view.x, pad - w ), VW - pad );
		view.y = Math.min( Math.max( view.y, pad - h ), VH - pad );
	}
	function zoomAt( px, py, f ) {
		var s2 = Math.min( 48, Math.max( 4, view.s * f ) );
		var k = s2 / view.s;
		view.x = px - ( px - view.x ) * k;
		view.y = py - ( py - view.y ) * k;
		view.s = s2;
		clampView();
		draw();
	}
	function fmt( v ) {
		return ( v + '' ).replace( '.', ',' );
	}
	function snap( v ) {
		return Math.round( v / SNAP ) * SNAP;
	}
	function rr( g, x, y, w, h, r ) {
		r = Math.min( r, w / 2, h / 2 );
		g.beginPath();
		g.moveTo( x + r, y );
		g.arcTo( x + w, y, x + w, y + h, r );
		g.arcTo( x + w, y + h, x, y + h, r );
		g.arcTo( x, y + h, x, y, r );
		g.arcTo( x, y, x + w, y, r );
		g.closePath();
	}

	/* ===== отрисовка: rAF-батчинг + пакетные штрихи сетки ===== */
	function draw() {
		if ( rafId ) {
			return;
		}
		rafId = requestAnimationFrame( function () { rafId = 0; renderNow(); } );
	}
	function drawGrid( g, ox, oy, s ) {
		if ( s >= 7 ) {
			g.strokeStyle = 'rgba(46,107,79,.06)';
			g.lineWidth = 1;
			g.beginPath();
			var m;
			for ( m = 0.5; m < plot.w; m += 0.5 ) { g.moveTo( ox + m * s, oy ); g.lineTo( ox + m * s, oy + plot.h * s ); }
			for ( m = 0.5; m < plot.h; m += 0.5 ) { g.moveTo( ox, oy + m * s ); g.lineTo( ox + plot.w * s, oy + m * s ); }
			g.stroke();
		}
		if ( s >= 3 ) {
			g.strokeStyle = 'rgba(46,107,79,.16)';
			g.lineWidth = 1.2;
			g.beginPath();
			var m1;
			for ( m1 = 1; m1 < plot.w; m1 += 1 ) { g.moveTo( ox + m1 * s, oy ); g.lineTo( ox + m1 * s, oy + plot.h * s ); }
			for ( m1 = 1; m1 < plot.h; m1 += 1 ) { g.moveTo( ox, oy + m1 * s ); g.lineTo( ox + plot.w * s, oy + m1 * s ); }
			g.stroke();
		}
		if ( s >= 2.2 ) {
			g.strokeStyle = 'rgba(46,107,79,.28)';
			g.lineWidth = 1.4;
			g.beginPath();
			var m5;
			for ( m5 = 5; m5 < plot.w; m5 += 5 ) { g.moveTo( ox + m5 * s, oy ); g.lineTo( ox + m5 * s, oy + plot.h * s ); }
			for ( m5 = 5; m5 < plot.h; m5 += 5 ) { g.moveTo( ox, oy + m5 * s ); g.lineTo( ox + plot.w * s, oy + m5 * s ); }
			g.stroke();
		}
		g.strokeStyle = 'rgba(46,107,79,.5)';
		g.lineWidth = 2;
		g.strokeRect( ox, oy, plot.w * s, plot.h * s );
	}
	/* подпись «Ш × Д м» под элементом с короткой выносной линией */
	function drawElemLabel( g, x, y, w, h, text ) {
		var mx = x + w / 2;
		g.strokeStyle = 'rgba(46,107,79,.55)';
		g.lineWidth = 1;
		g.beginPath();
		g.moveTo( mx, y + h + 3 );
		g.lineTo( mx, y + h + 11 );
		g.stroke();
		g.font = '10.5px system-ui';
		g.textAlign = 'center';
		var tw = g.measureText( text ).width;
		g.fillStyle = 'rgba(255,255,255,.88)';
		g.fillRect( mx - tw / 2 - 4, y + h + 12, tw + 8, 14 );
		g.fillStyle = '#3E4A40';
		g.fillText( text, mx, y + h + 23 );
	}
	/* выносные линии выделенного элемента — снизу и справа (как раньше) */
	function drawSelDims( g, x, y, w, h, it ) {
		g.strokeStyle = 'rgba(46,107,79,.65)';
		g.fillStyle = handleInk();
		g.lineWidth = 1;
		g.font = '10.5px system-ui';
		g.textAlign = 'center';
		g.beginPath();
		g.moveTo( x, y + h + 10 );
		g.lineTo( x + w, y + h + 10 );
		g.stroke();
		g.fillText( fmt( it.w ) + ' м', x + w / 2, y + h + 22 );
		g.beginPath();
		g.moveTo( x + w + 10, y );
		g.lineTo( x + w + 10, y + h );
		g.stroke();
		g.save();
		g.translate( x + w + 22, y + h / 2 );
		g.rotate( -Math.PI / 2 );
		g.fillText( fmt( it.h ) + ' м', 0, 0 );
		g.restore();
	}

	/* ===== фигуры: дерево — «облачком», куст — кругами, прочее — скруглённый прямоугольник ===== */
	function drawItemShape( g, it, x, y, w, h, sel ) {
		var c = it.crop ? cropById( it.crop ) : null;
		var i;
		if ( it.type === 'tree' ) {
			var r = Math.min( w, h ) / 2, mx = x + w / 2, my = y + h / 2;
			var blobs = [ [ -0.35, -0.25, 0.62 ], [ 0.35, -0.28, 0.58 ], [ -0.05, -0.5, 0.5 ], [ 0.42, 0.12, 0.45 ], [ -0.45, 0.15, 0.45 ], [ 0, 0.35, 0.4 ] ];
			g.fillStyle = sel ? '#4C7F4B' : typeColor( 'tree' );
			g.beginPath();
			g.arc( mx, my, r, 0, 6.2832 );
			g.fill();
			g.beginPath();
			for ( i = 0; i < blobs.length; i++ ) {
				g.moveTo( mx + blobs[ i ][ 0 ] * w + blobs[ i ][ 2 ] * r * 0.55, my + blobs[ i ][ 1 ] * h );
				g.arc( mx + blobs[ i ][ 0 ] * w, my + blobs[ i ][ 1 ] * h, blobs[ i ][ 2 ] * r * 0.55, 0, 6.2832 );
			}
			g.fill();
			g.fillStyle = '#6E6252';
			g.fillRect( mx - r * 0.09, y + h - r * 0.4, r * 0.18, r * 0.45 );
			g.strokeStyle = 'rgba(0,0,0,.22)';
			g.lineWidth = 1.2;
			g.beginPath();
			g.arc( mx, my, r, 0, 6.2832 );
			g.stroke();
		} else if ( it.type === 'bush' ) {
			var br = Math.min( w, h ) / 2, bx = x + w / 2, by = y + h / 2;
			var parts = [ [ 0, -0.2, 0.6 ], [ -0.3, 0.1, 0.5 ], [ 0.3, 0.1, 0.5 ], [ 0, 0.25, 0.45 ] ];
			g.fillStyle = sel ? '#7FB06B' : typeColor( 'bush' );
			for ( i = 0; i < parts.length; i++ ) {
				g.beginPath();
				g.arc( bx + parts[ i ][ 0 ] * w, by + parts[ i ][ 1 ] * h, parts[ i ][ 2 ] * br * 0.9, 0, 6.2832 );
				g.fill();
			}
			g.strokeStyle = 'rgba(0,0,0,.2)';
			g.lineWidth = 1.1;
			for ( i = 0; i < parts.length; i++ ) {
				g.beginPath();
				g.arc( bx + parts[ i ][ 0 ] * w, by + parts[ i ][ 1 ] * h, parts[ i ][ 2 ] * br * 0.9, 0, 6.2832 );
				g.stroke();
			}
		} else {
			g.fillStyle = typeColor( it.type );
			rr( g, x, y, w, h, Math.min( 10, w / 4, h / 4 ) );
			g.fill();
			g.strokeStyle = 'rgba(0,0,0,.18)';
			g.lineWidth = 1.2;
			g.stroke();
		}
		if ( w > 34 && h > 20 ) {
			g.fillStyle = 'rgba(255,255,255,.95)';
			g.font = 'bold ' + Math.min( 12, Math.max( 9, w / 8 ) ) + 'px system-ui';
			g.textAlign = 'center';
			g.fillText( c ? c.name : TYPES[ it.type ].name, x + w / 2, y + h / 2 + 4 );
		}
	}

	/* ===== кадр на экране ===== */
	function renderNow() {
		cx.clearRect( 0, 0, VW, VH );
		var ox = view.x, oy = view.y, s = view.s;
		cx.fillStyle = 'rgba(252,251,248,.96)';
		cx.fillRect( ox, oy, plot.w * s, plot.h * s );
		drawGrid( cx, ox, oy, s );
		if ( showDims ) {
			cx.fillStyle = '#5C645D';
			cx.font = '11px system-ui';
			cx.textAlign = 'center';
			cx.fillText( fmt( plot.w ) + ' м', ox + plot.w * s / 2, oy - 8 );
			cx.save();
			cx.translate( ox - 10, oy + plot.h * s / 2 );
			cx.rotate( -Math.PI / 2 );
			cx.fillText( fmt( plot.h ) + ' м', 0, 0 );
			cx.restore();
		}
		items.forEach( function ( it ) {
			if ( TYPES[ it.type ].shadow ) {
				cx.fillStyle = 'rgba(80,95,110,.12)';
				cx.fillRect( ox + it.x * s, oy + ( it.y + it.h ) * s, it.w * s, TYPES[ it.type ].shadow * s );
			}
		} );
		items.forEach( function ( it ) {
			var x = ox + it.x * s, y = oy + it.y * s, w = it.w * s, h = it.h * s, sel = it.id === selId;
			drawItemShape( cx, it, x, y, w, h, sel );
			/* зоны захвата: у КАЖДОГО элемента — маленькие зелёные точки в 4 углах (при зуме ≥ 5 px/м) */
			if ( s >= 5 ) {
				cx.fillStyle = handleSoft();
				[ [ x, y ], [ x + w, y ], [ x, y + h ], [ x + w, y + h ] ].forEach( function ( p ) {
					cx.beginPath();
					cx.arc( p[ 0 ], p[ 1 ], 2.5, 0, 6.2832 );
					cx.fill();
				} );
			}
			if ( sel ) {
				cx.strokeStyle = handleInk();
				cx.lineWidth = 2.5;
				cx.setLineDash( [ 6, 4 ] );
				cx.strokeRect( x - 3, y - 3, w + 6, h + 6 );
				cx.setLineDash( [] );
				[ [ x, y ], [ x + w, y ], [ x, y + h ], [ x + w, y + h ] ].forEach( function ( p ) {
					cx.fillStyle = handleInk();
					cx.strokeStyle = '#fff';
					cx.lineWidth = 2;
					cx.beginPath();
					cx.arc( p[ 0 ], p[ 1 ], 6, 0, 6.2832 );
					cx.fill();
					cx.stroke();
				} );
			}
		} );
		if ( showDims ) {
			items.forEach( function ( it ) {
				var x = ox + it.x * s, y = oy + it.y * s, w = it.w * s, h = it.h * s;
				if ( it.id === selId ) {
					drawSelDims( cx, x, y, w, h, it );
					return;
				}
				if ( w >= 46 && h >= 34 ) {
					drawElemLabel( cx, x, y, w, h, fmt( it.w ) + '×' + fmt( it.h ) + ' м' );
				}
			} );
		}
		/* углы участка — когда ничего не выделено (тянутся и меняют размер участка) */
		if ( ! selId ) {
			[ [ ox, oy ], [ ox + plot.w * s, oy ], [ ox, oy + plot.h * s ], [ ox + plot.w * s, oy + plot.h * s ] ].forEach( function ( c ) {
				cx.fillStyle = handleInk();
				cx.strokeStyle = '#fff';
				cx.lineWidth = 2;
				cx.beginPath();
				cx.arc( c[ 0 ], c[ 1 ], 6, 0, 6.2832 );
				cx.fill();
				cx.stroke();
			} );
		}
	}

	/* ===== чистовик ИЗ ДАННЫХ: JPG и печать берут картинку только отсюда ===== */
	function drawScene( g, ox, oy, s, opt ) {
		opt = opt || {};
		g.fillStyle = '#FCFBF8';
		g.fillRect( ox, oy, plot.w * s, plot.h * s );
		drawGrid( g, ox, oy, s );
		if ( opt.dims ) {
			g.fillStyle = '#5C645D';
			g.font = '11px system-ui';
			g.textAlign = 'center';
			g.fillText( fmt( plot.w ) + ' м', ox + plot.w * s / 2, oy - 8 );
			g.save();
			g.translate( ox - 10, oy + plot.h * s / 2 );
			g.rotate( -Math.PI / 2 );
			g.fillText( fmt( plot.h ) + ' м', 0, 0 );
			g.restore();
		}
		items.forEach( function ( it ) {
			if ( TYPES[ it.type ].shadow ) {
				g.fillStyle = 'rgba(80,95,110,.12)';
				g.fillRect( ox + it.x * s, oy + ( it.y + it.h ) * s, it.w * s, TYPES[ it.type ].shadow * s );
			}
		} );
		items.forEach( function ( it ) {
			drawItemShape( g, it, ox + it.x * s, oy + it.y * s, it.w * s, it.h * s, false );
		} );
		if ( opt.dims ) {
			items.forEach( function ( it ) {
				var x = ox + it.x * s, y = oy + it.y * s, w = it.w * s, h = it.h * s;
				if ( w >= 46 && h >= 34 ) {
					drawElemLabel( g, x, y, w, h, fmt( it.w ) + '×' + fmt( it.h ) + ' м' );
				}
			} );
		}
	}
	function renderPlanCanvas() {
		var m = 30, s = Math.min( 880 / plot.w, 1150 / plot.h, 28 );
		var c = document.createElement( 'canvas' );
		c.width = Math.ceil( plot.w * s + 2 * m );
		c.height = Math.ceil( plot.h * s + 2 * m );
		drawScene( c.getContext( '2d' ), m, m, s, { dims: true } );
		return c;
	}

	/* ===== указатели: перенос, размер за углы, углы участка, панорама, щипок ===== */
	function selItem() {
		for ( var i = 0; i < items.length; i++ ) {
			if ( items[ i ].id === selId ) {
				return items[ i ];
			}
		}
		return null;
	}
	function HANDLEm() { return 12 / view.s; }
	function pos( e ) {
		var r = cv.getBoundingClientRect();
		return { x: ( e.clientX - r.left - view.x ) / view.s, y: ( e.clientY - r.top - view.y ) / view.s };
	}
	function hit( p ) {
		for ( var i = items.length - 1; i >= 0; i-- ) {
			var it = items[ i ];
			if ( p.x >= it.x && p.x <= it.x + it.w && p.y >= it.y && p.y <= it.y + it.h ) {
				return it;
			}
		}
		return null;
	}
	function offCorner( p, x, y, w, h ) {
		var H = HANDLEm();
		var cs = [ [ x, y, 'nw' ], [ x + w, y, 'ne' ], [ x, y + h, 'sw' ], [ x + w, y + h, 'se' ] ];
		for ( var i = 0; i < cs.length; i++ ) {
			if ( Math.abs( p.x - cs[ i ][ 0 ] ) <= H && Math.abs( p.y - cs[ i ][ 1 ] ) <= H ) {
				return cs[ i ][ 2 ];
			}
		}
		return null;
	}
	/* угол элемента: сначала выделенного (у него крупные маркеры), потом остальных */
	function itemCorner( p ) {
		var sel = selItem();
		if ( sel ) {
			var c0 = offCorner( p, sel.x, sel.y, sel.w, sel.h );
			if ( c0 ) { return { it: sel, c: c0 }; }
		}
		for ( var i = items.length - 1; i >= 0; i-- ) {
			var it = items[ i ];
			if ( it.id === selId ) { continue; }
			var c = offCorner( p, it.x, it.y, it.w, it.h );
			if ( c ) { return { it: it, c: c }; }
		}
		return null;
	}
	function plotCorner( p ) {
		var H = HANDLEm();
		var cs = [ [ 0, 0, 'nw' ], [ plot.w, 0, 'ne' ], [ 0, plot.h, 'sw' ], [ plot.w, plot.h, 'se' ] ];
		for ( var i = 0; i < cs.length; i++ ) {
			if ( Math.abs( p.x - cs[ i ][ 0 ] ) <= H && Math.abs( p.y - cs[ i ][ 1 ] ) <= H ) {
				return cs[ i ][ 2 ];
			}
		}
		return null;
	}

	cv.addEventListener( 'pointerdown', function ( e ) {
		cv.setPointerCapture( e.pointerId );
		pointers.set( e.pointerId, { x: e.clientX, y: e.clientY } );
		if ( pointers.size === 2 ) {
			drag = null;
			var pp = Array.from( pointers.values() );
			pinch = {
				d0: Math.hypot( pp[ 0 ].x - pp[ 1 ].x, pp[ 0 ].y - pp[ 1 ].y ),
				s0: view.s,
				vx: view.x,
				vy: view.y,
				mx: ( pp[ 0 ].x + pp[ 1 ].x ) / 2,
				my: ( pp[ 0 ].y + pp[ 1 ].y ) / 2
			};
			return;
		}
		var pt = pos( e );
		/* 1) угол элемента — размер */
		var ch = itemCorner( pt );
		if ( ch ) {
			if ( selId !== ch.it.id ) { selId = ch.it.id; refreshPanels(); }
			pushH();
			drag = { mode: 'resize', it: ch.it, c: ch.c, orig: { w: ch.it.w, h: ch.it.h, x: ch.it.x, y: ch.it.y } };
			return;
		}
		/* 2) угол участка — размер участка */
		var pc = plotCorner( pt );
		if ( pc ) {
			drag = { mode: 'plot', c: pc, ow: plot.w, oh: plot.h };
			return;
		}
		var t = hit( pt );
		/* 3) Alt+клик — копия */
		if ( t && e.altKey ) {
			pushH();
			var copy = { id: uid(), type: t.type, crop: t.crop, w: t.w, h: t.h,
				x: snap( Math.min( t.x + 0.5, Math.max( 0, plot.w - t.w ) ) ),
				y: snap( Math.min( t.y + 0.5, Math.max( 0, plot.h - t.h ) ) ) };
			items.push( copy );
			selId = copy.id;
			drag = { mode: 'move', it: copy, dx: pt.x - copy.x, dy: pt.y - copy.y, moved: true };
			refreshPanels();
			return;
		}
		/* 4) инструмент — новый элемент */
		if ( tool && ! t ) {
			pushH();
			var T = TYPES[ tool ];
			var it = {
				id: uid(),
				type: tool,
				x: snap( Math.max( 0, Math.min( pt.x - T.w / 2, plot.w - T.w ) ) ),
				y: snap( Math.max( 0, Math.min( pt.y - T.h / 2, plot.h - T.h ) ) ),
				w: T.w,
				h: T.h,
				crop: null
			};
			items.push( it );
			selId = it.id;
			checkNew( it );
			refreshPanels();
			draw();
			toast( 'green', 'Элемент добавлен', 'Тяните за зелёные углы — размер, за центр — перенос.' );
			if ( ! e.shiftKey ) {
				tool = null;
				updatePal();
				resetHint();
			}
			return;
		}
		/* 5) элемент — перенос */
		if ( t ) {
			drag = { mode: 'move', it: t, dx: pt.x - t.x, dy: pt.y - t.y, moved: false };
			if ( selId !== t.id ) { selId = t.id; refreshPanels(); draw(); }
			return;
		}
		/* 6) пустое место — панорама */
		drag = { mode: 'pan', sx: e.clientX, sy: e.clientY, vx: view.x, vy: view.y, moved: false };
	} );

	cv.addEventListener( 'pointermove', function ( e ) {
		if ( pointers.has( e.pointerId ) ) {
			pointers.set( e.pointerId, { x: e.clientX, y: e.clientY } );
		}
		if ( pinch && pointers.size === 2 ) {
			var pp = Array.from( pointers.values() );
			var d = Math.hypot( pp[ 0 ].x - pp[ 1 ].x, pp[ 0 ].y - pp[ 1 ].y );
			var mx = ( pp[ 0 ].x + pp[ 1 ].x ) / 2, my = ( pp[ 0 ].y + pp[ 1 ].y ) / 2;
			view.s = Math.min( 48, Math.max( 4, pinch.s0 * d / pinch.d0 ) );
			var k = view.s / pinch.s0;
			view.x = mx - ( pinch.mx - pinch.vx ) * k;
			view.y = my - ( pinch.my - pinch.vy ) * k;
			clampView();
			draw();
			return;
		}
		var pt = pos( e );
		if ( ! drag ) {
			var ch = itemCorner( pt );
			if ( ch ) {
				cv.style.cursor = ( ch.c === 'nw' || ch.c === 'se' ) ? 'nwse-resize' : 'nesw-resize';
				return;
			}
			var pc = plotCorner( pt );
			if ( pc ) {
				cv.style.cursor = ( pc === 'nw' || pc === 'se' ) ? 'nwse-resize' : 'nesw-resize';
				return;
			}
			cv.style.cursor = hit( pt ) ? 'move' : ( tool ? 'copy' : 'grab' );
			return;
		}
		if ( drag.mode === 'pan' ) {
			view.x = drag.vx + ( e.clientX - drag.sx );
			view.y = drag.vy + ( e.clientY - drag.sy );
			if ( Math.hypot( e.clientX - drag.sx, e.clientY - drag.sy ) > 4 ) { drag.moved = true; }
			clampView();
			draw();
			return;
		}
		if ( drag.mode === 'plot' ) {
			/* участок меняет размер В РЕАЛЬНОМ ВРЕМЕНИ, элементы поджимаются внутрь */
			plot.w = Math.max( PMIN, Math.min( PMAX, snap( pt.x ) ) );
			plot.h = Math.max( PMIN, Math.min( PMAX, snap( pt.y ) ) );
			items.forEach( function ( it ) {
				it.x = Math.max( 0, Math.min( it.x, plot.w - it.w ) );
				it.y = Math.max( 0, Math.min( it.y, plot.h - it.h ) );
			} );
			$( 'plW' ).value = plot.w;
			$( 'plH' ).value = plot.h;
			draw();
			return;
		}
		if ( drag.mode === 'move' ) {
			var it1 = drag.it;
			var nx = snap( Math.max( 0, Math.min( pt.x - drag.dx, plot.w - it1.w ) ) );
			var ny = snap( Math.max( 0, Math.min( pt.y - drag.dy, plot.h - it1.h ) ) );
			if ( nx !== it1.x || ny !== it1.y ) {
				it1.x = nx;
				it1.y = ny;
				drag.moved = true;
				draw();
			}
		} else {
			var it2 = drag.it, o = drag.orig;
			var x1 = o.x, y1 = o.y, x2 = o.x + o.w, y2 = o.y + o.h;
			if ( drag.c === 'se' ) { x2 = Math.max( o.x + SNAP, pt.x ); y2 = Math.max( o.y + SNAP, pt.y ); }
			if ( drag.c === 'nw' ) { x1 = Math.min( o.x + o.w, pt.x ); y1 = Math.min( o.y + o.h, pt.y ); }
			if ( drag.c === 'ne' ) { x2 = Math.max( o.x + SNAP, pt.x ); y1 = Math.min( o.y + o.h, pt.y ); }
			if ( drag.c === 'sw' ) { x1 = Math.min( o.x + o.w, pt.x ); y2 = Math.max( o.y + SNAP, pt.y ); }
			it2.x = snap( Math.max( 0, x1 ) );
			it2.y = snap( Math.max( 0, y1 ) );
			it2.w = Math.max( SNAP, snap( x2 - it2.x ) );
			it2.h = Math.max( SNAP, snap( y2 - it2.y ) );
			$( 'selW' ).value = it2.w;
			$( 'selH' ).value = it2.h;
			draw();
		}
	} );

	function endPointer( e ) {
		pointers.delete( e.pointerId );
		if ( pointers.size < 2 ) { pinch = null; }
		if ( drag ) {
			if ( drag.mode === 'pan' ) {
				if ( ! drag.moved ) { selId = null; refreshPanels(); draw(); }
			} else if ( drag.mode === 'plot' ) {
				refreshPanels();
			} else if ( drag.moved || drag.mode === 'resize' ) {
				checkNeighbors( drag.it );
				refreshPanels();
				saveDebounced();
			}
			drag = null;
		}
		cv.style.cursor = 'default';
	}
	cv.addEventListener( 'pointerup', endPointer );
	cv.addEventListener( 'pointercancel', endPointer );
	cv.addEventListener( 'wheel', function ( e ) {
		e.preventDefault();
		var r = cv.getBoundingClientRect();
		zoomAt( e.clientX - r.left, e.clientY - r.top, e.deltaY < 0 ? 1.12 : 1 / 1.12 );
	}, { passive: false } );

	document.addEventListener( 'keydown', function ( e ) {
		var tag = ( e.target && e.target.tagName ) || '';
		if ( tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA' ) { return; }
		if ( ( e.ctrlKey || e.metaKey ) && e.key === 'z' ) { e.preventDefault(); undo(); }
		if ( ( e.ctrlKey || e.metaKey ) && ( e.key === 'y' || ( e.key === 'z' && e.shiftKey ) ) ) { e.preventDefault(); redo(); }
		if ( ( e.key === 'Delete' || e.key === 'Backspace' ) && selId ) { e.preventDefault(); delSel(); }
		if ( ( e.key === 'd' || e.key === 'D' ) && selId ) { e.preventDefault(); dupSel(); }
		if ( e.key === 'Escape' ) { tool = null; updatePal(); resetHint(); closePanes(); }
		if ( e.key === '+' || e.key === '=' ) { zoomAt( VW / 2, VH / 2, 1.2 ); }
		if ( e.key === '-' ) { zoomAt( VW / 2, VH / 2, 1 / 1.2 ); }
	} );

	/* ===== история ===== */
	function pushH() {
		history.push( JSON.stringify( items ) );
		if ( history.length > 40 ) { history.shift(); }
		future = [];
		syncUndo();
	}
	function undo() {
		if ( ! history.length ) { return; }
		future.push( JSON.stringify( items ) );
		items = JSON.parse( history.pop() );
		selId = null;
		syncUndo();
		refreshPanels();
		draw();
	}
	function redo() {
		if ( ! future.length ) { return; }
		history.push( JSON.stringify( items ) );
		items = JSON.parse( future.pop() );
		syncUndo();
		refreshPanels();
		draw();
	}
	function syncUndo() {
		$( 'btnUndo' ).disabled = ! history.length;
		$( 'btnRedo' ).disabled = ! future.length;
		var mu = $( 'mUndo' );
		if ( mu ) { mu.style.display = history.length ? '' : 'none'; }
	}

	/* ===== палитра, подсказки-чипы, действия ===== */
	function hint( html ) {
		var hb = $( 'hintBar' );
		if ( hb ) { hb.innerHTML = '<span class="hint-chip">' + html + '</span>'; }
	}
	function resetHint() {
		hint( 'Выберите элемент · тяните за <b>зелёные углы</b> — размер, за <b>центр</b> — перенос · <b>углы участка</b> — его размер' );
	}
	function updatePal() {
		Array.prototype.forEach.call( root.querySelectorAll( '.pal-item' ), function ( el ) {
			el.classList.toggle( 'active', el.getAttribute( 'data-type' ) === tool );
		} );
	}
	function addFromPalette( type ) {
		tool = type;
		updatePal();
		if ( window.innerWidth < MOBILE ) {
			/* мобильная: тап по плитке закрывает шторки и подсказывает следующий шаг */
			closePanes();
		}
		hint( '<b>' + TYPES[ type ].name + '</b> — кликните на участок. Shift+клик — несколько подряд' );
	}
	function toggleDims() {
		showDims = ! showDims;
		$( 'btnDims' ).classList.toggle( 'on', showDims );
		var md = $( 'mDims' );
		if ( md ) {
			md.classList.toggle( 'on', showDims );
			md.setAttribute( 'aria-pressed', showDims ? 'true' : 'false' );
		}
		draw();
	}
	function delSel() {
		if ( ! selId ) { return; }
		pushH();
		items = items.filter( function ( i ) { return i.id !== selId; } );
		selId = null;
		refreshPanels();
		draw();
		saveDebounced();
	}
	function dupSel() {
		var it = selItem();
		if ( ! it ) { return; }
		pushH();
		items.push( {
			id: uid(),
			type: it.type,
			crop: it.crop,
			w: it.w,
			h: it.h,
			x: snap( Math.min( it.x + 0.5, Math.max( 0, plot.w - it.w ) ) ),
			y: snap( Math.min( it.y + 0.5, Math.max( 0, plot.h - it.h ) ) )
		} );
		selId = items[ items.length - 1 ].id;
		refreshPanels();
		draw();
		saveDebounced();
	}

	/* ===== шторки и подложка (мобильная) ===== */
	function backdropEl() { return root.querySelector( '.pane-backdrop' ); }
	function paneBack() {
		var b = backdropEl();
		if ( ! b ) { return; }
		b.classList.toggle( 'on', root.querySelector( '.side.open' ) !== null );
	}
	function closePanes() {
		Array.prototype.forEach.call( root.querySelectorAll( '.side' ), function ( p ) { p.classList.remove( 'open' ); } );
		var b = backdropEl();
		if ( b ) { b.classList.remove( 'on' ); }
		Array.prototype.forEach.call( root.querySelectorAll( '.m-btn' ), function ( el ) { el.classList.remove( 'on' ); } );
		var mb = $( 'mBoard' );
		if ( mb ) { mb.classList.add( 'on' ); }
	}
	function togglePane( which ) {
		var p = $( which === 'left' ? 'paneLeft' : 'paneRight' );
		var o = $( which === 'left' ? 'paneRight' : 'paneLeft' );
		if ( o ) { o.classList.remove( 'open' ); }
		if ( ! p ) { return; }
		p.classList.toggle( 'open' );
		var opened = p.classList.contains( 'open' );
		paneBack();
		var b1 = $( which === 'left' ? 'mPal' : 'mSum' );
		var b2 = $( which === 'left' ? 'mSum' : 'mPal' );
		if ( b1 ) { b1.classList.toggle( 'on', opened ); }
		if ( b2 ) { b2.classList.remove( 'on' ); }
	}
	root.addEventListener( 'click', function ( e ) {
		if ( e.target && e.target.classList && e.target.classList.contains( 'pane-backdrop' ) ) {
			closePanes();
		}
	} );

	/* ===== кнопки и поля ===== */
	Array.prototype.forEach.call( root.querySelectorAll( '.pal-item' ), function ( el ) {
		el.addEventListener( 'click', function () { addFromPalette( el.getAttribute( 'data-type' ) ); } );
	} );
	$( 'zin' ).addEventListener( 'click', function () { zoomAt( VW / 2, VH / 2, 1.25 ); } );
	$( 'zout' ).addEventListener( 'click', function () { zoomAt( VW / 2, VH / 2, 1 / 1.25 ); } );
	$( 'zfit' ).addEventListener( 'click', function () { fitView(); draw(); } );
	$( 'btnDims' ).addEventListener( 'click', toggleDims );
	$( 'mDims' ).addEventListener( 'click', toggleDims );
	$( 'btnUndo' ).addEventListener( 'click', undo );
	$( 'btnRedo' ).addEventListener( 'click', redo );
	$( 'mUndo' ).addEventListener( 'click', undo );
	$( 'mPal' ).addEventListener( 'click', function () { togglePane( 'left' ); } );
	$( 'mSum' ).addEventListener( 'click', function () { togglePane( 'right' ); } );
	$( 'mBoard' ).addEventListener( 'click', function () { closePanes(); } );
	$( 'mExport' ).addEventListener( 'click', function () { $( 'exportSheet' ).classList.toggle( 'on' ); } );
	$( 'btnSave' ).addEventListener( 'click', function () { doSave( false ); } );
	$( 'sClose' ).addEventListener( 'click', function () { $( 'exportSheet' ).classList.remove( 'on' ); } );
	$( 'planName' ).addEventListener( 'input', saveDebounced );
	$( 'plnRegion' ).addEventListener( 'change', function () {
		lsSet( 'sz_region', this.value );
		document.dispatchEvent( new CustomEvent( 'sz:region-change', { detail: { region: this.value } } ) );
		saveDebounced();
	} );
	$( 'selDel' ).addEventListener( 'click', delSel );
	$( 'selDup' ).addEventListener( 'click', dupSel );
	$( 'selW' ).addEventListener( 'input', function () {
		var it = selItem();
		if ( ! it ) { return; }
		it.w = Math.max( 0.5, snap( parseFloat( this.value ) || 1 ) );
		draw();
		renderStats();
		saveDebounced();
	} );
	$( 'selH' ).addEventListener( 'input', function () {
		var it = selItem();
		if ( ! it ) { return; }
		it.h = Math.max( 0.5, snap( parseFloat( this.value ) || 1 ) );
		draw();
		renderStats();
		saveDebounced();
	} );
	$( 'plW' ).addEventListener( 'input', function () {
		plot.w = Math.max( PMIN, Math.min( PMAX, snap( parseFloat( this.value ) || PMIN ) ) );
		items.forEach( function ( it ) { it.x = Math.min( it.x, plot.w - it.w ); } );
		fitView();
		draw();
		renderStats();
		saveDebounced();
	} );
	$( 'plH' ).addEventListener( 'input', function () {
		plot.h = Math.max( PMIN, Math.min( PMAX, snap( parseFloat( this.value ) || PMIN ) ) );
		items.forEach( function ( it ) { it.y = Math.min( it.y, plot.h - it.h ); } );
		fitView();
		draw();
		renderStats();
		saveDebounced();
	} );

	/* ===== панели и сводка ===== */
	function refreshPanels() {
		var it = selItem();
		$( 'selCard' ).hidden = ! it;
		$( 'plotCard' ).hidden = !! it;
		$( 'selEmpty' ).hidden = !! it;
		if ( it ) {
			$( 'selTitle' ).textContent = TYPES[ it.type ].name;
			var sizeable = [ 'bed', 'green', 'path', 'rest', 'house', 'build' ].indexOf( it.type ) >= 0;
			$( 'selSizeRow' ).style.display = sizeable ? '' : 'none';
			$( 'selW' ).value = it.w;
			$( 'selH' ).value = it.h;
			$( 'cropPick' ).style.display = TYPES[ it.type ].crop ? '' : 'none';
			var c = it.crop ? cropById( it.crop ) : null;
			$( 'cropSearch' ).value = c ? c.name : '';
		} else {
			$( 'plW' ).value = plot.w;
			$( 'plH' ).value = plot.h;
		}
		renderStats();
	}
	function gapBetween( a, b ) {
		return Math.max(
			Math.abs( ( a.x + a.w / 2 ) - ( b.x + b.w / 2 ) ) - ( a.w + b.w ) / 2,
			Math.abs( ( a.y + a.h / 2 ) - ( b.y + b.h / 2 ) ) - ( a.h + b.h ) / 2
		);
	}
	function stats() {
		var beds = items.filter( function ( i ) { return i.type === 'bed' || i.type === 'green'; } );
		var area = beds.reduce( function ( s, i ) { return s + i.w * i.h; }, 0 );
		var crops = {};
		items.forEach( function ( i ) {
			if ( i.crop ) { crops[ i.crop ] = ( crops[ i.crop ] || 0 ) + 1; }
		} );
		var trees = items.filter( function ( i ) { return i.type === 'tree' || i.type === 'bush'; } ).length;
		var conf = [];
		var a, b;
		for ( a = 0; a < items.length; a++ ) {
			for ( b = a + 1; b < items.length; b++ ) {
				var A = items[ a ], B = items[ b ];
				if ( ! A.crop || ! B.crop || gapBetween( A, B ) > 1 ) { continue; }
				var ca = cropById( A.crop ), cb = cropById( B.crop );
				if ( ca && cb && pairRule( ca, cb ) === 'bad' ) {
					conf.push( ca.name + ' × ' + cb.name );
				}
			}
		}
		return { beds: beds, area: area, crops: crops, trees: trees, conf: conf };
	}
	function renderStats() {
		var st = stats();
		var html = '<div class="sum-card">' +
			'<div class="sum-row"><span>Размер участка</span><b>' + fmt( plot.w ) + ' × ' + fmt( plot.h ) + ' м</b></div>' +
			'<div class="sum-row"><span>Площадь</span><b>' + ( plot.w * plot.h ).toFixed( 0 ) + ' м² (' + ( plot.w * plot.h / 100 ).toFixed( 1 ) + ' сот.)</b></div>' +
			'<div class="sum-row"><span>Грядок / теплиц</span><b>' + st.beds.length + '</b></div>' +
			'<div class="sum-row"><span>Площадь посадок</span><b>' + st.area.toFixed( 1 ) + ' м²</b></div>' +
			'<div class="sum-row"><span>Деревьев и кустов</span><b>' + st.trees + '</b></div>' +
			'<div class="sum-row"><span>Конфликтов</span><b style="color:' + ( st.conf.length ? 'var(--pln-warn-red)' : 'var(--accent)' ) + '">' + st.conf.length + '</b></div>' +
			'</div>';
		var keys = Object.keys( st.crops );
		if ( keys.length ) {
			html += '<div class="sum-card"><div class="pane-title" style="margin-bottom:6px">Культуры в плане</div>';
			keys.forEach( function ( id ) {
				var c = cropById( id );
				html += '<div class="crop-line"><span style="width:11px;height:11px;border-radius:4px;background:var(--accent);flex-shrink:0"></span>' +
					esc( c ? c.name : id ) + '<b style="margin-left:auto">×' + st.crops[ id ] + '</b></div>';
			} );
			html += '</div>';
		}
		if ( st.conf.length ) {
			html += '<div class="conflict"><b>⚠ Конфликты соседства</b><div style="margin-top:6px">' +
				st.conf.map( function ( c ) { return '• ' + esc( c ); } ).join( '<br>' ) + '</div></div>';
		}
		var hints = planHints();
		if ( hints.length ) {
			html += '<div class="sum-card"><div class="pane-title" style="margin-bottom:6px">Подсказки</div>';
			hints.slice( 0, 8 ).forEach( function ( h, idx ) {
				html += '<button type="button" class="hint-row ' +
					( h.sev === 0 ? 'is-bad' : ( h.sev === 1 ? 'is-warn' : 'is-good' ) ) +
					'" data-hint="' + idx + '">' + esc( h.title ) + '</button>';
			} );
			html += '</div>';
		}
		if ( items.length ) {
			var present = Object.keys( TYPES ).filter( function ( t ) {
				return items.some( function ( i ) { return i.type === t; } );
			} );
			html += '<div class="sum-card"><div class="pane-title" style="margin-bottom:6px">Легенда</div>' +
				present.map( function ( t ) {
					return '<div class="crop-line"><span style="width:11px;height:11px;border-radius:4px;background:' + typeColor( t ) + ';flex-shrink:0"></span>' + TYPES[ t ].name + '</div>';
				} ).join( '' ) + '</div>';
		}
		$( 'summary' ).innerHTML = html;

		var bl = $( 'bottomline' );
		bl.hidden = ! items.length;
		var hb = $( 'hintBar' );
		if ( hb ) { hb.style.opacity = ( ! items.length && window.innerWidth < MOBILE ) ? '0' : '1'; }
		if ( ! items.length ) { return; }
		bl.innerHTML = '<span class="cap">Итог</span>' +
			'<span class="b-item"><b>' + ( plot.w * plot.h ).toFixed( 0 ) + '</b> м² уч.</span><span class="sep"></span>' +
			'<span class="b-item"><b>' + st.area.toFixed( 1 ) + '</b> м² посадки</span><span class="sep"></span>' +
			'<span class="b-item"><b>' + st.beds.length + '</b> грядки</span><span class="sep"></span>' +
			'<span class="b-item"><b>' + st.trees + '</b> дер./куст.</span><span class="sep"></span>' +
			'<span class="b-item"><b>' + keys.length + '</b> культур</span>';
	}

	/* ===== культура у элемента: поиск с автодополнением ===== */
	$( 'cropSearch' ).addEventListener( 'input', function () {
		var q = this.value.trim().toLowerCase();
		var list = q ? CROPS.filter( function ( c ) { return c.name.toLowerCase().indexOf( q ) >= 0; } ).slice( 0, 30 ) : [];
		var box = $( 'cropList' );
		box.hidden = ! q;
		box.innerHTML = list.map( function ( c ) {
			return '<button type="button" data-id="' + esc( c.id ) + '"><span style="display:inline-block;width:11px;height:11px;border-radius:4px;background:var(--accent);margin-right:8px"></span>' + esc( c.name ) + '</button>';
		} ).join( '' );
	} );
	$( 'cropList' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		var it = selItem();
		if ( ! b || ! it ) { return; }
		pushH();
		it.crop = b.getAttribute( 'data-id' );
		var c = cropById( it.crop );
		$( 'cropSearch' ).value = c ? c.name : '';
		$( 'cropList' ).hidden = true;
		checkNeighbors( it );
		draw();
		renderStats();
		saveDebounced();
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '.crop-pick' ) ) { $( 'cropList' ).hidden = true; }
	} );
	/* клик по подсказке в «Сводке» — подсветить элемент (выбрать и показать в центре) */
	$( 'summary' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.hint-row' );
		if ( ! b ) { return; }
		var h = planHints()[ parseInt( b.getAttribute( 'data-hint' ), 10 ) ];
		if ( ! h ) { return; }
		selId = h.ids[ 0 ];
		var it = selItem();
		if ( it ) {
			view.x = VW / 2 - ( it.x + it.w / 2 ) * view.s;
			view.y = VH / 2 - ( it.y + it.h / 2 ) * view.s;
			clampView();
		}
		refreshPanels();
		draw();
	} );

	/* ===== тосты и подсказки (совместимость и тень) ===== */
	function esc( s ) {
		return String( s ).replace( /[&<>"]/g, function ( ch ) {
			return ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' } )[ ch ];
		} );
	}
	function toast( kind, title, text, pairKey ) {
		var box = $( 'toasts' );
		if ( ! box ) { return; }
		var el = document.createElement( 'div' );
		el.className = 'toast ' + kind;
		var html = '<span class="dot"></span><div><b>' + esc( title ) + '</b><p>' + esc( text ) + '</p>';
		if ( pairKey ) { html += '<button type="button" class="mute-btn">Не показывать для этой пары</button>'; }
		html += '</div><button type="button" class="x" aria-label="Закрыть">✕</button>';
		el.innerHTML = html;
		box.appendChild( el );
		var kill = function () {
			el.classList.add( 'fading' );
			setTimeout( function () { el.remove(); }, 400 );
		};
		el.querySelector( '.x' ).addEventListener( 'click', kill );
		var mb = el.querySelector( '.mute-btn' );
		if ( mb ) {
			mb.addEventListener( 'click', function () {
				var m = hintsMuted();
				if ( m.indexOf( pairKey ) < 0 ) { m.push( pairKey ); }
				lsSet( 'sz_plan_hints', m );
				kill();
			} );
		}
		setTimeout( kill, 8000 );
		while ( box.children.length > 3 ) { box.removeChild( box.firstChild ); }
	}
	function hintsMuted() {
		var m = lsGet( 'sz_plan_hints' );
		return Array.isArray( m ) ? m : [];
	}
	function listOf( text ) {
		return String( text || '' ).toLowerCase().split( /[,;]+/ ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
	}
	/* матчинг по названию растения: регистронезависимо, вхождением строки */
	function nameInList( text, nameA, nameB ) {
		var list = listOf( text );
		for ( var i = 0; i < list.length; i++ ) {
			var item = list[ i ];
			if ( item === nameA || item === nameB ) { return true; }
			if ( item.indexOf( nameA ) >= 0 || nameA.indexOf( item ) >= 0 ) { return true; }
			if ( item.indexOf( nameB ) >= 0 || nameB.indexOf( item ) >= 0 ) { return true; }
		}
		return false;
	}
	function pairRule( a, b ) {
		var an = String( a.name || '' ).toLowerCase(), bn = String( b.name || '' ).toLowerCase();
		if ( ! an || ! bn ) { return false; }
		if ( nameInList( a.bad, an, bn ) || nameInList( b.bad, an, bn ) ) { return 'bad'; }
		if ( nameInList( a.good, an, bn ) || nameInList( b.good, an, bn ) ) { return 'good'; }
		return false;
	}
	/**
	 * Все подсказки плана, отсортированные по приоритету bad(0) → warn(1) → good(2).
	 * Уровень 3 (ph) — заготовка Партии 4.
	 */
	function planHints() {
		var out = [];
		var muted = hintsMuted();
		var i, j;
		for ( i = 0; i < items.length; i++ ) {
			var A = items[ i ];
			if ( ! A.crop ) { continue; }
			var ca = cropById( A.crop );
			if ( ! ca ) { continue; }
			for ( j = i + 1; j < items.length; j++ ) {
				var B = items[ j ];
				if ( ! B.crop || gapBetween( A, B ) > 1 ) { continue; }
				var cb = cropById( B.crop );
				if ( ! cb ) { continue; }
				var rule = pairRule( ca, cb );
				if ( ! rule ) { continue; }
				var key = [ ca.id, cb.id ].sort().join( '+' );
				if ( muted.indexOf( key ) >= 0 ) { continue; }
				out.push( {
					sev: rule === 'bad' ? 0 : 2,
					pair: true,
					key: key,
					ids: [ A.id, B.id ],
					title: rule === 'bad'
						? ( 'Не дружат: ' + ca.name + ' × ' + cb.name )
						: ( 'Отличная пара: ' + ca.name + ' × ' + cb.name ),
					text: rule === 'bad'
						? 'Общие болезни или угнетение: разнесите минимум на 2 м или разделите дорожкой.'
						: 'Взаимно помогают — так и оставьте.'
				} );
			}
			if ( ca.light === 'sun' ) {
				for ( j = 0; j < items.length; j++ ) {
					var S = items[ j ];
					if ( S.id === A.id ) { continue; }
					var sh = TYPES[ S.type ].shadow;
					if ( ! sh ) { continue; }
					var dt = Math.max( Math.abs( ( A.x + A.w / 2 ) - ( S.x + S.w / 2 ) ) - ( A.w + S.w ) / 2, 0 );
					if ( dt < sh ) {
						out.push( {
							sev: 1,
							pair: false,
							key: 'shade_' + A.id + '_' + S.id,
							ids: [ A.id ],
							title: ca.name + ' может не хватать света',
							text: TYPES[ S.type ].name + ' отбрасывает тень на ' + sh + ' м. Выберите солнечное место.'
						} );
						break;
					}
				}
			}
		}
		out.sort( function ( a, b ) { return a.sev - b.sev; } );
		return out;
	}
	function checkNew( it ) {
		if ( it.type === 'house' ) {
			toast( 'yellow', 'Тень учтена', 'Дом отбрасывает тень ~3 м на север. Светолюбивые — не вплотную к северной стене.' );
		}
	}
	/* подсказки по элементу: не больше двух карточек, приоритет bad → warn → good */
	function checkNeighbors( it ) {
		if ( ! it ) { return; }
		planHints().filter( function ( h ) {
			return h.ids.indexOf( it.id ) >= 0;
		} ).slice( 0, 2 ).forEach( function ( h ) {
			var kind = h.sev === 0 ? 'red' : ( h.sev === 1 ? 'yellow' : 'green' );
			toast( kind, h.title, h.text, h.pair ? h.key : '' );
		} );
	}

	/* ===== мастер первого шага ===== */
	var wizCrops = [];
	function wizRenderCrops() {
		var box = $( 'wizCrops' );
		if ( ! box || box.children.length ) { return; }
		box.innerHTML = CROPS.slice( 0, 18 ).map( function ( c ) {
			return '<button type="button" class="wiz-crop" data-crop="' + esc( c.id ) + '">' + esc( c.name ) + '</button>';
		} ).join( '' );
	}
	function goStep( n ) {
		Array.prototype.forEach.call( root.querySelectorAll( '.wiz-step' ), function ( s ) {
			s.classList.toggle( 'on', Number( s.getAttribute( 'data-step' ) ) === n );
		} );
		Array.prototype.forEach.call( root.querySelectorAll( '.wiz-dots i' ), function ( d, i ) {
			d.classList.toggle( 'on', i < n );
		} );
	}
	$( 'wNext1' ).addEventListener( 'click', function () {
		plot.w = Math.max( PMIN, Math.min( PMAX, parseFloat( $( 'wW' ).value ) || 20 ) );
		plot.h = Math.max( PMIN, Math.min( PMAX, parseFloat( $( 'wH' ).value ) || 30 ) );
		fitView();
		draw();
		goStep( 2 );
		hint( '<b>Кликните на схеме</b> — здесь встанет дом (6×8 м). Можно пропустить.' );
		tool = 'house';
		updatePal();
	} );
	$( 'wBack2' ).addEventListener( 'click', function () { goStep( 1 ); } );
	$( 'wBack3' ).addEventListener( 'click', function () { goStep( 2 ); } );
	$( 'wNext2' ).addEventListener( 'click', function () {
		tool = null;
		updatePal();
		resetHint();
		wizRenderCrops();
		goStep( 3 );
	} );
	$( 'wizCrops' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.wiz-crop' );
		if ( ! b ) { return; }
		b.classList.toggle( 'on' );
		var id = b.getAttribute( 'data-crop' );
		var i = wizCrops.indexOf( id );
		if ( i >= 0 ) { wizCrops.splice( i, 1 ); } else { wizCrops.push( id ); }
	} );
	$( 'wFinish' ).addEventListener( 'click', function () {
		pushH();
		var margin = 1, bx = margin, i;
		var house = null;
		for ( i = 0; i < items.length; i++ ) {
			if ( items[ i ].type === 'house' ) { house = items[ i ]; break; }
		}
		var by = house ? house.y + house.h + 1 : 1;
		if ( by + 4 > plot.h ) { by = 1; }
		wizCrops.forEach( function ( cid ) {
			if ( by + 3 > plot.h ) { by = 1; bx = 6; }
			items.push( { id: uid(), type: 'bed', x: bx, y: by, w: 1, h: 3, crop: cid } );
			bx += 2;
			if ( bx + 1 > plot.w - 1 ) { bx = margin; by += 4; }
		} );
		items.push( { id: uid(), type: 'rest', x: Math.max( 0, plot.w - 4 ), y: Math.max( 0, plot.h - 4 ), w: 3, h: 3, crop: null } );
		selId = null;
		$( 'wizard' ).style.display = 'none';
		fitView();
		resetHint();
		refreshPanels();
		draw();
		doSave( true );
	} );

	/* ===== экспорт: JPG, печать/PDF, «Поделиться» ===== */
	function fileBase() {
		return ( $( 'planName' ).value || 'plan' ).replace( /[\\/:*?"<>|]+/g, '_' );
	}
	function doJpg() {
		var a = document.createElement( 'a' );
		a.download = fileBase() + '_' + plot.w + 'x' + plot.h + '.jpg';
		a.href = renderPlanCanvas().toDataURL( 'image/jpeg', 0.92 );
		a.click();
	}
	function fillPrintDoc( url, iw, ih ) {
		var st = stats();
		$( 'pdName' ).textContent = $( 'planName' ).value || 'Мой участок';
		$( 'pdSub' ).textContent = 'Участок ' + fmt( plot.w ) + ' × ' + fmt( plot.h ) + ' м (' + ( plot.w * plot.h / 100 ).toFixed( 1 ) + ' сот.) · ' + new Date().toLocaleDateString( 'ru' );
		/* персональный блок шапки — только данные текущего пользователя WP */
		var u = CFG.user || {};
		var today = new Date().toLocaleDateString( 'ru' );
		var userBox = $( 'pdUser' );
		if ( userBox ) {
			if ( u.loggedIn ) {
				userBox.innerHTML = '<div>План подготовил: ' + esc( u.name ) + ' (' + esc( u.email ) + ')</div><div>' + esc( today ) + '</div>';
			} else {
				userBox.innerHTML = '<div>Создано в планировщике Сад знаний</div><div>' + esc( today ) + '</div>' +
					'<div class="pd-user-cta">Зарегистрируйтесь — и планы сохраняются в личном кабинете</div>';
			}
		}
		var img = $( 'pdImg' );
		img.width = iw;
		img.height = ih;
		img.src = url;
		$( 'pdStats' ).innerHTML = '<span>Посадки: <b>' + st.area.toFixed( 1 ) + ' м²</b></span>' +
			'<span>Грядки/теплицы: <b>' + st.beds.length + '</b></span>' +
			'<span>Деревья и кусты: <b>' + st.trees + '</b></span>' +
			'<span>Культур: <b>' + Object.keys( st.crops ).length + '</b></span>';
		var types = Object.keys( TYPES ).filter( function ( t ) {
			return items.some( function ( i ) { return i.type === t; } );
		} );
		$( 'pdLegend' ).innerHTML = types.map( function ( t ) {
			return '<span><i style="background:' + typeColor( t ) + '"></i>' + TYPES[ t ].name + '</span>';
		} ).join( '' );
		$( 'pdTable' ).innerHTML = '<tr><th>Показатель</th><th>Значение</th></tr>' +
			'<tr><td>Размер участка</td><td>' + fmt( plot.w ) + ' × ' + fmt( plot.h ) + ' м (' + ( plot.w * plot.h / 100 ).toFixed( 1 ) + ' сот.)</td></tr>' +
			'<tr><td>Площадь посадок</td><td>' + st.area.toFixed( 1 ) + ' м²</td></tr>' +
			'<tr><td>Грядок / теплиц</td><td>' + st.beds.length + '</td></tr>' +
			'<tr><td>Деревьев и кустарников</td><td>' + st.trees + '</td></tr>' +
			'<tr><td>Прочих элементов</td><td>' + ( items.length - st.beds.length - st.trees ) + '</td></tr>';
		$( 'pdCrops' ).innerHTML = '<tr><th>Культура</th><th>Кол-во</th></tr>' +
			Object.keys( st.crops ).map( function ( id ) {
				var c = cropById( id );
				return '<tr><td>' + esc( c ? c.name : id ) + '</td><td>' + st.crops[ id ] + '</td></tr>';
			} ).join( '' );
		$( 'pdConflicts' ).innerHTML = st.conf.length
			? '<div class="conflict" style="margin-top:12px"><b>⚠ Конфликты соседства:</b><div style="margin-top:6px">' + st.conf.map( function ( c ) { return '• ' + esc( c ); } ).join( '<br>' ) + '</div></div>'
			: '';
		$( 'pdCare' ).innerHTML = '<tr><th>Культура</th><th>Свет</th><th>Почва</th><th>Полив</th></tr>' +
			Object.keys( st.crops ).map( function ( id ) {
				var c = cropById( id );
				if ( ! c ) { return ''; }
				var light = c.light === 'sun' ? 'солнце' : ( c.light === 'shade' ? 'тень' : 'солнце/полутень' );
				return '<tr><td>' + esc( c.name ) + '</td><td>' + esc( light ) + '</td><td>' + esc( c.soil ) + '</td><td>' + esc( waterLabel( c.water ) ) + '</td></tr>';
			} ).join( '' );
	}
	/* печать: картинка — только из renderPlanCanvas(); перед print() — rAF + таймаут */
	function doPrint() {
		$( 'exportSheet' ).classList.remove( 'on' );
		var c = renderPlanCanvas();
		fillPrintDoc( c.toDataURL( 'image/jpeg', 0.92 ), c.width, c.height );
		requestAnimationFrame( function () {
			setTimeout( function () { window.print(); }, 80 );
		} );
	}
	function doShare() {
		$( 'exportSheet' ).classList.remove( 'on' );
		var url = location.origin + location.pathname + '?plan=' + planId;
		if ( navigator.share ) {
			navigator.share( {
				title: 'План участка — Сад знаний',
				text: 'Мой план участка ' + fmt( plot.w ) + '×' + fmt( plot.h ) + ' м',
				url: url
			} ).catch( function () { copyLink( url ); } );
			return;
		}
		copyLink( url );
	}
	function copyLink( url ) {
		var done = function () {
			toast( 'green', 'Ссылка скопирована', 'Отправьте её кому угодно — план откроется для просмотра.' );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( done ).catch( function () { window.prompt( 'Скопируйте ссылку:', url ); } );
		} else {
			window.prompt( 'Скопируйте ссылку:', url );
		}
	}

	/* ===== гейт регистрации: «Печать/PDF» и «В календарь телефона» — для залогиненных ===== */
	function needsGate() {
		var g = CFG.gate || {};
		var u = CFG.user || {};
		return ( ! u.loggedIn ) && ( g.required !== false );
	}
	function openGate() {
		var m = $( 'plnGate' );
		if ( ! m ) { return; }
		m.hidden = false;
		closePanes();
		$( 'exportSheet' ).classList.remove( 'on' );
	}
	function closeGate() {
		var m = $( 'plnGate' );
		if ( m && ! m.hidden ) { m.hidden = true; }
	}
	/* печать: гость при включённом гейте сначала входит */
	function printAction() {
		if ( needsGate() ) { openGate(); return; }
		doPrint();
	}
	/* .ics: до Партии 4 фид по плану не собирается — честная заготовка */
	function icsAction() {
		if ( needsGate() ) { openGate(); return; }
		toast( 'yellow', 'Свой .ics по плану — в следующем обновлении', 'Пока напоминания о работах месяца можно подписать в календаре дачника: znai-sad.ru/kalendar/.' );
	}

	/* ===== старт ===== */
	( function init() {
		var bd = document.createElement( 'div' );
		bd.className = 'pane-backdrop';
		root.insertBefore( bd, root.firstChild );

		var first = planIndex()[ 0 ];
		var loaded = false;
		if ( first && first.id ) {
			loaded = planLoad( first.id );
		}
		if ( ! loaded ) {
			var old = lsGet( 'sz_plan_current' ); /* план прежней демо-версии */
			if ( old && old.items && old.items.length ) {
				plot.w = Number( old.plot && old.plot.w ) || 20;
				plot.h = Number( old.plot && old.plot.h ) || 30;
				items = old.items.map( function ( e ) {
					return {
						id: uid(),
						type: TYPES[ e.type ] ? e.type : 'bed',
						x: Number( e.x ) || 0,
						y: Number( e.y ) || 0,
						w: Number( e.w ) || 1,
						h: Number( e.h ) || 1,
						crop: e.crop ? String( e.crop ) : null
					};
				} );
				loaded = true;
			}
		}
		if ( loaded ) {
			$( 'wizard' ).style.display = 'none';
		} else {
			$( 'wizard' ).style.display = '';
			$( 'wW' ).value = plot.w;
			$( 'wH' ).value = plot.h;
			wizRenderCrops();
		}

		$( 'btnDims' ).classList.toggle( 'on', showDims );
		$( 'mDims' ).classList.toggle( 'on', showDims );
		$( 'mDims' ).setAttribute( 'aria-pressed', showDims ? 'true' : 'false' );
		resetHint();
		fitRoot();
		refreshPanels();
		resizeCanvas();

		$( 'btnJpg' ).addEventListener( 'click', doJpg );
		$( 'sJpg' ).addEventListener( 'click', function () { $( 'exportSheet' ).classList.remove( 'on' ); doJpg(); } );
		$( 'btnPrint' ).addEventListener( 'click', printAction );
		$( 'sPrint' ).addEventListener( 'click', printAction );
		$( 'btnIcs' ).addEventListener( 'click', icsAction );
		$( 'sIcs' ).addEventListener( 'click', icsAction );
		$( 'btnShare' ).addEventListener( 'click', doShare );
		$( 'sShare' ).addEventListener( 'click', doShare );

		if ( window.ResizeObserver ) {
			new ResizeObserver( function () { fitRoot(); resizeCanvas(); } ).observe( shell );
		}
		window.addEventListener( 'resize', function () { fitRoot(); resizeCanvas(); } );
		window.addEventListener( 'load', function () {
			setTimeout( function () { fitRoot(); resizeCanvas(); }, 120 );
		} );
		/* гейт: закрытие модалки + возврат после входа с ?print=1 → печать сразу */
		Array.prototype.forEach.call( root.querySelectorAll( '[data-gate-close]' ), function ( el ) {
			el.addEventListener( 'click', closeGate );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeGate(); }
		} );
		if ( /[?&]print=1/.test( window.location.search ) ) {
			try {
				window.history.replaceState( null, '', window.location.pathname + window.location.hash );
			} catch ( err ) {}
			if ( ! needsGate() ) {
				setTimeout( printAction, 400 );
			}
		}
		window.addEventListener( 'beforeunload', function () { doSave( true ); } );
	}() );
}() );












