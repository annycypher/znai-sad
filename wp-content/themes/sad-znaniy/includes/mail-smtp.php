<?php
/**
 * Отправка почты сайта через SMTP хостинга (spaceweb), ящик info@znai-sad.ru.
 *
 * Зачем: PHP-функция mail() на shared-хостинге часто попадает в спам и не
 * проходит проверку отправителя. SMTP-ящик домена даёт корректный From,
 * SPF/DKIM-выравнивание и понятные ошибки доставки.
 *
 * Пароль в коде не хранится: реквизиты задаются константами в wp-config.php
 * (файл вне git). Если константы не определены — тема работает как раньше,
 * через стандартный wp_mail(), поэтому локальная копия письма по-прежнему
 * перехватывает mail-log.php и ничего наружу не уходит.
 *
 * Константы (задаются на боевом сервере):
 *   SZ_SMTP_HOST      — smtp.spaceweb.ru
 *   SZ_SMTP_PORT      — 465 (SSL) или 587 (STARTTLS)
 *   SZ_SMTP_SECURE    — 'ssl' | 'tls' | '' (без шифрования)
 *   SZ_SMTP_USER      — info@znai-sad.ru
 *   SZ_SMTP_PASS      — пароль ящика
 *   SZ_SMTP_FROM      — адрес отправителя (обычно совпадает с SZ_SMTP_USER)
 *   SZ_SMTP_FROM_NAME — имя отправителя («Сад знаний»)
 *   SZ_SMTP_TIMEOUT   — таймаут в секундах (по умолчанию 15)
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Заданы ли реквизиты SMTP в wp-config.php.
 *
 * @return bool
 */
function sad_znaniy_smtp_ready() {
	return defined( 'SZ_SMTP_HOST' ) && defined( 'SZ_SMTP_USER' ) && defined( 'SZ_SMTP_PASS' );
}

/**
 * Переводит PHPMailer на SMTP хостинга.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Объект PHPMailer из wp_mail().
 */
function sad_znaniy_phpmailer_init( $phpmailer ) {
	if ( ! sad_znaniy_smtp_ready() ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = SZ_SMTP_HOST;
	$phpmailer->Port       = defined( 'SZ_SMTP_PORT' ) ? (int) SZ_SMTP_PORT : 465;
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = SZ_SMTP_USER;
	$phpmailer->Password   = SZ_SMTP_PASS;
	$phpmailer->CharSet    = 'UTF-8';
	$phpmailer->Timeout    = defined( 'SZ_SMTP_TIMEOUT' ) ? (int) SZ_SMTP_TIMEOUT : 15;
	$phpmailer->SMTPAutoTLS = true;

	$secure = defined( 'SZ_SMTP_SECURE' ) ? SZ_SMTP_SECURE : 'ssl';
	if ( $secure ) {
		$phpmailer->SMTPSecure = $secure;
	}
}
add_action( 'phpmailer_init', 'sad_znaniy_phpmailer_init' );

/**
 * Подменяет адрес отправителя на ящик домена (иначе письма уходят от
 * wordpress@<хост> и ломают SPF/DKIM).
 *
 * @param string $email Текущий адрес отправителя.
 * @return string
 */
function sad_znaniy_mail_from( $email ) {
	return ( defined( 'SZ_SMTP_FROM' ) && SZ_SMTP_FROM ) ? SZ_SMTP_FROM : $email;
}
add_filter( 'wp_mail_from', 'sad_znaniy_mail_from', 20 );

/**
 * Имя отправителя писем сайта.
 *
 * @param string $name Текущее имя отправителя.
 * @return string
 */
function sad_znaniy_mail_from_name( $name ) {
	return ( defined( 'SZ_SMTP_FROM_NAME' ) && SZ_SMTP_FROM_NAME ) ? SZ_SMTP_FROM_NAME : $name;
}
add_filter( 'wp_mail_from_name', 'sad_znaniy_mail_from_name', 20 );
