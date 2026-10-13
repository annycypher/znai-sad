<?php
/**
 * СЖАТИЕ ИЗОБРАЖЕНИЙ (Уровень 2) — WebP/AVIF и вывод <picture>.
 *
 * Что делает:
 *   • при загрузке картинки рядом с оригиналом и каждой миниатюрой создаёт .webp
 *     (и .avif, если включено в настройках);
 *   • при выводе подменяет <img> на <picture> с источниками AVIF → WebP → оригинал
 *     (браузер сам выбирает поддерживаемый формат, старые получают JPEG/PNG);
 *   • работает и для картинок темы (хук wp_get_attachment_image), и для картинок
 *     внутри текстов статей (хук wp_content_img_tag) — правок шаблонов не требуется;
 *   • вложения с галочкой «Не сжимать» (рекламные баннеры) не конвертируются.
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

/**
 * URL с другим расширением (только для файлов uploads), иначе пустая строка.
 *
 * @param string $url Исходный URL.
 * @param string $ext Расширение без точки (webp/avif).
 * @return string
 */
function sad_znaniy_ng_swap_url( $url, $ext ) {
	$url = (string) $url;
	$up  = wp_get_upload_dir();
	if ( "" === $url || 0 !== strpos( $url, $up["baseurl"] ) ) {
		return "";
	}
	if ( ! preg_match( "/\.(jpe?g|png|webp)$/i", $url ) ) {
		return "";
	}
	return (string) preg_replace( "/\.(jpe?g|png|webp)$/i", "." . $ext, $url );
}

/**
 * Существует ли файл по uploads-URL (с кэшем на запрос).
 *
 * @param string $url URL.
 * @return bool
 */
function sad_znaniy_ng_exists( $url ) {
	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$up   = wp_get_upload_dir();
	$path = str_replace( $up["baseurl"], $up["basedir"], (string) $url );
	$cache[ $url ] = ( "" !== $url && is_file( $path ) );
	return $cache[ $url ];
}

/**
 * Пишет сайдкар нужного формата из файла.
 *
 * @param string $path    Путь к исходнику.
 * @param string $ext     webp|avif.
 * @param int    $quality Качество.
 * @return bool
 */
function sad_znaniy_ng_write( $path, $ext, $quality ) {
	if ( ! is_file( $path ) || ! function_exists( "imagecreatefromjpeg" ) ) {
		return false;
	}
	$info = @getimagesize( $path );
	if ( ! $info ) {
		return false;
	}
	$mime = (string) $info["mime"];
	if ( ! in_array( $mime, array( "image/jpeg", "image/png", "image/webp" ), true ) ) {
		return false;
	}
	$img = sad_znaniy_img_load( $path, $mime );
	if ( ! $img ) {
		return false;
	}
	$to = (string) preg_replace( "/\.(jpe?g|png|webp)$/i", "." . $ext, $path );
	$ok = false;
	if ( "webp" === $ext && function_exists( "imagewebp" ) ) {
		$ok = @imagewebp( $img, $to, (int) $quality );
	} elseif ( "avif" === $ext && function_exists( "imageavif" ) ) {
		$ok = @imageavif( $img, $to, (int) $quality );
	}
	imagedestroy( $img );
	return (bool) $ok;
}

/**
 * Создаёт сайдкары для вложения: оригинал + все миниатюры.
 *
 * @param int $attachment_id ID вложения.
 * @return array{created:int,bytes:int}
 */
function sad_znaniy_ng_make_for_attachment( $attachment_id ) {
	$set      = sad_znaniy_img_settings();
	$stat     = array( "created" => 0, "bytes" => 0 );
	$file     = get_attached_file( (int) $attachment_id );
	if ( ! $file || ! is_file( $file ) ) {
		return $stat;
	}
	$exts = array( "webp" );
	if ( ! empty( $set["avif"] ) ) {
		$exts[] = "avif";
	}

	$targets = array( $file );
	$meta    = wp_get_attachment_metadata( (int) $attachment_id );
	if ( ! empty( $meta["sizes"] ) ) {
		$dir = trailingslashit( dirname( $file ) );
		foreach ( (array) $meta["sizes"] as $size ) {
			if ( empty( $size["file"] ) || (int) $size["width"] < 300 ) {
				continue;
			}
			$targets[] = $dir . $size["file"];
		}
	}

	foreach ( $targets as $src ) {
		$info = @getimagesize( $src );
		if ( ! $info || ! in_array( (string) $info["mime"], array( "image/jpeg", "image/png", "image/webp" ), true ) ) {
			continue;
		}
		foreach ( $exts as $ext ) {
			$to = (string) preg_replace( "/\.(jpe?g|png|webp)$/i", "." . $ext, $src );
			if ( is_file( $to ) ) {
				continue;
			}
			$q = "webp" === $ext ? (int) $set["webp_quality"] : (int) $set["avif_quality"];
			if ( sad_znaniy_ng_write( $src, $ext, $q ) && is_file( $to ) ) {
				$stat["created"]++;
				$stat["bytes"] += (int) filesize( $to );
			}
		}
	}
	return $stat;
}

/**
 * После генерации метаданных вложения — делаем next-gen версии.
 *
 * @param array $metadata      Метаданные.
 * @param int   $attachment_id ID вложения.
 * @return array
 */
function sad_znaniy_ng_after_metadata( $metadata, $attachment_id ) {
	$set = sad_znaniy_img_settings();
	if ( empty( $set["enabled"] ) || empty( $set["nextgen"] ) ) {
		return $metadata;
	}
	if ( get_post_meta( (int) $attachment_id, "_sz_img_skip", true ) ) {
		return $metadata;
	}
	if ( 0 !== strpos( (string) get_post_mime_type( (int) $attachment_id ), "image/" ) ) {
		return $metadata;
	}
	sad_znaniy_ng_make_for_attachment( (int) $attachment_id );
	return $metadata;
}
add_filter( "wp_generate_attachment_metadata", "sad_znaniy_ng_after_metadata", 20, 2 );

/**
 * Собирает srcset для одного формата.
 *
 * @param string $srcset Исходный srcset.
 * @param string $src    Одиночный src.
 * @param string $ext    webp|avif.
 * @return string "" — если ни одного файла нет.
 */
function sad_znaniy_ng_srcset( $srcset, $src, $ext ) {
	$items = array();
	if ( "" !== (string) $srcset ) {
		foreach ( explode( ",", (string) $srcset ) as $part ) {
			$part = trim( $part );
			if ( "" === $part ) {
				continue;
			}
			$bits = preg_split( "/\s+/", $part );
			$u    = isset( $bits[0] ) ? sad_znaniy_ng_swap_url( $bits[0], $ext ) : "";
			if ( "" === $u || ! sad_znaniy_ng_exists( $u ) ) {
				continue;
			}
			$items[] = $u . ( isset( $bits[1] ) ? " " . $bits[1] : "" );
		}
	}
	if ( ! $items ) {
		$u = sad_znaniy_ng_swap_url( $src, $ext );
		if ( "" !== $u && sad_znaniy_ng_exists( $u ) ) {
			$items[] = $u;
		}
	}
	return implode( ", ", $items );
}

/**
 * Оборачивает <img> в <picture> с источниками AVIF и WebP.
 *
 * @param string $html HTML с <img>.
 * @return string
 */
function sad_znaniy_ng_wrap( $html ) {
	$html = (string) $html;
	if ( "" === $html || false !== strpos( $html, "<picture" ) || ! preg_match( "/<img\b[^>]*>/i", $html, $m ) ) {
		return $html;
	}
	$tag    = $m[0];
	$src    = "";
	$srcset = "";
	$sizes  = "";
	if ( preg_match( "/\bsrc\s*=\s*\"([^\"]+)\"/i", $tag, $x ) ) {
		$src = $x[1];
	}
	if ( preg_match( "/\bsrcset\s*=\s*\"([^\"]+)\"/i", $tag, $y ) ) {
		$srcset = $y[1];
	}
	if ( preg_match( "/\bsizes\s*=\s*\"([^\"]+)\"/i", $tag, $z ) ) {
		$sizes = $z[1];
	}
	$avif = sad_znaniy_ng_srcset( $srcset, $src, "avif" );
	$webp = sad_znaniy_ng_srcset( $srcset, $src, "webp" );
	if ( "" === $avif && "" === $webp ) {
		return $html;
	}
	$tail = ( "" !== $sizes ) ? " sizes=\"" . esc_attr( $sizes ) . "\"" : "";
	$out  = "<picture>";
	if ( "" !== $avif ) {
		$out .= "<source type=\"image/avif\" srcset=\"" . esc_attr( $avif ) . "\"" . $tail . ">";
	}
	if ( "" !== $webp ) {
		$out .= "<source type=\"image/webp\" srcset=\"" . esc_attr( $webp ) . "\"" . $tail . ">";
	}
	$out .= $tag . "</picture>";
	return str_replace( $tag, $out, $html );
}

/**
 * Картинки, собранные темой (карточки, растения, страницы).
 *
 * @param string $html          HTML <img>.
 * @param int    $attachment_id ID вложения.
 * @param mixed  $size          Размер.
 * @param bool   $icon          Иконка ли.
 * @param array  $attr          Атрибуты.
 * @return string
 */
function sad_znaniy_ng_filter_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
	$set = sad_znaniy_img_settings();
	if ( $icon || empty( $set["enabled"] ) || empty( $set["nextgen"] ) ) {
		return $html;
	}
	if ( get_post_meta( (int) $attachment_id, "_sz_img_skip", true ) ) {
		return $html;
	}
	return sad_znaniy_ng_wrap( $html );
}
add_filter( "wp_get_attachment_image", "sad_znaniy_ng_filter_attachment_image", 20, 5 );

/**
 * Картинки внутри текста статьи (<img> в контенте).
 *
 * @param string $filtered_image HTML <img>.
 * @param string $context        Контекст.
 * @param int    $attachment_id  ID вложения.
 * @return string
 */
function sad_znaniy_ng_filter_content_img( $filtered_image, $context, $attachment_id ) {
	$set = sad_znaniy_img_settings();
	if ( empty( $set["enabled"] ) || empty( $set["nextgen"] ) ) {
		return $filtered_image;
	}
	if ( $attachment_id && get_post_meta( (int) $attachment_id, "_sz_img_skip", true ) ) {
		return $filtered_image;
	}
	return sad_znaniy_ng_wrap( $filtered_image );
}
add_filter( "wp_content_img_tag", "sad_znaniy_ng_filter_content_img", 20, 3 );
/**
 * <picture> вокруг одиночного <img> по прямому URL (карточки без ID вложения).
 *
 * @param string $url   URL картинки в uploads.
 * @param string $alt   Подпись.
 * @param string $class CSS-класс картинки.
 * @return string
 */
function sad_znaniy_ng_picture_url( $url, $alt, $class ) {
	$url = (string) $url;
	$img = "<img class=\"" . esc_attr( $class ) . "\" src=\"" . esc_url( $url ) . "\" alt=\"" . esc_attr( $alt ) . "\" loading=\"lazy\" decoding=\"async\">";
	$set = sad_znaniy_img_settings();
	if ( empty( $set["enabled"] ) || empty( $set["nextgen"] ) ) {
		return $img;
	}
	return sad_znaniy_ng_wrap( $img );
}