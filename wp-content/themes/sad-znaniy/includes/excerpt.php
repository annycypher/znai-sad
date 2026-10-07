<?php
/**
 * Анонс (отрывок) материала: единый текст для карточек, соцпостов и поиска.
 *
 * Источник выбирается по цепочке:
 * 1) поле «Отрывок» (если владелец заполнил вручную);
 * 2) SEO-описание (оно уже написано как короткая выжимка по теме);
 * 3) начало текста записи.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Анонс материала в виде чистого текста.
 *
 * @param WP_Post|int $post Запись или её ID.
 * @return string
 */
function sad_znaniy_announce_text( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	$excerpt = trim( (string) $post->post_excerpt );
	if ( '' !== $excerpt ) {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $excerpt ) ) );
	}

	$seo = trim( (string) get_post_meta( $post->ID, '_sz_seo_description', true ) );
	if ( '' !== $seo ) {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $seo ) ) );
	}

	return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) ) );
}

/**
 * Подставляет анонс вместо пустого отрывка (карточки, ленты, поиск).
 *
 * Важно: приоритет 20 — ядро на приоритете 10 подставляет начало текста
 * (wp_trim_excerpt), иначе SEO-описание до нашей проверки не дошло бы.
 *
 * @param string  $excerpt Отрывок.
 * @param WP_Post $post    Запись.
 * @return string
 */
function sad_znaniy_excerpt_filter( $excerpt, $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return $excerpt;
	}

	// Ручной отрывок всегда важнее.
	if ( '' !== trim( (string) $post->post_excerpt ) ) {
		return $excerpt;
	}

	$announce = sad_znaniy_announce_text( $post );

	return '' !== $announce ? $announce : $excerpt;
}
add_filter( 'get_the_excerpt', 'sad_znaniy_excerpt_filter', 20, 2 );
