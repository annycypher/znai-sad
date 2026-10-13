<?php
/**
 * ОДНОРАЗОВЫЙ импорт выгрузок Вордстата целой папкой (ключи SEO-Хаба).
 *
 * Папка → все *.csv → та же логика, что у экрана «📊 SEO-Хаб → Ключи»:
 *   • разделитель определяется по первой непустой строке («;», таб, «,»);
 *   • строка-заголовок («Запросы со словами;…») пропускается;
 *   • частота — максимум цифр в строке, битые частоты (> 1 000 000) отсеиваются;
 *   • фраза нормализуется (нижний регистр, сжатие пробелов, обрезка до 191);
 *   • дедуп по фразе: частота = GREATEST(старая, новая), дата обновления — сегодня.
 * В конце — один пересчёт авторазметки и запись в журнал хаба.
 * Идемпотентно: повторный запуск добавляет 0 фраз.
 *
 * Запуск через wp-cli:
 *   wp eval-file _tools/import-wordstat.php dry
 *   wp eval-file _tools/import-wordstat.php
 *   wp eval-file _tools/import-wordstat.php "C:/путь/к/папке"
 *
 * Запуск без wp-cli (PHP LocalWP; MYSQL-порт — из sites.json Local, здесь 10005):
 *   php.exe -d extension_dir="<php>/ext" -d extension=php_mysqli.dll \
 *     -d extension=php_mbstring.dll -d mysqli.default_port=10005 \
 *     _tools/import-wordstat.php dry
 *
 * Ключ «dry» — только отчёт, без записи в базу.
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
require dirname( __DIR__, 4 ) . "/wp-load.php"; // Запуск из CLI без wp-cli.
}

if ( ! function_exists( "sad_znaniy_seo_keys_import" ) ) {
echo "Тема sad-znaniy не активна — импорт невозможен.\n";
return;
}

$sz_args = array();
if ( isset( $args ) && is_array( $args ) ) {
$sz_args = $args;                          // wp eval-file: дополнительные аргументы.
} elseif ( isset( $argv ) && is_array( $argv ) ) {
$sz_args = array_slice( $argv, 1 );        // php CLI: без имени файла скрипта.
}

$sz_dir = "C:/Users/krs3d/Local Sites/sadznaniy/вордстат гарден";
$sz_dry = false;
foreach ( $sz_args as $sz_arg ) {
$sz_arg = trim( (string) $sz_arg );
if ( "dry" === $sz_arg || "--dry" === $sz_arg ) {
$sz_dry = true;
} elseif ( "" !== $sz_arg && is_dir( $sz_arg ) ) {
$sz_dir = $sz_arg;
}
}

if ( ! is_dir( $sz_dir ) ) {
echo "Папка не найдена: " . $sz_dir . "\n";
return;
}

if ( ! class_exists( "RecursiveDirectoryIterator" ) ) {
echo "Нет расширения SPL — импорт невозможен.\n";
return;
}

global $wpdb;
$sz_tables = sad_znaniy_seo_tables();
$sz_table  = $sz_tables["keys"];

$sz_files = 0;
$sz_rows  = 0;
$sz_skip  = 0;
$sz_dup   = 0;
$sz_uniq  = array();

$sz_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sz_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $sz_it as $sz_file ) {
if ( ! $sz_file->isFile() || "csv" !== strtolower( (string) $sz_file->getExtension() ) ) {
continue;
}

$sz_files++;
$sz_text = (string) file_get_contents( $sz_file->getPathname() );
$sz_text = str_replace( array( "\r\n", "\r" ), "\n", $sz_text );
$sz_text = str_replace( "\xEF\xBB\xBF", "", $sz_text );

$sz_delim = "";
foreach ( preg_split( "/\n/", $sz_text ) as $sz_line ) {
$sz_line = trim( (string) $sz_line );
if ( "" === $sz_line ) {
continue;
}

if ( "" === $sz_delim ) {
if ( substr_count( $sz_line, ";" ) >= 1 ) {
$sz_delim = ";";
} elseif ( substr_count( $sz_line, "\t" ) >= 1 ) {
$sz_delim = "\t";
} else {
$sz_delim = ",";
}
}

$sz_rows++;
$sz_cells = array_map( "trim", (array) str_getcsv( $sz_line, $sz_delim, chr( 34 ) ) );

$sz_phrase = isset( $sz_cells[0] ) ? (string) $sz_cells[0] : "";
if ( "" === $sz_phrase || preg_match( "/^(фраза|phrase|запрос|слово|ключ|word)/ui", $sz_phrase ) ) {
$sz_skip++;
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
$sz_skip++;
continue;
}

$sz_phrase = mb_strtolower( preg_replace( "/\s+/u", " ", $sz_phrase ), "UTF-8" );
if ( mb_strlen( $sz_phrase, "UTF-8" ) > 191 ) {
$sz_phrase = mb_substr( $sz_phrase, 0, 191, "UTF-8" );
}

if ( isset( $sz_uniq[ $sz_phrase ] ) ) {
$sz_dup++;
$sz_uniq[ $sz_phrase ] = max( $sz_uniq[ $sz_phrase ], $sz_freq );
} else {
$sz_uniq[ $sz_phrase ] = $sz_freq;
}
}
}

echo "Папка: " . $sz_dir . "\n";
echo "Файлов: $sz_files, строк: $sz_rows, пропущено: $sz_skip, дублей внутри папки: $sz_dup, уникальных фраз: " . count( $sz_uniq ) . "\n";

// Что уже есть в базе — для честных счётчиков «добавлено / обновлено».
$sz_existing = array();
foreach ( $wpdb->get_results( "SELECT phrase, freq FROM {$sz_table}" ) as $sz_row ) { // phpcs:ignore WordPress.DB
$sz_existing[ $sz_row->phrase ] = (int) $sz_row->freq;
}

$sz_add  = 0;
$sz_upd  = 0;
$sz_same = 0;
foreach ( $sz_uniq as $sz_phrase => $sz_freq ) {
if ( ! isset( $sz_existing[ $sz_phrase ] ) ) {
$sz_add++;
} elseif ( $sz_freq > $sz_existing[ $sz_phrase ] ) {
$sz_upd++;
} else {
$sz_same++;
}
}
echo "Новых: $sz_add, частота вырастет у: $sz_upd, без изменений: $sz_same (было в базе: " . count( $sz_existing ) . ")\n";

if ( ! $sz_dry ) {
$sz_created = current_time( "mysql" );
$sz_chunks  = 0;
foreach ( array_chunk( $sz_uniq, 500, true ) as $sz_part ) {
$sz_rows_sql = array();
$sz_values   = array();
foreach ( $sz_part as $sz_phrase => $sz_freq ) {
$sz_rows_sql[] = "(%s, %d, %s)";
$sz_values[]   = $sz_phrase;
$sz_values[]   = $sz_freq;
$sz_values[]   = $sz_created;
}
$sz_sql = "INSERT INTO {$sz_table} (phrase, freq, created) VALUES " . implode( ", ", $sz_rows_sql )
. " ON DUPLICATE KEY UPDATE freq = GREATEST(freq, VALUES(freq)), created = VALUES(created)";
$wpdb->query( $wpdb->prepare( $sz_sql, $sz_values ) ); // phpcs:ignore WordPress.DB
$sz_chunks++;
}
echo "Записано фраз: " . count( $sz_uniq ) . " (пачек: $sz_chunks)\n";

sad_znaniy_seo_recalc();
sad_znaniy_seo_log( "Импорт ключей папкой: +" . $sz_add . " / ~" . $sz_upd );
}

// Итоговый отчёт по таблице ключей.
$sz_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sz_table}" ); // phpcs:ignore WordPress.DB
$sz_thr   = sad_znaniy_seo_weak_threshold();

echo "\n=== ОТЧЁТ ПО БАЗЕ" . ( $sz_dry ? " (DRY — база не менялась)" : "" ) . " ===\n";
echo "Всего фраз: $sz_total | порог «слабой»: $sz_thr | частота < 10: " . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sz_table} WHERE freq < 10" ) . "\n"; // phpcs:ignore WordPress.DB
foreach ( $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$sz_table} GROUP BY status ORDER BY c DESC" ) as $sz_row ) { // phpcs:ignore WordPress.DB
echo "  " . $sz_row->status . ": " . $sz_row->c . "\n";
}
echo "Страниц с «живыми» фразами: " . (int) $wpdb->get_var( "SELECT COUNT(DISTINCT matched_url) FROM {$sz_table} WHERE status = 'live'" ) . "\n"; // phpcs:ignore WordPress.DB

echo "\nТОП-20 фраз по частоте:\n";
foreach ( $wpdb->get_results( "SELECT phrase, freq, status, matched_url FROM {$sz_table} ORDER BY freq DESC LIMIT 20" ) as $sz_row ) { // phpcs:ignore WordPress.DB
echo "  " . $sz_row->freq . " | " . $sz_row->phrase . " | " . $sz_row->status . " | " . $sz_row->matched_url . "\n";
}