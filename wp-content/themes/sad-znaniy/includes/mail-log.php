<?php
/**
 * ЛОКАЛЬНАЯ ПОЧТА (Этап 7.3, продолжение S4): перехват писем на локалке.
 *
 * На LocalWP нет почтового транспорта (нет sendmail, SMTP localhost:25),
 * поэтому wp_mail() молча возвращает false — письма WordPress (подтверждение
 * смены почты администратора, уведомления лимитера) терялись.
 *
 * Здесь письма НЕ отправляются, а сохраняются в файлы вне webroot
 * (../logs/mail/*.html) и показываются на экране «🛡 Безопасность» — со
 * кнопкой «Открыть ссылку из письма». На боевом сервере этот перехват
 * выключен: там письма уходят по-настоящему.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Включён ли перехват (только на локалке).
 *
 * @return bool
 */
function sad_znaniy_mail_enabled() {
	return (bool) apply_filters( 'sad_znaniy_mail_catch_enabled', 'local' === WP_ENVIRONMENT_TYPE );
}

/**
 * Папка для перехваченных писем (вне webroot).
 *
 * @return string
 */
function sad_znaniy_mail_dir() {
	return sad_znaniy_security_base_dir() . '/logs/mail';
}

/**
 * Журнал писем (последние 20).
 *
 * @return array
 */
function sad_znaniy_mail_log() {
	$log = get_option( 'sad_znaniy_mail_log' );
	return is_array( $log ) ? $log : array();
}

/**
 * Перехватывает wp_mail на локалке: сохраняет письмо в файл и в журнал.
 *
 * @param null|bool $return Что вернуть вместо отправки.
 * @param array     $atts   Параметры письма.
 * @return null|bool
 */
function sad_znaniy_mail_catch( $return, $atts ) {
	if ( ! sad_znaniy_mail_enabled() ) {
		return $return;
	}

	$to      = isset( $atts['to'] ) ? ( is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'] ) : '';
	$subject = isset( $atts['subject'] ) ? (string) $atts['subject'] : '';
	$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
	$headers = isset( $atts['headers'] ) ? $atts['headers'] : array();

	$dir = sad_znaniy_mail_dir();
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0775, true ) ) {
		return true; // всё равно не даём зависнуть на недоступном транспорте
	}

	$stamp = current_time( 'Y-m-d-His' );
	$file  = 'mail-' . $stamp . '-' . wp_rand( 100, 999 ) . '.html';

	if ( is_array( $headers ) ) {
		$headers = implode( "\n", array_map( 'strval', $headers ) );
	}

	$html = '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
		. '<title>' . esc_html( $subject ) . '</title>'
		. '<style>body{font-family:system-ui,Arial,sans-serif;margin:24px;color:#1f2937}'
		. '.head{background:#f3f4f6;border-left:4px solid #68B96E;padding:12px 16px;margin-bottom:18px}'
		. 'pre{white-space:pre-wrap;font-family:Consolas,monospace;font-size:13px}</style></head><body>'
		. '<div class="head"><strong>Письмо с локалки (не отправлено, перехвачено)</strong><br>'
		. 'Кому: ' . esc_html( $to ) . '<br>Тема: ' . esc_html( $subject ) . '<br>Время: ' . esc_html( current_time( 'd.m.Y H:i:s' ) )
		. ( '' !== $headers ? '<br>Заголовки: ' . esc_html( $headers ) : '' ) . '</div>'
		. $message . '</body></html>';

	file_put_contents( $dir . '/' . $file, $html );

	// Первая «админская» ссылка из письма — обычно это ссылка подтверждения.
	$link = '';
	if ( preg_match( '#https?://[^\s"\'<>]+/?wp-admin/[^\s"\'<>]+#', $message, $m ) ) {
		$link = $m[0];
	}

	$log = sad_znaniy_mail_log();
	array_unshift(
		$log,
		array(
			'time'    => current_time( 'mysql' ),
			'to'      => $to,
			'subject' => $subject,
			'file'    => $file,
			'link'    => $link,
		)
	);
	update_option( 'sad_znaniy_mail_log', array_slice( $log, 0, 20 ), false );

	return true;
}
add_filter( 'pre_wp_mail', 'sad_znaniy_mail_catch', 10, 2 );

/**
 * Отправка тестового письма (для проверки, что перехват работает).
 *
 * @return string Отчёт.
 */
function sad_znaniy_mail_test() {
	$ok = wp_mail(
		get_option( 'admin_email' ),
		'Проверка почты «Сад знаний»',
		'<p>Это тестовое письмо. На локалке оно перехвачено и показано в разделе «Почта (локалка)»; на боевом сервере такие письма уходят по-настоящему.</p>',
		array( 'Content-Type: text/html; charset=UTF-8' )
	);
	return $ok ? 'тестовое письмо создано' : 'не удалось создать письмо (транспорт вернул ошибку)';
}

/* === Показ письма из админки ============================================= */

/**
 * Отдаёт сохранённое письмо (только администратору, с nonce).
 *
 * @return void
 */
function sad_znaniy_mail_view() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	check_admin_referer( 'sz_mail_view' );

	$file = isset( $_GET['file'] ) ? basename( sanitize_file_name( wp_unslash( (string) $_GET['file'] ) ) ) : '';
	$path = sad_znaniy_mail_dir() . '/' . $file;

	if ( '' === $file || ! is_file( $path ) ) {
		wp_die( 'Письмо не найдено.' );
	}

	header( 'Content-Type: text/html; charset=utf-8' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
add_action( 'admin_post_sz_mail_view', 'sad_znaniy_mail_view' );

/* === Панель на экране «🛡 Безопасность» ================================== */

/**
 * Панель «Почта (локалка)»: перехваченные письма и кнопка проверки.
 *
 * @return void
 */
function sad_znaniy_mail_panel() {
	$log = sad_znaniy_mail_log();

	echo '<h3>Почта (локалка)</h3>';

	if ( ! sad_znaniy_mail_enabled() ) {
		echo '<p>На этом окружении письма отправляются обычным способом (перехват выключен).</p>';
		return;
	}

	echo '<p class="description">На локальном компьютере почтового сервера нет, поэтому письма WordPress (подтверждение смены почты администратора, уведомления лимитера) <strong>не отправляются, а сохраняются</strong> в <code>'
		. esc_html( sad_znaniy_mail_dir() ) . '</code> и показываются здесь. На боевом сервере письма уйдут по-настоящему.</p>';

	echo '<form method="post" style="margin:6px 0 12px;">';
	wp_nonce_field( 'sz_security_action' );
	echo '<input type="hidden" name="sz_security_action" value="mail_test">'
		. '<button class="button">Создать тестовое письмо</button></form>';

	if ( empty( $log ) ) {
		echo '<p>Писем пока не было.</p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr><th>Когда</th><th>Кому</th><th>Тема</th><th>Действия</th></tr></thead><tbody>';
	foreach ( $log as $row ) {
		$view = wp_nonce_url(
			admin_url( 'admin-post.php?action=sz_mail_view&file=' . rawurlencode( isset( $row['file'] ) ? (string) $row['file'] : '' ) ),
			'sz_mail_view'
		);
		echo '<tr><td>' . esc_html( isset( $row['time'] ) ? (string) $row['time'] : '' ) . '</td><td>'
			. esc_html( isset( $row['to'] ) ? (string) $row['to'] : '' ) . '</td><td>'
			. esc_html( isset( $row['subject'] ) ? (string) $row['subject'] : '' ) . '</td><td>'
			. '<a class="button button-small" href="' . esc_url( $view ) . '" target="_blank" rel="noopener">Показать письмо</a> ';
		if ( ! empty( $row['link'] ) ) {
			echo '<a class="button button-small button-primary" href="' . esc_url( (string) $row['link'] ) . '">Открыть ссылку из письма</a>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}
