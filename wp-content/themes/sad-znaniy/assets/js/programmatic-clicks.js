/**
 * Учёт кликов «Подробнее → статья» на программатик-страницах (Этап 7.2).
 *
 * Отправляет один POST на REST-роут при клике по ссылке внутри блока
 * «Подробнее» (.t-links). Ошибки игнорируются — аналитика не должна
 * мешать переходу по ссылке.
 */
( function () {
	'use strict';

	var cfg = window.sadZnaniySeo;
	if ( ! cfg || ! cfg.restUrl ) {
		return;
	}

	var block = document.querySelector( '.t-links' );
	if ( ! block ) {
		return;
	}

	block.addEventListener(
		'click',
		function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a' ) : null;
			if ( ! link ) {
				return;
			}

			var body = JSON.stringify( { url: cfg.pageUrl, nonce: cfg.nonce } );

			try {
				if ( navigator.sendBeacon ) {
					navigator.sendBeacon( cfg.restUrl, new Blob( [ body ], { type: 'application/json' } ) );
				} else {
					fetch( cfg.restUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: body,
						keepalive: true
					} );
				}
			} catch ( err ) {
				// Молча: учёт клика не должен ломать переход.
			}
		},
		true
	);
} )();
