<?php

// Только CLI: скрипты разработки не исполняются из браузера (Этап 7.3, S2).
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'Скрипт запускается только из командной строки.' );
}
/**
 * BACKFILL next-gen версий для уже загруженных изображений (WebP/AVIF).
 *
 * Проходит по всем вложениям-картинкам и создаёт рядом с оригиналом и каждой
 * миниатюрой файлы .webp (и .avif, если включено в настройках сжатия). Вложения
 * с галочкой «Не сжимать» пропускаются. Тема при этом не меняется — только файлы.
 *
 * Запуск (PHP LocalWP, GD обязателен):
 *   php.exe -d extension_dir="<php>/ext" -d extension=php_gd.dll -d extension=php_mysqli.dll \
 *     -d extension=php_mbstring.dll -d mysqli.default_port=10005 _tools/nextgen-images.php dry
 *   ... _tools/nextgen-images.php confirm          (все вложения)
 *   ... _tools/nextgen-images.php confirm 60       (партия: первые 60 вложений без сайдкаров)
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
	require dirname( __DIR__, 4 ) . "/wp-load.php";
}

if ( ! function_exists( "sad_znaniy_ng_make_for_attachment" ) ) {
	echo "Тема sad-znaniy не активна — backfill невозможен.\n";
	return;
}

$args    = isset( $argv ) ? array_slice( $argv, 1 ) : array();
$confirm = in_array( "confirm", $args, true );
$limit   = 0;
foreach ( $args as $a ) {
	if ( preg_match( "/^\d+$/", (string) $a ) ) {
		$limit = (int) $a;
	}
}

$ids = get_posts(
	array(
		"post_type"      => "attachment",
		"post_status"    => "inherit",
		"post_mime_type" => "image",
		"posts_per_page" => -1,
		"fields"         => "ids",
	)
);

$done = 0;
$created = 0;
$bytes = 0;
$skipped = 0;
$touched = 0;

echo "вложений-картинок: " . count( $ids ) . " | режим: " . ( $confirm ? "confirm" : "dry" ) . ( $limit ? " | лимит вложений: " . $limit : "" ) . "\n";

foreach ( $ids as $id ) {
	if ( get_post_meta( (int) $id, "_sz_img_skip", true ) ) {
		$skipped++;
		continue;
	}
	$file = get_attached_file( (int) $id );
	if ( ! $file || ! is_file( $file ) ) {
		continue;
	}
	$meta = wp_get_attachment_metadata( (int) $id );
	$need = array( $file );
	if ( ! empty( $meta["sizes"] ) ) {
		$dir = trailingslashit( dirname( $file ) );
		foreach ( (array) $meta["sizes"] as $size ) {
			if ( ! empty( $size["file"] ) && (int) $size["width"] >= 300 ) {
				$need[] = $dir . $size["file"];
			}
		}
	}
	$missing = 0;
	foreach ( $need as $src ) {
		foreach ( array( "webp", "avif" ) as $ext ) {
			$to = (string) preg_replace( "/\.(jpe?g|png|webp)$/i", "." . $ext, $src );
			if ( ! is_file( $to ) ) {
				$missing++;
			}
		}
	}
	if ( ! $missing ) {
		continue;
	}
	if ( ! $confirm ) {
		$touched++;
		continue;
	}
	$stat     = sad_znaniy_ng_make_for_attachment( (int) $id );
	$created += (int) $stat["created"];
	$bytes   += (int) $stat["bytes"];
	$done++;
	echo "[" . $done . "] #" . $id . " " . basename( $file ) . " → создано " . (int) $stat["created"] . ", " . round( (int) $stat["bytes"] / 1024 ) . " КБ\n";
	if ( $limit && $done >= $limit ) {
		break;
	}
}

echo "---\n";
if ( $confirm ) {
	echo "вложений обработано: " . $done . " | сайдкаров создано: " . $created . " | объём next-gen: " . round( $bytes / 1048576, 2 ) . " МБ | пропущено (баннеры): " . $skipped . "\n";
	sad_znaniy_img_log( "next-gen backfill: вложений " . $done . ", файлов " . $created . ", " . round( $bytes / 1048576, 2 ) . " МБ" );
} else {
	echo "нужно доработать вложений: " . $touched . " | пропущено (баннеры): " . $skipped . "\n";
	echo "запустите с параметром confirm (можно с лимитом: confirm 60)\n";
}
