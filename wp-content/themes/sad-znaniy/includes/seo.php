<?php
/**
 * Минимальный SEO-базис: поля seo_title / seo_description у записей.
 *
 * Выводит <title> и <meta name="description"> из полей записи.
 * Используется Этапом 3 и далее (без сторонних SEO-плагинов).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Добавляет метабокс SEO для записей, растений и страниц.
 */
function sad_znaniy_seo_add_meta_box() {
	add_meta_box(
		'sad-znaniy-seo',
		__( 'SEO (заголовок и описание)', 'sad-znaniy' ),
		'sad_znaniy_seo_meta_box_html',
		array( 'post', 'plant', 'page' ),
		'normal',
		'low'
	);
}
add_action( 'add_meta_boxes', 'sad_znaniy_seo_add_meta_box' );

/**
 * Разметка метабокса SEO.
 *
 * @param WP_Post $post Текущая запись.
 */
function sad_znaniy_seo_meta_box_html( $post ) {
	wp_nonce_field( 'sad_znaniy_seo_save', 'sad_znaniy_seo_nonce' );

	$title = (string) get_post_meta( $post->ID, '_sz_seo_title', true );
	$desc  = (string) get_post_meta( $post->ID, '_sz_seo_description', true );
	?>
	<p>
		<label for="sz_seo_title"><strong><?php esc_html_e( 'SEO-заголовок (title)', 'sad-znaniy' ); ?></strong></label><br>
		<input type="text" id="sz_seo_title" name="sz_seo_title" value="<?php echo esc_attr( $title ); ?>" class="widefat" maxlength="70">
		<span class="description"><?php esc_html_e( 'Рекомендуется 50–60 знаков, ключевая фраза в начале.', 'sad-znaniy' ); ?></span>
	</p>
	<p>
		<label for="sz_seo_description"><strong><?php esc_html_e( 'SEO-описание (description)', 'sad-znaniy' ); ?></strong></label><br>
		<textarea id="sz_seo_description" name="sz_seo_description" rows="2" class="widefat" maxlength="170"><?php echo esc_textarea( $desc ); ?></textarea>
		<span class="description"><?php esc_html_e( 'Рекомендуется 120–160 знаков, с призывом или выгодой.', 'sad-znaniy' ); ?></span>
	</p>
	<?php
}

/**
 * Сохраняет SEO-поля.
 *
 * @param int $post_id ID записи.
 */
function sad_znaniy_seo_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['sad_znaniy_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sad_znaniy_seo_nonce'] ) ), 'sad_znaniy_seo_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$title = isset( $_POST['sz_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_seo_title'] ) ) : '';
	$desc  = isset( $_POST['sz_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sz_seo_description'] ) ) : '';

	update_post_meta( $post_id, '_sz_seo_title', $title );
	update_post_meta( $post_id, '_sz_seo_description', $desc );
}
add_action( 'save_post', 'sad_znaniy_seo_save_meta' );

/**
 * Подменяет <title> значением seo_title на одиночных записях.
 *
 * @param array $parts Части заголовка.
 * @return array
 */
function sad_znaniy_seo_title_parts( $parts ) {
	if ( is_singular( array( 'post', 'plant', 'page' ) ) ) {
		$t = get_post_meta( get_queried_object_id(), '_sz_seo_title', true );
		if ( $t ) {
			$parts['title'] = $t;
			unset( $parts['site'], $parts['tagline'] );
		}
	}

	return $parts;
}
add_filter( 'document_title_parts', 'sad_znaniy_seo_title_parts' );

/**
 * Выводит <meta name="description"> на одиночных записях.
 */
function sad_znaniy_seo_meta_description() {
	if ( is_singular( array( 'post', 'plant', 'page' ) ) ) {
		$d = get_post_meta( get_queried_object_id(), '_sz_seo_description', true );
		if ( $d ) {
			echo '<meta name="description" content="' . esc_attr( $d ) . '" />' . "\n";
		}
	}
}
add_action( 'wp_head', 'sad_znaniy_seo_meta_description', 1 );
