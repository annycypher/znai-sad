<?php
/**
 * Базовая настройка темы: поддержки и меню.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует поддержки темы и локации меню.
 */
function sad_znaniy_setup() {
	load_theme_textdomain( 'sad-znaniy', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script' )
	);

	register_nav_menus(
		array(
			'main-menu'        => __( 'Основное меню (шапка)', 'sad-znaniy' ),
			'footer-menu'      => __( 'Меню в футере (разделы)', 'sad-znaniy' ),
			'footer-tools-menu' => __( 'Футер: инструменты', 'sad-znaniy' ),
		)
	);
}
add_action( 'after_setup_theme', 'sad_znaniy_setup' );