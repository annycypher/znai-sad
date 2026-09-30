<?php
/**
 * Настройки темы «Сад знаний».
 *
 * Сейчас одна опция: выбор растений для блока «Популярное» на главной
 * (Выгляд → «Сад знаний»). Здесь же на Этапе 4 появятся нормативы
 * калькуляторов.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует опцию темы.
 */
function sad_znaniy_register_settings() {
	register_setting(
		'sad_znaniy_settings',
		'sad_znaniy_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sad_znaniy_sanitize_options',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'sad_znaniy_register_settings' );

/**
 * Очищает значения настроек перед сохранением.
 *
 * @param mixed $input Сырые значения.
 * @return array
 */
function sad_znaniy_sanitize_options( $input ) {
	$output = array();

	if ( isset( $input['popular_plants'] ) && is_array( $input['popular_plants'] ) ) {
		$ids = array_filter( array_map( 'absint', $input['popular_plants'] ) );
		$output['popular_plants'] = array_values( array_unique( $ids ) );
	} else {
		$output['popular_plants'] = array();
	}

	return $output;
}

/**
 * Добавляет страницу настроек в раздел «Внешний вид».
 */
function sad_znaniy_add_options_page() {
	add_theme_page(
		__( 'Настройки «Сад знаний»', 'sad-znaniy' ),
		__( 'Сад знаний', 'sad-znaniy' ),
		'manage_options',
		'sad-znaniy-options',
		'sad_znaniy_render_options_page'
	);
}
add_action( 'admin_menu', 'sad_znaniy_add_options_page' );

/**
 * Выводит страницу настроек.
 */
function sad_znaniy_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$options = get_option( 'sad_znaniy_options', array() );
	$popular = isset( $options['popular_plants'] ) ? (array) $options['popular_plants'] : array();
	$popular = array_map( 'absint', $popular );

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
	<div class="wrap">
		<h1><?php esc_html_e( 'Настройки «Сад знаний»', 'sad-znaniy' ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'sad_znaniy_settings' ); ?>

			<h2><?php esc_html_e( 'Популярное на главной', 'sad-znaniy' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Выберите растения, ссылки на которые появятся в чипах «Популярное» на главной странице (Ctrl — выбрать несколько).', 'sad-znaniy' ); ?></p>

			<?php if ( $plants ) : ?>
				<select name="sad_znaniy_options[popular_plants][]" multiple size="10" style="min-width:340px;">
					<?php foreach ( $plants as $plant ) : ?>
						<option value="<?php echo esc_attr( $plant->ID ); ?>" <?php echo in_array( (int) $plant->ID, $popular, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( get_the_title( $plant ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<p><?php esc_html_e( 'Растений пока нет. Сначала добавьте их в разделе «Растения».', 'sad-znaniy' ); ?></p>
			<?php endif; ?>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
