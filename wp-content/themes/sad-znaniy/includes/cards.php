<?php
/**
 * Картинка карточки материала: обложка, а если её нет — первое изображение из текста.
 *
 * Нужна для разделов /stati/ и /rasteniya/: посетитель видит миниатюру рядом
 * с анонсом, даже когда «Изображение записи» не задано, но фото уже стоит в статье.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Данные картинки карточки.
 *
 * @param WP_Post|int $post Запись или её ID.
 * @return array{id:int,url:string,alt:string}|null
 */
function sad_znaniy_card_image( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}

	$fallback_alt = function_exists( 'sad_znaniy_image_alt_suggestion' )
		? sad_znaniy_image_alt_suggestion( $post )
		: (string) get_the_title( $post );

	// 1. Обложка («Изображение записи») — приоритет.
	if ( has_post_thumbnail( $post ) ) {
		$id  = (int) get_post_thumbnail_id( $post );
		$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( '' === trim( $alt ) ) {
			$alt = $fallback_alt;
		}

		return array(
			'id'  => $id,
			'url' => (string) get_the_post_thumbnail_url( $post, 'medium_large' ),
			'alt' => $alt,
		);
	}

	$content = (string) $post->post_content;

	// 2. Блок «Изображение» с привязкой к медиатеке — даёт srcset.
	if ( preg_match( '/wp:image\s*\{[^}]*"id"\s*:\s*(\d+)/', $content, $m ) ) {
		$id  = (int) $m[1];
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'medium_large' ) : '';
		if ( '' !== $url ) {
			$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( '' === trim( $alt ) ) {
				$alt = $fallback_alt;
			}

			return array(
				'id'  => $id,
				'url' => $url,
				'alt' => $alt,
			);
		}
	}

	// 3. Первый <img src="…"> в тексте. Заглушки «TODO: фото» без src пропускаем.
	if ( preg_match_all( '/<img\b[^>]*>/i', $content, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			if ( ! preg_match( '/\bsrc\s*=\s*"([^"]+)"/i', $tag, $src ) ) {
				continue;
			}
			if ( ! preg_match( '/\balt\s*=\s*"([^"]*)"/i', $tag, $a ) ) {
				$a = array( '', '' );
			}

			$alt = trim( wp_strip_all_tags( html_entity_decode( $a[1], ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' === $alt ) {
				$alt = $fallback_alt;
			}

			return array(
				'id'  => (int) attachment_url_to_postid( $src[1] ),
				'url' => (string) $src[1],
				'alt' => $alt,
			);
		}
	}

	return null;
}

/**
 * HTML тега <img> для карточки.
 *
 * @param array $image Данные из sad_znaniy_card_image().
 * @return string
 */
function sad_znaniy_card_image_tag( $image ) {
	if ( empty( $image ) ) {
		return '';
	}

	if ( ! empty( $image['id'] ) ) {
		return (string) wp_get_attachment_image(
			(int) $image['id'],
			'medium_large',
			false,
			array(
				'class'   => 'post-card__img',
				'alt'     => $image['alt'],
				'loading' => 'lazy',
			)
		);
	}

	if ( function_exists( 'sad_znaniy_ng_picture_url' ) ) {
		return sad_znaniy_ng_picture_url( (string) $image['url'], (string) $image['alt'], 'post-card__img' );
	}

	return '<img class="post-card__img" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $image['alt'] ) . '" loading="lazy" decoding="async">';
}
