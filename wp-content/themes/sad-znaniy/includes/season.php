<?php
/**
 * Этап 5.7 — Партия B: сезонная маска в карточке растения.
 *
 * Сезон = рабочие окна культуры по региону из CPT calendar_event
 * (мета _sz_event_crop + _sz_event_regions + _sz_event_date_from/_to).
 * Лунные дни показываются только внутри окон; вне окон — «сезон закрыт».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Разбирает даты события в «ММ-ДД» (год не важен: окна ежегодные).
 *
 * @param string $from Дата начала (Y-m-d или '').
 * @param string $to   Дата конца (Y-m-d или '').
 * @return array [md_from, md_to]
 */
function sad_znaniy_season_range( $from, $to ) {
	$from = trim( (string) $from );
	if ( '' === $from ) {
		return array( '', '' );
	}
	$md_from = substr( $from, 5, 5 );
	$md_to   = ( '' !== trim( (string) $to ) ) ? substr( trim( (string) $to ), 5, 5 ) : $md_from;
	if ( '' === $md_to ) {
		$md_to = $md_from;
	}
	return array( $md_from, $md_to );
}

/**
 * Попадает ли «ММ-ДД» в диапазон (учёт перехода через новый год).
 *
 * @param string $md   Проверяемая дата «ММ-ДД».
 * @param string $from Начало.
 * @param string $to   Конец.
 * @return bool
 */
function sad_znaniy_season_md_in_range( $md, $from, $to ) {
	if ( '' === $from || '' === $md ) {
		return false;
	}
	if ( $from === $to ) {
		return ( $md === $from );
	}
	if ( $from < $to ) {
		return ( $md >= $from && $md <= $to );
	}
	// Диапазон через новый год: ноябрь → февраль.
	return ( $md >= $from || $md <= $to );
}

/**
 * Сезонные окна культуры (посев и посадка) для региона.
 *
 * @param int    $plant_id ID растения.
 * @param string $region   Ключ региона ('' — без фильтра).
 * @return array
 */
function sad_znaniy_plant_season_windows( $plant_id, $region = '' ) {
	static $cache = array();
	$cache_key = (int) $plant_id . '|' . (string) $region;
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$events = get_posts(
		array(
			'post_type'      => 'calendar_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_sz_event_crop',
					'value'   => (int) $plant_id,
					'compare' => '=',
				),
			),
		)
	);

	$work = sad_znaniy_work_types();
	$out  = array();

	foreach ( $events as $eid ) {
		$slugs = wp_get_post_terms( $eid, 'work_type', array( 'fields' => 'slugs' ) );
		$slug  = ( is_array( $slugs ) && $slugs ) ? (string) $slugs[0] : '';

		// Сезон — это посев и посадка; подкормки/обрезки сезон не задают.
		if ( ! in_array( $slug, array( 'posev', 'posadka' ), true ) ) {
			continue;
		}

		$all     = '1' === (string) get_post_meta( $eid, '_sz_event_all_regions', true );
		$regions = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $eid, '_sz_event_regions', true ) ) ) );

		if ( '' !== $region && ! $all && ! in_array( $region, $regions, true ) ) {
			continue;
		}

		list( $md_from, $md_to ) = sad_znaniy_season_range(
			(string) get_post_meta( $eid, '_sz_event_date_from', true ),
			(string) get_post_meta( $eid, '_sz_event_date_to', true )
		);
		if ( '' === $md_from ) {
			continue;
		}

		$out[] = array(
			'id'          => (int) $eid,
			'title'       => (string) get_the_title( $eid ),
			'md_from'     => $md_from,
			'md_to'       => $md_to,
			'month'       => (int) substr( $md_from, 0, 2 ),
			'work'        => $slug,
			'work_label'  => isset( $work[ $slug ] ) ? $work[ $slug ] : '',
			'all_regions' => $all,
			'regions'     => $regions,
			'recurring'   => '1' === (string) get_post_meta( $eid, '_sz_event_recurring', true ),
		);
	}

	usort(
		$out,
		function ( $a, $b ) {
			return strcmp( $a['md_from'], $b['md_from'] );
		}
	);

	$cache[ $cache_key ] = $out;
	return $out;
}

/**
 * Дни месяца, входящие в окно.
 *
 * @param array $window Окно.
 * @param int   $year   Год.
 * @param int   $month  Месяц.
 * @return int[]
 */
function sad_znaniy_season_window_days( $window, $year, $month ) {
	$total = (int) gmdate( 't', gmmktime( 12, 0, 0, (int) $month, 1, (int) $year ) );
	$days  = array();
	for ( $d = 1; $d <= $total; $d++ ) {
		$md = sprintf( '%02d-%02d', (int) $month, $d );
		if ( sad_znaniy_season_md_in_range( $md, $window['md_from'], $window['md_to'] ) ) {
			$days[] = $d;
		}
	}
	return $days;
}

/**
 * Окна, которые пересекают указанный месяц.
 *
 * @param array $windows Окна культуры.
 * @param int   $year    Год.
 * @param int   $month   Месяц.
 * @return array
 */
function sad_znaniy_season_windows_in_month( $windows, $year, $month ) {
	$out = array();
	foreach ( (array) $windows as $win ) {
		$days = sad_znaniy_season_window_days( $win, $year, $month );
		if ( $days ) {
			$win['days_in_month'] = $days;
			$out[]                = $win;
		}
	}
	return $out;
}

/**
 * Ближайшее следующее окно (месяц и само окно) после указанного месяца.
 *
 * @param array $windows Окна культуры.
 * @param int   $month   Текущий месяц.
 * @return array|null
 */
function sad_znaniy_season_next_window( $windows, $month ) {
	$best        = null;
	$best_delta  = 13;
	foreach ( (array) $windows as $win ) {
		$delta = ( (int) $win['month'] - (int) $month + 12 ) % 12;
		if ( 0 === $delta ) {
			continue; // окно в текущем месяце — месяц не «закрыт»
		}
		if ( $delta < $best_delta ) {
			$best_delta = $delta;
			$best       = $win;
		}
	}
	return $best;
}

/**
 * Родительный падеж месяца: «мая», «октября» (для диапазонов дат).
 *
 * @param int $month Месяц (1–12).
 * @return string
 */
function sad_znaniy_season_month_genitive( $month ) {
	$names = sad_znaniy_moon_month_names();
	$month = (int) $month;
	if ( ! isset( $names[ $month ][1] ) ) {
		return '';
	}
	$base = (string) $names[ $month ][1];
	$last = mb_substr( $base, -1 );
	return ( 'ь' === $last ) ? ( mb_substr( $base, 0, -1 ) . 'я' ) : ( $base . 'а' );
}

/**
 * Подпись диапазона окна: «10–20 мая».
 *
 * @param array $win Окно.
 * @return string
 */
function sad_znaniy_season_range_label( $win ) {
	$from_m = (int) substr( $win['md_from'], 0, 2 );
	$from_d = (int) substr( $win['md_from'], 3, 2 );
	$to_m   = (int) substr( $win['md_to'], 0, 2 );
	$to_d   = (int) substr( $win['md_to'], 3, 2 );

	$m_from = sad_znaniy_season_month_genitive( $from_m );
	$m_to   = sad_znaniy_season_month_genitive( $to_m );

	if ( $from_m === $to_m ) {
		return ( $from_d === $to_d ) ? ( $from_d . ' ' . $m_from ) : ( $from_d . '–' . $to_d . ' ' . $m_from );
	}
	return $from_d . ' ' . $m_from . ' – ' . $to_d . ' ' . $m_to;
}

/**
 * Два месяца лунных дней (текущий и следующий) — когда сезон неизвестен.
 *
 * @param string $cat   Лунная категория.
 * @param int    $year  Год.
 * @param int    $month Месяц.
 * @return array
 */
function sad_znaniy_plant_moon_months( $cat, $year, $month ) {
	$names   = sad_znaniy_moon_month_names();
	$year    = (int) $year;
	$month   = (int) $month;
	$pairs   = array( array( $year, $month ) );
	$pairs[] = ( 12 === $month ) ? array( $year + 1, 1 ) : array( $year, $month + 1 );

	$out = array();
	foreach ( $pairs as $pair ) {
		$out[] = array(
			'label' => isset( $names[ $pair[1] ][0] ) ? $names[ $pair[1] ][0] : '',
			'days'  => sad_znaniy_moon_category_days( $pair[0], $pair[1], $cat ),
			'url'   => sad_znaniy_moon_category_url( $pair[1], $cat ),
		);
	}
	return $out;
}

/**
 * Данные сезонного блока растения.
 *
 * Режимы: active (окно идёт) / closed (сезон закрыт) / other_region
 * (нет сроков для региона) / no_data (у культуры нет сезонных событий) /
 * no_region (регион не выбран).
 *
 * @param int    $plant_id ID растения.
 * @param string $region   Ключ региона ('' — не выбран).
 * @return array Пусто, если у растения нет лунной категории.
 */
function sad_znaniy_plant_moon_data( $plant_id, $region = '' ) {
	$plant_id = (int) $plant_id;
	$cat      = sad_znaniy_moon_plant_category( $plant_id );
	if ( '' === $cat ) {
		return array();
	}

	$cats   = sad_znaniy_lunar_categories();
	$names  = sad_znaniy_moon_month_names();
	$year   = (int) current_time( 'Y' );
	$month  = (int) current_time( 'n' );
	$region = (string) $region;

	$data = array(
		'plant_id'     => $plant_id,
		'cat'          => $cat,
		'cat_label'    => isset( $cats[ $cat ] ) ? $cats[ $cat ] : '',
		'region'       => $region,
		'region_label' => ( '' !== $region ) ? sad_znaniy_region_label_by_key( $region ) : '',
		'year'         => $year,
		'month'        => $month,
		'mode'         => 'no_region',
		'windows'      => array(),
		'good_days'    => array(),
		'next'         => array(),
		'note'         => '',
		'months'       => array(),
	);

	$month_days = sad_znaniy_moon_category_days( $year, $month, $cat );

	if ( '' === $region ) {
		$data['note']   = __( 'Регион не выбран — показаны лунные дни для всех регионов.', 'sad-znaniy' );
		$data['months'] = sad_znaniy_plant_moon_months( $cat, $year, $month );
		return $data;
	}

	$windows = sad_znaniy_plant_season_windows( $plant_id, $region );

	if ( ! $windows ) {
		$any          = sad_znaniy_plant_season_windows( $plant_id, '' );
		$data['mode'] = $any ? 'other_region' : 'no_data';
		$data['note'] = $any
			? sprintf( 'Точных сроков для региона «%s» в календаре пока нет — показываем лунные дни для всех регионов.', $data['region_label'] )
			: __( 'Сезонных сроков этой культуры в календаре пока нет — показаны лунные дни для всех регионов.', 'sad-znaniy' );
		$data['months'] = sad_znaniy_plant_moon_months( $cat, $year, $month );
		return $data;
	}

	$in_month = sad_znaniy_season_windows_in_month( $windows, $year, $month );

	if ( $in_month ) {
		$data['mode'] = 'active';
		$good         = array();
		foreach ( $in_month as $win ) {
			$inside            = array_values( array_intersect( $month_days, $win['days_in_month'] ) );
			$win['good_days']  = $inside;
			$good              = array_merge( $good, $inside );
			$data['windows'][] = $win;
		}
		$good = array_values( array_unique( $good ) );
		sort( $good );
		$data['good_days'] = $good;
		return $data;
	}

	$next         = sad_znaniy_season_next_window( $windows, $month );
	$data['mode'] = 'closed';

	if ( $next ) {
		$nm      = (int) $next['month'];
		$ndays   = sad_znaniy_moon_category_days( $year, $nm, $cat );
		$in_next = sad_znaniy_season_windows_in_month( array( $next ), $year, $nm );
		$inside  = $in_next ? array_values( array_intersect( $ndays, $in_next[0]['days_in_month'] ) ) : array();

		$data['next'] = array(
			'month'  => $nm,
			'label'  => isset( $names[ $nm ][1] ) ? $names[ $nm ][1] : '',
			'in'     => isset( $names[ $nm ][2] ) ? $names[ $nm ][2] : '',
			'days'   => $inside ? $inside : $ndays,
			'url'    => sad_znaniy_moon_category_url( $nm, $cat ),
			'window' => $next,
		);
	}

	return $data;
}

/**
 * Рендер сезонного блока растения (SSR и REST используют одно и то же).
 *
 * @param int    $plant_id ID растения.
 * @param string $region   Ключ региона.
 * @return string HTML.
 */
function sad_znaniy_plant_moon_render( $plant_id, $region = '' ) {
	$data = sad_znaniy_plant_moon_data( $plant_id, $region );
	if ( empty( $data ) ) {
		return '';
	}

	$list = function ( $days ) {
		$days = array_filter( array_map( 'intval', (array) $days ) );
		return $days ? implode( ', ', array_map( 'strval', $days ) ) : '—';
	};

	ob_start();
	?>
	<div class="about-panel plant-lunar" data-sz-plant-moon data-plant="<?php echo (int) $data['plant_id']; ?>" data-region="<?php echo esc_attr( $data['region'] ); ?>">
		<div>
			<h2><?php esc_html_e( 'Лунные дни для посадки', 'sad-znaniy' ); ?></h2>
			<p class="plant-lunar__meta">
				<?php echo esc_html( sprintf( 'Культура лунной категории «%s»', $data['cat_label'] ) ); ?>
				<?php if ( $data['region_label'] ) : ?>
					· <?php echo esc_html( 'регион: ' . $data['region_label'] ); ?>
				<?php endif; ?>
			</p>

			<?php if ( 'active' === $data['mode'] ) : ?>
				<?php foreach ( $data['windows'] as $win ) : ?>
					<p class="plant-lunar__win">
						<strong><?php echo esc_html( $win['title'] ); ?></strong>
						<span><?php echo esc_html( $win['work_label'] . ' · ' . sad_znaniy_season_range_label( $win ) ); ?></span>
					</p>
				<?php endforeach; ?>
				<p>
					<?php echo esc_html( 'Благоприятные лунные дни внутри окна: ' . $list( $data['good_days'] ) ); ?>
					<a href="<?php echo esc_url( sad_znaniy_moon_category_url( $data['month'], $data['cat'] ) ); ?>"><?php esc_html_e( 'весь месяц →', 'sad-znaniy' ); ?></a>
				</p>
				<?php if ( ! $data['good_days'] ) : ?>
					<p class="plant-lunar__note"><?php esc_html_e( 'В окне нет благоприятных лунных дней — ориентируйтесь на погоду, а точные числа смотрите в календаре месяца.', 'sad-znaniy' ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'closed' === $data['mode'] && ! empty( $data['next'] ) ) : ?>
				<p class="plant-lunar__closed">
					<?php echo esc_html( 'Сезон закрыт — вернётся в ' . $data['next']['in'] . '.' ); ?>
					<?php echo esc_html( 'Благоприятные дни в ' . $data['next']['in'] . ': ' . $list( $data['next']['days'] ) . '.' ); ?>
					<a href="<?php echo esc_url( $data['next']['url'] ); ?>"><?php esc_html_e( 'открыть в календаре →', 'sad-znaniy' ); ?></a>
				</p>
			<?php else : ?>
				<p class="plant-lunar__note"><?php echo esc_html( $data['note'] ); ?></p>
				<?php foreach ( (array) $data['months'] as $mn ) : ?>
					<p>
						<strong><?php echo esc_html( $mn['label'] ); ?>:</strong>
						<?php echo esc_html( $list( $mn['days'] ) ); ?>
						&nbsp;— <a href="<?php echo esc_url( $mn['url'] ); ?>"><?php esc_html_e( 'в календаре →', 'sad-znaniy' ); ?></a>
					</p>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div>
			<p class="plant-lunar__hint"><?php esc_html_e( 'Регион меняется в шапке сайта — блок обновится без перезагрузки.', 'sad-znaniy' ); ?></p>
			<p><a class="btn btn-light" href="<?php echo esc_url( sad_znaniy_moon_month_url( $data['month'] ) ); ?>"><?php esc_html_e( 'Лунный календарь месяца →', 'sad-znaniy' ); ?></a></p>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * REST: блок лунных дней растения для выбранного региона.
 *
 * @param WP_REST_Request $request Запрос.
 * @return WP_REST_Response
 */
function sad_znaniy_rest_plant_moon( $request ) {
	$plant  = (int) $request->get_param( 'plant' );
	$region = (string) $request->get_param( 'region' );

	if ( ! array_key_exists( $region, sad_znaniy_region_keys() ) ) {
		$region = '';
	}

	return rest_ensure_response( array( 'html' => sad_znaniy_plant_moon_render( $plant, $region ) ) );
}

/**
 * Регистрирует REST-маршрут.
 */
function sad_znaniy_register_plant_moon_rest() {
	register_rest_route(
		'sad-znaniy/v1',
		'/plant-moon',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'sad_znaniy_rest_plant_moon',
		)
	);
}
add_action( 'rest_api_init', 'sad_znaniy_register_plant_moon_rest' );

/**
 * Подключает обновление блока на странице растения.
 */
function sad_znaniy_season_assets() {
	if ( ! is_singular( 'plant' ) ) {
		return;
	}

	wp_enqueue_script(
		'sad-znaniy-plant-moon',
		get_template_directory_uri() . '/assets/js/plant-moon.js',
		array(),
		sad_znaniy_asset_ver( 'assets/js/plant-moon.js' ),
		true
	);

	wp_localize_script(
		'sad-znaniy-plant-moon',
		'szPlantMoon',
		array( 'url' => esc_url_raw( rest_url( 'sad-znaniy/v1/plant-moon' ) ) )
	);
}
add_action( 'wp_enqueue_scripts', 'sad_znaniy_season_assets', 21 );



