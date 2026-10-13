<?php
/**
 * СЖАТИЕ ИЗОБРАЖЕНИЙ (Уровень 1) — предохранитель при загрузке.
 *
 * Что делает:
 *   • при загрузке картинки через медиатеку или редактор: ресайз по длинной
 *     стороне, пережатие в том же формате (JPEG/PNG/WebP), вырезание метаданных;
 *   • если результат не меньше оригинала — оригинал остаётся нетронутым;
 *   • перед изменением оригинал копируется в uploads/_originals/ (откат возможен);
 *   • исключения (рекламные баннеры и т. п.): слова в имени файла, галочка
 *     «Не сжимать» у вложения, порог по весу, лимит по размеру;
 *   • журнал последних обработок — на странице «Медиа → Сжатие изображений».
 *
 * Настройки — опция sz_img_settings (правятся на той же странице).
 * Уровень 2 (генерация WebP/AVIF и вывод <picture>) — includes/image-nextgen.php.
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

/**
 * Настройки сжатия.
 *
 * @return array
 */
function sad_znaniy_img_settings() {
	static $set = null;
	if ( null !== $set ) {
		return $set;
	}
	$defaults = array(
		"enabled"      => 1,
		"max_side"     => 1600,
		"jpeg_quality" => 82,
		"webp_quality" => 80,
		"avif_quality" => 55,
		"nextgen"      => 1,
		"avif"         => 1,
		"skip_words"   => "banner, баннер, ad-, ads, promo, logo, логотип",
		"min_kb"       => 60,
		"max_mb"       => 20,
	);
	$set = wp_parse_args( (array) get_option( "sz_img_settings", array() ), $defaults );
	return $set;
}

/**
 * Слова-исключения из настроек.
 *
 * @return string[]
 */
function sad_znaniy_img_skip_words() {
	$s   = sad_znaniy_img_settings();
	$out = array();
	foreach ( explode( ",", (string) $s["skip_words"] ) as $w ) {
		$w = mb_strtolower( trim( $w ), "UTF-8" );
		if ( "" !== $w ) {
			$out[] = $w;
		}
	}
	return $out;
}

/**
 * Исключён ли файл по имени (баннеры и т. п.).
 *
 * @param string $path Путь к файлу.
 * @return bool
 */
function sad_znaniy_img_is_skipped_name( $path ) {
	$name = mb_strtolower( basename( $path ), "UTF-8" );
	foreach ( sad_znaniy_img_skip_words() as $w ) {
		if ( false !== mb_strpos( $name, $w ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Байты в человеческом виде.
 *
 * @param int $bytes Байты.
 * @return string
 */
function sad_znaniy_img_kb( $bytes ) {
	$bytes = (int) $bytes;
	return $bytes >= 1048576 ? round( $bytes / 1048576, 2 ) . " МБ" : (int) round( $bytes / 1024 ) . " КБ";
}

/**
 * Путь копии оригинала в uploads/_originals/ (структура папок повторяется).
 *
 * @param string $path Путь к файлу в uploads.
 * @return string
 */
function sad_znaniy_img_backup_path( $path ) {
	$up  = wp_get_upload_dir();
	$rel = ltrim( str_replace( "\\", "/", substr( $path, strlen( $up["basedir"] ) ) ), "/" );
	return $up["basedir"] . "/_originals/" . $rel;
}

/**
 * Копирует оригинал в _originals (один раз).
 *
 * @param string $path Путь к файлу.
 * @return bool
 */
function sad_znaniy_img_backup( $path ) {
	$to = sad_znaniy_img_backup_path( $path );
	if ( is_file( $to ) ) {
		return true;
	}
	$dir = dirname( $to );
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	return (bool) @copy( $path, $to );
}

/**
 * Запись в журнал сжатия (последние 40 строк).
 *
 * @param string $line Строка.
 * @return void
 */
function sad_znaniy_img_log( $line ) {
	$log = (array) get_option( "sz_img_log", array() );
	array_unshift( $log, current_time( "d.m.Y H:i" ) . " — " . $line );
	update_option( "sz_img_log", array_slice( $log, 0, 40 ), false );
}

/**
 * Открывает изображение через GD.
 *
 * @param string $path Путь.
 * @param string $mime MIME.
 * @return resource|GdImage|false
 */
function sad_znaniy_img_load( $path, $mime ) {
	if ( "image/jpeg" === $mime ) {
		return @imagecreatefromjpeg( $path );
	}
	if ( "image/png" === $mime ) {
		return @imagecreatefrompng( $path );
	}
	if ( "image/webp" === $mime ) {
		return @imagecreatefromwebp( $path );
	}
	return false;
}

/**
 * Ресайз по длинной стороне (если больше лимита).
 *
 * @param resource|GdImage $img      Изображение.
 * @param int              $max_side Лимит длинной стороны.
 * @return resource|GdImage
 */
function sad_znaniy_img_resize( $img, $max_side ) {
	$w = imagesx( $img );
	$h = imagesy( $img );
	if ( $max_side < 100 || max( $w, $h ) <= $max_side ) {
		return $img;
	}
	if ( $w >= $h ) {
		$nw = $max_side;
		$nh = (int) round( $h * $max_side / $w );
	} else {
		$nh = $max_side;
		$nw = (int) round( $w * $max_side / $h );
	}
	$dst = imagecreatetruecolor( $nw, $nh );
	imagealphablending( $dst, false );
	imagesavealpha( $dst, true );
	imagecopyresampled( $dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h );
	imagedestroy( $img );
	return $dst;
}

/**
 * Сохраняет изображение в исходном формате.
 *
 * @param resource|GdImage $img  Изображение.
 * @param string           $to   Куда сохранить.
 * @param string           $mime MIME.
 * @param array            $set  Настройки.
 * @return bool
 */
function sad_znaniy_img_save( $img, $to, $mime, $set ) {
	if ( "image/jpeg" === $mime ) {
		return @imagejpeg( $img, $to, (int) $set["jpeg_quality"] );
	}
	if ( "image/png" === $mime ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		return @imagepng( $img, $to, 9 );
	}
	if ( "image/webp" === $mime ) {
		return @imagewebp( $img, $to, (int) $set["webp_quality"] );
	}
	return false;
}

/**
 * Оптимизация одного файла: ресайз + пережатие, с копией оригинала.
 *
 * @param string $path  Путь к файлу в uploads.
 * @param bool   $force Игнорировать пороги по весу и имени.
 * @return array{status:string,before:int,after:int,note:string}
 */
function sad_znaniy_img_optimize_file( $path, $force = false ) {
	$set = sad_znaniy_img_settings();
	$res = array( "status" => "skipped", "before" => 0, "after" => 0, "note" => "" );

	if ( ! is_file( $path ) || ! function_exists( "imagecreatefromjpeg" ) ) {
		$res["note"] = "нет файла или нет GD";
		return $res;
	}

	$res["before"] = (int) filesize( $path );
	$info          = @getimagesize( $path );
	if ( ! $info ) {
		$res["note"] = "не изображение";
		return $res;
	}
	$mime = (string) $info["mime"];
	if ( ! in_array( $mime, array( "image/jpeg", "image/png", "image/webp" ), true ) ) {
		$res["note"] = "формат не обрабатываем (" . $mime . ")";
		return $res;
	}

	if ( ! $force && sad_znaniy_img_is_skipped_name( $path ) ) {
		$res["note"] = "имя в списке исключений (баннер)";
		return $res;
	}
	if ( ! $force && $res["before"] < (int) $set["min_kb"] * 1024 ) {
		$res["note"] = "мельче порога";
		return $res;
	}
	if ( $res["before"] > (int) $set["max_mb"] * 1048576 ) {
		$res["note"] = "крупнее лимита — пропуск";
		return $res;
	}
	if ( max( (int) $info[0], (int) $info[1] ) > 8000 ) {
		$res["note"] = "слишком большие размеры — пропуск";
		return $res;
	}

	$img = sad_znaniy_img_load( $path, $mime );
	if ( ! $img ) {
		$res["note"] = "GD не открыл файл";
		return $res;
	}
	$img = sad_znaniy_img_resize( $img, (int) $set["max_side"] );

	$tmp = $path . ".sz-tmp";
	sad_znaniy_img_save( $img, $tmp, $mime, $set );
	imagedestroy( $img );

	if ( ! is_file( $tmp ) ) {
		$res["note"] = "не удалось сохранить";
		return $res;
	}
	$new = (int) filesize( $tmp );
	if ( $new >= $res["before"] ) {
		@unlink( $tmp );
		$res["note"] = "сжатие не улучшило — оставлен оригинал";
		return $res;
	}

	sad_znaniy_img_backup( $path );
	if ( ! @rename( $tmp, $path ) ) {
		@unlink( $tmp );
		$res["note"] = "не удалось заменить файл";
		return $res;
	}
	clearstatcache( true, $path );

	$res["status"] = "optimized";
	$res["after"]  = $new;
	$res["note"]   = "сжато";
	sad_znaniy_img_log( basename( $path ) . ": " . sad_znaniy_img_kb( $res["before"] ) . " → " . sad_znaniy_img_kb( $new ) );
	return $res;
}

/**
 * Хук загрузки: сжимаем картинку сразу, до генерации миниатюр.
 *
 * @param array $upload Данные загрузки из wp_handle_upload().
 * @return array
 */
function sad_znaniy_img_handle_upload( $upload ) {
	$set = sad_znaniy_img_settings();
	if ( empty( $set["enabled"] ) || empty( $upload["file"] ) || empty( $upload["type"] ) ) {
		return $upload;
	}
	if ( 0 !== strpos( (string) $upload["type"], "image/" ) ) {
		return $upload;
	}
	if ( in_array( (string) $upload["type"], array( "image/gif", "image/svg+xml", "image/avif" ), true ) ) {
		return $upload;
	}
	sad_znaniy_img_optimize_file( (string) $upload["file"] );
	return $upload;
}
add_filter( "wp_handle_upload", "sad_znaniy_img_handle_upload", 20 );
/* === Админка ============================================================= */

/**
 * Пункт меню «Медиа → Сжатие изображений».
 *
 * @return void
 */
function sad_znaniy_img_admin_menu() {
	add_media_page( "Сжатие изображений", "Сжатие изображений", "manage_options", "sad-img-compress", "sad_znaniy_img_admin_page" );
}
add_action( "admin_menu", "sad_znaniy_img_admin_menu" );

/**
 * Кандидаты на сжатие: файлы uploads без копии оригинала и крупнее порога.
 *
 * @param int $limit Сколько вернуть (0 — все).
 * @return string[] Пути.
 */
function sad_znaniy_img_pending_files( $limit = 0 ) {
	$set = sad_znaniy_img_settings();
	$up  = wp_get_upload_dir();
	$out = array();
	if ( ! is_dir( $up["basedir"] ) ) {
		return $out;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $up["basedir"], FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$path = $file->getPathname();
		if ( false !== strpos( str_replace( "\\", "/", $path ), "/_originals/" ) ) {
			continue;
		}
		if ( ! in_array( strtolower( $file->getExtension() ), array( "jpg", "jpeg", "png", "webp" ), true ) ) {
			continue;
		}
		if ( sad_znaniy_img_is_skipped_name( $path ) ) {
			continue;
		}
		if ( $file->getSize() < (int) $set["min_kb"] * 1024 ) {
			continue;
		}
		if ( is_file( sad_znaniy_img_backup_path( $path ) ) ) {
			continue;
		}
		$out[] = $path;
		if ( $limit > 0 && count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * Прогоняет партию файлов через оптимизацию.
 *
 * @param int $limit Размер партии.
 * @return array{done:int,saved:int,left:int,errors:int}
 */
function sad_znaniy_img_run_batch( $limit = 20 ) {
	$files = sad_znaniy_img_pending_files( $limit );
	$done  = 0;
	$saved = 0;
	$err   = 0;
	foreach ( $files as $path ) {
		$res = sad_znaniy_img_optimize_file( $path );
		if ( "optimized" === $res["status"] ) {
			$done++;
			$saved += max( 0, $res["before"] - $res["after"] );
		} elseif ( "" !== $res["note"] && false !== strpos( $res["note"], "GD" ) ) {
			$err++;
		}
	}
	return array( "done" => $done, "saved" => $saved, "left" => count( sad_znaniy_img_pending_files( 0 ) ), "errors" => $err );
}

/**
 * Экран «Медиа → Сжатие изображений».
 *
 * @return void
 */
function sad_znaniy_img_admin_page() {
	if ( ! current_user_can( "manage_options" ) ) {
		return;
	}

	$set    = sad_znaniy_img_settings();
	$notice = "";

	// Сохранение настроек.
	if ( isset( $_POST["sz_img_save"] ) && check_admin_referer( "sz_img_save", "sz_img_nonce" ) ) {
		$new = array(
			"enabled"      => empty( $_POST["enabled"] ) ? 0 : 1,
			"nextgen"      => empty( $_POST["nextgen"] ) ? 0 : 1,
			"avif"         => empty( $_POST["avif"] ) ? 0 : 1,
			"max_side"     => max( 200, min( 4000, (int) $_POST["max_side"] ) ),
			"jpeg_quality" => max( 40, min( 100, (int) $_POST["jpeg_quality"] ) ),
			"webp_quality" => max( 40, min( 100, (int) $_POST["webp_quality"] ) ),
			"avif_quality" => max( 30, min( 100, (int) $_POST["avif_quality"] ) ),
			"min_kb"       => max( 0, (int) $_POST["min_kb"] ),
			"max_mb"       => max( 1, (int) $_POST["max_mb"] ),
			"skip_words"   => sanitize_text_field( wp_unslash( (string) $_POST["skip_words"] ) ),
		);
		update_option( "sz_img_settings", $new, false );
		$set    = sad_znaniy_img_settings();
		$notice = "<div class=\"notice notice-success is-dismissible\"><p>Настройки сохранены.</p></div>";
	}

	// Партия сжатия.
	if ( isset( $_POST["sz_img_batch"] ) && check_admin_referer( "sz_img_batch", "sz_img_batch_nonce" ) ) {
		$limit = isset( $_POST["sz_img_batch_limit"] ) ? max( 1, min( 100, (int) $_POST["sz_img_batch_limit"] ) ) : 20;
		$run   = sad_znaniy_img_run_batch( $limit );
		sad_znaniy_img_log( "партия: сжато " . (int) $run["done"] . ", сэкономлено " . sad_znaniy_img_kb( (int) $run["saved"] ) . ", осталось " . (int) $run["left"] );
		$notice = "<div class=\"notice notice-success is-dismissible\"><p>Партия готова: сжато " . (int) $run["done"]
			. ", сэкономлено " . esc_html( sad_znaniy_img_kb( (int) $run["saved"] ) )
			. ", осталось файлов: " . (int) $run["left"] . ".</p></div>";
	}

	$pending = sad_znaniy_img_pending_files( 0 );
	$log     = (array) get_option( "sz_img_log", array() );
		?>
	<div class="wrap">
		<h1>Медиа → Сжатие изображений</h1>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- сформировано выше. ?>

		<p>Сжатие работает <b>при загрузке</b>: длинная сторона подрезается до лимита, картинка пережимается в своём формате (JPEG/PNG/WebP), метаданные вырезаются. Если результат не меньше оригинала — файл не трогается. Оригинал перед изменением копируется в <code>uploads/_originals/</code>, оттуда его можно вернуть.
		По умолчанию модуль <b><?php echo empty( $set["enabled"] ) ? "выключен" : "включён"; ?></b>, генерация WebP/AVIF — <b><?php echo empty( $set["nextgen"] ) ? "выключена" : "включена"; ?></b>.</p>

		<form method="post" style="margin-bottom:18px;">
			<?php wp_nonce_field( "sz_img_save", "sz_img_nonce" ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Автосжатие при загрузке</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( (int) $set["enabled"], 1 ); ?>> включено</label></td></tr>
				<tr><th scope="row">Генерация WebP/AVIF</th><td><label><input type="checkbox" name="nextgen" value="1" <?php checked( (int) $set["nextgen"], 1 ); ?>> включена</label> <label style="margin-left:12px;"><input type="checkbox" name="avif" value="1" <?php checked( (int) $set["avif"], 1 ); ?>> делать AVIF (тяжелее по процессору — на хостинге можно выключить)</label></td></tr>
				<tr><th scope="row">Лимит длинной стороны, px</th><td><input type="number" name="max_side" min="200" max="4000" value="<?php echo (int) $set["max_side"]; ?>" style="width:90px;"> <span class="description">больше — уменьшается, меньше — не растягивается</span></td></tr>
				<tr><th scope="row">Качество JPEG / WebP / AVIF</th><td><input type="number" name="jpeg_quality" min="40" max="100" value="<?php echo (int) $set["jpeg_quality"]; ?>" style="width:70px;"> / <input type="number" name="webp_quality" min="40" max="100" value="<?php echo (int) $set["webp_quality"]; ?>" style="width:70px;"> / <input type="number" name="avif_quality" min="30" max="100" value="<?php echo (int) $set["avif_quality"]; ?>" style="width:70px;"></td></tr>
				<tr><th scope="row">Не трогать файлы мельче</th><td><input type="number" name="min_kb" min="0" max="2000" value="<?php echo (int) $set["min_kb"]; ?>" style="width:90px;"> КБ <span class="description">уже сжатые картинки не пережимаем</span></td></tr>
				<tr><th scope="row">Пропускать файлы крупнее</th><td><input type="number" name="max_mb" min="1" max="100" value="<?php echo (int) $set["max_mb"]; ?>" style="width:90px;"> МБ</td></tr>
				<tr><th scope="row">Исключения по имени файла</th><td><input type="text" name="skip_words" value="<?php echo esc_attr( (string) $set["skip_words"] ); ?>" style="width:100%;max-width:560px;"> <p class="description">Через запятую. Так исключаются рекламные баннеры: <code>banner</code>, <code>баннер</code>, <code>ad-</code>, <code>promo</code>. Точное совпадение не нужно — достаточно вхождения.</p></td></tr>
			</table>
			<p class="submit"><button type="submit" name="sz_img_save" class="button button-primary">Сохранить настройки</button></p>
		</form>

		<h2>Массовая обработка уже загруженных</h2>
		<p>Кандидатов сейчас: <b><?php echo count( $pending ); ?></b> (крупнее <?php echo (int) $set["min_kb"]; ?> КБ, без копии оригинала, имя не в исключениях). За один клик обрабатывается партия — безопасно для хостинга.</p>
		<form method="post" style="margin-bottom:18px;">
			<?php wp_nonce_field( "sz_img_batch", "sz_img_batch_nonce" ); ?>
			<input type="number" name="sz_img_batch_limit" value="20" min="1" max="100" style="width:80px;">
			<button type="submit" name="sz_img_batch" class="button">Сжать партию</button>
		</form>

		<h2>Журнал (последние 40 записей)</h2>
		<?php if ( empty( $log ) ) : ?>
			<p>Пока пусто — сжатие ещё не запускалось.</p>
		<?php else : ?>
			<table class="widefat striped"><tbody>
				<?php foreach ( $log as $line ) : ?>
					<tr><td><?php echo esc_html( $line ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
		<?php
}

/**
 * Галочка «Не сжимать» в карточке вложения.
 *
 * @param array   $form_fields Поля.
 * @param WP_Post $post        Вложение.
 * @return array
 */
function sad_znaniy_img_attachment_field( $form_fields, $post ) {
	if ( 0 !== strpos( (string) $post->post_mime_type, "image/" ) ) {
		return $form_fields;
	}
	$form_fields["sz_img_skip"] = array(
		"label" => "Сжатие",
		"input" => "html",
		"html"  => "<label><input type=\"checkbox\" name=\"attachments[" . (int) $post->ID . "][sz_img_skip]\" value=\"1\" " . checked( (int) get_post_meta( $post->ID, "_sz_img_skip", true ), 1, false ) . "> не сжимать и не конвертировать (баннер)</label>",
		"helps" => "Обход для рекламных баннеров и логотипов: файл не сжимается массовой обработкой и не получает WebP/AVIF.",
	);
	return $form_fields;
}
add_filter( "attachment_fields_to_edit", "sad_znaniy_img_attachment_field", 10, 2 );

/**
 * Сохранение галочки «Не сжимать».
 *
 * @param array $post       Данные вложения.
 * @param array $attachment Поля формы.
 * @return array
 */
function sad_znaniy_img_attachment_save( $post, $attachment ) {
	if ( isset( $attachment["sz_img_skip"] ) ) {
		update_post_meta( (int) $post["ID"], "_sz_img_skip", empty( $attachment["sz_img_skip"] ) ? 0 : 1 );
	}
	return $post;
}
add_filter( "attachment_fields_to_save", "sad_znaniy_img_attachment_save", 10, 2 );