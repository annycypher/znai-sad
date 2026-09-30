<?php
/**
 * CPT «Растение» (plant) + таксономии «Раздел» и «Тип».
 *
 * Трио фичи: (1) у растения есть страница по стилю макета и колонки в списке,
 * (2) параметры выращивания и совместимость правятся в админке (метабоксы),
 * (3) описано в ADMINGUIDE.md.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует тип записи «Растение».
 */
function sad_znaniy_register_plant() {
	register_post_type(
		'plant',
		array(
			'labels'        => array(
				'name'               => __( 'Растения', 'sad-znaniy' ),
				'singular_name'      => __( 'Растение', 'sad-znaniy' ),
				'add_new'            => __( 'Добавить растение', 'sad-znaniy' ),
				'add_new_item'       => __( 'Новое растение', 'sad-znaniy' ),
				'edit_item'          => __( 'Редактировать растение', 'sad-znaniy' ),
				'new_item'           => __( 'Новое растение', 'sad-znaniy' ),
				'view_item'          => __( 'Просмотреть растение', 'sad-znaniy' ),
				'search_items'       => __( 'Искать растения', 'sad-znaniy' ),
				'not_found'          => __( 'Растений не найдено', 'sad-znaniy' ),
				'not_found_in_trash' => __( 'В корзине растений нет', 'sad-znaniy' ),
				'all_items'          => __( 'Все растения', 'sad-znaniy' ),
				'menu_name'          => __( 'Растения', 'sad-znaniy' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'rasteniya',
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-palmtree',
			'menu_position' => 22,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		)
	);
}
add_action( 'init', 'sad_znaniy_register_plant' );

/**
 * Регистрирует таксономии растения: «Раздел» и «Тип».
 */
function sad_znaniy_register_plant_taxonomies() {
	register_taxonomy(
		'plant_section',
		array( 'plant' ),
		array(
			'labels'            => array(
				'name'          => __( 'Разделы', 'sad-znaniy' ),
				'singular_name' => __( 'Раздел', 'sad-znaniy' ),
				'menu_name'     => __( 'Раздел', 'sad-znaniy' ),
				'all_items'     => __( 'Все разделы', 'sad-znaniy' ),
				'add_new_item'  => __( 'Новый раздел', 'sad-znaniy' ),
				'edit_item'     => __( 'Редактировать раздел', 'sad-znaniy' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'razdel',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'plant_type',
		array( 'plant' ),
		array(
			'labels'            => array(
				'name'          => __( 'Типы', 'sad-znaniy' ),
				'singular_name' => __( 'Тип', 'sad-znaniy' ),
				'menu_name'     => __( 'Тип', 'sad-znaniy' ),
				'all_items'     => __( 'Все типы', 'sad-znaniy' ),
				'add_new_item'  => __( 'Новый тип', 'sad-znaniy' ),
				'edit_item'     => __( 'Редактировать тип', 'sad-znaniy' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'tip',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'sad_znaniy_register_plant_taxonomies' );

/**
 * Добавляет метабоксы растения.
 */
function sad_znaniy_plant_add_meta_boxes() {
	add_meta_box(
		'sad-znaniy-plant-growing',
		__( 'Параметры выращивания', 'sad-znaniy' ),
		'sad_znaniy_plant_growing_meta_box_html',
		'plant',
		'normal',
		'high'
	);

	add_meta_box(
		'sad-znaniy-plant-compat',
		__( 'Совместимость', 'sad-znaniy' ),
		'sad_znaniy_plant_compat_meta_box_html',
		'plant',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'sad_znaniy_plant_add_meta_boxes' );

/**
 * Метабокс «Параметры выращивания».
 *
 * @param WP_Post $post Текущая запись.
 */
function sad_znaniy_plant_growing_meta_box_html( $post ) {
	wp_nonce_field( 'sad_znaniy_plant_save', 'sad_znaniy_plant_nonce' );

	$light   = (string) get_post_meta( $post->ID, '_sz_plant_light', true );
	$water   = (string) get_post_meta( $post->ID, '_sz_plant_water', true );
	$ph_from = (string) get_post_meta( $post->ID, '_sz_plant_ph_from', true );
	$ph_to   = (string) get_post_meta( $post->ID, '_sz_plant_ph_to', true );
	$soil    = (string) get_post_meta( $post->ID, '_sz_plant_soil', true );
	?>
	<p>
		<label for="sz_plant_light"><strong><?php esc_html_e( 'Свет', 'sad-znaniy' ); ?></strong></label><br>
		<select id="sz_plant_light" name="sz_plant_light">
			<option value=""><?php esc_html_e( '— не задано —', 'sad-znaniy' ); ?></option>
			<?php foreach ( sad_znaniy_light_options() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $light, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="sz_plant_water"><strong><?php esc_html_e( 'Полив', 'sad-znaniy' ); ?></strong></label><br>
		<select id="sz_plant_water" name="sz_plant_water">
			<option value=""><?php esc_html_e( '— не задано —', 'sad-znaniy' ); ?></option>
			<?php foreach ( sad_znaniy_water_options() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $water, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<strong><?php esc_html_e( 'Кислотность почвы (pH)', 'sad-znaniy' ); ?></strong><br>
		<label for="sz_plant_ph_from"><?php esc_html_e( 'от', 'sad-znaniy' ); ?></label>
		<input type="number" id="sz_plant_ph_from" name="sz_plant_ph_from" value="<?php echo esc_attr( $ph_from ); ?>" min="0" max="14" step="0.1" style="width:80px;">
		<label for="sz_plant_ph_to"><?php esc_html_e( 'до', 'sad-znaniy' ); ?></label>
		<input type="number" id="sz_plant_ph_to" name="sz_plant_ph_to" value="<?php echo esc_attr( $ph_to ); ?>" min="0" max="14" step="0.1" style="width:80px;">
	</p>
	<p>
		<label for="sz_plant_soil"><strong><?php esc_html_e( 'Почва', 'sad-znaniy' ); ?></strong></label><br>
		<input type="text" id="sz_plant_soil" name="sz_plant_soil" value="<?php echo esc_attr( $soil ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'например: рыхлая, плодородная, дренированная', 'sad-znaniy' ); ?>">
	</p>
	<?php
}

/**
 * Метабокс «Совместимость».
 *
 * @param WP_Post $post Текущая запись.
 */
function sad_znaniy_plant_compat_meta_box_html( $post ) {
	$good = (string) get_post_meta( $post->ID, '_sz_plant_neighbors_good', true );
	$bad  = (string) get_post_meta( $post->ID, '_sz_plant_neighbors_bad', true );
	?>
	<p>
		<label for="sz_plant_neighbors_good"><strong><?php esc_html_e( 'Хорошие соседи', 'sad-znaniy' ); ?></strong></label><br>
		<textarea id="sz_plant_neighbors_good" name="sz_plant_neighbors_good" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'по одной культуре в строке или через запятую', 'sad-znaniy' ); ?>"><?php echo esc_textarea( $good ); ?></textarea>
	</p>
	<p>
		<label for="sz_plant_neighbors_bad"><strong><?php esc_html_e( 'Плохие соседи', 'sad-znaniy' ); ?></strong></label><br>
		<textarea id="sz_plant_neighbors_bad" name="sz_plant_neighbors_bad" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'по одной культуре в строке или через запятую', 'sad-znaniy' ); ?>"><?php echo esc_textarea( $bad ); ?></textarea>
	</p>
	<p class="description"><?php esc_html_e( 'Данные используются на странице растения и (в будущем) в планировщике участка.', 'sad-znaniy' ); ?></p>
	<?php
}

/**
 * Сохраняет метаполя растения.
 *
 * @param int $post_id ID записи.
 */
function sad_znaniy_plant_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['sad_znaniy_plant_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sad_znaniy_plant_nonce'] ) ), 'sad_znaniy_plant_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$light = isset( $_POST['sz_plant_light'] ) ? sanitize_key( wp_unslash( $_POST['sz_plant_light'] ) ) : '';
	$water = isset( $_POST['sz_plant_water'] ) ? sanitize_key( wp_unslash( $_POST['sz_plant_water'] ) ) : '';
	if ( ! array_key_exists( $light, sad_znaniy_light_options() ) ) {
		$light = '';
	}
	if ( ! array_key_exists( $water, sad_znaniy_water_options() ) ) {
		$water = '';
	}

	$ph_from_raw = isset( $_POST['sz_plant_ph_from'] ) ? trim( (string) wp_unslash( $_POST['sz_plant_ph_from'] ) ) : '';
	$ph_to_raw   = isset( $_POST['sz_plant_ph_to'] ) ? trim( (string) wp_unslash( $_POST['sz_plant_ph_to'] ) ) : '';
	$ph_from     = ( '' === $ph_from_raw ) ? '' : max( 0.0, min( 14.0, (float) $ph_from_raw ) );
	$ph_to       = ( '' === $ph_to_raw ) ? '' : max( 0.0, min( 14.0, (float) $ph_to_raw ) );

	$soil = isset( $_POST['sz_plant_soil'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_plant_soil'] ) ) : '';
	$good = isset( $_POST['sz_plant_neighbors_good'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sz_plant_neighbors_good'] ) ) : '';
	$bad  = isset( $_POST['sz_plant_neighbors_bad'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sz_plant_neighbors_bad'] ) ) : '';

	update_post_meta( $post_id, '_sz_plant_light', $light );
	update_post_meta( $post_id, '_sz_plant_water', $water );
	update_post_meta( $post_id, '_sz_plant_ph_from', $ph_from );
	update_post_meta( $post_id, '_sz_plant_ph_to', $ph_to );
	update_post_meta( $post_id, '_sz_plant_soil', $soil );
	update_post_meta( $post_id, '_sz_plant_neighbors_good', $good );
	update_post_meta( $post_id, '_sz_plant_neighbors_bad', $bad );
}
add_action( 'save_post_plant', 'sad_znaniy_plant_save_meta' );

/**
 * Колонки списка растений в админке: свет и полив.
 *
 * @param array $columns Существующие колонки.
 * @return array
 */
function sad_znaniy_plant_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['sz_plant_light'] = __( 'Свет', 'sad-znaniy' );
			$new['sz_plant_water'] = __( 'Полив', 'sad-znaniy' );
		}
	}

	return $new;
}
add_filter( 'manage_plant_posts_columns', 'sad_znaniy_plant_columns' );

/**
 * Выводит значения колонок растений.
 *
 * @param string $column  Имя колонки.
 * @param int    $post_id ID записи.
 */
function sad_znaniy_plant_column_content( $column, $post_id ) {
	if ( 'sz_plant_light' === $column ) {
		$light = (string) get_post_meta( $post_id, '_sz_plant_light', true );
		$label = sad_znaniy_option_label( sad_znaniy_light_options(), $light );
		echo $label ? esc_html( $label ) : '&mdash;';
	}

	if ( 'sz_plant_water' === $column ) {
		$water = (string) get_post_meta( $post_id, '_sz_plant_water', true );
		$label = sad_znaniy_option_label( sad_znaniy_water_options(), $water );
		echo $label ? esc_html( $label ) : '&mdash;';
	}
}
add_action( 'manage_plant_posts_custom_column', 'sad_znaniy_plant_column_content', 10, 2 );


