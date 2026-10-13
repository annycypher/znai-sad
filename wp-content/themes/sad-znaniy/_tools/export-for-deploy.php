<?php
/**
 * ЭКСПОРТ ДЛЯ ПЕРЕНОСА (Этап 8, партия 8.0): дамп БД + архив wp-content + контрольные суммы.
 *
 * Одной командой собирает в папку deploy (вне веб-рута и вне git):
 *   db-YYYY-MM-DD.sql          — полный дамп базы (mysqldump, один транзакционный снимок);
 *   wp-content-YYYY-MM-DD.zip   — тема и вложения (мусор исключён);
 *   SHA256SUMS.txt             — контрольные суммы артефактов;
 *   deploy-README.txt          — что это и что с этим делать на хостинге.
 *
 * Запуск (PHP LocalWP; порт MySQL — MYSQL из sites.json Local, по умолчанию 10005):
 *   php.exe -d extension_dir="<php>/ext" -d extension=php_zip.dll -d extension=php_mbstring.dll _tools/export-for-deploy.php
 *   ... _tools/export-for-deploy.php --port=10005 --out="C:/путь/к/deploy"
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
require dirname( __DIR__, 4 ) . "/wp-load.php";
}

$sz_args = isset( $argv ) && is_array( $argv ) ? array_slice( $argv, 1 ) : array();
$sz_port = 10005;
$sz_out  = dirname( dirname( rtrim( ABSPATH, "/\\" ) ) ) . "/deploy";
foreach ( $sz_args as $sz_arg ) {
if ( preg_match( "/^--port=(\d+)$/", (string) $sz_arg, $sz_m ) ) {
$sz_port = (int) $sz_m[1];
}
if ( preg_match( "/^--out=(.+)$/", (string) $sz_arg, $sz_m ) ) {
$sz_out = $sz_m[1];
}
}

if ( ! class_exists( "ZipArchive" ) ) {
echo "Нет расширения ZIP: добавьте -d extension=php_zip.dll в запуск.\n";
return;
}

// mysqldump из LocalWP (или системный, если найдётся в PATH).
$sz_dump_bin = "";
$sz_pattern  = (string) getenv( "APPDATA" ) . "/Local/lightning-services/mysql-*/bin/win64/bin/mysqldump.exe";
foreach ( (array) glob( $sz_pattern ) as $sz_candidate ) {
if ( is_file( $sz_candidate ) ) {
$sz_dump_bin = $sz_candidate;
}
}
if ( "" === $sz_dump_bin ) {
$sz_dump_bin = "mysqldump";
}

if ( ! is_dir( $sz_out ) ) {
mkdir( $sz_out, 0775, true );
}
if ( ! is_dir( $sz_out ) ) {
echo "Не удалось создать папку: " . $sz_out . "\n";
return;
}

$sz_date = current_time( "Y-m-d" );
$sz_sql  = $sz_out . "/db-" . $sz_date . ".sql";
$sz_zip  = $sz_out . "/wp-content-" . $sz_date . ".zip";

// 1. Дамп базы.
$sz_cmd = escapeshellarg( $sz_dump_bin )
. " -h 127.0.0.1 -P " . (int) $sz_port
. " -u " . escapeshellarg( DB_USER )
. " -p" . escapeshellarg( DB_PASSWORD )
. " --single-transaction --skip-lock-tables --default-character-set=utf8mb4"
. " --result-file=" . escapeshellarg( $sz_sql )
. " " . escapeshellarg( DB_NAME ) . " 2>&1";
$sz_out_lines = array();
$sz_code      = 0;
exec( $sz_cmd, $sz_out_lines, $sz_code );

echo "дамп: exit=" . $sz_code . " | " . ( is_file( $sz_sql ) ? round( filesize( $sz_sql ) / 1048576, 2 ) . " MB" : "файла нет" ) . "\n";
if ( 0 !== $sz_code || ! is_file( $sz_sql ) ) {
echo "Ошибка mysqldump: " . implode( " ", array_slice( $sz_out_lines, 0, 3 ) ) . "\n";
return;
}

// 2. Архив wp-content (тема + вложения), мусор исключаем.
$sz_root   = rtrim( ABSPATH, "/\\" ) . "/wp-content";
$sz_skip   = array( "debug.log", "upgrade/", "backups/", "cache/", "ai1wm-backups/", ".DS_Store", "Thumbs.db" );
$sz_zipobj = new ZipArchive();
if ( true !== $sz_zipobj->open( $sz_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
echo "Не удалось создать архив: " . $sz_zip . "\n";
return;
}
$sz_count = 0;
$sz_it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sz_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $sz_it as $sz_file ) {
$sz_rel = str_replace( "\\", "/", substr( $sz_file->getPathname(), strlen( $sz_root ) + 1 ) );
$sz_bad = false;
foreach ( $sz_skip as $sz_s ) {
if ( $sz_rel === $sz_s || false !== strpos( $sz_rel, $sz_s ) ) {
$sz_bad = true;
break;
}
}
if ( $sz_bad || ! $sz_file->isFile() ) {
continue;
}
if ( $sz_zipobj->addFile( $sz_file->getPathname(), "wp-content/" . $sz_rel ) ) {
$sz_count++;
}
}
$sz_zipobj->close();
echo "архив: файлов " . $sz_count . " | " . round( filesize( $sz_zip ) / 1048576, 2 ) . " MB\n";

// 3. Контрольные суммы + памятка.
$sz_sums = "SHA-256 (" . $sz_date . ")\n"
. hash_file( "sha256", $sz_sql ) . "  " . basename( $sz_sql ) . "\n"
. hash_file( "sha256", $sz_zip ) . "  " . basename( $sz_zip ) . "\n";
file_put_contents( $sz_out . "/SHA256SUMS.txt", $sz_sums );

$sz_readme = "АРТЕФАКТЫ ПЕРЕНОСА «САД ЗНАНИЙ» — " . $sz_date . "\n\n"
. "db-" . $sz_date . ".sql        — полный дамп БД (utf8mb4), импортируется в пустую базу на хостинге.\n"
. "wp-content-" . $sz_date . ".zip  — тема sad-znaniy и вложения (uploads). Ядро WordPress и wp-config внутрь НЕ входят.\n"
. "SHA256SUMS.txt             — контрольные суммы (сверка после передачи): certutil -hashfile <файл> SHA256\n\n"
. "Порядок на хостинге (партия 8.1):\n"
. "  1) создать базу и пользователя;\n"
. "  2) развернуть WordPress 7.1.x в папке сайта;\n"
. "  3) распаковать zip поверх wp-content (тема + uploads);\n"
. "  4) импортировать дамп: mysql -u USER -p DB < db-" . $sz_date . ".sql (или через phpMyAdmin);\n"
. "  5) в wp-config.php прописать реквизиты базы, соли, WP_DEBUG_LOG=true, WP_DEBUG_DISPLAY=false;\n"
. "  6) search-replace sadznaniy.local → боевой адрес по всем таблицам;\n"
. "  7) smoke-тест по _docs/deploy-checklist.md.\n\n"
. "Тяжёлые операции (пересчёт ключей, матрица) — только локально.\n";
file_put_contents( $sz_out . "/deploy-README.txt", $sz_readme );

echo "папка: " . $sz_out . "\n";
echo "суммы: " . $sz_out . "/SHA256SUMS.txt\n";