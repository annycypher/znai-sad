<?php
/**
 * CPT «События календаря» (calendar_event).
 *
 * Трио фичи: (1) события выводятся на главной в блоке «Календарь работ»,
 * (2) редактируются в админке (метабокс «Дата и регион», колонки списка),
 * (3) описаны в ADMINGUIDE.md.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует тип записи «Событие календаря».
 */
function sad_znaniy_register_calendar_event() {
	register_post_type(
		'calendar_event',
		array(
			'labels'        => array(
				'name'               => __( 'События календаря', 'sad-znaniy' ),
				'singular_name'      => __( 'Событие', 'sad-znaniy' ),
				'add_new'            => __( 'Добавить событие', 'sad-znaniy' ),
				'add_new_item'       => __( 'Новое событие', 'sad-znaniy' ),
				'edit_item'          => __( 'Редактировать событие', 'sad-znaniy' ),
				'new_item'           => __( 'Новое событие', 'sad-znaniy' ),
				'view_item'          => __( 'Просмотреть событие', 'sad-znaniy' ),
				'search_items'       => __( 'Искать события', 'sad-znaniy' ),
				'not_found'          => __( 'Событий не найдено', 'sad-znaniy' ),
				'not_found_in_trash' => __( 'В корзине событий нет', 'sad-znaniy' ),
				'all_items'          => __( 'Все события', 'sad-znaniy' ),
				'menu_name'          => __( 'События', 'sad-znaniy' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'sobytiya',
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-calendar-alt',
			'menu_position' => 21,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		)
	);
}
add_action( 'init', 'sad_znaniy_register_calendar_event' );

/**
 * Добавляет метабокс «Дата и регион».
 */
function sad_znaniy_event_add_meta_box() {
	add_meta_box(
		'sad-znaniy-event-details',
		__( 'Дата и регион', 'sad-znaniy' ),
		'sad_znaniy_event_meta_box_html',
		'calendar_event',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'sad_znaniy_event_add_meta_box' );

/**
 * Разметка метабокса события.
 *
 * @param WP_Post $post Текущая запись.
 */
function sad_znaniy_event_meta_box_html( $post ) {
	wp_nonce_field( 'sad_znaniy_event_save', 'sad_znaniy_event_nonce' );

	$from   = (string) get_post_meta( $post->ID, '_sz_event_date_from', true );
	$to     = (string) get_post_meta( $post->ID, '_sz_event_date_to', true );
	$region = (string) get_post_meta( $post->ID, '_sz_event_region', true );
	?>
	<p>
		<label for="sz_event_date_from"><strong><?php esc_html_e( 'Дата начала', 'sad-znaniy' ); ?></strong></label><br>
		<input type="date" id="sz_event_date_from" name="sz_event_date_from" value="<?php echo esc_attr( $from ); ?>" class="widefat">
	</p>
	<p>
		<label for="sz_event_date_to"><strong><?php esc_html_e( 'Дата окончания (необязательно)', 'sad-znaniy' ); ?></strong></label><br>
		<input type="date" id="sz_event_date_to" name="sz_event_date_to" value="<?php echo esc_attr( $to ); ?>" class="widefat">
	</p>
	<p>
		<label for="sz_event_region"><strong><?php esc_html_e( 'Регион', 'sad-znaniy' ); ?></strong></label><br>
		<select id="sz_event_region" name="sz_event_region" class="widefat">
			<?php foreach ( sad_znaniy_regions() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $region, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description"><?php esc_html_e( 'Ближайшие события выводятся в блоке «Календарь работ» на главной. Прошедшие скрываются автоматически.', 'sad-znaniy' ); ?></p>
	<?php
}

/**
 * Сохраняет метаполя события.
 *
 * @param int $post_id ID записи.
 */
function sad_znaniy_event_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['sad_znaniy_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sad_znaniy_event_nonce'] ) ), 'sad_znaniy_event_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$from   = isset( $_POST['sz_event_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_event_date_from'] ) ) : '';
	$to     = isset( $_POST['sz_event_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_event_date_to'] ) ) : '';
	$region = isset( $_POST['sz_event_region'] ) ? sanitize_key( wp_unslash( $_POST['sz_event_region'] ) ) : '';

	if ( $from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
		$from = '';
	}
	if ( $to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
		$to = '';
	}
	if ( ! array_key_exists( $region, sad_znaniy_regions() ) ) {
		$region = '';
	}

	update_post_meta( $post_id, '_sz_event_date_from', $from );
	update_post_meta( $post_id, '_sz_event_date_to', $to );
	update_post_meta( $post_id, '_sz_event_region', $region );
}
add_action( 'save_post_calendar_event', 'sad_znaniy_event_save_meta' );

/**
 * Колонки списка событий в админке: дата и регион.
 *
 * @param array $columns Существующие колонки.
 * @return array
 */
function sad_znaniy_event_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['sz_event_date']   = __( 'Дата', 'sad-znaniy' );
			$new['sz_event_region'] = __( 'Регион', 'sad-znaniy' );
		}
	}

	return $new;
}
add_filter( 'manage_calendar_event_posts_columns', 'sad_znaniy_event_columns' );

/**
 * Выводит значения колонок событий.
 *
 * @param string $column  Имя колонки.
 * @param int    $post_id ID записи.
 */
function sad_znaniy_event_column_content( $column, $post_id ) {
	if ( 'sz_event_date' === $column ) {
		$from = (string) get_post_meta( $post_id, '_sz_event_date_from', true );
		$to   = (string) get_post_meta( $post_id, '_sz_event_date_to', true );
		$text = sad_znaniy_event_term_text( $from, $to );
		echo $text ? esc_html( $text ) : '&mdash;';
	}

	if ( 'sz_event_region' === $column ) {
		$region = (string) get_post_meta( $post_id, '_sz_event_region', true );
		$label  = sad_znaniy_region_label( $region );
		echo $label ? esc_html( $label ) : '&mdash;';
	}
}
add_action( 'manage_calendar_event_posts_custom_column', 'sad_znaniy_event_column_content', 10, 2 );

/**
 * Делает колонку «Дата» сортируемой.
 *
 * @param array $columns Сортируемые колонки.
 * @return array
 */
function sad_znaniy_event_sortable_columns( $columns ) {
	$columns['sz_event_date'] = 'sz_event_date';
	return $columns;
}
add_filter( 'manage_edit-calendar_event_sortable_columns', 'sad_znaniy_event_sortable_columns' );

/**
 * Сортировка списка событий по дате (по умолчанию — от ближайших).
 *
 * @param WP_Query $query Текущий запрос.
 */
function sad_znaniy_event_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'calendar_event' !== $query->get( 'post_type' ) ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	if ( 'sz_event_date' === $orderby ) {
		$query->set( 'meta_key', '_sz_event_date_from' );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'ASC' === $query->get( 'order' ) ? 'DESC' : 'ASC' );
		return;
	}

	if ( ! $orderby ) {
		$query->set( 'meta_key', '_sz_event_date_from' );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'sad_znaniy_event_admin_order' );


