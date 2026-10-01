<?php
/**
 * SEO-Хаб → Эффективность (Этап 7.2, Партия 3).
 *
 * Свои счётчики: визиты страниц программатика и календаря (без куки,
 * агрегат по дню) и клики «Подробнее → статья» (REST + nonce).
 * Визиты администраторов не считаются.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL-ключ текущего запроса (путь без параметров и домена).
 *
 * @return string
 */
function sad_znaniy_seo_current_url_key() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	return '/' . ltrim( $path, '/' );
}

/**
 * Считает визит на программатик-странице и странице календаря.
 *
 * Администраторы не считаются (проверка своими глазами не искажает статистику).
 */
function sad_znaniy_seo_count_view() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return;
	}
	if ( is_404() || is_search() || is_feed() ) {
		return;
	}
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return;
	}

	$is_programmatic = (bool) get_query_var( 'sad_pg' );
	$is_calendar     = is_page_template( 'template-calendar.php' );

	if ( ! $is_programmatic && ! $is_calendar ) {
		return;
	}

	// Черновики и пустышки не считаем: такие страницы не открываются (404).
	if ( $is_programmatic ) {
		$index = sad_znaniy_seo_pages_index();
		$url   = home_url( sad_znaniy_seo_current_url_key() );
		if ( ! isset( $index[ $url ] ) || 'live' !== $index[ $url ] ) {
			return;
		}
	}

	$tables = sad_znaniy_seo_tables();
	sad_znaniy_seo_db_write(
		$tables['views'],
		array(
			'url'  => mb_substr( sad_znaniy_seo_current_url_key(), 0, 191, 'UTF-8' ),
			'day'  => current_time( 'Y-m-d' ),
			'views' => 1,
		),
		'views'
	);
}
add_action( 'template_redirect', 'sad_znaniy_seo_count_view', 5 );

/**
 * Сводка визитов и кликов по URL: 7 дней, 30 дней, прошлая неделя, всего.
 *
 * @return object[]
 */
function sad_znaniy_seo_views_rows() {
	global $wpdb;

	$t     = sad_znaniy_seo_tables();
	$table = $t['views'];

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- имя таблицы задано кодом.
	return $wpdb->get_results(
		"SELECT url,
			SUM( CASE WHEN day >= DATE_SUB( CURDATE(), INTERVAL 6 DAY ) THEN views ELSE 0 END ) AS v7,
			SUM( CASE WHEN day >= DATE_SUB( CURDATE(), INTERVAL 13 DAY ) AND day < DATE_SUB( CURDATE(), INTERVAL 6 DAY ) THEN views ELSE 0 END ) AS v_prev7,
			SUM( CASE WHEN day >= DATE_SUB( CURDATE(), INTERVAL 29 DAY ) THEN views ELSE 0 END ) AS v30,
			SUM( views ) AS v_all,
			SUM( clicks ) AS c_all
		 FROM {$table}
		 GROUP BY url"
	);
}

/**
 * Фраза из экрана «Ключи» по URL (для связи эффективности с семантикой).
 *
 * @return array URL => фраза.
 */
function sad_znaniy_seo_phrase_by_url() {
	global $wpdb;

	static $map = null;
	if ( null !== $map ) {
		return $map;
	}

	$t     = sad_znaniy_seo_tables();
	$table = $t['keys'];
	$rows  = $wpdb->get_results( "SELECT phrase, matched_url FROM {$table} WHERE matched_url <> ''" );

	$map = array();
	foreach ( (array) $rows as $row ) {
		if ( ! isset( $map[ $row->matched_url ] ) ) {
			$map[ $row->matched_url ] = $row->phrase;
		}
	}

	return $map;
}

/**
 * Регистрирует REST-роут учёта кликов «Подробнее → статья».
 */
function sad_znaniy_seo_register_rest() {
	register_rest_route(
		'sad-znaniy/v1',
		'/seo/click',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'sad_znaniy_seo_rest_click',
			'permission_callback' => '__return_true',
			'args'                => array(
				'nonce' => array(
					'type'     => 'string',
					'required' => true,
				),
				'url'   => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'sad_znaniy_seo_register_rest' );

/**
 * REST: учитывает клик по ссылке «Подробнее» на программатик-странице.
 *
 * @param WP_REST_Request $request Запрос.
 * @return WP_REST_Response|WP_Error
 */
function sad_znaniy_seo_rest_click( WP_REST_Request $request ) {
	if ( ! wp_verify_nonce( (string) $request->get_param( 'nonce' ), 'wp_rest' ) ) {
		return new WP_Error( 'sz_bad_nonce', __( 'Неверный nonce.', 'sad-znaniy' ), array( 'status' => 403 ) );
	}

	$path = (string) wp_parse_url( (string) $request->get_param( 'url' ), PHP_URL_PATH );
	$path = '/' . ltrim( $path, '/' );

	if ( ! preg_match( '#^/(kalendar|kogda-sazhat|uhod)/#', $path ) ) {
		return new WP_Error( 'sz_bad_url', __( 'URL вне программатика.', 'sad-znaniy' ), array( 'status' => 400 ) );
	}

	// Учитываем клики только по существующим страницам программатика.
	if ( ! isset( sad_znaniy_seo_pages_index()[ home_url( $path ) ] ) ) {
		return new WP_Error( 'sz_unknown_url', __( 'Такой страницы нет в матрице программатика.', 'sad-znaniy' ), array( 'status' => 400 ) );
	}

	$tables = sad_znaniy_seo_tables();
	sad_znaniy_seo_db_write(
		$tables['views'],
		array(
			'url'   => mb_substr( $path, 0, 191, 'UTF-8' ),
			'day'   => current_time( 'Y-m-d' ),
			'clicks' => 1,
		),
		'clicks'
	);

	return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * Подключает скрипт учёта кликов на страницах программатика и календаря.
 */
function sad_znaniy_seo_enqueue_clicks() {
	if ( ! get_query_var( 'sad_pg' ) && ! is_page_template( 'template-calendar.php' ) ) {
		return;
	}

	$rel = 'assets/js/programmatic-clicks.js';
	wp_enqueue_script(
		'sad-znaniy-seo-clicks',
		get_template_directory_uri() . '/' . $rel,
		array(),
		sad_znaniy_asset_ver( $rel ),
		true
	);
	wp_localize_script(
		'sad-znaniy-seo-clicks',
		'sadZnaniySeo',
		array(
			'restUrl' => esc_url_raw( rest_url( 'sad-znaniy/v1/seo/click' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'pageUrl' => sad_znaniy_seo_current_url_key(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'sad_znaniy_seo_enqueue_clicks' );

/**
 * Экран «📊 SEO-Хаб → Эффективность».
 */
function sad_znaniy_seo_hub_eff_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$rows    = sad_znaniy_seo_views_rows();
	$phrases = sad_znaniy_seo_phrase_by_url();
	$items   = array();

	foreach ( (array) $rows as $row ) {
		$v7   = (int) $row->v7;
		$v30  = (int) $row->v30;
		$prev = (int) $row->v_prev7;

		$trend = ( 0 === $prev ) ? ( ( $v7 > 0 ) ? 100 : 0 ) : (int) round( ( $v7 - $prev ) / $prev * 100 );

		$items[] = array(
			'url'    => (string) $row->url,
			'v7'     => $v7,
			'v30'    => $v30,
			'all'    => (int) $row->v_all,
			'clicks' => (int) $row->c_all,
			'trend'  => $trend,
			'phrase' => isset( $phrases[ home_url( (string) $row->url ) ] ) ? (string) $phrases[ home_url( (string) $row->url ) ] : '',
		);
	}

	$growing = array_values(
		array_filter(
			$items,
			static function ( $i ) {
				return $i['v7'] > 0 && $i['trend'] > 0;
			}
		)
	);
	usort(
		$growing,
		static function ( $a, $b ) {
			return $b['trend'] <=> $a['trend'];
		}
	);

	$falling = array_values(
		array_filter(
			$items,
			static function ( $i ) {
				return $i['trend'] < 0;
			}
		)
	);
	usort(
		$falling,
		static function ( $a, $b ) {
			return $a['trend'] <=> $b['trend'];
		}
	);

	$stale = array_values(
		array_filter(
			$items,
			static function ( $i ) {
				return 0 === $i['v30'];
			}
		)
	);
	usort(
		$stale,
		static function ( $a, $b ) {
			return strcmp( $a['url'], $b['url'] );
		}
	);

	usort(
		$items,
		static function ( $a, $b ) {
			return $b['v30'] <=> $a['v30'];
		}
	);
	?>
	<div class="wrap">
		<h1>SEO-Хаб → Эффективность</h1>

		<p><b>Для чего:</b> показывать, какие программатик-страницы и календарь приносят визиты, а какие «молчат». Счётчики свои и невидимые для посетителей: без куки и без внешних сервисов, визиты администраторов не считаются.</p>

		<?php
		sad_znaniy_seo_help_box(
			'Как этим пользоваться (инструкция)',
			array(
				'<b>Понедельник = 5 минут:</b> откройте «ТОП-10 падающих». Падение больше <b>30 %</b> — откройте страницу, проверьте интро и события месяца, при необходимости допишите календарь.',
				'<b>Столбцы:</b> «визиты 7д / 30д» — свои счётчики; «тренд» — сравнение последних 7 дней с предыдущими 7; «позиция» заполнится после Этапа 8.',
				'<b>Без визитов 30 дней</b> — кандидаты на вычитку интро: либо данные слабые, либо страница не нужна.',
				'<b>Клики</b> — нажатия «Подробнее → статья» на программатик-страницах: показывают, какие страницы реально ведут к статьям.',
				'<b>Честность данных:</b> страница-пустышка (&lt;5 событий) не открывается и визитов не получит — это нормально.',
			),
			'«13. SEO-Хаб»'
		);
		?>

		<h2>1. Таблица страниц</h2>
		<?php if ( ! $items ) : ?>
			<p>Пока нет данных. Откройте несколько программатик-страниц или страницу календаря (не из-под админа) — визиты появятся здесь.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>URL</th>
						<th style="width:26%;">Фраза (из «Ключей»)</th>
						<th style="width:70px;">7 дн.</th>
						<th style="width:70px;">30 дн.</th>
						<th style="width:90px;">Тренд</th>
						<th style="width:70px;">Клики</th>
						<th style="width:120px;">Позиция</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( home_url( $item['url'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['url'] ); ?></a></td>
						<td><?php echo ( '' !== $item['phrase'] ) ? esc_html( $item['phrase'] ) : '—'; ?></td>
						<td><?php echo (int) $item['v7']; ?></td>
						<td><?php echo (int) $item['v30']; ?></td>
						<td>
							<?php
							if ( $item['trend'] > 0 ) {
								echo '<span style="color:#2e6b4f;">↑ ' . (int) $item['trend'] . '%</span>';
							} elseif ( $item['trend'] < 0 ) {
								echo '<span style="color:#a94442;">↓ ' . (int) abs( $item['trend'] ) . '%</span>';
							} else {
								echo '→ 0%';
							}
							?>
						</td>
						<td><?php echo (int) $item['clicks']; ?></td>
						<td>— (после Этапа 8)</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>2. Витрины недели</h2>
		<h3>ТОП-10 растущих</h3>
		<?php sad_znaniy_seo_eff_list( array_slice( $growing, 0, 10 ), 'растущих' ); ?>

		<h3>ТОП-10 падающих <span class="description">(падает больше 30 % — открыть и вычитать интро)</span></h3>
		<?php sad_znaniy_seo_eff_list( array_slice( $falling, 0, 10 ), 'падающих' ); ?>

		<h3>Без визитов 30 дней <span class="description">(кандидаты на вычитку интро)</span></h3>
		<?php sad_znaniy_seo_eff_list( array_slice( $stale, 0, 10 ), 'без визитов' ); ?>
	</div>
	<?php
}

/**
 * Витрина страниц эффективности (список со показателями).
 *
 * @param array  $items Строки эффективности.
 * @param string $label Подпись для пустого состояния.
 */
function sad_znaniy_seo_eff_list( $items, $label = '' ) {
	if ( ! $items ) {
		echo '<p>Пока пусто (' . esc_html( $label ) . ').</p>';
		return;
	}

	echo '<ul style="margin-left:18px;list-style:disc;">';
	foreach ( $items as $item ) {
		echo '<li><a href="' . esc_url( home_url( $item['url'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $item['url'] ) . '</a>'
			. ' — 7 дн.: ' . (int) $item['v7']
			. ', 30 дн.: ' . (int) $item['v30']
			. ', тренд: ' . (int) $item['trend'] . '%';
		if ( '' !== $item['phrase'] ) {
			echo ' · фраза: «' . esc_html( $item['phrase'] ) . '»';
		}
		echo '</li>';
	}
	echo '</ul>';
}

