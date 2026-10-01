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

/**
 * Регионы для календаря (Этап 5.5). Ключи — как в демо kalendar-demo.html.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_region_keys() {
	return array(
		'south' => __( 'Юг', 'sad-znaniy' ),
		'mid'   => __( 'Средняя полоса', 'sad-znaniy' ),
		'ural'  => __( 'Урал', 'sad-znaniy' ),
		'sib'   => __( 'Сибирь', 'sad-znaniy' ),
		'dv'    => __( 'Дальний Восток', 'sad-znaniy' ),
	);
}

/**
 * Типы работ календаря (таксономия work_type).
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_work_types() {
	return array(
		'sow'     => __( 'Посев', 'sad-znaniy' ),
		'plant'   => __( 'Посадка', 'sad-znaniy' ),
		'water'   => __( 'Полив', 'sad-znaniy' ),
		'feed'    => __( 'Подкормка', 'sad-znaniy' ),
		'prune'   => __( 'Обрезка', 'sad-znaniy' ),
		'protect' => __( 'Защита', 'sad-znaniy' ),
		'harvest' => __( 'Сбор', 'sad-znaniy' ),
		'prep'    => __( 'Подготовка', 'sad-znaniy' ),
	);
}

/**
 * Цвета типов работ (из демо kalendar-demo.html, переменные --c-*).
 *
 * @return array Ключ => HEX.
 */
function sad_znaniy_work_type_colors() {
	return array(
		'sow'     => '#E8B34B',
		'plant'   => '#3FA46F',
		'water'   => '#4A90D9',
		'feed'    => '#9B6FD0',
		'prune'   => '#A9714B',
		'protect' => '#D14D57',
		'harvest' => '#E07B39',
		'prep'    => '#7C8B93',
	);
}

/**
 * Уровни сложности события.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_difficulty_options() {
	return array(
		'new' => __( 'Новичок', 'sad-znaniy' ),
		'exp' => __( 'Опытный', 'sad-znaniy' ),
		'all' => __( 'Всем', 'sad-znaniy' ),
	);
}

/**
 * Приоритеты события.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_priority_options() {
	return array(
		'must'    => __( 'Обязательно', 'sad-znaniy' ),
		'opt'     => __( 'Желательно', 'sad-znaniy' ),
		'weather' => __( 'По погоде', 'sad-znaniy' ),
	);
}

/**
 * Возвращает опубликованные события, пересекающиеся с заданным месяцем.
 *
 * @param int $year  Год.
 * @param int $month Месяц (1–12).
 * @return WP_Post[]
 */
function sad_znaniy_get_month_events( $year, $month ) {
	$events = get_posts(
		array(
			'post_type'      => 'calendar_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_sz_event_date_from',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	$last_day = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );
	$first    = sprintf( '%04d-%02d-01', $year, $month );
	$last     = sprintf( '%04d-%02d-%02d', $year, $month, $last_day );

	$result = array();
	foreach ( $events as $event ) {
		$from = (string) get_post_meta( $event->ID, '_sz_event_date_from', true );
		$to   = (string) get_post_meta( $event->ID, '_sz_event_date_to', true );
		if ( '' === $from ) {
			continue;
		}
		$to_eff = '' !== $to ? $to : $from;
		if ( $from <= $last && $to_eff >= $first ) {
			$result[] = $event;
		}
	}

	return $result;
}

/**
 * Нормализует данные события для рендера карточки календаря.
 *
 * @param int $post_id ID события.
 * @param int $year    Год отображаемого месяца.
 * @param int $month   Месяц (1–12).
 * @return array
 */
function sad_znaniy_event_data( $post_id, $year, $month ) {
	$from = (string) get_post_meta( $post_id, '_sz_event_date_from', true );
	$to   = (string) get_post_meta( $post_id, '_sz_event_date_to', true );

	$all_regions = '1' === (string) get_post_meta( $post_id, '_sz_event_all_regions', true );
	$regions_raw = (string) get_post_meta( $post_id, '_sz_event_regions', true );
	$regions     = $all_regions
		? array( 'all' )
		: array_values( array_filter( array_map( 'sanitize_key', explode( ',', $regions_raw ) ) ) );

	$crop_id = (int) get_post_meta( $post_id, '_sz_event_crop', true );
	$crop    = $crop_id ? get_the_title( $crop_id ) : '';

	$terms = get_the_terms( $post_id, 'work_type' );
	$type  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : 'prep';

	// Дни месяца для отображения (событие может выходить за границы месяца).
	$last_day = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );
	$d1       = 1;
	$d2       = $last_day;

	if ( '' !== $from ) {
		$y1 = (int) substr( $from, 0, 4 );
		$m1 = (int) substr( $from, 5, 2 );
		$d1 = (int) substr( $from, 8, 2 );
		if ( $y1 < $year || ( $y1 === $year && $m1 < $month ) ) {
			$d1 = 1;
		}
	}
	if ( '' !== $to ) {
		$y2 = (int) substr( $to, 0, 4 );
		$m2 = (int) substr( $to, 5, 2 );
		if ( $y2 > $year || ( $y2 === $year && $m2 > $month ) ) {
			$d2 = $last_day;
		} else {
			$d2 = (int) substr( $to, 8, 2 );
		}
	} else {
		$d2 = $d1;
	}

	return array(
		'id'       => (int) $post_id,
		'title'    => get_the_title( $post_id ),
		'type'     => $type,
		'd1'       => $d1,
		'd2'       => $d2,
		'crop'     => $crop,
		'crop_id'  => $crop_id,
		'exp'      => (string) get_post_meta( $post_id, '_sz_event_difficulty', true ),
		'pr'       => (string) get_post_meta( $post_id, '_sz_event_priority', true ),
		'hint'     => (string) get_post_meta( $post_id, '_sz_event_weather_hint', true ),
		'regions'  => $regions,
		'permalink'=> get_permalink( $post_id ),
	);
}

/**
 * Строит URL страницы календаря с параметрами.
 *
 * @param int|null    $year   Год.
 * @param int|null    $month  Месяц.
 * @param string      $region Ключ региона.
 * @param string      $exp    Ключ опыта (new/exp/all).
 * @return string
 */
function sad_znaniy_calendar_url( $year = null, $month = null, $region = '', $exp = '' ) {
	$url  = home_url( '/kalendar/' );
	$args = array();
	if ( $year && $month ) {
		$args['year']  = $year;
		$args['month'] = $month;
	}
	if ( '' !== $region ) {
		$args['region'] = $region;
	}
	if ( '' !== $exp ) {
		$args['exp'] = $exp;
	}

	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Ссылки блока «Подробнее» у события: растение + раздел + статьи по культуре.
 *
 * @param int $crop_id ID растения-культуры (0 — если не задана).
 * @return array[] Список пар [подпись, URL] (до 3).
 */
function sad_znaniy_event_links( $crop_id ) {
	$links = array();
	$crop_id = (int) $crop_id;

	if ( $crop_id ) {
		$title = get_the_title( $crop_id );
		if ( $title && 'publish' === get_post_status( $crop_id ) ) {
			$links[] = array( 'Растение «' . $title . '»', get_permalink( $crop_id ) );
		}

		$sections = get_the_terms( $crop_id, 'plant_section' );
		if ( $sections && ! is_wp_error( $sections ) ) {
			$links[] = array( 'Раздел «' . $sections[0]->name . '»', get_term_link( $sections[0] ) );
		}

		if ( $title ) {
			$articles = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					's'              => $title,
					'posts_per_page' => 2,
					'no_found_rows'  => true,
				)
			);
			foreach ( $articles as $article ) {
				if ( (int) $article->ID === $crop_id ) {
					continue;
				}
				$links[] = array( get_the_title( $article ), get_permalink( $article ) );
			}
		}
	}

	return array_slice( $links, 0, 3 );
}
