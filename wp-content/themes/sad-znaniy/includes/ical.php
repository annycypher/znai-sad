<?php
/**
 * ICS-ЭКСПОРТ КАЛЕНДАРЯ — «В календарь телефона».
 *
 * Что делает:
 *   • отдаёт файл .ics (iCalendar, RFC 5545) с работами месяца и выбранного
 *     региона: подходит для импорта в Google Calendar, iPhone Calendar и др.;
 *   • адрес /kalendar/ical/?region=X&month=Y — скачать месяц;
 *   • адрес /kalendar/ical/?region=X (без месяца) — «плавающее окно»: текущий
 *     месяц и 11 следующих. Такой URL можно добавить в телефон «по URL» —
 *     напоминания будут обновляться сами (REFRESH-INTERVAL);
 *   • поддержаны фильтры календаря: region, month, year, exp;
 *   • в каждом событии: даты окна (all-day), заголовок с культурой и работой,
 *     описание текстом события + ссылка на карточку растения, напоминание
 *     за день (VALARM -P1D).
 *
 * Всё своё, без библиотек и внешних сервисов (формат простой).
 *
 * @package sad-znaniy
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

/**
 * Регистрирует ЧПУ /kalendar/ical/ и один раз сбрасывает правила перезаписи.
 *
 * @return void
 */
function sad_znaniy_ical_init() {
	add_rewrite_rule( "^kalendar/ical/?$", "index.php?sz_ical=1", "top" );
	if ( "2" !== (string) get_option( "sz_ical_rules", "" ) ) {
		flush_rewrite_rules( false );
		update_option( "sz_ical_rules", "2", false );
	}
}
add_action( "init", "sad_znaniy_ical_init", 5 );

/**
 * Добавляет переменную запроса.
 *
 * @param string[] $vars Переменные.
 * @return string[]
 */
function sad_znaniy_ical_vars( $vars ) {
	$vars[] = "sz_ical";
	return $vars;
}
add_filter( "query_vars", "sad_znaniy_ical_vars" );

/**
 * Отдаёт .ics, если запрошен эндпоинт (и завершает запрос).
 *
 * @return void
 */
function sad_znaniy_ical_maybe_output() {
	$uri = isset( $_SERVER["REQUEST_URI"] ) ? (string) $_SERVER["REQUEST_URI"] : ""; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! isset( $_GET["sz_ical"] ) && ! preg_match( "#/kalendar/ical/?([?]|$)#", $uri ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$data = sad_znaniy_ical_build();
	nocache_headers();
	header( "Content-Type: " . $data["mime"] );
	header( "Content-Disposition: attachment; filename=\"" . $data["name"] . "\"" );
	header( "X-Robots-Tag: noindex, nofollow" );
	echo $data["body"]; // phpcs:ignore WordPress.Security.EscapeOutput -- это текстовый файл iCalendar.
	exit;
}
add_action( "init", "sad_znaniy_ical_maybe_output", 20 );

/**
 * Экранирование текста для iCalendar.
 *
 * @param string $text Текст.
 * @return string
 */
function sad_znaniy_ical_escape( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = str_replace( array( "\\", ";", "," ), array( "\\\\", "\\;", "\\," ), $text );
	$text = str_replace( array( "\r\n", "\r", "\n" ), "\\n", $text );
	return $text;
}

/**
 * Свёртка длинной строки по RFC 5545 (не длиннее 75 октетов, продолжение с пробела).
 *
 * @param string $line Строка.
 * @return string
 */
function sad_znaniy_ical_fold( $line ) {
	if ( strlen( $line ) <= 73 ) {
		return $line;
	}
	$out   = "";
	$chunk = "";
	foreach ( preg_split( "//u", $line, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		if ( strlen( $chunk . $ch ) > 73 ) {
			$out  .= ( "" === $out ? "" : "\r\n " ) . $chunk;
			$chunk = $ch;
		} else {
			$chunk .= $ch;
		}
	}
	return $out . ( "" === $out ? "" : "\r\n " ) . $chunk;
}

/**
 * Собирает тело .ics по текущим GET-параметрам.
 *
 * @return array{mime:string,name:string,body:string}
 */
function sad_znaniy_ical_build() {
	$region = isset( $_GET["region"] ) ? sanitize_key( wp_unslash( (string) $_GET["region"] ) ) : ""; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( "" !== $region && ! array_key_exists( $region, sad_znaniy_region_keys() ) ) {
		$region = "";
	}
	$exp = isset( $_GET["exp"] ) ? sanitize_key( wp_unslash( (string) $_GET["exp"] ) ) : ""; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $exp, array( "new", "exp", "all" ), true ) ) {
		$exp = "";
	}
	$month = isset( $_GET["month"] ) ? (int) $_GET["month"] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$year  = isset( $_GET["year"] ) ? (int) $_GET["year"] : (int) current_datetime()->format( "Y" );
	if ( $year < 2020 || $year > 2040 ) {
		$year = (int) current_datetime()->format( "Y" );
	}

	$windows = array();
	if ( $month >= 1 && $month <= 12 ) {
		$windows[] = array( $year, $month );
		$name_part = $year . "-" . zeroise( $month, 2 );
	} else {
		$start = current_datetime()->modify( "first day of this month" );
		for ( $i = 0; $i < 12; $i++ ) {
			$d = $start->modify( "+" . $i . " month" );
			$windows[] = array( (int) $d->format( "Y" ), (int) $d->format( "n" ) );
		}
		$name_part = "12-mesyacev";
	}

	$region_label = "" === $region ? __( "Все регионы", "sad-znaniy" ) : sad_znaniy_option_label( sad_znaniy_region_keys(), $region );
	$lines   = array();
	$lines[] = "BEGIN:VCALENDAR";
	$lines[] = "VERSION:2.0";
	$lines[] = "PRODID:-//Сад знаний//Календарь дачника//RU";
	$lines[] = "CALSCALE:GREGORIAN";
	$lines[] = "METHOD:PUBLISH";
	$lines[] = "X-WR-CALNAME:" . sad_znaniy_ical_escape( "Сад знаний — " . $region_label . ", " . $name_part );
	$lines[] = "X-WR-TIMEZONE:Europe/Moscow";
	$lines[] = "REFRESH-INTERVAL;VALUE=DURATION:PT24H";
	$lines[] = "X-PUBLISHED-TTL:PT1H";

	$seen = array();
	foreach ( $windows as $win ) {
		list( $y, $m ) = $win;
		foreach ( sad_znaniy_get_month_events( $y, $m ) as $post ) {
			$t = sad_znaniy_event_data( $post->ID, $y, $m );
			if ( isset( $seen[ (int) $t["id"] ] ) ) {
				continue;
			}
			if ( $region && ! in_array( "all", $t["regions"], true ) && ! in_array( $region, $t["regions"], true ) ) {
				continue;
			}
			if ( "new" === $exp && "exp" === $t["exp"] ) {
				continue;
			}
			$seen[ (int) $t["id"] ] = true;
			$lines             = array_merge( $lines, sad_znaniy_ical_vevent( $t, $y, $m, $region ) );
		}
	}
	$lines[] = "END:VCALENDAR";

	$body = "";
	foreach ( $lines as $line ) {
		$body .= sad_znaniy_ical_fold( $line ) . "\r\n";
	}
	return array(
		"mime" => "text/calendar; charset=utf-8",
		"name" => "znai-sad-" . ( "" === $region ? "all" : $region ) . "-" . $name_part . ".ics",
		"body" => $body,
	);
}

/**
 * Одно событие календаря → строки VEVENT.
 *
 * @param array $t Задача из sad_znaniy_event_data().
 * @param int   $y Год.
 * @param int   $m Месяц.
 * @return string[]
 */
function sad_znaniy_ical_vevent( $t, $y, $m, $region = "" ) {
	$start = sprintf( "%04d%02d%02d", $y, $m, max( 1, (int) $t["d1"] ) );
	$end_d = max( (int) $t["d1"], (int) $t["d2"] );
	$dt    = DateTimeImmutable::createFromFormat( "Y-n-j", $y . "-" . $m . "-" . $end_d );
	$end   = $dt ? $dt->modify( "+1 day" )->format( "Ymd" ) : $start;

	$type_label = sad_znaniy_option_label( sad_znaniy_work_types(), $t["type"] );
	$summary    = (string) $t["title"] . ( $t["crop"] ? " — " . (string) $t["crop"] : "" );

	$desc = array();
	$body = (string) get_post_field( "post_content", (int) $t["id"] );
	if ( "" !== trim( $body ) ) {
		$desc[] = trim( $body );
	}
	if ( ! empty( $t["hint"] ) ) {
		$desc[] = (string) $t["hint"];
	}
	if ( ! empty( $t["crop_id"] ) ) {
		$desc[] = "Растение: " . get_permalink( (int) $t["crop_id"] );
	}
	$desc[] = "Календарь: " . sad_znaniy_calendar_url( $y, $m, $region );

	return array(
		"BEGIN:VEVENT",
		"UID:sz-" . (int) $t["id"] . "-" . $start . "@znai-sad.ru",
		"DTSTAMP:" . gmdate( "Ymd\THis\Z" ),
		"DTSTART;VALUE=DATE:" . $start,
		"DTEND;VALUE=DATE:" . $end,
		"SUMMARY:" . sad_znaniy_ical_escape( $summary ),
		"DESCRIPTION:" . sad_znaniy_ical_escape( implode( "\n", $desc ) ),
		"CATEGORIES:" . sad_znaniy_ical_escape( $type_label ),
		"TRANSP:TRANSPARENT",
		"BEGIN:VALARM",
		"TRIGGER:-P1D",
		"ACTION:DISPLAY",
		"DESCRIPTION:" . sad_znaniy_ical_escape( "Завтра: " . $summary ),
		"END:VALARM",
		"END:VEVENT",
	);
}
/* === Админ-экран ========================================================= */

/**
 * Пункт меню «Инструменты → Календарь: ICS».
 *
 * @return void
 */
function sad_znaniy_ical_menu() {
	add_management_page( "Календарь: ICS", "Календарь: ICS", "manage_options", "sad-ical", "sad_znaniy_ical_page" );
}
add_action( "admin_menu", "sad_znaniy_ical_menu" );

/**
 * Экран «Инструменты → Календарь: ICS» — конструктор ссылок.
 *
 * @return void
 */
function sad_znaniy_ical_page() {
	if ( ! current_user_can( "manage_options" ) ) {
		return;
	}
	$region = isset( $_GET["region"] ) ? sanitize_key( wp_unslash( (string) $_GET["region"] ) ) : ""; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( "" !== $region && ! array_key_exists( $region, sad_znaniy_region_keys() ) ) {
		$region = "";
	}
	$month = isset( $_GET["month"] ) ? (int) $_GET["month"] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$base  = home_url( "/kalendar/ical/" );

	$args = array();
	if ( "" !== $region ) {
		$args["region"] = $region;
	}
	if ( $month ) {
		$args["month"] = $month;
	}
	$download = add_query_arg( $args, $base );

	$sub_args = array();
	if ( "" !== $region ) {
		$sub_args["region"] = $region;
	}
	$subscribe = add_query_arg( $sub_args, $base );

	echo "<div class=\"wrap\">";
	echo "<h1>Календарь: ICS (в телефон)</h1>";
	echo "<p>Файл .ics с работами выбранного региона и месяца: открывается в Google Calendar и iPhone Calendar, напоминание приходит за день. На странице календаря для этого уже есть кнопка «🗓 В календарь телефона».</p>";
	echo "<form method=\"get\" style=\"margin:14px 0;\">";
	echo "<input type=\"hidden\" name=\"page\" value=\"sad-ical\">";
	echo "<label>Регион: <select name=\"region\"><option value=\"\">Все регионы</option>";
	foreach ( sad_znaniy_region_keys() as $key => $label ) {
		echo "<option value=\"" . esc_attr( $key ) . "\"" . selected( $region, $key, false ) . ">" . esc_html( $label ) . "</option>";
	}
	echo "</select></label> <label>Месяц: <select name=\"month\"><option value=\"0\">плавающее окно (12 месяцев)</option>";
	for ( $m = 1; $m <= 12; $m++ ) {
		echo "<option value=\"" . (int) $m . "\"" . selected( $month, $m, false ) . ">" . esc_html( $GLOBALS["wp_locale"]->month[ zeroise( $m, 2 ) ] ) . "</option>";
	}
	echo "</select></label> <button class=\"button\">Показать ссылки</button></form>";

	echo "<h2>1. Скачать (импорт в календарь телефона)</h2>";
	echo "<p><a class=\"button button-primary\" href=\"" . esc_url( $download ) . "\">Скачать .ics</a></p>";
	echo "<p><code>" . esc_html( $download ) . "</code></p>";
	echo "<p class=\"description\">На телефоне ссылка откроет календарь для импорта; в Google Calendar — «Другие календари → Импорт».</p>";

	echo "<h2>2. Подписка (напоминания обновляются сами)</h2>";
	echo "<p><code>" . esc_html( $subscribe ) . "</code></p>";
	echo "<p class=\"description\">iPhone: «Календари → Добавить календарь → Добавить по URL». Google: «Другие календари → По URL». Подписка отдаёт текущий месяц и 11 следующих, обновление — раз в сутки. Эту ссылку удобно давать посетителям в ответах и соцсетях.</p>";
	echo "</div>";
}