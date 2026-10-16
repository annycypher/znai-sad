<?php
/**
 * CLI-аудит перелинковки (Этап 6/7, блок C).
 *
 * Считает для каждой опубликованной страницы, записи и растения:
 *  - исходящие внутренние ссылки (из post_content);
 *  - входящие: из контента других объектов и из пунктов меню (меню-локации).
 * Плюс список «сирот» (0 входящих) и внутренних ссылок, которые никуда не
 * ведут (битые/устаревшие). Результат — _docs/internal-links.md.
 *
 * Запуск из папки темы:
 *   php _tools/link-audit.php
 *
 * @package sad-znaniy
 */

if ( 'cli' !== PHP_SAPI ) {
http_response_code( 403 );
exit( 'Только CLI' );
}

define( 'WP_USE_THEMES', false );
$root = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $root ) ) {
fwrite( STDERR, 'Не найден wp-load.php: ' . $root );
exit( 1 );
}
require_once $root;

$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
$home = untrailingslashit( home_url( '/' ) );

/** Нормализация URL для сравнения. */
function sz_norm( $url, $home ) {
$url = strtok( $url, '#' );
if ( '' === $url || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'javascript:' ) ) {
return '';
}
if ( 0 === strpos( $url, '/' ) ) {
$url = $home . $url;
}
if ( 0 !== strpos( $url, $home ) ) {
return 'EXT';
}
return untrailingslashit( strtolower( explode( '?', $url )[0] ) );
}

/** Ссылки из HTML. */
function sz_links( $html ) {
$out = array();
preg_match_all( '/href="([^"]+)"/i', $html, $m );
$out = array_merge( $out, $m[1] );
preg_match_all( "/href='([^']+)'/i", $html, $m2 );
return array_merge( $out, $m2[1] );
}

$types = get_post_types( array( 'public' => true ), 'names' );
unset( $types['attachment'] );
$posts = get_posts(
array(
'post_type'   => array_values( $types ),
'post_status' => 'publish',
'numberposts' => -1,
'orderby'     => 'title',
'order'       => 'ASC',
)
);

$items = array();
$by_url = array();
foreach ( $posts as $p ) {
$link = sz_norm( get_permalink( $p ), $home );
$items[ $p->ID ] = array(
'id'    => $p->ID,
'title' => $p->post_title,
'type'  => $p->post_type,
'url'   => get_permalink( $p ),
'norm'  => $link,
'out'   => array(),
'in'    => array(),
'inmenu' => 0,
);
if ( $link ) {
$by_url[ $link ] = $p->ID;
}
}

$front  = sz_norm( home_url( '/' ), $home );
$broken = array();
$total_out = 0;

foreach ( $items as $id => $it ) {
foreach ( sz_links( get_post_field( 'post_content', $id ) ) as $raw ) {
$u = sz_norm( $raw, $home );
if ( '' === $u ) {
continue;
}
if ( 'EXT' === $u ) {
continue;
}
$items[ $id ]['out'][] = $u;
++$total_out;
if ( isset( $by_url[ $u ] ) ) {
$target = $by_url[ $u ];
if ( $target !== $id ) {
$items[ $target ]['in'][] = 'контент: ' . $it['title'];
}
} else {
if ( ! isset( $archives[ $u ] ) ) {		$broken[ $u ] = true;		}
}
}
}

$menus = wp_get_nav_menus();
$menu_links = 0;
foreach ( $menus as $menu ) {
$mitems = wp_get_nav_menu_items( $menu->term_id );
if ( ! $mitems ) {
continue;
}
foreach ( $mitems as $mi ) {
$u = sz_norm( $mi->url, $home );
if ( '' === $u || 'EXT' === $u ) {
continue;
}
if ( isset( $by_url[ $u ] ) ) {
$target = $by_url[ $u ];
$items[ $target ]['inmenu']++;
++$menu_links;
}
}
}

$orphans = array();
foreach ( $items as $it ) {
if ( $it['norm'] === $front ) {
continue;
}
if ( 0 === count( $it['in'] ) && 0 === $it['inmenu'] ) {
$orphans[] = $it;
}
}

$doc  = '# Внутренняя перелинковка — аудит' . "\n\n";
$doc .= 'Автоматический отчёт (`_tools/link-audit.php`). Дата: ' . date_i18n( 'd.m.Y H:i' ) . ".\n\n";
$doc .= 'Всего опубликованных объектов: ' . count( $items ) . ', исходящих внутренних ссылок из контента: ' . $total_out . ', ссылок из меню: ' . $menu_links . ".\n\n";
$doc .= '## Карта (входящие / исходящие)' . "\n\n| Объект | Тип | Входящих (контент) | Входящих (меню) | Исходящих | URL |\n|---|---|---|---|---|---|\n";
$sorted = $items;
usort(
$sorted,
function ( $a, $b ) {
return count( $b['in'] ) + $b['inmenu'] - count( $a['in'] ) - $a['inmenu'];
}
);
foreach ( $sorted as $it ) {
$doc .= '| ' . str_replace( '|', '/', $it['title'] ) . ' | ' . $it['type'] . ' | ' . count( $it['in'] ) . ' | ' . $it['inmenu'] . ' | ' . count( $it['out'] ) . ' | ' . $it['url'] . " |\n";
}
$doc .= "\n## Сироты (0 входящих)\n\nУчитываются ссылки из контента и из меню, зарегистрированных в локациях. Ссылки, зашитые в шаблонах шапки/футера/панели слева, не считаются, поэтому часть страниц может выглядеть «сиротами».\n\n";
if ( ! $orphans ) {
$doc .= 'Нет — у каждого объекта есть хотя бы одна входящая ссылка.' . "\n";
} else {
foreach ( $orphans as $it ) {
$doc .= '- ' . $it['title'] . ' (' . $it['type'] . ') — ' . $it['url'] . "\n";
}
}
$doc .= "\n## Внутренние ссылки, не ведущие к записи (проверить вручную: архивы разделов и таксономий, служебные страницы)\n\n";
if ( ! $broken ) {
$doc .= 'Нет.' . "\n";
} else {
foreach ( array_keys( $broken ) as $u ) {
$doc .= '- ' . $u . "\n";
}
}
$target = __DIR__ . '/../_docs/internal-links.md';
file_put_contents( $target, $doc );

echo 'Объектов: ' . count( $items ) . ' | исходящих: ' . $total_out . ' | из меню: ' . $menu_links . "\n";
echo 'Сирот: ' . count( $orphans ) . ' | битых внутренних ссылок: ' . count( $broken ) . "\n";
foreach ( array_slice( $orphans, 0, 12 ) as $it ) {
echo '  сирота: ' . $it['title'] . ' (' . $it['type'] . ')
';
}
foreach ( array_slice( array_keys( $broken ), 0, 12 ) as $u ) {
echo '  без цели: ' . $u . '
';
}
echo 'Отчёт: ' . realpath( $target ) . '
';