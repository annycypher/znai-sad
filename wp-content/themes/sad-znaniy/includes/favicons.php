<?php
/**
 * Иконки сайта: вкладка браузера, «Избранное», домашний экран iOS.
 *
 * Утверждённый вариант — В5: белый знак «Сад знаний» на акцентном зелёном
 * (--accent #68B96E, --accent-on #FFFFFF). Мастера и превью всех вариантов —
 * _design/favicons/ (локальная папка, на прод не выкладывается, в выдаче
 * сайта не участвует).
 *
 * Файлы лежат в assets/img/favicons/ и собираются скриптом
 * _design/favicons/_tools/build_prod.py из мастера 512×512, поэтому
 * вручную иконки не правятся — только пересборкой.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL иконки с кешбастингом по filemtime.
 *
 * @param string $file Имя файла в assets/img/favicons/.
 * @return string
 */
function sad_znaniy_favicon_url( $file ) {
	$dir = 'assets/img/favicons';
	$url = get_template_directory_uri() . '/' . $dir . '/' . $file;
	$ver = sad_znaniy_asset_ver( $dir . '/' . $file );

	return $ver ? $url . '?ver=' . $ver : $url;
}

/**
 * Печатает ссылки на иконки в <head>.
 *
 * Порядок важен: SVG первым (его выбирают Chrome и Edge), затем PNG нужных
 * размеров, ICO как запасной для старых браузеров и Windows, последним —
 * apple-touch-icon для iOS (без прозрачности).
 */
function sad_znaniy_print_favicons() {
	printf(
		'<link rel="icon" type="image/svg+xml" href="%s">' . "\n",
		esc_url( sad_znaniy_favicon_url( 'favicon.svg' ) )
	);

	printf(
		'<link rel="icon" type="image/png" sizes="32x32" href="%s">' . "\n",
		esc_url( sad_znaniy_favicon_url( 'favicon-32.png' ) )
	);

	printf(
		'<link rel="icon" type="image/png" sizes="16x16" href="%s">' . "\n",
		esc_url( sad_znaniy_favicon_url( 'favicon-16.png' ) )
	);

	printf(
		'<link rel="icon" type="image/x-icon" sizes="any" href="%s">' . "\n",
		esc_url( sad_znaniy_favicon_url( 'favicon.ico' ) )
	);

	printf(
		'<link rel="apple-touch-icon" sizes="180x180" href="%s">' . "\n",
		esc_url( sad_znaniy_favicon_url( 'apple-touch-icon.png' ) )
	);
}
add_action( 'wp_head', 'sad_znaniy_print_favicons', 2 );
