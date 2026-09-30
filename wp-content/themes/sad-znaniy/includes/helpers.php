<?php
/**
 * Вспомогательные функции темы «Сад знаний».
 *
 * Наборы значений (регионы, свет, полив) и форматирование дат —
 * единый источник для метабоксов, колонок админки и шаблонов.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Список регионов для событий календаря.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_regions() {
	return array(
		'yug'       => __( 'Юг', 'sad-znaniy' ),
		'srednyaya' => __( 'Средняя полоса', 'sad-znaniy' ),
		'ural'      => __( 'Урал', 'sad-znaniy' ),
		'sibir'     => __( 'Сибирь', 'sad-znaniy' ),
		'dalniy'    => __( 'Дальний Восток', 'sad-znaniy' ),
		'vse'       => __( 'Все регионы', 'sad-znaniy' ),
	);
}

/**
 * Подпись региона по ключу.
 *
 * @param string $key Ключ региона.
 * @return string
 */
function sad_znaniy_region_label( $key ) {
	$regions = sad_znaniy_regions();
	return isset( $regions[ $key ] ) ? $regions[ $key ] : '';
}

/**
 * Варианты освещённости растения.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_light_options() {
	return array(
		'sun'     => __( 'Солнце', 'sad-znaniy' ),
		'partial' => __( 'Полутень', 'sad-znaniy' ),
		'shade'   => __( 'Тень', 'sad-znaniy' ),
	);
}

/**
 * Варианты полива растения.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_water_options() {
	return array(
		'low'      => __( 'Редкий', 'sad-znaniy' ),
		'moderate' => __( 'Умеренный', 'sad-znaniy' ),
		'high'     => __( 'Обильный', 'sad-znaniy' ),
	);
}

/**
 * Подпись значения из произвольной карты «ключ => подпись».
 *
 * @param array  $map Карта значений.
 * @param string $key Ключ.
 * @return string
 */
function sad_znaniy_option_label( $map, $key ) {
	return isset( $map[ $key ] ) ? $map[ $key ] : '';
}

/**
 * Русские названия месяцев в родительном падеже (для дат событий).
 *
 * @return array Номер месяца => название.
 */
function sad_znaniy_months_genitive() {
	return array(
		1  => __( 'января', 'sad-znaniy' ),
		2  => __( 'февраля', 'sad-znaniy' ),
		3  => __( 'марта', 'sad-znaniy' ),
		4  => __( 'апреля', 'sad-znaniy' ),
		5  => __( 'мая', 'sad-znaniy' ),
		6  => __( 'июня', 'sad-znaniy' ),
		7  => __( 'июля', 'sad-znaniy' ),
		8  => __( 'августа', 'sad-znaniy' ),
		9  => __( 'сентября', 'sad-znaniy' ),
		10 => __( 'октября', 'sad-znaniy' ),
		11 => __( 'ноября', 'sad-znaniy' ),
		12 => __( 'декабря', 'sad-znaniy' ),
	);
}

/**
 * Разбирает дату события (Y-m-d) на части для блока даты.
 *
 * @param string $date Дата в формате Y-m-d.
 * @return array [num, month, label].
 */
function sad_znaniy_format_event_date( $date ) {
	$empty = array(
		'num'   => '',
		'month' => '',
		'label' => '',
	);

	$date = trim( (string) $date );
	if ( '' === $date ) {
		return $empty;
	}

	$time = strtotime( $date );
	if ( false === $time ) {
		return $empty;
	}

	$num     = gmdate( 'd', $time );
	$months  = sad_znaniy_months_genitive();
	$month_n = (int) gmdate( 'n', $time );
	$month   = isset( $months[ $month_n ] ) ? $months[ $month_n ] : '';

	return array(
		'num'   => $num,
		'month' => $month,
		'label' => trim( $num . ' ' . $month ),
	);
}

/**
 * Текст срока события («1 июня — 10 июня» или «с 1 июня»).
 *
 * @param string $from Дата начала (Y-m-d).
 * @param string $to   Дата окончания (Y-m-d), необязательно.
 * @return string
 */
function sad_znaniy_event_term_text( $from, $to ) {
	$f = sad_znaniy_format_event_date( $from );
	$t = sad_znaniy_format_event_date( $to );

	if ( '' !== $f['label'] && '' !== $t['label'] ) {
		/* translators: 1: дата начала, 2: дата окончания */
		return sprintf( __( '%1$s — %2$s', 'sad-znaniy' ), $f['label'], $t['label'] );
	}

	if ( '' !== $f['label'] ) {
		/* translators: %s: дата начала */
		return sprintf( __( 'с %s', 'sad-znaniy' ), $f['label'] );
	}

	return '';
}

/**
 * Ссылка на архив термина по слагу (или пустая строка).
 *
 * @param string $slug     Слаг термина.
 * @param string $taxonomy Таксономия.
 * @return string
 */
function sad_znaniy_term_link( $slug, $taxonomy ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : $link;
}

/**
 * Ссылка на раздел базы знаний (таксономия «Раздел»).
 *
 * @param string $slug Слаг раздела.
 * @return string
 */
function sad_znaniy_section_link( $slug ) {
	return sad_znaniy_term_link( $slug, 'plant_section' );
}

/**
 * Ссылка на рубрику «Рецепты».
 *
 * @return string
 */
function sad_znaniy_recipes_link() {
	return sad_znaniy_term_link( 'retsepty', 'category' );
}

/**
 * Ссылка на страницу «Все статьи» (страница записей), иначе — на главную.
 *
 * @return string
 */
function sad_znaniy_blog_url() {
	$page_for_posts = (int) get_option( 'page_for_posts' );
	if ( $page_for_posts ) {
		$link = get_permalink( $page_for_posts );
		if ( $link ) {
			return $link;
		}
	}

	return home_url( '/' );
}

/**
 * Убирает префикс «Архивы:» у заголовков архивов типов записей.
 *
 * @param string $title Заголовок архива.
 * @return string
 */
function sad_znaniy_clean_archive_title( $title ) {
	if ( is_post_type_archive() ) {
		$title = post_type_archive_title( '', false );
	}

	return $title;
}
add_filter( 'get_the_archive_title', 'sad_znaniy_clean_archive_title' );

/**
 * Выбранные в настройках темы «популярные» растения для чипов на главной.
 *
 * @param int $limit Сколько максимум растений вывести.
 * @return WP_Post[] Список записей.
 */
function sad_znaniy_get_popular_plants( $limit = 6 ) {
	$options = get_option( 'sad_znaniy_options', array() );
	$ids     = isset( $options['popular_plants'] ) ? (array) $options['popular_plants'] : array();
	$ids     = array_filter( array_map( 'absint', $ids ) );

	if ( ! $ids ) {
		return array();
	}

	$ids = array_slice( $ids, 0, (int) $limit );

	$plants = get_posts(
		array(
			'post_type'      => 'plant',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $ids ),
			'no_found_rows'  => true,
		)
	);

	return $plants;
}
