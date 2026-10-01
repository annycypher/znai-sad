<?php
/**
 * Одноразовая установка структуры сайта: термины таксономий растения
 * («Раздел», «Тип») и рубрика «Рецепты».
 *
 * Это СТРУКТУРА каталога (значения таксономий из PROJECT-PLAN.md),
 * а не контент: статьи, события, растения и рецепты владелец добавляет
 * только через админку. Термины можно свободно переименовать или удалить
 * в разделах «Растения → Раздел / Тип» и «Записи → Рубрики».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Создаёт недостающие структурные термины один раз.
 */
function sad_znaniy_maybe_install_terms() {
	if ( get_option( 'sad_znaniy_terms_ready' ) ) {
		return;
	}

	// Ждём, пока таксономии зарегистрированы.
	if ( ! taxonomy_exists( 'plant_section' ) || ! taxonomy_exists( 'plant_type' ) ) {
		return;
	}

	$sections = array(
		'sad'    => __( 'Сад', 'sad-znaniy' ),
		'ogorod' => __( 'Огород', 'sad-znaniy' ),
		'tsvety' => __( 'Цветы', 'sad-znaniy' ),
	);

	$types = array(
		'derevo'      => __( 'Дерево', 'sad-znaniy' ),
		'kustarnik'   => __( 'Кустарник', 'sad-znaniy' ),
		'mnogoletnik' => __( 'Многолетник', 'sad-znaniy' ),
		'odnoletnik'  => __( 'Однолетник', 'sad-znaniy' ),
		'ovoshch'     => __( 'Овощ', 'sad-znaniy' ),
	);

	foreach ( $sections as $slug => $name ) {
		if ( ! term_exists( $slug, 'plant_section' ) ) {
			wp_insert_term( $name, 'plant_section', array( 'slug' => $slug ) );
		}
	}

	foreach ( $types as $slug => $name ) {
		if ( ! term_exists( $slug, 'plant_type' ) ) {
			wp_insert_term( $name, 'plant_type', array( 'slug' => $slug ) );
		}
	}

	if ( ! term_exists( 'retsepty', 'category' ) ) {
		wp_insert_term( __( 'Рецепты', 'sad-znaniy' ), 'category', array( 'slug' => 'retsepty' ) );
	}

	// Новые типы записей и таксономии требуют пересборки правил ЧПУ один раз.
	flush_rewrite_rules();

	update_option( 'sad_znaniy_terms_ready', 1, false );
}
add_action( 'wp_loaded', 'sad_znaniy_maybe_install_terms' );

/**
 * Создаёт термины таксономии «Тип работы» с цветами (Этап 5.5), один раз.
 */
function sad_znaniy_maybe_install_work_types() {
	if ( get_option( 'sad_znaniy_work_types_ready' ) ) {
		return;
	}
	if ( ! taxonomy_exists( 'work_type' ) ) {
		return;
	}

	$colors = sad_znaniy_work_type_colors();
	foreach ( sad_znaniy_work_types() as $slug => $name ) {
		if ( ! term_exists( $slug, 'work_type' ) ) {
			$result = wp_insert_term( $name, 'work_type', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $result ) && isset( $colors[ $slug ] ) ) {
				update_term_meta( $result['term_id'], '_sz_color', $colors[ $slug ] );
			}
		}
	}

	update_option( 'sad_znaniy_work_types_ready', 1, false );
}
add_action( 'wp_loaded', 'sad_znaniy_maybe_install_work_types' );
