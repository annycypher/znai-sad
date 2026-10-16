<?php
/**
 * ICS ИЗ ПЛАНА ПЛАНИРОВЩИКА (Этап 6, Партия 5).
 *
 * Отдаёт .ics по адресу /planirovshchik/?plan=<uuid>&ics=1 — события по
 * УНИКАЛЬНЫМ культурам плана (не по каждому элементу), чтобы 15 грядок
 * томатов не превращались в 15 событий.
 *
 * Даты берём из сезонного окна календаря (calendar_event) для региона плана;
 * если окна нет — событие ставится на первое число месяца плана с пометкой
 * «срок уточните в карточке». Гейт: только залогиненным (как у PDF).
 *
 * Свои escape/fold по образцу includes/ical.php — чтобы не зависеть от
 * внутренних имён соседнего модуля.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Экранирование значения по RFC 5545.
 *
 * @param string $text Текст.
 * @return string
 */
function sad_znaniy_planner_ics_escape( $text ) {
$text = str_replace( array( "\r\n", "\r", "\n" ), '\\n', (string) $text );
return str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,'), $text );
}

/**
 * Свёртка строки на 75 октетов (продолжение — с пробела).
 *
 * @param string $line Строка.
 * @return string
 */
function sad_znaniy_planner_ics_fold( $line ) {
$out = '';
$len = strlen( $line );
for ( $i = 0; $i < $len; $i++ ) {
$chunk = $i > 0 ? " \r\n" : '';
$out .= $chunk . substr( $line, $i, $i > 0 ? 74 : 75 );
$i += $i > 0 ? 74 : 75;
}
return $out;
}

/**
 * Отдаёт .ics плана, если запрошено (?plan=<uuid>&ics=1).
 *
 * @return void
 */
function sad_znaniy_planner_ics_maybe_output() {
if ( ! isset( $_GET['plan'] ) || ! isset( $_GET['ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
return;
}
$id = sanitize_text_field( wp_unslash( (string) $_GET['plan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! sad_znaniy_planner_is_id( $id ) ) {
return;
}

if ( ! is_user_logged_in() ) {
status_header( 403 );
header( 'Content-Type: text/plain; charset=utf-8' );
exit( 'Войдите в аккаунт — файл календаря формируется для сохранённых планов.' );
}

$plan = sad_znaniy_planner_get_public( $id );
if ( ! $plan ) {
status_header( 404 );
header( 'Content-Type: text/plain; charset=utf-8' );
exit( 'План не найден.' );
}

$data = sad_znaniy_planner_ics_build( $plan );
header( 'Content-Type: ' . $data['mime'] );
header( 'Content-Disposition: attachment; filename="' . $data['name'] . '"' );
echo $data['body'];
exit;
}
add_action( 'init', 'sad_znaniy_planner_ics_maybe_output', 21 );
/**
 * Собирает .ics по плану: одно событие на УНИКАЛЬНУЮ культуру.
 *
 * @param array $plan План (из кабинета или публичного чтения).
 * @return array { mime, name, body }
 */
function sad_znaniy_planner_ics_build( $plan ) {
$elements = isset( $plan['elements'] ) && is_array( $plan['elements'] ) ? $plan['elements'] : array();
$region   = isset( $plan['meta']['region'] ) ? (string) $plan['meta']['region'] : '';
$title    = isset( $plan['meta']['title'] ) ? (string) $plan['meta']['title'] : 'Мой участок';
$id       = isset( $plan['id'] ) ? (string) $plan['id'] : '';
$link     = '' !== $id ? sad_znaniy_planner_share_url( $id ) : '';

// уникальные культуры и их количество
$crops = array();
foreach ( $elements as $e ) {
$slug = isset( $e['crop'] ) ? (string) $e['crop'] : '';
if ( '' === $slug ) {
continue;
}
$crops[ $slug ] = isset( $crops[ $slug ] ) ? $crops[ $slug ] + 1 : 1;
}

$today  = current_time( 'Y-m-d' );
$lines  = array(
'BEGIN:VCALENDAR',
'VERSION:2.0',
'PRODID:-//Сад знаний//План участка//RU',
'CALSCALE:GREGORIAN',
'METHOD:PUBLISH',
'X-WR-CALNAME:' . sad_znaniy_planner_ics_escape( 'Сад знаний: ' . $title ),
'X-WR-TIMEZONE:Europe/Moscow',
);

$n = 0;
foreach ( $crops as $slug => $count ) {
$plant = get_page_by_path( $slug, OBJECT, 'plant' );
if ( ! $plant ) {
continue;
}
$n++;

$from  = '';
$note  = 'Точный срок — в карточке растения.';
$when  = 'upcoming';
$args  = array(
'post_type'      => 'calendar_event',
'post_status'    => 'publish',
'posts_per_page' => 1,
'meta_key'       => '_sz_event_date_from',
'orderby'        => 'meta_value',
'order'          => 'ASC',
'no_found_rows'  => true,
'meta_query'     => array(
'relation' => 'AND',
array( 'key' => '_sz_event_crop', 'value' => (string) $plant->ID ),
array( 'key' => '_sz_event_date_from', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
array(
'relation' => 'OR',
array( 'key' => '_sz_event_all_regions', 'value' => '1' ),
array( 'key' => '_sz_event_regions', 'value' => $region, 'compare' => 'LIKE' ),
),
),
);
$found = get_posts( $args );
if ( ! $found ) {
$args['meta_query'][2] = array( 'key' => '_sz_event_crop', 'value' => (string) $plant->ID );
unset( $args['meta_query'][3] );
$args['meta_query'] = array_values( $args['meta_query'] );
$args['meta_query'][1] = array( 'key' => '_sz_event_crop', 'value' => (string) $plant->ID );
$args['meta_query'] = array( 'relation' => 'AND', array( 'key' => '_sz_event_crop', 'value' => (string) $plant->ID ) );
$args['orderby'] = 'meta_value';
$found = get_posts( $args );
$when  = 'any';
}
if ( $found ) {
$ev   = $found[0];
$from = (string) get_post_meta( $ev->ID, '_sz_event_date_from', true );
$to   = (string) get_post_meta( $ev->ID, '_sz_event_date_to', true );
if ( '' === $to ) {
$to = $from;
}
if ( 'any' === $when ) {
$note = 'Окно работ: ' . $ev->post_title . '. Точный срок года — в карточке растения.';
}
} else {
$from = gmdate( 'Y-m-01' );
$to   = $from;
}
if ( '' === $from ) {
$from = gmdate( 'Y-m-01' );
}
$to = ( '' !== $to && $to >= $from ) ? $to : $from;
$dtend = gmdate( 'Ymd', strtotime( $to . ' +1 day' ) );

$desc = array(
'План: ' . $title,
'Элементов в плане: ' . (int) $count,
'Культура: ' . $plant->post_title,
$note,
'Карточка растения: ' . get_permalink( $plant ),
);
if ( '' !== $link ) {
$desc[] = 'План на сайте: ' . $link;
}

$lines[] = 'BEGIN:VEVENT';
$lines[] = 'UID:' . $id . '-' . $slug . '-' . $n . '@znai-sad.ru';
$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
$lines[] = 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $from ) );
$lines[] = 'DTEND;VALUE=DATE:' . $dtend;
$lines[] = 'SUMMARY:' . sad_znaniy_planner_ics_escape( 'Посадка: ' . $plant->post_title );
$lines[] = 'DESCRIPTION:' . sad_znaniy_planner_ics_escape( implode( '\n', $desc ) );
$lines[] = 'CATEGORIES:План участка';
$lines[] = 'TRANSP:TRANSPARENT';
$lines[] = 'BEGIN:VALARM';
$lines[] = 'TRIGGER:-P1D';
$lines[] = 'ACTION:DISPLAY';
$lines[] = 'DESCRIPTION:' . sad_znaniy_planner_ics_escape( 'Завтра: ' . $plant->post_title );
$lines[] = 'END:VALARM';
$lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';

$body = array();
foreach ( $lines as $line ) {
$body[] = sad_znaniy_planner_ics_fold( $line );
}

return array(
'mime' => 'text/calendar; charset=utf-8',
'name' => 'znai-sad-plan-' . substr( $id, 0, 8 ) . '.ics',
'body' => implode( "\r\n", $body ) . "\r\n",
);
}