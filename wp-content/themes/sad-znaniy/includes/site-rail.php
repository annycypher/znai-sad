<?php
/**
 * Постоянная панель инструментов слева (Этап 6/7).
 *
 * ВАЖНО: печатается из wp_footer(), потому что в footer.php вызов стоит
 * ПОСЛЕ `</div><!-- .frame -->`, а у .frame есть backdrop-filter — внутри
 * такого предка position:fixed считается от него и «плавает». Из wp_footer
 * элемент уже вне .frame и фиксируется от окна корректно.
 *
 * Состав: Поделиться (Web Share → буфер обмена), MAX, Почта, Наверх.
 * Иконки — инлайн-SVG, без CDN, без внешних скриптов и запросов.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Разметка панели + минимальный vanilla-JS (без jQuery).
 */
function sad_znaniy_site_rail() {
if ( is_admin() ) {
return;
}
$max  = 'https://web.max.ru/-76163835891728';
$mail = 'info@znai-sad.ru';
?>
<div id="siteRail" class="site-rail" role="complementary" aria-label="<?php esc_attr_e( 'Панель инструментов страницы', 'sad-znaniy' ); ?>">
		<button type="button" class="rail-handle" aria-expanded="false" aria-controls="siteRailList" aria-label="<?php esc_attr_e( 'Показать панель: поделиться, связь, наверх', 'sad-znaniy' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
		</button>
		<div class="rail-list" id="siteRailList">
				<button type="button" class="rail-btn" id="railShare">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
						<span><?php esc_html_e( 'Поделиться', 'sad-znaniy' ); ?></span>
				</button>
				<a class="rail-btn" href="<?php echo esc_url( $max ); ?>" target="_blank" rel="noopener">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.7 9.7 0 0 1-2.5-.3L4 21l1.4-4.3A8.2 8.2 0 0 1 4 11.5C4 7 7.8 3.5 12.5 3.5S21 7 21 11.5Z"/></svg>
						<span><?php esc_html_e( 'Мы в MAX', 'sad-znaniy' ); ?></span>
				</a>
				<a class="rail-btn" href="mailto:<?php echo esc_attr( $mail ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
						<span><?php esc_html_e( 'Почта', 'sad-znaniy' ); ?></span>
				</a>
				<button type="button" class="rail-btn" id="railTop">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20V5"/><path d="M6 11l6-6 6 6"/></svg>
						<span><?php esc_html_e( 'Наверх', 'sad-znaniy' ); ?></span>
				</button>
		</div>
</div>
<span class="rail-toast" id="railToast" role="status" aria-live="polite"></span>
<script>
(function () {
		var rail = document.getElementById( 'siteRail' );
		if ( ! rail ) { return; }
		var handle = rail.querySelector( '.rail-handle' );
		var toast  = document.getElementById( 'railToast' );
		var share  = document.getElementById( 'railShare' );
		var topBtn = document.getElementById( 'railTop' );
		function say( text ) {
				if ( ! toast ) { return; }
				toast.textContent = text;
				toast.classList.add( 'is-on' );
				window.clearTimeout( say.timer );
				say.timer = window.setTimeout( function () { toast.classList.remove( 'is-on' ); }, 2400 );
		}
		function setOpen( open ) {
				rail.classList.toggle( 'is-open', open );
				if ( handle ) { handle.setAttribute( 'aria-expanded', open ? 'true' : 'false' ); }
		}
		if ( handle ) {
				handle.addEventListener( 'click', function () { setOpen( ! rail.classList.contains( 'is-open' ) ); } );
		}
		document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) { setOpen( false ); }
		} );
		if ( share ) {
				share.addEventListener( 'click', function () {
						var url = window.location.href;
						var title = document.title;
						function copy() {
								if ( navigator.clipboard && navigator.clipboard.writeText ) {
										navigator.clipboard.writeText( url ).then( function () { say( '✓ Ссылка скопирована' ); }, function () { say( 'Скопируйте ссылку из адресной строки' ); } );
								} else {
										say( 'Скопируйте ссылку из адресной строки' );
								}
						}
						if ( navigator.share ) {
								navigator.share( { title: title, url: url } ).catch( copy );
						} else {
								copy();
						}
				} );
		}
		if ( topBtn ) {
				var onScroll = function () { rail.classList.toggle( 'is-scrolled', window.pageYOffset > 400 ); };
				window.addEventListener( 'scroll', onScroll, { passive: true } );
				onScroll();
				topBtn.addEventListener( 'click', function () { window.scrollTo( { top: 0, behavior: 'smooth' } ); } );
		}
}());
</script>
<?php
}
add_action( 'wp_footer', 'sad_znaniy_site_rail', 20 );