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

	// Место под узкий баннер над содержимым (страница календаря и её печатные режимы).
	register_sidebar(
		array(
			'id'            => 'banner-top',
			'name'          => __( 'Баннер над содержимым (узкий)', 'sad-znaniy' ),
			'description'   => __( 'Полоса над контентом на странице календаря: картинка 728×90 или свой HTML. В печать не выводится. Пустая заглушка видна только администратору.', 'sad-znaniy' ),
			'before_widget' => '<div id="%1$s" class="banner-slot__item %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<span class="banner-slot__title">',
			'after_title'   => '</span>',
		)
	);
}
add_action( 'after_setup_theme', 'sad_znaniy_setup' );