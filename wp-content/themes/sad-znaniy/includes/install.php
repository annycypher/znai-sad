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
