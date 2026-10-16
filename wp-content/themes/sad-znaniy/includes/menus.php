<?php
/**
 * Меню темы.
 *
 * Классы макета — контракт: контейнеру .nav нужны прямые потомки <a>
 * (в макете это плоский список ссылок). Поэтому используем свой walker,
 * который выводит ссылки без <ul>/<li>, сохраняя вид макета.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker: выводит пункты меню как плоские ссылки <a>.</a>.
 */
class SAD_Znaniy_Flat_Walker extends Walker_Nav_Menu {

	/**
	 * Уровни вложенности не используются — подавляем.
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {}

	/**
	 * См. start_lvl().
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	/**
	 * Выводит пункт меню как <a>.
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param WP_Post  $item   Пункт меню.
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 * @param int      $id     ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		/* Пункт с двумя подписями: полная — для бургера и печати, короткая — для десктопа. */
		if ( ! empty( $item->sz_labels ) && is_array( $item->sz_labels ) && isset( $item->sz_labels['full'], $item->sz_labels['short'] ) ) {
			$output .= sprintf(
				'<a href="%1$s"><span class="nav-lbl-full">%2$s</span><span class="nav-lbl-short">%3$s</span></a>',
				esc_url( $item->url ),
				esc_html( (string) $item->sz_labels['full'] ),
				esc_html( (string) $item->sz_labels['short'] )
			);
			return;
		}

		$output .= sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $item->url ),
			esc_html( $item->title )
		);
	}

	/**
	 * См. start_el().
	 *
	 * @param string   $output Вывод (по ссылке).
	 * @param WP_Post  $item   Пункт меню.
	 * @param int      $depth  Уровень.
	 * @param stdClass $args   Аргументы.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}

/**
 * Ссылки основного меню по умолчанию (как в макете), если меню ещё не назначено.
 *
 * Это временный fallback Этапа 1: владелец создаст меню в админке и назначит
 * его на локацию «main-menu» — тогда отобразится оно.
 *
 * @return array Массив [url, подпись].
 */
function sad_znaniy_default_main_links() {
	return array(
		array( '#main',        __( 'Главная', 'sad-znaniy' ) ),
		array( '#sadvogorod',  __( 'База знаний', 'sad-znaniy' ) ),
		array( '#tools',       __( 'Инструменты', 'sad-znaniy' ) ),
		array( '#calendar',    __( 'Календарь', 'sad-znaniy' ) ),
		array( home_url( '/kalkulyatory/' ),   __( 'Калькуляторы', 'sad-znaniy' ) ),
		array( home_url( '/planirovshchik/' ), __( 'Планировщик участка', 'sad-znaniy' ) ),
		array( '#recipes',     __( 'Рецепты', 'sad-znaniy' ) ),
		array( '#about',       __( 'О нас', 'sad-znaniy' ) ),
	);
}

/**
 * Выводит основное меню (плоские ссылки). Если меню не назначено —
 * выводит fallback-ссылки макета.
 */
function sad_znaniy_main_menu() {
	if ( has_nav_menu( 'main-menu' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'main-menu',
				'container'      => false,
				'items_wrap'     => '%3$s',
				'depth'          => 1,
				'walker'         => new SAD_Znaniy_Flat_Walker(),
			)
		);
		return;
	}

	foreach ( sad_znaniy_default_main_links() as $link ) {
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $link[0] ),
			esc_html( $link[1] )
		);
	}
}

/**
 * Пункты-инструменты, которые добавляем в основное меню программно.
 *
 * Ключевые инструменты сайта (калькуляторы, планировщик) могут отсутствовать
 * в WP-меню — тогда они пропадают и в шапке, и в бургере. Если владелец позже
 * добавит их в админке, фильтр не продублирует: дедуп по URL.
 *
 * @return array
 */
function sad_znaniy_menu_extra_items() {
	return array(
		array(
			'url'   => home_url( '/kalkulyatory/' ),
			'title' => __( 'Калькуляторы', 'sad-znaniy' ),
		),
		array(
			'url'    => home_url( '/planirovshchik/' ),
			'title'  => __( 'Планировщик участка', 'sad-znaniy' ),
			'labels' => array(
				'full'  => __( 'Планировщик участка', 'sad-znaniy' ),
				'short' => __( 'Планировщик', 'sad-znaniy' ),
			),
		),
	);
}

/**
 * Нормализованный путь пункта меню — для сравнения и дедупликации.
 *
 * Сравниваем именно путь: в WP-меню ссылки могут храниться и абсолютными,
 * и относительными («/kalendar/»), поэтому хост и схему игнорируем.
 *
 * @param string $url URL пункта.
 * @return string
 */
function sad_znaniy_menu_norm_url( $url ) {
	$url  = (string) $url;
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		$path = $url;
	}
	return untrailingslashit( strtolower( $path ) );
}

/**
 * Добавляет «Калькуляторы» и «Планировщик участка» в основное меню (десктоп и бургер).
 *
 * Порядок: после «Лунного календаря»; если его нет — после «Календаря»;
 * если и его нет — перед «Рецептами»; иначе в конец. Только локация main-menu.
 *
 * @param array    $items Пункты меню.
 * @param stdClass $args  Аргументы wp_nav_menu().
 * @return array
 */
function sad_znaniy_menu_add_extra_objects( $items, $args ) {
	if ( empty( $args->theme_location ) || 'main-menu' !== $args->theme_location ) {
		return $items; /* футер и прочие локации не трогаем: там свои «Инструменты» */
	}
	if ( ! is_array( $items ) ) {
		return $items;
	}
	/* Ключи массива на веб-запросе не всегда позиционные: приводим к списку,
	   иначе array_splice() с позицией уходит за границы и пункты падают в конец. */
	$items = array_values( $items );

	$have = array();
	foreach ( $items as $item ) {
		if ( isset( $item->url ) ) {
			$have[] = sad_znaniy_menu_norm_url( $item->url );
		}
	}

	$anchors = array(
		'lunar'   => '/lunnyj-kalendar/',
		'cal'     => '/kalendar/',
		'recipes' => '/category/retsepty/',
	);
	$at = array();
	foreach ( $anchors as $key => $path ) {
		$needle = sad_znaniy_menu_norm_url( home_url( $path ) );
		$pos    = 0;
		foreach ( $items as $item ) {
			if ( isset( $item->url ) && sad_znaniy_menu_norm_url( $item->url ) === $needle ) {
				$at[ $key ] = $pos;
			}
			++$pos;
		}
	}

	if ( isset( $at['lunar'] ) ) {
		$insert_at = $at['lunar'] + 1;
	} elseif ( isset( $at['cal'] ) ) {
		$insert_at = $at['cal'] + 1;
	} elseif ( isset( $at['recipes'] ) ) {
		$insert_at = $at['recipes'];
	} else {
		$insert_at = count( $items );
	}

	$fresh = array();
	foreach ( sad_znaniy_menu_extra_items() as $data ) {
		if ( in_array( sad_znaniy_menu_norm_url( $data['url'] ), $have, true ) ) {
			continue; /* пункт уже есть в меню — не дублируем */
		}
		$item                        = new stdClass();
		$item->ID                    = 0;
		$item->db_id                 = 0;
		$item->title                 = (string) $data['title'];
		$item->url                   = (string) $data['url'];
		if ( isset( $data['labels'] ) && is_array( $data['labels'] ) ) {
			$item->sz_labels = $data['labels'];
		}
		$item->menu_item_parent      = 0;
		$item->post_parent           = 0;
		$item->object                = 'custom';
		$item->object_id             = 0;
		$item->type                  = 'custom';
		$item->type_label            = 'Custom Link';
		$item->classes               = array();
		$item->target                = '';
		$item->attr_title            = '';
		$item->description           = '';
		$item->xfn                   = '';
		$item->current               = false;
		$item->current_item_ancestor = false;
		$item->current_item_parent   = false;
		$fresh[]                     = $item;
	}

	if ( ! $fresh ) {
		return $items;
	}

	array_splice( $items, $insert_at, 0, $fresh );
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'sad_znaniy_menu_add_extra_objects', 10, 2 );