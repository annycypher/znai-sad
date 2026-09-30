<?php
/**
 * Меню темы.
 *
 * Классы макета — контракт: контейнеру .nav нужны прямые потомки <a>
 * (в макете это плоский список ссылок). Поэтому используем свой walker,
 * который выводит ссылки без <ul>/<li>, сохраняя вид макета.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker: выводит пункты меню как плоские ссылки <a>.</a>.
 */
class SAD_Znaniy_Flat_Walker extends Walker_Nav_Menu {

	/**
	 * Уровни вложенности не используются — подавляем.
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {}

	/**
	 * См. start_lvl().
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	/**
	 * Выводит пункт меню как <a>.
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param WP_Post  $item   Пункт меню.
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 * @param int      $id     ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$output .= sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $item->url ),
			esc_html( $item->title )
		);
	}

	/**
	 * См. start_el().
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param WP_Post  $item   Пункт меню.
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}

/**
 * Ссылки основного меню по умолчанию (как в макете), если меню ещё не назначено.
 *
 * Это временный fallback Этапа 1: владелец создаст меню в админке и назначит
 * его на локацию «main-menu» — тогда отобразится оно.
 *
 * @return array Массив [url, подпись].
 */
function sad_znaniy_default_main_links() {
	return array(
		array( '#main',        __( 'Главная', 'sad-znaniy' ) ),
		array( '#sadvogorod',  __( 'База знаний', 'sad-znaniy' ) ),
		array( '#tools',       __( 'Инструменты', 'sad-znaniy' ) ),
		array( '#calendar',    __( 'Календарь', 'sad-znaniy' ) ),
		array( '#recipes',     __( 'Рецепты', 'sad-znaniy' ) ),
		array( '#about',       __( 'О нас', 'sad-znaniy' ) ),
	);
}

/**
 * Выводит основное меню (плоские ссылки). Если меню не назначено —
 * выводит fallback-ссылки макета.
 */
function sad_znaniy_main_menu() {
	if ( has_nav_menu( 'main-menu' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'main-menu',
				'container'      => false,
				'items_wrap'     => '%3$s',
				'depth'          => 1,
				'walker'         => new SAD_Znaniy_Flat_Walker(),
			)
		);
		return;
	}

	foreach ( sad_znaniy_default_main_links() as $link ) {
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $link[0] ),
			esc_html( $link[1] )
		);
	}
}