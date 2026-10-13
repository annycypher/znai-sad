<?php
/**
 * КЭШ СТРАНИЦ (свой, без плагинов) — подготовка к боевому трафику.
 *
 * Как работает:
 *   • при GET-запросе анонимного посетителя HTML страницы сохраняется файлом
 *     в wp-content/cache/sz/ и отдаётся следующим посетителям без запуска
 *     генерации (счётчик визитов при этом работает: он висит на том же хуке
 *     template_redirect с приоритетом 5, а кэш — с приоритетом 20);
 *   • ключ кэша = адрес с параметрами + регион из cookie (регион меняет разметку);
 *   • не кэшируются: админка, REST/AJAX, залогиненные, черновик-предпросмотр,
 *     404, поиск, sitemap/robots/feed/xml, POST-запросы;
 *   • обход для отладки: добавьте ?nocache=1 к адресу;
 *   • сброс: любое сохранение записи, правка меню, смена темы, сохранение настроек,
 *     команда «Очистить кэш» на экране «Инструменты → Кэш страниц»;
 *   • по возрасту файлы устаревают сами (TTL, по умолчанию 12 часов).
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

/**
 * Настройки кэша.
 *
 * @return array
 */
function sad_znaniy_cache_settings() {
	static $set = null;
	if ( null !== $set ) {
		return $set;
	}
	$set = wp_parse_args(
		(array) get_option( "sz_cache_settings", array() ),
		array(
			"enabled"   => 1,
			"ttl"       => 12,
			"logged_in" => 0,
		)
	);
	return $set;
}

/**
 * Папка кэша (создаётся при первой записи).
 *
 * @return string
 */
function sad_znaniy_cache_dir() {
	return WP_CONTENT_DIR . "/cache/sz";
}

/**
 * Файл кэша для текущего запроса.
 *
 * @return string
 */
function sad_znaniy_cache_file() {
	$uri    = isset( $_SERVER["REQUEST_URI"] ) ? (string) $_SERVER["REQUEST_URI"] : "/"; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$region = isset( $_COOKIE["sz_region"] ) ? sanitize_key( wp_unslash( (string) $_COOKIE["sz_region"] ) ) : "";
	$key    = md5( $uri . "|" . $region . "|" . ( is_ssl() ? "https" : "http" ) );
	return sad_znaniy_cache_dir() . "/" . substr( $key, 0, 2 ) . "/" . $key . ".html";
}

/**
 * Можно ли кэшировать текущий запрос.
 *
 * @return bool
 */
function sad_znaniy_cache_can() {
	$set = sad_znaniy_cache_settings();
	if ( empty( $set["enabled"] ) ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || ( defined( "REST_REQUEST" ) && REST_REQUEST ) || ( defined( "DOING_CRON" ) && DOING_CRON ) || ( defined( "WP_CLI" ) && WP_CLI ) ) {
		return false;
	}
	if ( "GET" !== strtoupper( isset( $_SERVER["REQUEST_METHOD"] ) ? (string) $_SERVER["REQUEST_METHOD"] : "GET" ) ) {
		return false;
	}
	if ( is_user_logged_in() && empty( $set["logged_in"] ) ) {
		return false;
	}
	if ( is_preview() || is_404() || is_search() || is_feed() || is_trackback() || is_robots() || is_customize_preview() ) {
		return false;
	}
	if ( isset( $_GET["nocache"] ) || isset( $_COOKIE["sz_nocache"] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- служебный обход кэша.
		return false;
	}
	if ( defined( "DONOTCACHEPAGE" ) && DONOTCACHEPAGE ) {
		return false;
	}
	$uri = isset( $_SERVER["REQUEST_URI"] ) ? (string) $_SERVER["REQUEST_URI"] : ""; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( preg_match( "#(wp-sitemap|sitemap\.xml|robots\.txt|/feed|\.xml|wp-json|/wp-admin|admin-ajax)#i", $uri ) ) {
		return false;
	}
	return true;
}

/**
 * Отдаёт страницу из кэша либо включает запись буфера.
 *
 * @return void
 */
function sad_znaniy_cache_serve() {
	if ( ! sad_znaniy_cache_can() ) {
		return;
	}
	$file = sad_znaniy_cache_file();
	$ttl  = max( 1, (int) sad_znaniy_cache_settings()["ttl"] ) * HOUR_IN_SECONDS;

	if ( is_file( $file ) && ( time() - (int) filemtime( $file ) ) <= $ttl ) {
		header( "X-SZ-Cache: HIT" );
		header( "Content-Type: text/html; charset=UTF-8" );
		readfile( $file );
		exit;
	}
	if ( is_file( $file ) ) {
		@unlink( $file );
	}
	header( "X-SZ-Cache: MISS" );
	ob_start( "sad_znaniy_cache_store" );
}
add_action( "template_redirect", "sad_znaniy_cache_serve", 20 );

/**
 * Сохраняет готовую страницу в файл кэша.
 *
 * @param string $html Готовый HTML.
 * @return string
 */
function sad_znaniy_cache_store( $html ) {
	if ( ! is_string( $html ) || strlen( $html ) < 800 ) {
		return $html;
	}
	if ( false === strpos( $html, "</html>" ) ) {
		return $html; // буфер отдан частями — не сохраняем обрезанное
	}
	if ( ! sad_znaniy_cache_can() ) {
		return $html;
	}
	$file = sad_znaniy_cache_file();
	wp_mkdir_p( dirname( $file ) );
	sad_znaniy_cache_guard( dirname( $file ) );
	$tmp = $file . ".tmp";
	if ( false !== file_put_contents( $tmp, $html ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		@rename( $tmp, $file );
	}
	return $html;
}

/**
 * Ставит в папку кэша заглушки, чтобы файлы не открывались по прямому адресу.
 *
 * @param string $dir Папка.
 * @return void
 */
function sad_znaniy_cache_guard( $dir ) {
	$root = sad_znaniy_cache_dir();
	if ( ! is_dir( $root ) ) {
		wp_mkdir_p( $root );
	}
	if ( ! is_file( $root . "/index.php" ) ) {
		file_put_contents( $root . "/index.php", "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( ! is_file( $root . "/.htaccess" ) ) {
		file_put_contents( $root . "/.htaccess", "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	unset( $dir );
}
/* === Сброс и обслуживание ================================================ */

/**
 * Удаляет все файлы кэша.
 *
 * @return int Сколько файлов удалено.
 */
function sad_znaniy_cache_flush() {
	$dir = sad_znaniy_cache_dir();
	if ( ! is_dir( $dir ) ) {
		return 0;
	}
	$count = 0;
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $file ) {
		if ( $file->isFile() && "html" === strtolower( $file->getExtension() ) ) {
			if ( @unlink( $file->getPathname() ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$count++;
			}
		}
	}
	return $count;
}

/**
 * Статистика кэша.
 *
 * @return array{files:int,bytes:int,newest:int}
 */
function sad_znaniy_cache_stats() {
	$dir = sad_znaniy_cache_dir();
	$out = array( "files" => 0, "bytes" => 0, "newest" => 0 );
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( ! $file->isFile() || "html" !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$out["files"]++;
		$out["bytes"] += (int) $file->getSize();
		$out["newest"] = max( $out["newest"], (int) $file->getMTime() );
	}
	return $out;
}

// Сброс кэша при любом изменении контента, меню, темы и настроек.
foreach ( array( "save_post", "deleted_post", "trashed_post", "untrashed_post", "wp_update_nav_menu", "switch_theme", "customize_save_after", "wp_insert_comment", "edit_comment", "deleted_comment" ) as $sz_hook ) {
	add_action( $sz_hook, "sad_znaniy_cache_flush" );
}
foreach ( array( "sad_znaniy_options", "sidebars_widgets", "sz_cache_settings", "sz_img_settings", "sad_znaniy_programmatic_pages" ) as $sz_opt ) {
	add_action( "update_option_" . $sz_opt, "sad_znaniy_cache_flush" );
}

/**
 * Суточная уборка устаревших файлов.
 *
 * @return void
 */
function sad_znaniy_cache_purge() {
	$dir = sad_znaniy_cache_dir();
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$ttl = max( 1, (int) sad_znaniy_cache_settings()["ttl"] ) * HOUR_IN_SECONDS * 2;
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( $file->isFile() && "html" === strtolower( $file->getExtension() ) && ( time() - (int) $file->getMTime() ) > $ttl ) {
			@unlink( $file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
}
add_action( "sz_cache_purge", "sad_znaniy_cache_purge" );

/**
 * Ставит суточную задачу уборки.
 *
 * @return void
 */
function sad_znaniy_cache_schedule() {
	if ( ! wp_next_scheduled( "sz_cache_purge" ) ) {
		wp_schedule_event( time() + 3600, "daily", "sz_cache_purge" );
	}
}
add_action( "init", "sad_znaniy_cache_schedule" );

/* === Админ-экран ========================================================= */

/**
 * Пункт меню «Инструменты → Кэш страниц».
 *
 * @return void
 */
function sad_znaniy_cache_menu() {
	add_management_page( "Кэш страниц", "Кэш страниц", "manage_options", "sad-page-cache", "sad_znaniy_cache_page" );
}
add_action( "admin_menu", "sad_znaniy_cache_menu" );

/**
 * Экран «Инструменты → Кэш страниц».
 *
 * @return void
 */
function sad_znaniy_cache_page() {
	if ( ! current_user_can( "manage_options" ) ) {
		return;
	}
	$notice = "";

	if ( isset( $_POST["sz_cache_save"] ) && check_admin_referer( "sz_cache_save", "sz_cache_nonce" ) ) {
		update_option(
			"sz_cache_settings",
			array(
				"enabled"   => empty( $_POST["enabled"] ) ? 0 : 1,
				"ttl"       => max( 1, min( 168, (int) $_POST["ttl"] ) ),
				"logged_in" => empty( $_POST["logged_in"] ) ? 0 : 1,
			),
			false
		);
		sad_znaniy_cache_flush();
		$notice = "<div class=\"notice notice-success is-dismissible\"><p>Настройки сохранены, кэш очищен.</p></div>";
	}

	if ( isset( $_POST["sz_cache_flush"] ) && check_admin_referer( "sz_cache_flush", "sz_cache_flush_nonce" ) ) {
		$removed = sad_znaniy_cache_flush();
		$notice  = "<div class=\"notice notice-success is-dismissible\"><p>Кэш очищен: удалено файлов — " . (int) $removed . ".</p></div>";
	}

	$set = sad_znaniy_cache_settings();
	$st  = sad_znaniy_cache_stats();
		?>
	<div class="wrap">
		<h1>Кэш страниц</h1>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- сформировано выше. ?>

		<p>Кэш сохраняет готовый HTML страницы и отдаёт его следующим посетителям без повторной генерации. Нужен для трафика: без кэша каждый просмотр — это запуск PHP и запросы к базе, а тариф хостинга ограничивает нагрузку. Состояние: <b><?php echo empty( $set["enabled"] ) ? "выключен" : "включён"; ?></b>.</p>

		<p><b>Файлов в кэше:</b> <?php echo (int) $st["files"]; ?> · <b>объём:</b> <?php echo esc_html( size_format( (int) $st["bytes"], 1 ) ); ?> · <b>последний сохранённый:</b> <?php echo $st["newest"] ? esc_html( date_i18n( "d.m.Y H:i", $st["newest"] ) ) : "—"; ?><br>
		<span class="description">Залогиненные пользователи, админка, REST/AJAX, поиск, sitemap и 404 не кэшируются. Проверка попадания: откройте страницу и посмотрите в DevTools → Network заголовок <code>X-SZ-Cache: HIT</code> (первый заход — <code>MISS</code>). Обход кэша вручную: добавьте <code>?nocache=1</code> к адресу.</span></p>

		<form method="post" style="margin:14px 0;">
			<?php wp_nonce_field( "sz_cache_save", "sz_cache_nonce" ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Кэш страниц</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( (int) $set["enabled"], 1 ); ?>> включён</label></td></tr>
				<tr><th scope="row">Время жизни, часов</th><td><input type="number" name="ttl" min="1" max="168" value="<?php echo (int) $set["ttl"]; ?>" style="width:90px;"> <span class="description">после этого файл пересобирается при следующем заходе</span></td></tr>
				<tr><th scope="row">Залогиненные</th><td><label><input type="checkbox" name="logged_in" value="1" <?php checked( (int) $set["logged_in"], 1 ); ?>> отдавать кэш и им (администраторы видят «замороженную» страницу — по умолчанию выключено)</label></td></tr>
			</table>
			<p class="submit"><button type="submit" name="sz_cache_save" class="button button-primary">Сохранить</button></p>
		</form>

		<form method="post">
			<?php wp_nonce_field( "sz_cache_flush", "sz_cache_flush_nonce" ); ?>
			<p><button type="submit" name="sz_cache_flush" class="button">Очистить кэш сейчас</button> <span class="description">сброс также происходит автоматически при сохранении записей, правке меню и сохранении настроек</span></p>
		</form>
	</div>
		<?php
}