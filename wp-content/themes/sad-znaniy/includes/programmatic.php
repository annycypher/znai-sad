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
			'key'      => 'rm:' . $region . ':' . $month,
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
			'key'      => 'pr:' . $plant->ID . ':' . $region,
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
			'key'      => 'pm:' . $plant->ID . ':' . $month,
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

	$page = sad_znaniy_programmatic_get_page( $ctx['key'] );
	if ( ! $page || empty( $page['enabled'] ) || sad_znaniy_intro_sentences( $page['intro'] ) < 2 ) {
		return sad_znaniy_programmatic_404();
	}

	$ctx['page']       = $page;
	$GLOBALS['sz_pg']  = $ctx;
	return get_template_directory() . '/templates/programmatic/' . $ctx['template'] . '.php';
}
add_filter( 'template_include', 'sad_znaniy_programmatic_template_include', 20 );

/**
 * Пересчитывает матрицу программатик-страниц (все кандидаты с >=1 событием),
 * сохраняя админ-поля (интро, включение, SEO). Хранит в опции.
 *
 * @return array Ключ => страница.
 */
function sad_znaniy_programmatic_matrix() {
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

	$existing = get_option( 'sad_znaniy_programmatic_pages', array() );
	$pages    = array();
	foreach ( $rm as $k => $n ) {
		list( $rk, $m ) = explode( '|', $k );
		$m              = (int) $m;
		$key            = 'rm:' . $rk . ':' . $m;
		$pages[ $key ]  = array(
			'key'    => $key,
			'family' => 'region-month',
			'region' => $rk,
			'month'  => $m,
			'plant'  => 0,
			'url'    => home_url( '/kalendar/' . sad_znaniy_region_key_to_slug( $rk ) . '/' . sad_znaniy_month_slugs()[ $m ] . '/' ),
			'phrase' => sad_znaniy_programmatic_phrase( 'region-month', $rk, $m ),
			'count'  => $n,
		);
	}
	foreach ( $pr as $k => $n ) {
		list( $pid, $rk ) = explode( '|', $k );
		if ( ! isset( $plant_slugs[ $pid ] ) ) {
			continue;
		}
		$key           = 'pr:' . $pid . ':' . $rk;
		$pages[ $key ] = array(
			'key'    => $key,
			'family' => 'plant-region',
			'region' => $rk,
			'month'  => 0,
			'plant'  => (int) $pid,
			'url'    => home_url( '/kogda-sazhat/' . $plant_slugs[ $pid ] . '/' . sad_znaniy_region_key_to_slug( $rk ) . '/' ),
			'phrase' => sad_znaniy_programmatic_phrase( 'plant-region', $rk, 0, get_post( $pid ) ),
			'count'  => $n,
		);
	}
	foreach ( $pm as $k => $n ) {
		list( $pid, $m ) = explode( '|', $k );
		if ( ! isset( $plant_slugs[ $pid ] ) ) {
			continue;
		}
		$m              = (int) $m;
		$key            = 'pm:' . $pid . ':' . $m;
		$pages[ $key ]  = array(
			'key'    => $key,
			'family' => 'plant-month',
			'region' => '',
			'month'  => $m,
			'plant'  => (int) $pid,
			'url'    => home_url( '/uhod/' . $plant_slugs[ $pid ] . '/' . sad_znaniy_month_slugs()[ $m ] . '/' ),
			'phrase' => sad_znaniy_programmatic_phrase( 'plant-month', '', $m, get_post( $pid ) ),
			'count'  => $n,
		);
	}

	foreach ( $pages as $key => $page ) {
		$prev                      = isset( $existing[ $key ] ) ? $existing[ $key ] : array();
		$page['intro']             = isset( $prev['intro'] ) ? $prev['intro'] : '';
		$page['enabled']           = isset( $prev['enabled'] ) ? (int) $prev['enabled'] : 0;
		$page['seo_title']         = isset( $prev['seo_title'] ) ? $prev['seo_title'] : '';
		$page['seo_description']   = isset( $prev['seo_description'] ) ? $prev['seo_description'] : '';
		$pages[ $key ]             = $page;
	}

	update_option( 'sad_znaniy_programmatic_pages', $pages, false );
	return $pages;
}

/**
 * Пересчитывает матрицу при сохранении события.
 *
 * @param int $post_id ID события.
 */
function sad_znaniy_programmatic_matrix_on_save( $post_id ) {
	if ( 'calendar_event' !== get_post_type( $post_id ) ) {
		return;
	}
	sad_znaniy_programmatic_matrix();
}
add_action( 'save_post_calendar_event', 'sad_znaniy_programmatic_matrix_on_save' );

/**
 * Отдаёт sitemap.xml (статические + «живые» программатик-страницы).
 */
function sad_znaniy_programmatic_sitemap() {
	if ( 1 !== (int) get_query_var( 'sad_sitemap' ) ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: application/xml; charset=utf-8' );

	$pages  = get_option( 'sad_znaniy_programmatic_pages', array() );
	$static = array( home_url( '/' ), home_url( '/kalendar/' ), home_url( '/rasteniya/' ) );

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $static as $u ) {
		echo "\t<url><loc>" . esc_url( $u ) . "</loc></url>\n";
	}
	foreach ( $pages as $page ) {
		if ( 'live' === sad_znaniy_programmatic_status( $page ) ) {
			echo "\t<url><loc>" . esc_url( $page['url'] ) . "</loc></url>\n";
		}
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

/**
 * Строит фразу (H1) программатик-страницы.
 *
 * @param string     $family Семейство.
 * @param string     $region Ключ региона.
 * @param int        $month  Месяц (0 — нет).
 * @param WP_Post|null $plant Растение (или null).
 * @return string
 */
function sad_znaniy_programmatic_phrase( $family, $region, $month, $plant = null ) {
	global $wp_locale;
	if ( 'region-month' === $family ) {
		return 'Работы дачника: ' . sad_znaniy_option_label( sad_znaniy_region_keys(), $region ) . ', ' . mb_strtolower( $wp_locale->month[ zeroise( $month, 2 ) ], 'UTF-8' );
	}
	if ( $plant ) {
		$pn = mb_strtolower( $plant->post_title, 'UTF-8' );
		if ( 'plant-region' === $family ) {
			return 'Когда сажать ' . $pn . ' (' . sad_znaniy_option_label( sad_znaniy_region_keys(), $region ) . ')';
		}
		return 'Уход за ' . $pn . ': ' . mb_strtolower( $wp_locale->month[ zeroise( $month, 2 ) ], 'UTF-8' );
	}
	return '';
}

/**
 * Считает предложения в интро (для порога >=2).
 *
 * @param string $intro Текст интро.
 * @return int
 */
function sad_znaniy_intro_sentences( $intro ) {
	$intro = trim( (string) $intro );
	if ( '' === $intro ) {
		return 0;
	}
	$parts = preg_split( '/[.!?]+/u', $intro, -1, PREG_SPLIT_NO_EMPTY );
	return count( $parts );
}

/**
 * Возвращает запись программатик-страницы по ключу.
 *
 * @param string $key Ключ страницы.
 * @return array|null
 */
function sad_znaniy_programmatic_get_page( $key ) {
	$pages = get_option( 'sad_znaniy_programmatic_pages', array() );
	return isset( $pages[ $key ] ) ? $pages[ $key ] : null;
}

/**
 * Статус программатик-страницы: live / draft / empty.
 *
 * @param array $page Запись страницы.
 * @return string
 */
function sad_znaniy_programmatic_status( $page ) {
	if ( (int) $page['count'] < 5 ) {
		return 'empty';
	}
	if ( ! empty( $page['enabled'] ) && sad_znaniy_intro_sentences( $page['intro'] ) >= 2 ) {
		return 'live';
	}
	return 'draft';
}

/**
 * Подменяет <title> на программатик-страницах.
 *
 * @param array $parts Части заголовка.
 * @return array
 */
function sad_znaniy_programmatic_title( $parts ) {
	if ( ! isset( $GLOBALS['sz_pg'] ) ) {
		return $parts;
	}
	$page  = $GLOBALS['sz_pg']['page'];
	$title = $page['seo_title'] ? $page['seo_title'] : ( $page['phrase'] . ' — календарь и сроки' );
	$parts['title'] = $title;
	return $parts;
}
add_filter( 'document_title_parts', 'sad_znaniy_programmatic_title', 20 );

/**
 * Выводит meta description и Schema.org (BreadcrumbList + ItemList).
 */
function sad_znaniy_programmatic_head() {
	if ( ! isset( $GLOBALS['sz_pg'] ) ) {
		return;
	}
	$ctx  = $GLOBALS['sz_pg'];
	$page = $ctx['page'];
	$desc = $page['seo_description'] ? $page['seo_description'] : ( $page['phrase'] . ' — календарь работ и сроки по данным «Сада знаний».' );
	echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";

	$items = array();
	foreach ( array_slice( $ctx['events'], 0, 20 ) as $i => $event ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => get_the_title( $event ),
		);
	}
	$schema = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => home_url( '/' ) ),
					array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Календарь дачника', 'item' => home_url( '/kalendar/' ) ),
					array( '@type' => 'ListItem', 'position' => 3, 'name' => $page['phrase'] ),
				),
			),
			array(
				'@type'           => 'ItemList',
				'name'            => $page['phrase'],
				'itemListElement' => $items,
			),
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
}
add_action( 'wp_head', 'sad_znaniy_programmatic_head', 1 );

/**
 * Родственные страницы («Сроки по регионам» / соседние месяцы).
 *
 * @param array $ctx Контекст.
 * @return array[]
 */
function sad_znaniy_programmatic_related( $ctx ) {
	$pages   = get_option( 'sad_znaniy_programmatic_pages', array() );
	$related = array();
	foreach ( $pages as $key => $page ) {
		if ( $key === $ctx['key'] ) {
			continue;
		}
		if ( 'empty' === sad_znaniy_programmatic_status( $page ) ) {
			continue;
		}
		if ( 'region-month' === $ctx['template'] && 'region-month' === $page['family'] && $page['month'] === $ctx['month'] ) {
			$related[] = $page;
		}
		if ( 'plant-region' === $ctx['template'] && 'plant-region' === $page['family'] && $page['plant'] === $ctx['plant']->ID ) {
			$related[] = $page;
		}
		if ( 'plant-month' === $ctx['template'] && 'plant-month' === $page['family'] && $page['plant'] === $ctx['plant']->ID ) {
			$related[] = $page;
		}
	}
	return $related;
}

/**
 * Ссылки «Подробнее» страницы (календарь с фильтрами + растение + статьи).
 *
 * @param array $ctx Контекст.
 * @return array[] Пары [подпись, URL].
 */
function sad_znaniy_programmatic_page_links( $ctx ) {
	$links = array();

	if ( 'region-month' === $ctx['template'] ) {
		$links[] = array( 'Календарь: ' . sad_znaniy_option_label( sad_znaniy_region_keys(), $ctx['region'] ) . ', ' . sad_znaniy_month_slugs()[ $ctx['month'] ], sad_znaniy_calendar_url( gmdate( 'Y' ), $ctx['month'], $ctx['region'] ) );
	}

	if ( $ctx['plant'] ) {
		$links[] = array( 'Растение «' . $ctx['plant']->post_title . '»', get_permalink( $ctx['plant'] ) );
		$articles = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				's'              => $ctx['plant']->post_title,
				'posts_per_page' => 2,
				'no_found_rows'  => true,
			)
		);
		foreach ( $articles as $article ) {
			$links[] = array( get_the_title( $article ), get_permalink( $article ) );
		}
	}

	return array_slice( $links, 0, 4 );
}


