<?php
/**
 * Программатик-SEO на данных календаря (Этап 7.1).
 *
 * Три семейства ЧПУ (без новых CPT — через rewrite + query_vars + шаблоны
 * в /templates/programmatic/):
 *   /kalendar/{region}/{month}/      — «Работы дачника: регион, месяц»
 *   /kogda-sazhat/{plant}/{region}/  — «Когда сажать растение (регион)»
 *   /uhod/{plant}/{month}/           — «Уход за растением: месяц»
 *
 * Страница существует только при >=5 событиях (иначе 404).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует query-переменные программатика.
 *
 * @param array $vars Существующие переменные.
 * @return array
 */
function sad_znaniy_programmatic_query_vars( $vars ) {
	$vars[] = 'sad_pg';
	$vars[] = 'sad_region';
	$vars[] = 'sad_month';
	$vars[] = 'sad_plant';
	$vars[] = 'sad_sitemap';
	return $vars;
}
add_filter( 'query_vars', 'sad_znaniy_programmatic_query_vars' );

/**
 * Регистрирует rewrite-правила трёх семейств и sitemap.xml.
 */
function sad_znaniy_programmatic_rewrite_rules() {
	add_rewrite_rule( '^kalendar/([a-z0-9-]+)/([a-z0-9-]+)/?$', 'index.php?sad_pg=region-month&sad_region=$matches[1]&sad_month=$matches[2]', 'top' );
	add_rewrite_rule( '^kogda-sazhat/([a-z0-9-]+)/([a-z0-9-]+)/?$', 'index.php?sad_pg=plant-region&sad_plant=$matches[1]&sad_region=$matches[2]', 'top' );
	add_rewrite_rule( '^uhod/([a-z0-9-]+)/([a-z0-9-]+)/?$', 'index.php?sad_pg=plant-month&sad_plant=$matches[1]&sad_month=$matches[2]', 'top' );
	add_rewrite_rule( '^sitemap\.xml$', 'index.php?sad_sitemap=1', 'top' );
}
add_action( 'init', 'sad_znaniy_programmatic_rewrite_rules' );

/**
 * Один раз сбрасывает правила ЧПУ (по опции версии, не на каждом хите).
 */
function sad_znaniy_programmatic_maybe_flush() {
	if ( '1' === get_option( 'sad_znaniy_pg_rules_version' ) ) {
		return;
	}
	sad_znaniy_programmatic_rewrite_rules();
	flush_rewrite_rules();
	update_option( 'sad_znaniy_pg_rules_version', '1', false );
}
add_action( 'init', 'sad_znaniy_programmatic_maybe_flush', 20 );

/**
 * Входит ли событие в заданный месяц (по диапазону дат, без учёта года).
 *
 * @param int $post_id ID события.
 * @param int $month   Месяц (1–12).
 * @return bool
 */
function sad_znaniy_event_in_month( $post_id, $month ) {
	$from = (string) get_post_meta( $post_id, '_sz_event_date_from', true );
	if ( '' === $from ) {
		return false;
	}
	$to = (string) get_post_meta( $post_id, '_sz_event_date_to', true );
	$m1 = (int) substr( $from, 5, 2 );
	$m2 = '' !== $to ? (int) substr( $to, 5, 2 ) : $m1;

	if ( $m1 <= $m2 ) {
		return $month >= $m1 && $month <= $m2;
	}
	return $month >= $m1 || $month <= $m2;
}

/**
 * Выбирает опубликованные события по региону / месяцу / культуре.
 *
 * @param string $region   Ключ региона ('' — все).
 * @param int    $month    Месяц (0 — все).
 * @param int    $plant_id ID растения (0 — все).
 * @return WP_Post[]
 */
function sad_znaniy_programmatic_events( $region = '', $month = 0, $plant_id = 0 ) {
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

	$result = array();
	foreach ( $events as $event ) {
		if ( $plant_id && (int) get_post_meta( $event->ID, '_sz_event_crop', true ) !== $plant_id ) {
			continue;
		}
		if ( $region ) {
			$all  = '1' === (string) get_post_meta( $event->ID, '_sz_event_all_regions', true );
			$regs = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $event->ID, '_sz_event_regions', true ) ) ) );
			if ( ! $all && ! in_array( $region, $regs, true ) ) {
				continue;
			}
		}
		if ( $month && ! sad_znaniy_event_in_month( $event->ID, $month ) ) {
			continue;
		}
		$result[] = $event;
	}

	return $result;
}

/**
 * Находит опубликованное растение по слагу.
 *
 * @param string $slug Слаг растения.
 * @return WP_Post|null
 */
function sad_znaniy_programmatic_find_plant( $slug ) {
	$plant = get_page_by_path( sanitize_title( $slug ), OBJECT, 'plant' );
	return ( $plant && 'publish' === $plant->post_status ) ? $plant : null;
}

/**
 * Устанавливает 404 и возвращает шаблон 404.
 *
 * @return string
 */
function sad_znaniy_programmatic_404() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	return get_404_template();
}

/**
 * Разрешает программатик-запрос в контекст шаблона.
 *
 * @param string $pg Тип семейства (region-month / plant-region / plant-month).
 * @return array|null Контекст или null, если не удалось разрешить.
 */
function sad_znaniy_programmatic_resolve( $pg ) {
	$region_slug = (string) get_query_var( 'sad_region' );
	$month_slug  = (string) get_query_var( 'sad_month' );
	$plant_slug  = (string) get_query_var( 'sad_plant' );

	if ( 'region-month' === $pg ) {
		$region = sad_znaniy_region_slug_to_key( $region_slug );
		$month  = sad_znaniy_month_slug_to_num( $month_slug );
		if ( ! $region || ! $month ) {
			return null;
		}
		return array(
			'template' => 'region-month',
			'region'   => $region,
			'month'    => $month,
			'plant'    => null,
			'events'   => sad_znaniy_programmatic_events( $region, $month ),
		);
	}

	if ( 'plant-region' === $pg ) {
		$plant  = sad_znaniy_programmatic_find_plant( $plant_slug );
		$region = sad_znaniy_region_slug_to_key( $region_slug );
		if ( ! $plant || ! $region ) {
			return null;
		}
		return array(
			'template' => 'plant-region',
			'region'   => $region,
			'month'    => 0,
			'plant'    => $plant,
			'events'   => sad_znaniy_programmatic_events( $region, 0, $plant->ID ),
		);
	}

	if ( 'plant-month' === $pg ) {
		$plant = sad_znaniy_programmatic_find_plant( $plant_slug );
		$month = sad_znaniy_month_slug_to_num( $month_slug );
		if ( ! $plant || ! $month ) {
			return null;
		}
		return array(
			'template' => 'plant-month',
			'region'   => '',
			'month'    => $month,
			'plant'    => $plant,
			'events'   => sad_znaniy_programmatic_events( '', $month, $plant->ID ),
		);
	}

	return null;
}

/**
 * Подставляет программатик-шаблон или отдаёт 404 для «пустышек».
 *
 * @param string $template Текущий шаблон.
 * @return string
 */
function sad_znaniy_programmatic_template_include( $template ) {
	$pg = (string) get_query_var( 'sad_pg' );
	if ( '' === $pg ) {
		return $template;
	}

	$ctx = sad_znaniy_programmatic_resolve( $pg );
	if ( ! $ctx || count( $ctx['events'] ) < 5 ) {
		return sad_znaniy_programmatic_404();
	}

	$GLOBALS['sz_pg'] = $ctx;
	return get_template_directory() . '/templates/programmatic/' . $ctx['template'] . '.php';
}
add_filter( 'template_include', 'sad_znaniy_programmatic_template_include', 20 );

/**
 * Пересчитывает индекс «живых» программатик-URL (>=5 событий) в опцию.
 *
 * @return array
 */
function sad_znaniy_programmatic_rebuild_index() {
	$events = get_posts(
		array(
			'post_type'      => 'calendar_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	$plants = get_posts(
		array(
			'post_type'      => 'plant',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);

	$plant_slugs = array();
	foreach ( $plants as $p ) {
		$plant_slugs[ $p->ID ] = $p->post_name;
	}

	$rm = array(); // "region|month" => count.
	$pr = array(); // "plant|region" => count.
	$pm = array(); // "plant|month" => count.

	foreach ( $events as $e ) {
		$crop = (int) get_post_meta( $e->ID, '_sz_event_crop', true );
		$all  = '1' === (string) get_post_meta( $e->ID, '_sz_event_all_regions', true );
		$regs = $all
			? array( 'all' )
			: array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $e->ID, '_sz_event_regions', true ) ) ) );

		$from = (string) get_post_meta( $e->ID, '_sz_event_date_from', true );
		$to   = (string) get_post_meta( $e->ID, '_sz_event_date_to', true );
		$m1   = '' !== $from ? (int) substr( $from, 5, 2 ) : 0;
		$m2   = '' !== $to ? (int) substr( $to, 5, 2 ) : $m1;

		$months = array();
		if ( $m1 ) {
			if ( $m1 <= $m2 ) {
				for ( $m = $m1; $m <= $m2; $m++ ) {
					$months[] = $m;
				}
			} else {
				for ( $m = $m1; $m <= 12; $m++ ) {
					$months[] = $m;
				}
				for ( $m = 1; $m <= $m2; $m++ ) {
					$months[] = $m;
				}
			}
		}

		foreach ( $months as $m ) {
			foreach ( sad_znaniy_region_keys() as $rk => $rl ) {
				if ( in_array( 'all', $regs, true ) || in_array( $rk, $regs, true ) ) {
					$k        = $rk . '|' . $m;
					$rm[ $k ] = isset( $rm[ $k ] ) ? $rm[ $k ] + 1 : 1;
				}
			}
			if ( $crop ) {
				$k        = $crop . '|' . $m;
				$pm[ $k ] = isset( $pm[ $k ] ) ? $pm[ $k ] + 1 : 1;
			}
		}
		if ( $crop ) {
			foreach ( sad_znaniy_region_keys() as $rk => $rl ) {
				if ( in_array( 'all', $regs, true ) || in_array( $rk, $regs, true ) ) {
					$k        = $crop . '|' . $rk;
					$pr[ $k ] = isset( $pr[ $k ] ) ? $pr[ $k ] + 1 : 1;
				}
			}
		}
	}

	$index = array();
	foreach ( $rm as $k => $n ) {
		if ( $n < 5 ) {
			continue;
		}
		list( $rk, $m ) = explode( '|', $k );
		$index[] = array(
			'family' => 'region-month',
			'url'    => home_url( '/kalendar/' . sad_znaniy_region_key_to_slug( $rk ) . '/' . sad_znaniy_month_slugs()[ (int) $m ] . '/' ),
			'region' => $rk,
			'month'  => (int) $m,
		);
	}
	foreach ( $pr as $k => $n ) {
		if ( $n < 5 ) {
			continue;
		}
		list( $pid, $rk ) = explode( '|', $k );
		if ( ! isset( $plant_slugs[ $pid ] ) ) {
			continue;
		}
		$index[] = array(
			'family' => 'plant-region',
			'url'    => home_url( '/kogda-sazhat/' . $plant_slugs[ $pid ] . '/' . sad_znaniy_region_key_to_slug( $rk ) . '/' ),
			'plant'  => (int) $pid,
			'region' => $rk,
		);
	}
	foreach ( $pm as $k => $n ) {
		if ( $n < 5 ) {
			continue;
		}
		list( $pid, $m ) = explode( '|', $k );
		if ( ! isset( $plant_slugs[ $pid ] ) ) {
			continue;
		}
		$index[] = array(
			'family' => 'plant-month',
			'url'    => home_url( '/uhod/' . $plant_slugs[ $pid ] . '/' . sad_znaniy_month_slugs()[ (int) $m ] . '/' ),
			'plant'  => (int) $pid,
			'month'  => (int) $m,
		);
	}

	update_option( 'sad_znaniy_programmatic_index', $index, false );
	return $index;
}

/**
 * Пересчитывает индекс при сохранении события.
 *
 * @param int $post_id ID события.
 */
function sad_znaniy_programmatic_rebuild_on_save( $post_id ) {
	if ( 'calendar_event' !== get_post_type( $post_id ) ) {
		return;
	}
	sad_znaniy_programmatic_rebuild_index();
}
add_action( 'save_post_calendar_event', 'sad_znaniy_programmatic_rebuild_on_save' );

/**
 * Отдаёт sitemap.xml (статические + «живые» программатик-страницы).
 */
function sad_znaniy_programmatic_sitemap() {
	if ( 1 !== (int) get_query_var( 'sad_sitemap' ) ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: application/xml; charset=utf-8' );

	$index  = get_option( 'sad_znaniy_programmatic_index', array() );
	$static = array( home_url( '/' ), home_url( '/kalendar/' ), home_url( '/rasteniya/' ) );

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $static as $u ) {
		echo "\t<url><loc>" . esc_url( $u ) . "</loc></url>\n";
	}
	foreach ( $index as $item ) {
		echo "\t<url><loc>" . esc_url( $item['url'] ) . "</loc></url>\n";
	}
	echo '</urlset>';
	exit;
}
add_action( 'template_redirect', 'sad_znaniy_programmatic_sitemap', 1 );

/**
 * Дописывает ссылку на sitemap в robots.txt.
 *
 * @param string $output Содержимое robots.txt.
 * @return string
 */
function sad_znaniy_programmatic_robots( $output ) {
	$output .= "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
	return $output;
}
add_filter( 'robots_txt', 'sad_znaniy_programmatic_robots' );


