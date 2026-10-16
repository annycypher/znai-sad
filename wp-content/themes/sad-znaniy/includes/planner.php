<?php
/**
 * Планировщик участка (Этап 6, Партия 1): данные для клиента.
 *
 * Готовит «контракт данных» (спека _design/planner-spec.md, §7): регионы,
 * текущий регион и справочник растений из CPT `plant`. Всё уходит на страницу
 * одним JSON через wp_localize_script — без REST-запросов на старте.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регионы планировщика — те же ключи, что у region.js и cookie sz_region.
 * «Все регионы» в планировщике не нужен — участок всегда в одном регионе.
 *
 * @return array Список { key, label }.
 */
function sad_znaniy_planner_regions() {
	$out = array();
	foreach ( sad_znaniy_region_keys() as $key => $label ) {
		$out[] = array(
			'key'   => $key,
			'label' => $label,
		);
	}
	return $out;
}

/**
 * Справочник растений из CPT `plant` (те же мета-поля, что в карточке растения).
 *
 * @return array Список растений для клиента.
 */
function sad_znaniy_planner_plants() {
	$ids = get_posts(
		array(
			'post_type'              => 'plant',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'update_post_term_cache' => false,
		)
	);

	$plants = array();
	foreach ( $ids as $id ) {
		$plants[] = array(
			'id'    => (int) $id,
			'title' => (string) get_the_title( $id ),
			'slug'  => (string) get_post_field( 'post_name', $id ),
			'light' => (string) get_post_meta( $id, '_sz_plant_light', true ),
			'water' => (string) get_post_meta( $id, '_sz_plant_water', true ),
			'ph'    => array(
				(float) get_post_meta( $id, '_sz_plant_ph_from', true ),
				(float) get_post_meta( $id, '_sz_plant_ph_to', true ),
			),
			'soil'  => (string) get_post_meta( $id, '_sz_plant_soil', true ),
			'good'  => (string) get_post_meta( $id, '_sz_plant_neighbors_good', true ),
			'bad'   => (string) get_post_meta( $id, '_sz_plant_neighbors_bad', true ),
			'url'   => (string) get_permalink( $id ),
		);
	}

	return $plants;
}

/**
 * Единый словарь значений полива для печати (low/moderate/high → русские слова).
 *
 * Источник — sad_znaniy_water_options(): «Редкий», «Умеренный», «Обильный».
 * Один словарь на весь проект: и печатная таблица, и любые будущие места.
 *
 * @return array Ключ мета => подпись строчными буквами.
 */
function sad_znaniy_planner_water_labels() {
	$out = array();
	foreach ( sad_znaniy_water_options() as $key => $label ) {
		$out[ $key ] = mb_strtolower( (string) $label, 'UTF-8' );
	}
	return $out;
}

/**
 * Текущий пользователь для печатной шапки (только из WP, никакого ввода руками).
 *
 * @return array
 */
function sad_znaniy_planner_user() {
	if ( ! is_user_logged_in() ) {
		return array(
			'loggedIn' => false,
			'name'     => '',
			'email'    => '',
		);
	}

	$user = wp_get_current_user();

	return array(
		'loggedIn' => true,
		'name'     => (string) $user->display_name,
		'email'    => (string) $user->user_email,
	);
}

/**
 * Настройки гейта регистрации на экспорт (печать/PDF и «В календарь телефона»).
 *
 * @return array
 */
function sad_znaniy_planner_gate() {
	$after = add_query_arg( 'print', '1', get_permalink() );

	return array(
		'required'    => sad_znaniy_planner_gate_required(),
		'canRegister' => (bool) get_option( 'users_can_register' ),
		'loginUrl'    => wp_login_url(),
		'registerUrl' => add_query_arg( 'redirect_to', $after, wp_registration_url() ),
		'afterUrl'    => $after,
	);
}

/**
 * Инструменты сайта для последнего листа печати — только реальные адреса.
 *
 * URL берём из WordPress (страницы по слагу, архив растений, рубрика рецептов);
 * если объекта нет — строку не выводим.
 *
 * @return array Список { title, desc, url }.
 */
function sad_znaniy_planner_tools() {
	$rows = array(
		array(
			'slug'  => 'kalkulyatory',
			'title' => 'Калькуляторы',
			'desc'  => 'Полив, удобрения, грунт и pH, посев семян',
			'icon'  => 'i-calc',
		),
		array(
			'slug'  => 'planirovshchik',
			'title' => 'Планировщик участка',
			'desc'  => 'Расставьте грядки, деревья и дорожки, проверьте соседство',
			'icon'  => 'i-plan',
		),
		array(
			'slug'  => 'kalendar',
			'title' => 'Календарь дачника',
			'desc'  => 'Что делать в саду по месяцам и регионам',
			'icon'  => 'i-cal',
		),
		array(
			'slug'  => 'lunnyj-kalendar',
			'title' => 'Лунный календарь',
			'desc'  => 'Благоприятные дни для посева, посадки и ухода',
			'icon'  => 'i-moon',
		),
		array(
			'slug'  => 'plant-archive',
			'title' => 'База знаний растений',
			'desc'  => 'Карточки: свет, полив, почва, соседи',
			'icon'  => 'i-book',
		),
		array(
			'slug'  => 'retsepty',
			'title' => 'Рецепты',
			'desc'  => 'Заготовки и блюда из своего урожая',
			'icon'  => 'i-food',
		),
	);

	$out = array();
	foreach ( $rows as $row ) {
		$url = '';

		if ( 'plant-archive' === $row['slug'] ) {
			$url = (string) get_post_type_archive_link( 'plant' );
		} elseif ( 'retsepty' === $row['slug'] ) {
			$cat = get_category_by_slug( 'retsepty' );
			if ( $cat ) {
				$url = (string) get_category_link( $cat->term_id );
			}
		} else {
			$page = get_page_by_path( $row['slug'] );
			if ( $page && 'publish' === $page->post_status ) {
				$url = (string) get_permalink( $page );
			}
		}

		if ( '' === $url ) {
			continue;
		}

		$out[] = array(
			'title' => $row['title'],
			'desc'  => $row['desc'],
			'icon'  => $row['icon'],
			'url'   => $url,
		);
	}

	return $out;
}

/**
 * Инлайн-иконка карточки инструмента (24×24, тот же язык, что у иконок сайта).
 *
 * Глифы взяты из проекта: клавиши калькулятора, карта участка, лист календаря,
 * месяц, лист растения и банка с крышкой (палитра планировщика).
 *
 * @param string $icon Ключ иконки (i-calc…i-food).
 * @return string SVG-разметка или пустая строка.
 */
function sad_znaniy_planner_tool_icon( $icon ) {
	$icons = array(
		'i-calc' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01"/>',
		'i-plan' => '<path d="M9 20 3 17V4l6 3 6-3 6 3v13l-6-3-6 3Z"/><path d="M9 7v13M15 4v13"/>',
		'i-cal'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'i-moon' => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>',
		'i-book' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>',
		'i-food' => '<path d="M7 3h10l-1 5H8L7 3Z"/><rect x="5" y="8" width="14" height="13" rx="2"/><path d="M9 13h6"/>',
	);

	if ( ! isset( $icons[ $icon ] ) ) {
		return '';
	}

	return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
		. $icons[ $icon ] . '</svg>';
}

/**
 * Контракт данных планировщика для клиента (спека, §7).
 *
 * @return array
 */
function sad_znaniy_planner_contract() {
	$region = sad_znaniy_current_region();
	if ( '' === $region ) {
		$region = 'mid';
	}

	$sz_req_plan = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['plan'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sz_public   = '' !== $sz_req_plan ? sad_znaniy_planner_get_public( $sz_req_plan ) : null;

	return array(
		'version'       => 1,
		'readonly'      => (bool) $sz_public,
		'plan'          => $sz_public,
		'myPlansUrl'    => sad_znaniy_planner_my_plans_url(),
		'restPlans'     => esc_url_raw( rest_url( 'sad-znaniy/v1/plans' ) ),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
		'limits'        => sad_znaniy_planner_limits(),
		'regions'       => sad_znaniy_planner_regions(),
		'currentRegion' => $region,
		'plants'        => sad_znaniy_planner_plants(),
		'home'          => home_url( '/' ),
		'water'         => sad_znaniy_planner_water_labels(),
		'user'          => sad_znaniy_planner_user(),
		'gate'          => sad_znaniy_planner_gate(),
		'tools'         => sad_znaniy_planner_tools(),
		'qr'            => get_template_directory_uri() . '/assets/img/qr-site.svg',
		'icsReady'      => true,
	);
}

/**
 * Это страница планировщика?
 *
 * True, если странице назначен шаблон «Планировщик участка» ИЛИ это страница
 * со слагом planirovshchik (авто-режим: шаблон подставляется фильтром ниже,
 * чтобы фича работала без ручного выбора шаблона в админке).
 *
 * @return bool
 */
function sad_znaniy_is_planner_page() {
	if ( is_page_template( 'template-planner.php' ) ) {
		return true;
	}
	if ( ! is_page() ) {
		return false;
	}
	$post = get_queried_object();
	return ( $post && isset( $post->post_name ) && 'planirovshchik' === $post->post_name );
}

/**
 * Подставляет шаблон планировщика странице /planirovshchik/.
 *
 * @param string $template Путь шаблона, выбранный WordPress.
 * @return string
 */
function sad_znaniy_planner_template( $template ) {
	if ( sad_znaniy_is_planner_page() && ! is_page_template( 'template-planner.php' ) ) {
		$own = get_template_directory() . '/template-planner.php';
		if ( file_exists( $own ) ) {
			return $own;
		}
	}
	return $template;
}
add_filter( 'template_include', 'sad_znaniy_planner_template' );

