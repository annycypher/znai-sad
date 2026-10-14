<?php

// Только CLI: скрипты разработки не исполняются из браузера (Этап 7.3, S2).
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'Скрипт запускается только из командной строки.' );
}
/**
 * ОДНОРАЗОВЫЙ клин-скрипт Вордстата: обнулить ключи и переимпортировать ТОЛЬКО чистые файлы.
 *
 * Что делает:
 *   1) читает папку выгрузок (по умолчанию «вордстат гарден») и классифицирует файлы:
 *      ШУМНЫЙ = ≥300 уникальных фраз и полезность (live+ready+related) < 5 %;
 *   2) dry — только отчёт, ничего не пишет;
 *   3) confirm — TRUNCATE таблицы ключей → импорт чистых файлов (дедуп по фразе,
 *      частота = GREATEST) → один пересчёт авторазметки со стоп-листом → отчёт
 *      (было/стало/живых/готовых/привязано/слабых/junk/manual + топ-30 «живых»).
 *
 * Запуск (без wp-cli, PHP LocalWP; порт MySQL — MYSQL из sites.json Local):
 *   php.exe -d extension_dir="<php>/ext" -d extension=php_mysqli.dll -d extension=php_mbstring.dll -d mysqli.default_port=10005 _tools/clean-wordstat.php dry
 *   ... _tools/clean-wordstat.php confirm
 *
 * ПЕРЕД РЕАЛЬНЫМ ПРОГОНОМ СДЕЛАЙТЕ ДАМП БД (mysqldump ... --result-file=...).
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
require dirname( __DIR__, 4 ) . "/wp-load.php";
}

if ( ! function_exists( "sad_znaniy_seo_match" ) ) {
echo "Тема sad-znaniy не активна — чистка невозможна.\n";
return;
}

$sz_args = array();
if ( isset( $args ) && is_array( $args ) ) {
$sz_args = $args;
} elseif ( isset( $argv ) && is_array( $argv ) ) {
$sz_args = array_slice( $argv, 1 );
}

$sz_dir     = "C:/Users/krs3d/Local Sites/sadznaniy/вордстат гарден";
$sz_dry     = false;
$sz_confirm = false;
foreach ( $sz_args as $sz_arg ) {
$sz_arg = trim( (string) $sz_arg );
if ( "dry" === $sz_arg ) {
$sz_dry = true;
} elseif ( "confirm" === $sz_arg ) {
$sz_confirm = true;
} elseif ( "" !== $sz_arg && is_dir( $sz_arg ) ) {
$sz_dir = $sz_arg;
}
}

if ( ! $sz_dry && ! $sz_confirm ) {
echo "Нужен аргумент: dry (отчёт) или confirm (чистка).\n";
return;
}

if ( ! is_dir( $sz_dir ) ) {
echo "Папка не найдена: " . $sz_dir . "\n";
return;
}

global $wpdb;
$sz_tables = sad_znaniy_seo_tables();
$sz_table  = $sz_tables["keys"];

// Текущие статусы — по ним считаем полезность файла (без повторной разметки).
$sz_status = array();
foreach ( $wpdb->get_results( "SELECT phrase, status FROM {$sz_table}" ) as $sz_row ) { // phpcs:ignore WordPress.DB
$sz_status[ $sz_row->phrase ] = (string) $sz_row->status;
}

$sz_before = array( "live"=>0, "ready"=>0, "related"=>0, "weak"=>0, "junk"=>0, "manual"=>0 );
foreach ( $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$sz_table} GROUP BY status" ) as $sz_row ) { // phpcs:ignore WordPress.DB
$sz_before[ $sz_row->status ] = (int) $sz_row->c;
}
$sz_before_total = array_sum( $sz_before );

$sz_clean = array();
$sz_noisy = array();
$sz_uniq  = array();
$sz_files = 0;

$sz_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sz_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $sz_it as $sz_file ) {
if ( ! $sz_file->isFile() || "csv" !== strtolower( (string) $sz_file->getExtension() ) ) {
continue;
}
$sz_files++;

$sz_text = (string) file_get_contents( $sz_file->getPathname() );
$sz_text = str_replace( array( "\r\n", "\r" ), "\n", $sz_text );
$sz_text = str_replace( "\xEF\xBB\xBF", "", $sz_text );
$sz_lines = preg_split( "/\n/", $sz_text );

$sz_src = "?";
if ( preg_match( "/«(.+?)»/u", (string) $sz_lines[0], $sz_m ) ) {
$sz_src = $sz_m[1];
}

$sz_delim = ";";
$sz_uniq_f = array();
foreach ( $sz_lines as $sz_i => $sz_line ) {
if ( 0 === $sz_i ) {
continue;
}
$sz_line = trim( (string) $sz_line );
if ( "" === $sz_line ) {
continue;
}
$sz_cells = array_map( "trim", (array) str_getcsv( $sz_line, $sz_delim, chr( 34 ) ) );
$sz_phrase = isset( $sz_cells[0] ) ? (string) $sz_cells[0] : "";
if ( "" === $sz_phrase ) {
continue;
}
$sz_freq = 0;
foreach ( $sz_cells as $sz_cell ) {
$sz_digits = preg_replace( "/[^\d]/", "", (string) $sz_cell );
if ( "" !== $sz_digits ) {
$sz_freq = max( $sz_freq, (int) $sz_digits );
}
}
if ( $sz_freq > 1000000 || $sz_freq < 1 ) {
continue;
}
$sz_p = mb_strtolower( preg_replace( "/\s+/u", " ", $sz_phrase ), "UTF-8" );
if ( mb_strlen( $sz_p, "UTF-8" ) > 191 ) {
$sz_p = mb_substr( $sz_p, 0, 191, "UTF-8" );
}
$sz_uniq_f[ $sz_p ] = max( isset( $sz_uniq_f[ $sz_p ] ) ? $sz_uniq_f[ $sz_p ] : 0, $sz_freq );
}

$sz_n     = count( $sz_uniq_f );
$sz_useful = 0;
foreach ( array_keys( $sz_uniq_f ) as $sz_p ) {
$sz_st = isset( $sz_status[ $sz_p ] ) ? $sz_status[ $sz_p ] : "";
if ( "live" === $sz_st || "ready" === $sz_st || "related" === $sz_st ) {
$sz_useful++;
}
}
$sz_share = $sz_n > 0 ? (int) round( 100 * $sz_useful / $sz_n ) : 0;

$sz_rec = array(
"folder" => basename( dirname( $sz_file->getPathname() ) ),
"name"   => $sz_file->getFilename(),
"src"    => $sz_src,
"uniq"   => $sz_n,
"useful" => $sz_useful,
"share"  => $sz_share,
);

if ( $sz_n >= 300 && $sz_share < 5 ) {
$sz_noisy[] = $sz_rec;
continue;
}

$sz_clean[] = $sz_rec;
foreach ( $sz_uniq_f as $sz_p => $sz_freq ) {
$sz_uniq[ $sz_p ] = max( isset( $sz_uniq[ $sz_p ] ) ? $sz_uniq[ $sz_p ] : 0, $sz_freq );
}
}

$sz_report = "КЛИН-ИМПОРТ ВОРДСТАТА (" . ( $sz_dry ? "DRY — база не менялась" : "боевой прогон" ) . ")\n";
$sz_report .= "Папка: " . $sz_dir . "\nФайлов: " . $sz_files . ", чистых: " . count( $sz_clean ) . ", шумных: " . count( $sz_noisy ) . "\n";
$sz_report .= "Фраз к импорту: " . count( $sz_uniq ) . "\n\nИСКЛЮЧЕНО (шумные файлы):\n";
foreach ( $sz_noisy as $sz_r ) {
$sz_report .= sprintf( "  %-9s | %-30s | %-32s | фраз %5d | полезных %4d (%2d%%)\n", $sz_r["folder"], $sz_r["name"], $sz_r["src"], $sz_r["uniq"], $sz_r["useful"], $sz_r["share"] );
}

if ( $sz_dry ) {
$sz_report .= "\nБЫЛО: всего " . $sz_before_total . " | ";
foreach ( $sz_before as $sz_st => $sz_c ) {
$sz_report .= $sz_st . " " . $sz_c . " | ";
}
$sz_report .= "\n";
} else {
$wpdb->query( "TRUNCATE TABLE {$sz_table}" ); // phpcs:ignore WordPress.DB

$sz_created = current_time( "mysql" );
foreach ( array_chunk( $sz_uniq, 500, true ) as $sz_part ) {
$sz_rows_sql = array();
$sz_values   = array();
foreach ( $sz_part as $sz_p => $sz_freq ) {
$sz_rows_sql[] = "(%s, %d, %s)";
$sz_values[]   = $sz_p;
$sz_values[]   = $sz_freq;
$sz_values[]   = $sz_created;
}
$sz_sql = "INSERT INTO {$sz_table} (phrase, freq, created) VALUES " . implode( ", ", $sz_rows_sql )
. " ON DUPLICATE KEY UPDATE freq = GREATEST(freq, VALUES(freq)), created = VALUES(created)";
$wpdb->query( $wpdb->prepare( $sz_sql, $sz_values ) ); // phpcs:ignore WordPress.DB
}

sad_znaniy_seo_recalc();
sad_znaniy_seo_log( "Клин-импорт ключей: файлов " . count( $sz_clean ) . ", фраз " . count( $sz_uniq ) );

$sz_after = array();
foreach ( $wpdb->get_results( "SELECT status, COUNT(*) c, COALESCE(SUM(freq),0) s FROM {$sz_table} GROUP BY status ORDER BY c DESC" ) as $sz_row ) { // phpcs:ignore WordPress.DB
$sz_after[ $sz_row->status ] = array( (int) $sz_row->c, (int) $sz_row->s );
}
$sz_after_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sz_table}" ); // phpcs:ignore WordPress.DB

$sz_report .= "\nБЫЛО: всего " . $sz_before_total . " (" . implode( ", ", array_map( static function ( $kk, $vv ) { return $kk . " " . $vv; }, array_keys( $sz_before ), $sz_before ) ) . ")\n";
$sz_report .= "СТАЛО: всего " . $sz_after_total . "\n";
foreach ( $sz_after as $sz_st => $sz_v ) {
$sz_report .= "  " . $sz_st . ": " . $sz_v[0] . " фраз / " . $sz_v[1] . " показов\n";
}
$sz_report .= "\nТОП-30 «ЖИВЫХ» ФРАЗ ПО ЧАСТОТЕ (цель — включённая страница):\n";
foreach ( $wpdb->get_results( "SELECT phrase, freq, matched_url FROM {$sz_table} WHERE status = 'live' ORDER BY freq DESC LIMIT 30" ) as $sz_row ) { // phpcs:ignore WordPress.DB
$sz_report .= "  " . $sz_row->freq . " | " . $sz_row->phrase . " | " . $sz_row->matched_url . "\n";
}
$sz_report .= "\nТОП-15 «ГОТОВЫХ» (данные есть, страница выключена):\n";
foreach ( $wpdb->get_results( "SELECT phrase, freq, matched_url FROM {$sz_table} WHERE status = 'ready' ORDER BY freq DESC LIMIT 15" ) as $sz_row ) { // phpcs:ignore WordPress.DB
$sz_report .= "  " . $sz_row->freq . " | " . $sz_row->phrase . " | " . $sz_row->matched_url . "\n";
}
}

$sz_report_file = sys_get_temp_dir() . "/clean-wordstat-report.txt";
file_put_contents( $sz_report_file, $sz_report );
echo "files=" . $sz_files . " clean=" . count( $sz_clean ) . " noisy=" . count( $sz_noisy ) . " uniq=" . count( $sz_uniq ) . "\n";
echo "report: " . $sz_report_file . "\n";
