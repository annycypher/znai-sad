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
 * Регистрирует таксономию «Тип работы» (Этап 5.5).
 */
function sad_znaniy_register_work_type() {
	register_taxonomy(
		'work_type',
		array( 'calendar_event' ),
		array(
			'labels'            => array(
				'name'          => __( 'Типы работ', 'sad-znaniy' ),
				'singular_name' => __( 'Тип работы', 'sad-znaniy' ),
				'menu_name'     => __( 'Тип работы', 'sad-znaniy' ),
				'all_items'     => __( 'Все типы работ', 'sad-znaniy' ),
				'add_new_item'  => __( 'Новый тип работы', 'sad-znaniy' ),
				'edit_item'     => __( 'Редактировать тип', 'sad-znaniy' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'sad_znaniy_register_work_type' );

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

	$from         = (string) get_post_meta( $post->ID, '_sz_event_date_from', true );
	$to           = (string) get_post_meta( $post->ID, '_sz_event_date_to', true );
	$regions      = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $post->ID, '_sz_event_regions', true ) ) ) );
	$all_regions  = '1' === (string) get_post_meta( $post->ID, '_sz_event_all_regions', true );
	$crop         = (int) get_post_meta( $post->ID, '_sz_event_crop', true );
	$difficulty   = (string) get_post_meta( $post->ID, '_sz_event_difficulty', true );
	$priority     = (string) get_post_meta( $post->ID, '_sz_event_priority', true );
	$weather_hint = (string) get_post_meta( $post->ID, '_sz_event_weather_hint', true );

	$plants = get_posts(
		array(
			'post_type'      => 'plant',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	?>
	<p>
		<label for="sz_event_date_from"><strong><?php esc_html_e( 'Дата начала', 'sad-znaniy' ); ?></strong></label><br>
		<input type="date" id="sz_event_date_from" name="sz_event_date_from" value="<?php echo esc_attr( $from ); ?>" class="widefat">
	</p>
	<p>
		<label for="sz_event_date_to"><strong><?php esc_html_e( 'Дата окончания (необязательно)', 'sad-znaniy' ); ?></strong></label><br>
		<input type="date" id="sz_event_date_to" name="sz_event_date_to" value="<?php echo esc_attr( $to ); ?>" class="widefat">
	</p>
	<p><strong><?php esc_html_e( 'Регионы', 'sad-znaniy' ); ?></strong></p>
	<?php foreach ( sad_znaniy_region_keys() as $key => $label ) : ?>
		<label style="display:block;"><input type="checkbox" name="sz_event_regions[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $regions, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
	<?php endforeach; ?>
	<label style="display:block;margin-top:6px;"><input type="checkbox" name="sz_event_all_regions" value="1" <?php checked( $all_regions ); ?>> <?php esc_html_e( 'Для всех регионов', 'sad-znaniy' ); ?></label>
	<p>
		<label for="sz_event_crop"><strong><?php esc_html_e( 'Культура', 'sad-znaniy' ); ?></strong></label><br>
		<select id="sz_event_crop" name="sz_event_crop" class="widefat">
			<option value=""><?php esc_html_e( '— без культуры —', 'sad-znaniy' ); ?></option>
			<?php foreach ( $plants as $plant ) : ?>
				<option value="<?php echo esc_attr( $plant->ID ); ?>" <?php selected( $crop, $plant->ID ); ?>><?php echo esc_html( get_the_title( $plant ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="sz_event_difficulty"><strong><?php esc_html_e( 'Сложность', 'sad-znaniy' ); ?></strong></label><br>
		<select id="sz_event_difficulty" name="sz_event_difficulty" class="widefat">
			<?php foreach ( sad_znaniy_difficulty_options() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $difficulty, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="sz_event_priority"><strong><?php esc_html_e( 'Приоритет', 'sad-znaniy' ); ?></strong></label><br>
		<select id="sz_event_priority" name="sz_event_priority" class="widefat">
			<?php foreach ( sad_znaniy_priority_options() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $priority, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="sz_event_weather_hint"><strong><?php esc_html_e( 'Подсказка по погоде', 'sad-znaniy' ); ?></strong></label><br>
		<input type="text" id="sz_event_weather_hint" name="sz_event_weather_hint" value="<?php echo esc_attr( $weather_hint ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'например: при сухой почве', 'sad-znaniy' ); ?>">
	</p>
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

	$from = isset( $_POST['sz_event_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_event_date_from'] ) ) : '';
	$to   = isset( $_POST['sz_event_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_event_date_to'] ) ) : '';

	if ( $from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
		$from = '';
	}
	if ( $to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
		$to = '';
	}

	$regions = array();
	if ( isset( $_POST['sz_event_regions'] ) && is_array( $_POST['sz_event_regions'] ) ) {
		$valid = sad_znaniy_region_keys();
		foreach ( (array) $_POST['sz_event_regions'] as $region_key ) {
			$region_key = sanitize_key( wp_unslash( $region_key ) );
			if ( isset( $valid[ $region_key ] ) ) {
				$regions[] = $region_key;
			}
		}
	}
	$all_regions = isset( $_POST['sz_event_all_regions'] ) ? '1' : '';

	$crop = isset( $_POST['sz_event_crop'] ) ? absint( $_POST['sz_event_crop'] ) : 0;

	$difficulty = isset( $_POST['sz_event_difficulty'] ) ? sanitize_key( wp_unslash( $_POST['sz_event_difficulty'] ) ) : '';
	if ( ! array_key_exists( $difficulty, sad_znaniy_difficulty_options() ) ) {
		$difficulty = '';
	}

	$priority = isset( $_POST['sz_event_priority'] ) ? sanitize_key( wp_unslash( $_POST['sz_event_priority'] ) ) : '';
	if ( ! array_key_exists( $priority, sad_znaniy_priority_options() ) ) {
		$priority = '';
	}

	$weather_hint = isset( $_POST['sz_event_weather_hint'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_event_weather_hint'] ) ) : '';

	update_post_meta( $post_id, '_sz_event_date_from', $from );
	update_post_meta( $post_id, '_sz_event_date_to', $to );
	update_post_meta( $post_id, '_sz_event_regions', implode( ',', $regions ) );
	update_post_meta( $post_id, '_sz_event_all_regions', $all_regions );
	update_post_meta( $post_id, '_sz_event_crop', $crop );
	update_post_meta( $post_id, '_sz_event_difficulty', $difficulty );
	update_post_meta( $post_id, '_sz_event_priority', $priority );
	update_post_meta( $post_id, '_sz_event_weather_hint', $weather_hint );
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
			$new['sz_work_type']    = __( 'Работы', 'sad-znaniy' );
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

	if ( 'sz_work_type' === $column ) {
		$terms = get_the_terms( $post_id, 'work_type' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$colors = sad_znaniy_work_type_colors();
			foreach ( $terms as $term ) {
				$color = isset( $colors[ $term->slug ] ) ? $colors[ $term->slug ] : '#7C8B93';
				printf(
					'<span style="display:inline-block;padding:2px 8px;border-radius:999px;color:#fff;background:%1$s;font-size:11px;margin:1px;">%2$s</span> ',
					esc_attr( $color ),
					esc_html( $term->name )
				);
			}
		} else {
			echo '&mdash;';
		}
	}

	if ( 'sz_event_region' === $column ) {
		if ( '1' === (string) get_post_meta( $post_id, '_sz_event_all_regions', true ) ) {
			esc_html_e( 'Все регионы', 'sad-znaniy' );
		} else {
			$regions = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $post_id, '_sz_event_regions', true ) ) ) );
			$labels  = array();
			foreach ( $regions as $region_key ) {
				$label = sad_znaniy_option_label( sad_znaniy_region_keys(), $region_key );
				if ( $label ) {
					$labels[] = $label;
				}
			}
			echo $labels ? esc_html( implode( ', ', $labels ) ) : '&mdash;';
		}
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


