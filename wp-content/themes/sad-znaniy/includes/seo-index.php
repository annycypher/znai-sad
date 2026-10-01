<?php
/**
 * SEO-Хаб → Индексация (Этап 7.2, Партия 2).
 *
 * Свои логи визитов поисковых ботов (YandexBot / Googlebot / YandexImages)
 * к страницам программатика и календаря + раздел «Внешние сервисы»
 * (до Этапа 8 — заглушка с инструкцией, кнопки неактивны).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Имя поискового бота по User-Agent ('' — не бот).
 *
 * @return string
 */
function sad_znaniy_seo_bot_name() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
	if ( '' === $ua ) {
		return '';
	}

	if ( false !== stripos( $ua, 'yandeximages' ) ) {
		return 'YandexImages';
	}
	if ( false !== stripos( $ua, 'yandexbot' ) ) {
		return 'YandexBot';
	}
	if ( false !== stripos( $ua, 'googlebot' ) ) {
		return 'Googlebot';
	}

	return '';
}

/**
 * Путь URL, если он относится к программатику или календарю.
 *
 * @param string $request_uri REQUEST_URI.
 * @return string Путь или ''.
 */
function sad_znaniy_seo_watch_path( $request_uri ) {
	$path = (string) wp_parse_url( (string) $request_uri, PHP_URL_PATH );
	$path = '/' . ltrim( $path, '/' );

	if ( preg_match( '#^/(kalendar|kogda-sazhat|uhod)/#', $path ) ) {
		return $path;
	}

	return '';
}

/**
 * Фиксирует визит бота (дедуп: один URL + бот + день = одна запись).
 */
function sad_znaniy_seo_log_bot_visit() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$bot = sad_znaniy_seo_bot_name();
	if ( '' === $bot ) {
		return;
	}

	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = sad_znaniy_seo_watch_path( $uri );
	if ( '' === $path ) {
		return;
	}

	$tables = sad_znaniy_seo_tables();
	sad_znaniy_seo_db_write(
		$tables['bots'],
		array(
			'url' => mb_substr( $path, 0, 191, 'UTF-8' ),
			'bot' => $bot,
			'day' => current_time( 'Y-m-d' ),
			'at'  => current_time( 'mysql' ),
		)
	);
}
add_action( 'wp_loaded', 'sad_znaniy_seo_log_bot_visit' );

/**
 * Выводит verify-метки из опции (заготовка до Этапа 8).
 */
function sad_znaniy_seo_verify_meta() {
	$verify = get_option( 'sz_seo_verify', array() );
	$yandex = isset( $verify['yandex'] ) ? trim( (string) $verify['yandex'] ) : '';
	$google = isset( $verify['google'] ) ? trim( (string) $verify['google'] ) : '';

	if ( '' !== $yandex ) {
		echo '<meta name="yandex-verification" content="' . esc_attr( $yandex ) . '" />' . "\n";
	}
	if ( '' !== $google ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $google ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'sad_znaniy_seo_verify_meta', 1 );

/**
 * Визиты ботов по URL (сводка).
 *
 * @return object[]
 */
function sad_znaniy_seo_bot_rows() {
	global $wpdb;

	$t     = sad_znaniy_seo_tables();
	$table = $t['bots'];

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- имя таблицы задано кодом.
	return $wpdb->get_results(
		"SELECT url, MIN(day) AS first_seen, MAX(at) AS last_seen, COUNT(*) AS days, GROUP_CONCAT(DISTINCT bot ORDER BY bot) AS bots
		 FROM {$table}
		 GROUP BY url
		 ORDER BY last_seen DESC
		 LIMIT 300"
	);
}

/**
 * Экран «📊 SEO-Хаб → Индексация».
 */
function sad_znaniy_seo_hub_index_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = '';

	// Сохранение verify-меток.
	if ( isset( $_POST['sz_idx_verify'] ) && check_admin_referer( 'sz_idx_verify', 'sz_idx_nonce' ) ) {
		update_option(
			'sz_seo_verify',
			array(
				'yandex' => isset( $_POST['sz_verify_yandex'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_verify_yandex'] ) ) : '',
				'google' => isset( $_POST['sz_verify_google'] ) ? sanitize_text_field( wp_unslash( $_POST['sz_verify_google'] ) ) : '',
			),
			false
		);
		$notice = '<div class="notice notice-success is-dismissible"><p>Verify-метки сохранены — выводятся в &lt;head&gt; прямо сейчас.</p></div>';
	}

	// Ручной запуск суточной задачи.
	if ( isset( $_POST['sz_idx_cron'] ) && check_admin_referer( 'sz_idx_cron', 'sz_idx_cron_nonce' ) ) {
		sad_znaniy_seo_daily_sync();
		$notice = '<div class="notice notice-success is-dismissible"><p>Задача выполнена — см. журнал ниже.</p></div>';
	}

	$verify   = get_option( 'sz_seo_verify', array() );
	$external = sad_znaniy_seo_external_on();
	$bots     = sad_znaniy_seo_bot_rows();
	$log      = get_option( 'sz_seo_log', array() );
	$next     = wp_next_scheduled( 'sz_seo_daily_sync' );
	?>
	<div class="wrap">
		<h1>SEO-Хаб → Индексация</h1>

		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- сформировано кодом выше. ?>

		<p><b>Для чего:</b> здесь видно, ходят ли поисковые боты по страницам программатика и календаря. Пока сайт локальный — визитов почти не будет: это нормально, полноценно заработает после переноса на хостинг (Этап 8).</p>

		<?php
		sad_znaniy_seo_help_box(
			'Как этим пользоваться (инструкция)',
			array(
				'<b>Свои логи:</b> визиты YandexBot / Googlebot / YandexImages к URL программатика и календаря фиксируются автоматически, без внешних сервисов.',
				'<b>Столбцы:</b> «впервые увиден» — первый визит, «последний визит» — самый свежий. «Внешний статус» заполнится после Этапа 8 (Метрика и Вебмастер).',
				'<b>Verify-метки:</b> вставьте код подтверждения прав из Яндекс.Вебмастера и Google Search Console — метки уже выводятся в &lt;head&gt;.',
				'<b>Внешние сервисы:</b> кнопки проверки связи отключены до переноса. Включаются константой <code>SZ_EXTERNAL_ON</code> в wp-config.php (Этап 8).',
				'<b>Переобход</b> страниц в Вебмастере запрашивается только после переноса — локальный сайт индексировать не нужно.',
			),
			'«13. SEO-Хаб»'
		);
		?>

		<h2>1. Свои логи ботов</h2>
		<?php if ( ! $bots ) : ?>
			<p>Пока визитов ботов нет. Проверить механизм: откройте программатик-страницу с «ботским» User-Agent — строка появится здесь (команда для проверки есть в отчёте шага).</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>URL</th>
						<th style="width:150px;">Впервые увиден</th>
						<th style="width:160px;">Последний визит</th>
						<th style="width:140px;">Боты</th>
						<th style="width:170px;">Внешний статус</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $bots as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( home_url( $row->url ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row->url ); ?></a></td>
						<td><?php echo esc_html( (string) $row->first_seen ); ?></td>
						<td><?php echo esc_html( (string) $row->last_seen ); ?></td>
						<td><?php echo esc_html( (string) $row->bots ); ?></td>
						<td><?php echo esc_html( $external ? 'ожидает синхронизации' : '— (после Этапа 8)' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>2. Внешние сервисы (после переноса)</h2>
		<p><b>Что нужно создать на Этапе 8</b> (сейчас ничего не подключаем — правило «ноль внешних запросов»):</p>
		<ol style="margin-left:18px;list-style:decimal;">
			<li><b>Яндекс.Метрика</b> — счётчик на znai-sad.ru, номер счётчика вносится в настройки хаба на Этапе 8.</li>
			<li><b>Яндекс.Вебмастер</b> — добавить сайт, подтвердить права (meta-тег ниже или файл), создать OAuth-токен.</li>
			<li><b>Google Search Console</b> — добавить сайт, подтвердить права meta-тегом, создать ключ сервисного аккаунта.</li>
		</ol>
		<form method="post" style="margin-bottom:10px;">
			<?php wp_nonce_field( 'sz_idx_verify', 'sz_idx_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="sz_verify_yandex">Код подтверждения Яндекс.Вебмастера</label></th>
					<td><input type="text" id="sz_verify_yandex" name="sz_verify_yandex" class="regular-text" value="<?php echo esc_attr( isset( $verify['yandex'] ) ? (string) $verify['yandex'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="sz_verify_google">Код подтверждения Google Search Console</label></th>
					<td><input type="text" id="sz_verify_google" name="sz_verify_google" class="regular-text" value="<?php echo esc_attr( isset( $verify['google'] ) ? (string) $verify['google'] : '' ); ?>"></td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="sz_idx_verify" class="button button-primary">Сохранить метки</button>
			</p>
		</form>
		<p>
			<button type="button" class="button" disabled>Проверить связь с Метрикой<?php echo $external ? '' : ' (до Этапа 8)'; ?></button>
			<button type="button" class="button" disabled>Проверить связь с Вебмастером<?php echo $external ? '' : ' (до Этапа 8)'; ?></button>
		</p>

		<h2>3. Суточная задача</h2>
		<p>
			Состояние внешних сервисов: <b><?php echo $external ? 'включены' : 'выключены (SZ_EXTERNAL_ON не задана)'; ?></b>.
			Следующий запуск: <b><?php echo esc_html( $next ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $next ), 'Y-m-d H:i' ) : '—' ); ?></b>.
		</p>
		<form method="post" style="margin-bottom:10px;">
			<?php wp_nonce_field( 'sz_idx_cron', 'sz_idx_cron_nonce' ); ?>
			<button type="submit" name="sz_idx_cron" class="button">Выполнить задачу сейчас</button>
		</form>
		<?php if ( $log ) : ?>
			<p><b>Журнал (последние записи):</b></p>
			<ul style="margin-left:18px;list-style:disc;">
				<?php foreach ( $log as $line ) : ?>
					<li><?php echo esc_html( (string) $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
