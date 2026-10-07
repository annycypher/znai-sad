<?php
/**
 * Этап 5.7 — единый региональный контур (серверная часть).
 *
 * Регион хранится в localStorage (модуль region.js) и дублируется в cookie
 * `sz_region`, чтобы серверный рендер сразу знал выбор посетителя.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Имя cookie и ключа localStorage региона.
 *
 * @return string
 */
function sad_znaniy_region_cookie_name() {
	return 'sz_region';
}

/**
 * Регион посетителя из cookie ('' — не выбран).
 *
 * @return string
 */
function sad_znaniy_current_region() {
	if ( empty( $_COOKIE['sz_region'] ) ) {
		return '';
	}
	$key = sanitize_key( wp_unslash( $_COOKIE['sz_region'] ) );
	return array_key_exists( $key, sad_znaniy_region_keys() ) ? $key : '';
}

/**
 * Подпись региона по ключу.
 *
 * @param string $key Ключ региона.
 * @return string
 */
function sad_znaniy_region_label_by_key( $key ) {
	return (string) sad_znaniy_option_label( sad_znaniy_region_keys(), (string) $key );
}

/**
 * Подключает модуль региона на всех страницах.
 */
function sad_znaniy_region_assets() {
	$uri = get_template_directory_uri();

	wp_enqueue_style(
		'sad-znaniy-region',
		$uri . '/assets/css/region.css',
		array( 'sad-znaniy-main' ),
		sad_znaniy_asset_ver( 'assets/css/region.css' )
	);

	wp_enqueue_script(
		'sad-znaniy-region',
		$uri . '/assets/js/region.js',
		array(),
		sad_znaniy_asset_ver( 'assets/js/region.js' ),
		true
	);

	$items = array();
	foreach ( sad_znaniy_region_keys() as $key => $label ) {
		$items[] = array(
			'key'   => (string) $key,
			'label' => (string) $label,
		);
	}

	wp_localize_script(
		'sad-znaniy-region',
		'szRegionData',
		array(
			'key'        => sad_znaniy_region_cookie_name(),
			'askedKey'   => 'sz_region_asked',
			'legacyKey'  => 'sad_znaniy_cal',
			'current'    => sad_znaniy_current_region(),
			'regions'    => $items,
			'emptyLabel' => __( 'Выберите регион', 'sad-znaniy' ),
			'aliases'    => sad_znaniy_region_aliases(),
		)
	);

	// Подсветка региона в таблицах статей и карточек растений.
	if ( is_singular( array( 'post', 'plant', 'page' ) ) ) {
		wp_enqueue_script(
			'sad-znaniy-region-tables',
			$uri . '/assets/js/region-tables.js',
			array( 'sad-znaniy-region' ),
			sad_znaniy_asset_ver( 'assets/js/region-tables.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sad_znaniy_region_assets', 20 );

/**
 * Словоформы регионов для поиска в тексте таблиц (нижний регистр, основы).
 *
 * @return array
 */
function sad_znaniy_region_aliases() {
	return array(
		'south' => array( 'юг' ),
		'mid'   => array( 'средн' ),
		'ural'  => array( 'урал' ),
		'sib'   => array( 'сибир' ),
		'dv'    => array( 'дальн' ),
	);
}

/**
 * Индикатор региона в шапке: «📍 {регион} ▾».
 */
function sad_znaniy_region_indicator() {
	$current = sad_znaniy_current_region();
	$label   = ( '' !== $current ) ? sad_znaniy_region_label_by_key( $current ) : __( 'Выберите регион', 'sad-znaniy' );
	?>
	<details class="region-chip" data-sz-region-indicator>
		<summary class="region-chip__btn">
			<span aria-hidden="true">📍</span>
			<span data-sz-region-label><?php echo esc_html( $label ); ?></span>
			<span class="region-chip__caret" aria-hidden="true">▾</span>
		</summary>
		<div class="region-menu">
			<?php foreach ( sad_znaniy_region_keys() as $key => $lbl ) : ?>
				<a class="region-menu__item<?php echo ( $key === $current ) ? ' on' : ''; ?>"
					href="<?php echo esc_url( sad_znaniy_calendar_url( null, null, $key ) ); ?>"
					data-sz-region-option="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $lbl ); ?></a>
			<?php endforeach; ?>
		</div>
	</details>
	<?php
}

/**
 * Виджет первого визита «Укажите свой регион».
 */
function sad_znaniy_region_first_visit() {
	if ( '' !== sad_znaniy_current_region() ) {
		return; // регион уже выбран — виджет не нужен
	}
	?>
	<div class="region-ask" data-sz-region-ask hidden>
		<div class="region-ask__ico" aria-hidden="true">📍</div>
		<div class="region-ask__txt">
			<strong><?php esc_html_e( 'Укажите свой регион', 'sad-znaniy' ); ?></strong>
			<span><?php esc_html_e( 'Покажем сроки посева и лунные дни для вашей полосы.', 'sad-znaniy' ); ?></span>
		</div>
		<div class="region-ask__chips">
			<?php foreach ( sad_znaniy_region_keys() as $key => $lbl ) : ?>
				<button type="button" data-sz-region-option="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $lbl ); ?></button>
			<?php endforeach; ?>
		</div>
		<button type="button" class="region-ask__later" data-sz-region-later><?php esc_html_e( 'Позже', 'sad-znaniy' ); ?></button>
	</div>
	<?php
}
add_action( 'wp_footer', 'sad_znaniy_region_first_visit', 30 );
