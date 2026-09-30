<?php
/**
 * Подключение стилей и скриптов темы (с кешбастингом по filemtime).
 *
 * Порядок подключения CSS:
 *   1. fonts.css         — локальные @font-face (ноль внешних запросов)
 *   2. design-tokens.css — переменные (:root)
 *   3. main.css          — остальные стили макета
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Возвращает версию ассета по времени изменения файла (кешбастинг).
 *
 * @param string $rel_path Путь относительно каталога темы.
 * @return string|false
 */
function sad_znaniy_asset_ver( $rel_path ) {
	$abs = get_template_directory() . '/' . ltrim( $rel_path, '/' );
	return file_exists( $abs ) ? (string) filemtime( $abs ) : false;
}

/**
 * Подключает стили и скрипты фронтенда.
 */
function sad_znaniy_enqueue_assets() {
	$uri = get_template_directory_uri();

	wp_enqueue_style(
		'sad-znaniy-fonts',
		$uri . '/assets/fonts/fonts.css',
		array(),
		sad_znaniy_asset_ver( 'assets/fonts/fonts.css' )
	);

	wp_enqueue_style(
		'sad-znaniy-tokens',
		$uri . '/assets/css/design-tokens.css',
		array( 'sad-znaniy-fonts' ),
		sad_znaniy_asset_ver( 'assets/css/design-tokens.css' )
	);

	wp_enqueue_style(
		'sad-znaniy-main',
		$uri . '/assets/css/main.css',
		array( 'sad-znaniy-tokens' ),
		sad_znaniy_asset_ver( 'assets/css/main.css' )
	);

	wp_enqueue_script(
		'sad-znaniy-main',
		$uri . '/assets/js/main.js',
		array(),
		sad_znaniy_asset_ver( 'assets/js/main.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'sad_znaniy_enqueue_assets' );