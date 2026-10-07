<?php
/**
 * Этап 8.5 — раздел «📢 Дистрибуция»: 5 вкладок.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Пункт меню «📢 Дистрибуция».
 */
function sad_znaniy_social_admin_menu() {
	add_menu_page(
		__( 'Дистрибуция', 'sad-znaniy' ),
		'📢 ' . __( 'Дистрибуция', 'sad-znaniy' ),
		'manage_options',
		'sz-distribution',
		'sad_znaniy_social_render_page',
		'dashicons-share',
		58
	);
}
add_action( 'admin_menu', 'sad_znaniy_social_admin_menu' );

/**
 * Вкладки раздела.
 *
 * @return array
 */
function sad_znaniy_social_admin_tabs() {
	return array(
		'queue'    => __( 'Автопостинг', 'sad-znaniy' ),
		'settings' => __( 'Настройки каналов', 'sad-znaniy' ),
		'aff'      => __( 'Партнёрки', 'sad-znaniy' ),
		'erid'     => __( 'Маркировка', 'sad-znaniy' ),
		'help'     => __( 'Помощь', 'sad-znaniy' ),
	);
}

/**
 * Текущая вкладка.
 *
 * @return string
 */
function sad_znaniy_social_current_tab() {
	$tabs = sad_znaniy_social_admin_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'queue'; // phpcs:ignore WordPress.Security.NonceVerification
	return isset( $tabs[ $tab ] ) ? $tab : 'queue';
}

/**
 * Ссылка на вкладку раздела.
 *
 * @param string $tab Ключ вкладки.
 * @return string
 */
function sad_znaniy_social_tab_url( $tab ) {
	return add_query_arg( array( 'page' => 'sz-distribution', 'tab' => $tab ), admin_url( 'admin.php' ) );
}

/**
 * Экран раздела.
 */
function sad_znaniy_social_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tabs = sad_znaniy_social_admin_tabs();
	$tab  = sad_znaniy_social_current_tab();
	$msg  = isset( $_GET['sz_msg'] ) ? sanitize_key( wp_unslash( $_GET['sz_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="wrap">
		<h1><?php esc_html_e( '📢 Дистрибуция', 'sad-znaniy' ); ?></h1>

		<?php sad_znaniy_social_admin_notice( $msg ); ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab<?php echo ( $key === $tab ) ? ' nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( sad_znaniy_social_tab_url( $key ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<?php
		switch ( $tab ) {
			case 'settings':
				sad_znaniy_social_tab_settings();
				break;
			case 'aff':
				sad_znaniy_social_tab_aff();
				break;
			case 'erid':
				sad_znaniy_social_tab_erid();
				break;
			case 'help':
				sad_znaniy_social_tab_help();
				break;
			default:
				sad_znaniy_social_tab_queue();
		}
		?>
	</div>
	<?php
}

/**
 * Сообщения после действий.
 *
 * @param string $msg Код сообщения.
 */
function sad_znaniy_social_admin_notice( $msg ) {
	$text = '';
	$type = 'success';

	switch ( $msg ) {
		case 'start':
			$text = 'Автопостинг включён. Новые записи будут уходить в соцсети по расписанию.';
			break;
		case 'stop':
			$text = 'Автопостинг поставлен на паузу. Очередь сохранена.';
			$type = 'warning';
			break;
		case 'reset':
			$text = 'Граница анонсов сдвинута на «сейчас»: всё, что опубликовано раньше, повторно не уйдёт.';
			$type = 'warning';
			break;
		case 'queued':
			$text = 'Задание поставлено в очередь — уйдёт в ближайший прогон.';
			break;
		case 'queued_now':
			$text = 'Задание отправлено в очередь «сейчас» — уйдёт в течение 5 минут.';
			break;
		case 'cancelled':
			$text = 'Задание отменено.';
			$type = 'warning';
			break;
		case 'test':
			$res  = get_transient( 'sz_social_test_' . get_current_user_id() );
			$text = ( 'ok' === $res )
				? 'Проверка связи: тестовый пост отправлен успешно ✅'
				: 'Проверка связи не удалась ❌ ' . ( $res ? $res : 'нет данных' );
			$type = ( 'ok' === $res ) ? 'success' : 'error';
			delete_transient( 'sz_social_test_' . get_current_user_id() );
			break;
	}

	if ( '' === $text ) {
		return;
	}
	printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $text ) );
}

/**
 * Вкладка 1: мастер-выключатель, статистика, очередь.
 */
function sad_znaniy_social_tab_queue() {
	$enabled = sad_znaniy_social_is_enabled();
	$started = sad_znaniy_social_started_at();
	?>
	<h2><?php esc_html_e( 'Мастер-выключатель', 'sad-znaniy' ); ?></h2>

	<?php if ( $enabled ) : ?>
		<div class="notice notice-success inline" style="margin:10px 0;padding:12px 14px;">
			<p style="margin:0 0 8px;">
				<strong><?php esc_html_e( 'Автопостинг ВКЛЮЧЁН.', 'sad-znaniy' ); ?></strong>
				<?php echo esc_html( 'Анонсируются записи, опубликованные после ' . ( $started ? mysql2date( 'd.m.Y H:i', $started ) : '—' ) . '.' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'sz_social_switch' ); ?>
				<input type="hidden" name="action" value="sz_social_switch">
				<input type="hidden" name="to" value="stop">
				<?php submit_button( 'СТОП (пауза)', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:8px;">
				<?php wp_nonce_field( 'sz_social_switch' ); ?>
				<input type="hidden" name="action" value="sz_social_switch">
				<input type="hidden" name="to" value="reset">
				<button class="button" type="submit" onclick="return confirm('Сдвинуть границу анонсов на «сейчас»? Старые записи повторно не уйдут.');"><?php esc_html_e( 'Сдвинуть границу на «сейчас»', 'sad-znaniy' ); ?></button>
			</form>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline" style="margin:10px 0;padding:12px 14px;">
			<p style="margin:0 0 10px;">
				<strong><?php esc_html_e( 'Автопостинг ВЫКЛЮЧЕН.', 'sad-znaniy' ); ?></strong>
				<?php esc_html_e( 'Ничего не отправляется. Нажмите «СТАРТ», когда сайт уже на хостинге и каналы подключены: анонсироваться будут только записи, опубликованные после нажатия — старые не «выстрелят» пачкой.', 'sad-znaniy' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sz_social_switch' ); ?>
				<input type="hidden" name="action" value="sz_social_switch">
				<input type="hidden" name="to" value="start">
				<button class="button button-primary button-hero" type="submit">▶ <?php esc_html_e( 'СТАРТ автопостинга', 'sad-znaniy' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( ! sad_znaniy_social_external_allowed() ) : ?>
		<div class="notice notice-info inline" style="margin:10px 0;">
			<p><?php esc_html_e( 'Внешние запросы выключены (константа SZ_EXTERNAL_ON) — это локальный режим: в соцсети ничего не уходит. Включается после переноса на хостинг.', 'sad-znaniy' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	sad_znaniy_social_render_stats();
	sad_znaniy_social_render_queue_table();
}

/**
 * Статистика за 30 дней + мини-график на нативном canvas.
 */
function sad_znaniy_social_render_stats() {
	$counts = sad_znaniy_social_queue_counts();
	$stats  = sad_znaniy_social_stats( 30 );
	$all    = sad_znaniy_social_networks();

	$labels = array(
		'pending' => 'В очереди',
		'sent'    => 'Опубликовано',
		'error'   => 'Ошибок',
		'manual'  => 'Требует проверки',
		'skip'    => 'Отменено',
	);
	?>
	<h2><?php esc_html_e( 'Сводка очереди', 'sad-znaniy' ); ?></h2>
	<p>
		<?php
		$chunks = array();
		foreach ( $labels as $key => $label ) {
			$chunks[] = '<strong>' . esc_html( $label ) . ':</strong> ' . (int) ( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 );
		}
		echo wp_kses_post( implode( ' &nbsp;·&nbsp; ', $chunks ) );
		?>
	</p>

	<h2><?php esc_html_e( 'За 30 дней по сетям', 'sad-znaniy' ); ?></h2>
	<table class="widefat striped" style="max-width:720px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Сеть', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'Опубликовано', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'Ошибок', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'В очереди', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'Переходы по UTM', 'sad-znaniy' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $all as $key => $net ) : ?>
				<?php $row = isset( $stats['networks'][ $key ] ) ? $stats['networks'][ $key ] : array( 'sent' => 0, 'error' => 0, 'pending' => 0 ); ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $net['label'] ); ?></strong>
						<?php if ( empty( $net['ready'] ) ) : ?>
							<span style="color:#b32d2e;">(<?php echo esc_html( 'скоро, Партия ' . (int) $net['party'] ); ?>)</span>
						<?php endif; ?>
					</td>
					<td><?php echo (int) $row['sent']; ?></td>
					<td><?php echo (int) $row['error']; ?></td>
					<td><?php echo (int) $row['pending']; ?></td>
					<td>&mdash;</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Столбец «Переходы по UTM» наполнится в Партии 4, когда SEO-Хаб «Эффективность» начнёт считать utm_source.', 'sad-znaniy' ); ?></p>

	<?php
	$days = array();
	for ( $i = 29; $i >= 0; $i-- ) {
		$d          = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . $i . ' days' ) );
		$days[ $d ] = array(
			'sent'  => (int) ( isset( $stats['days'][ $d ]['sent'] ) ? $stats['days'][ $d ]['sent'] : 0 ),
			'error' => (int) ( isset( $stats['days'][ $d ]['error'] ) ? $stats['days'][ $d ]['error'] : 0 ),
		);
	}
	$sent_series  = array_values( wp_list_pluck( $days, 'sent' ) );
	$error_series = array_values( wp_list_pluck( $days, 'error' ) );
	?>
	<h3><?php esc_html_e( 'Динамика за 30 дней', 'sad-znaniy' ); ?></h3>
	<canvas id="szSocialChart" width="640" height="120" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;max-width:100%;"></canvas>
	<script>
	( function () {
		var c = document.getElementById( 'szSocialChart' );
		if ( ! c ) { return; }
		var ctx = c.getContext( '2d' );
		var sent = <?php echo wp_json_encode( $sent_series ); ?>;
		var err = <?php echo wp_json_encode( $error_series ); ?>;
		var max = Math.max( 1, Math.max.apply( null, sent.concat( err ) ) );
		var step = c.width / ( sent.length - 1 );
		function line( data, color ) {
			ctx.beginPath();
			ctx.strokeStyle = color;
			ctx.lineWidth = 2;
			for ( var i = 0; i < data.length; i++ ) {
				var x = i * step;
				var y = c.height - 10 - ( data[ i ] / max ) * ( c.height - 20 );
				if ( i === 0 ) { ctx.moveTo( x, y ); } else { ctx.lineTo( x, y ); }
			}
			ctx.stroke();
		}
		line( sent, '#2e6b4f' );
		line( err, '#b32d2e' );
		ctx.fillStyle = '#646970';
		ctx.font = '11px sans-serif';
		ctx.fillText( 'максимум за день: ' + max, 6, 12 );
	} )();
	</script>
	<p class="description"><span style="color:#2e6b4f;">&#9632;</span> <?php esc_html_e( 'опубликовано', 'sad-znaniy' ); ?> &nbsp; <span style="color:#b32d2e;">&#9632;</span> <?php esc_html_e( 'ошибки', 'sad-znaniy' ); ?></p>
	<?php
}

/**
 * Таблица очереди с фильтрами и действиями.
 */
function sad_znaniy_social_render_queue_table() {
	$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$network = isset( $_GET['network'] ) ? sanitize_key( wp_unslash( $_GET['network'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	$rows = sad_znaniy_social_queue_rows(
		array(
			'status'  => $status,
			'network' => $network,
			'search'  => $search,
			'limit'   => 100,
		)
	);

	$nets     = sad_znaniy_social_networks();
	$statuses = array(
		''        => 'Все статусы',
		'pending' => 'В очереди',
		'sent'    => 'Опубликовано',
		'error'   => 'Ошибка',
		'manual'  => 'Требует проверки',
		'skip'    => 'Отменено',
	);
	$badges   = array(
		'pending' => '#2271b1',
		'sent'    => '#2e6b4f',
		'error'   => '#b32d2e',
		'manual'  => '#996800',
		'skip'    => '#646970',
	);
	?>
	<h2><?php esc_html_e( 'Очередь', 'sad-znaniy' ); ?></h2>

	<form method="get" style="margin:10px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
		<input type="hidden" name="page" value="sz-distribution">
		<input type="hidden" name="tab" value="queue">
		<select name="status">
			<?php foreach ( $statuses as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="network">
			<option value=""><?php esc_html_e( 'Все сети', 'sad-znaniy' ); ?></option>
			<?php foreach ( $nets as $key => $net ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $network, $key ); ?>><?php echo esc_html( $net['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Поиск по статье', 'sad-znaniy' ); ?>">
		<button class="button"><?php esc_html_e( 'Показать', 'sad-znaniy' ); ?></button>
		<a class="button" href="<?php echo esc_url( sad_znaniy_social_tab_url( 'queue' ) ); ?>"><?php esc_html_e( 'Сбросить', 'sad-znaniy' ); ?></a>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width:55px;">ID</th>
				<th><?php esc_html_e( 'Запись', 'sad-znaniy' ); ?></th>
				<th style="width:110px;"><?php esc_html_e( 'Сеть', 'sad-znaniy' ); ?></th>
				<th style="width:140px;"><?php esc_html_e( 'Статус', 'sad-znaniy' ); ?></th>
				<th style="width:135px;"><?php esc_html_e( 'Отправка', 'sad-znaniy' ); ?></th>
				<th style="width:135px;"><?php esc_html_e( 'Отправлено', 'sad-znaniy' ); ?></th>
				<th style="width:55px;"><?php esc_html_e( 'Поп.', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'Ошибка', 'sad-znaniy' ); ?></th>
				<th style="width:250px;"><?php esc_html_e( 'Действия', 'sad-znaniy' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="9"><em><?php esc_html_e( 'Пока пусто. Задания появятся, когда автопостинг включён («СТАРТ») и выходит новая запись.', 'sad-znaniy' ); ?></em></td></tr>
			<?php endif; ?>
			<?php foreach ( (array) $rows as $row ) : ?>
				<?php
				$label = isset( $nets[ $row->network ]['label'] ) ? $nets[ $row->network ]['label'] : (string) $row->network;
				$color = isset( $badges[ $row->status ] ) ? $badges[ $row->status ] : '#646970';
				$s_lab = isset( $statuses[ $row->status ] ) ? $statuses[ $row->status ] : (string) $row->status;
				$edit  = get_edit_post_link( (int) $row->post_id );
				?>
				<tr>
					<td><?php echo (int) $row->id; ?></td>
					<td><strong><?php echo esc_html( get_the_title( (int) $row->post_id ) ); ?></strong><?php echo $edit ? ' <a href="' . esc_url( $edit ) . '">&#9998;</a>' : ''; ?></td>
					<td><?php echo esc_html( $label ); ?></td>
					<td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;">&#9679;</span> <?php echo esc_html( $s_lab ); ?></td>
					<td><?php echo esc_html( $row->scheduled_at ? mysql2date( 'd.m.Y H:i', $row->scheduled_at ) : '—' ); ?></td>
					<td><?php echo esc_html( $row->posted_at ? mysql2date( 'd.m.Y H:i', $row->posted_at ) : '—' ); ?></td>
					<td><?php echo (int) $row->attempts; ?></td>
					<td><?php echo esc_html( $row->error ? mb_substr( (string) $row->error, 0, 110 ) : '—' ); ?></td>
					<td><?php sad_znaniy_social_queue_actions( (int) $row->id, (string) $row->status ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Показаны последние 100 заданий. «Отправить сейчас» сдвигает время на текущее — задание уйдёт в ближайший прогон (в течение 5 минут).', 'sad-znaniy' ); ?></p>
	<?php
}

/**
 * Кнопки действий над заданием очереди.
 *
 * @param int    $id     ID задания.
 * @param string $status Статус задания.
 */
function sad_znaniy_social_queue_actions( $id, $status ) {
	$actions = array(
		array(
			'do'    => 'now',
			'label' => 'Отправить сейчас',
			'class' => 'button-primary',
			'when'  => in_array( $status, array( 'pending', 'error' ), true ),
		),
		array(
			'do'    => 'repeat',
			'label' => 'Повторить',
			'class' => 'button',
			'when'  => in_array( $status, array( 'error', 'skip', 'manual', 'sent' ), true ),
		),
		array(
			'do'    => 'cancel',
			'label' => 'Отменить',
			'class' => '',
			'when'  => in_array( $status, array( 'pending', 'error', 'manual' ), true ),
		),
	);

	foreach ( $actions as $action ) {
		if ( empty( $action['when'] ) ) {
			continue;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<?php wp_nonce_field( 'sz_social_action' ); ?>
			<input type="hidden" name="action" value="sz_social_action">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<input type="hidden" name="do" value="<?php echo esc_attr( $action['do'] ); ?>">
			<button class="button <?php echo esc_attr( $action['class'] ); ?> button-small" type="submit"><?php echo esc_html( $action['label'] ); ?></button>
		</form>
		<?php
	}
}

/**
 * ID записи для предпросмотра шаблонов.
 *
 * @return int
 */
function sad_znaniy_social_preview_post_id() {
	$ids = get_posts(
		array(
			'post_type'      => array( 'post', 'plant' ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Вкладка 2: каналы, шаблоны (живой предпросмотр), слоты и лимиты.
 */
function sad_znaniy_social_tab_settings() {
	$channels  = sad_znaniy_social_channels();
	$networks  = sad_znaniy_social_networks();
	$slots     = sad_znaniy_social_slots_list();
	$preview   = sad_znaniy_social_preview_post_id();
	$hints     = array(
		'telegram' => 'Telegram: BotFather → токен; chat_id — @username канала или числовой ID (бот должен быть админом).',
		'max'      => 'MAX: токен бота и chat_id канала (появится в Партии 2).',
		'vk'       => 'ВКонтакте: ключ доступа сообщества с правами wall и photos; owner_id — ID сообщества со знаком минус (Партия 3).',
	);

	$js_map = array(
		'{title}'    => $preview ? get_the_title( $preview ) : 'Пример заголовка статьи',
		'{excerpt}'  => $preview ? sad_znaniy_social_excerpt( $preview, 'telegram' ) : 'Короткий анонс статьи: 2–3 предложения.',
		'{link}'     => $preview ? (string) get_permalink( $preview ) : home_url( '/' ),
		'{tags}'     => $preview ? implode( ' ', sad_znaniy_social_hashtags( $preview ) ) : '#дача #сад',
		'{question}' => $preview ? (string) get_post_meta( $preview, '_sz_social_question', true ) : 'А что растёт у вас?',
		'{emoji}'    => $preview ? sad_znaniy_social_emoji( $preview ) : '🌱',
	);
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'sad_znaniy_social' ); ?>

		<h2><?php esc_html_e( 'Каналы', 'sad-znaniy' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Токены хранятся только в опциях и на экране маскируются. Оставьте поле как есть, чтобы не менять секрет; впишите «-», чтобы очистить.', 'sad-znaniy' ); ?></p>

		<?php foreach ( $networks as $key => $net ) : ?>
			<?php
			$row   = isset( $channels[ $key ] ) ? (array) $channels[ $key ] : array();
			$token = isset( $row['token'] ) ? (string) $row['token'] : '';
			$chat  = isset( $row['chat_id'] ) ? (string) $row['chat_id'] : '';
			?>
			<h3><?php echo esc_html( $net['label'] ); ?><?php echo empty( $net['ready'] ) ? ' <em>(отправка — Партия ' . (int) $net['party'] . ')</em>' : ''; ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Публиковать', 'sad-znaniy' ); ?></th>
					<td>
						<input type="hidden" name="sz_social_channels[<?php echo esc_attr( $key ); ?>][enabled]" value="0">
						<label>
							<input type="checkbox" name="sz_social_channels[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( 1, ! empty( $row['enabled'] ) ); ?>>
							<?php echo esc_html( 'Публиковать в ' . $net['label'] ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Токен', 'sad-znaniy' ); ?></th>
					<td>
						<input type="text" class="regular-text" autocomplete="off" name="sz_social_channels[<?php echo esc_attr( $key ); ?>][token]" value="<?php echo esc_attr( '' !== $token ? sad_znaniy_social_mask( $token ) : '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'chat_id / owner_id', 'sad-znaniy' ); ?></th>
					<td><input type="text" class="regular-text" name="sz_social_channels[<?php echo esc_attr( $key ); ?>][chat_id]" value="<?php echo esc_attr( $chat ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Проверить связь', 'sad-znaniy' ); ?></th>
					<td>
						<?php if ( empty( $net['ready'] ) ) : ?>
							<p class="description"><?php esc_html_e( 'Проверка появится, когда отправка в эту сеть будет подключена.', 'sad-znaniy' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Кнопка «Отправить тестовый пост» — в блоке «Проверка связи» под этой формой (после сохранения настроек).', 'sad-znaniy' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="description"><?php echo esc_html( isset( $hints[ $key ] ) ? $hints[ $key ] : '' ); ?></p>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Шаблоны постов', 'sad-znaniy' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Плейсхолдеры: {emoji} {title} {excerpt} {link} {tags} {question}. В Telegram и MAX заголовок оформляется жирным автоматически.', 'sad-znaniy' ); ?></p>
		<?php foreach ( $networks as $key => $net ) : ?>
			<h3><?php echo esc_html( $net['label'] ); ?></h3>
			<textarea class="large-text" rows="5" data-sz-tpl="<?php echo esc_attr( $key ); ?>" name="sz_social_templates[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( sad_znaniy_social_template( $key ) ); ?></textarea>
			<p><strong><?php esc_html_e( 'Предпросмотр', 'sad-znaniy' ); ?>:</strong></p>
			<pre data-sz-prev="<?php echo esc_attr( $key ); ?>" style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:10px;white-space:pre-wrap;max-width:760px;"></pre>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Слоты и лимиты', 'sad-znaniy' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Слоты времени', 'sad-znaniy' ); ?></th>
				<td>
					<?php foreach ( $slots as $i => $slot ) : ?>
						<input type="text" name="sz_social_slots[times][<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $slot ); ?>" style="width:80px;">
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Формат ЧЧ:ММ. Часовой пояс — как в «Настройки → Общие».', 'sad-znaniy' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Лимит в день на сеть', 'sad-znaniy' ); ?></th>
				<td><input type="number" min="1" max="50" name="sz_social_slots[limit]" value="<?php echo (int) sad_znaniy_social_daily_limit(); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Игнор-категории', 'sad-znaniy' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="sz_social_slots[ignore]" value="<?php echo esc_attr( implode( ', ', sad_znaniy_social_ignore_slugs() ) ); ?>" placeholder="retsepty, sluzhebnoe">
					<p class="description"><?php esc_html_e( 'Слаги рубрик через запятую: записи из них в соцсети не уходят.', 'sad-znaniy' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Хештеги по умолчанию', 'sad-znaniy' ); ?></th>
				<td>
					<input type="text" class="large-text" name="sz_social_slots[hashtags]" value="<?php echo esc_attr( sad_znaniy_social_default_hashtags() ); ?>">
					<p class="description"><?php esc_html_e( 'Используются для записей без меток (например, карточек растений).', 'sad-znaniy' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<h2><?php esc_html_e( 'Проверка связи', 'sad-znaniy' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Отправляет тестовый пост в канал. Зелёный результат — канал подключён, красный — покажет причину.', 'sad-znaniy' ); ?></p>
	<?php foreach ( $networks as $key => $net ) : ?>
		<?php if ( empty( $net['ready'] ) ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px;">
			<?php wp_nonce_field( 'sz_social_test' ); ?>
			<input type="hidden" name="action" value="sz_social_test">
			<input type="hidden" name="network" value="<?php echo esc_attr( $key ); ?>">
			<button class="button" type="submit"><?php echo esc_html( 'Проверить ' . $net['label'] ); ?></button>
		</form>
	<?php endforeach; ?>

	<script>
	( function () {
		var map = <?php echo wp_json_encode( $js_map, JSON_UNESCAPED_UNICODE ); ?>;
		function render( key ) {
			var ta = document.querySelector( '[data-sz-tpl="' + key + '"]' );
			var out = document.querySelector( '[data-sz-prev="' + key + '"]' );
			if ( ! ta || ! out ) { return; }
			var text = ta.value;
			Object.keys( map ).forEach( function ( k ) {
				text = text.split( k ).join( map[ k ] );
			} );
			out.textContent = text;
		}
		document.querySelectorAll( '[data-sz-tpl]' ).forEach( function ( ta ) {
			ta.addEventListener( 'input', function () { render( ta.getAttribute( 'data-sz-tpl' ) ); } );
			render( ta.getAttribute( 'data-sz-tpl' ) );
		} );
	} )();
	</script>
	<?php
}

/**
 * Вкладка 3: партнёрки (заготовка фазы 2).
 */
function sad_znaniy_social_tab_aff() {
	global $wpdb;

	$table  = $wpdb->prefix . 'sz_aff_links';
	$rows   = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC LIMIT 50" ); // phpcs:ignore WordPress.DB
	$clicks = sad_znaniy_social_aff_clicks();
	?>
	<div class="notice notice-info inline" style="margin:10px 0;">
		<p><strong><?php esc_html_e( 'Заготовка (фаза 2).', 'sad-znaniy' ); ?></strong> <?php esc_html_e( 'Партнёрские ссылки пока не заполняются — включим после переноса и обкатки автопостинга.', 'sad-znaniy' ); ?></p>
	</div>

	<h2><?php esc_html_e( 'Что уже готово', 'sad-znaniy' ); ?></h2>
	<ul style="list-style:disc;margin-left:20px;">
		<li><?php esc_html_e( 'Таблица ссылок sz_aff_links (слаг, магазин, целевой адрес).', 'sad-znaniy' ); ?></li>
		<li><?php esc_html_e( 'Редирект /go/{слаг}/ — отдаёт 302 на целевой адрес и считает переходы. Ссылку можно вставлять в статьи уже сейчас.', 'sad-znaniy' ); ?></li>
	</ul>

	<h2><?php esc_html_e( 'Ссылки', 'sad-znaniy' ); ?></h2>
	<table class="widefat striped" style="max-width:900px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Слаг', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'Магазин', 'sad-znaniy' ); ?></th>
				<th><?php esc_html_e( 'Адрес', 'sad-znaniy' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( 'Переходы', 'sad-znaniy' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( (array) $rows as $row ) : ?>
				<tr>
					<td><code>/go/<?php echo esc_html( $row->slug ); ?>/</code></td>
					<td><?php echo esc_html( $row->store ); ?></td>
					<td><?php echo esc_html( mb_substr( (string) $row->target_url, 0, 55 ) ); ?></td>
					<td><?php echo (int) ( isset( $clicks[ $row->slug ] ) ? $clicks[ $row->slug ] : 0 ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( '10 демо-строк с целевым адресом на этот сайт — заготовка. Управление появится вместе с партнёрской программой.', 'sad-znaniy' ); ?></p>

	<h2><?php esc_html_e( 'Чек-лист владельцу', 'sad-znaniy' ); ?></h2>
	<ol style="margin-left:20px;">
		<li><?php esc_html_e( 'Договоры с магазинами (семена, удобрения, инвентарь).', 'sad-znaniy' ); ?></li>
		<li><?php esc_html_e( 'Регистрация в ОРД, если ссылки пойдут в рекламных материалах.', 'sad-znaniy' ); ?></li>
		<li><?php esc_html_e( 'Пометка «реклама» у таких ссылок — требование закона.', 'sad-znaniy' ); ?></li>
	</ol>
	<?php
}

/**
 * Вкладка 4: маркировка (заготовка фазы 2).
 */
function sad_znaniy_social_tab_erid() {
	?>
	<div class="notice notice-info inline" style="margin:10px 0;">
		<p><strong><?php esc_html_e( 'Заготовка (фаза 2).', 'sad-znaniy' ); ?></strong> <?php esc_html_e( 'В базе уже есть колонка erid в очереди и мета записи _sz_erid — когда подключим ОРД, миграция не потребуется.', 'sad-znaniy' ); ?></p>
	</div>

	<h2><?php esc_html_e( 'Когда нужна маркировка', 'sad-znaniy' ); ?></h2>
	<table class="widefat striped" style="max-width:820px;">
		<thead><tr><th><?php esc_html_e( 'Что публикуем', 'sad-znaniy' ); ?></th><th style="width:240px;"><?php esc_html_e( 'Маркировка', 'sad-znaniy' ); ?></th></tr></thead>
		<tbody>
			<tr><td><?php esc_html_e( 'Анонс своей статьи (без чужих ссылок)', 'sad-znaniy' ); ?></td><td><strong><?php esc_html_e( 'не нужна', 'sad-znaniy' ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Пост с партнёрской (реферальной) ссылкой', 'sad-znaniy' ); ?></td><td><strong><?php esc_html_e( 'нужна', 'sad-znaniy' ); ?></strong></td></tr>
			<tr><td><?php esc_html_e( 'Свои платные услуги', 'sad-znaniy' ); ?></td><td><?php esc_html_e( 'спорно — уточнять', 'sad-znaniy' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Реклама РСЯ на сайте', 'sad-znaniy' ); ?></td><td><?php esc_html_e( 'маркирует сама система', 'sad-znaniy' ); ?></td></tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Порядок регистрации', 'sad-znaniy' ); ?></h2>
	<ol style="margin-left:20px;">
		<li><?php esc_html_e( 'Статус самозанятого или ИП.', 'sad-znaniy' ); ?></li>
		<li><?php esc_html_e( 'Приложение «Мой налог» — учёт дохода от рекламы.', 'sad-znaniy' ); ?></li>
		<li><?php esc_html_e( 'Регистрация в аккредитованном ОРД.', 'sad-znaniy' ); ?></li>
		<li><?php esc_html_e( 'Получение токенов и erid-идентификаторов для ссылок.', 'sad-znaniy' ); ?></li>
	</ol>
	<?php
}

/**
 * Вкладка 5: помощь — короткие инструкции.
 */
function sad_znaniy_social_tab_help() {
	$help = array(
		'Подключение Telegram за 5 минут' => array(
			'В Telegram откройте @BotFather → /newbot → задайте имя. Он пришлёт токен вида 123456:AA... — скопируйте его.',
			'Создайте канал (можно тестовый) и добавьте бота администратором канала с правом публикации.',
			'Узнайте chat_id: перешлите любой пост канала боту @userinfobot либо укажите @username канала (например, @moy_sad).',
			'Вкладка «Настройки каналов»: включите Telegram, вставьте токен и chat_id, сохраните.',
			'В блоке «Проверка связи» нажмите «Проверить Telegram» — в канале появится тестовый пост.',
		),
		'Подключение ВКонтакте (Партия 3)' => array(
			'Создайте сообщество → «Управление» → «Работа с API» → создайте ключ доступа с правами wall и photos.',
			'owner_id — числовой ID сообщества (для сообществ со знаком минус, например -12345678).',
			'Вставьте ключ и owner_id в «Настройки каналов».',
		),
		'Подключение MAX (Партия 2)' => array(
			'Интерфейс и поля уже на месте — отправка включится в Партии 2 этапа 8.5.',
			'Понадобятся токен бота и chat_id канала — как в Telegram.',
		),
		'Почему пост не ушёл: топ-5 причин' => array(
			'Автопостинг выключен — нажмите «СТАРТ» на вкладке «Автопостинг».',
			'Канал выключен: галочка «Публиковать» на вкладке «Настройки каналов».',
			'Не задан токен или chat_id — заполните и нажмите «Проверить связь».',
			'Токен истёк или недействителен (в очереди: «Токен ... недействителен») — обновите токен.',
			'Запись вышла раньше границы старта — в очереди нажмите «Отправить сейчас».',
			'Если в статье ссылка на чужой сайт, задание получает статус «Требует проверки»: уберите ссылку либо отправьте вручную.',
		),
		'Слоты и лимиты: как подобрать ритм' => array(
			'По умолчанию 3 слота (09:00 / 13:00 / 19:00) и лимит 3 поста в день на сеть.',
			'Несколько записей в один слот разводятся по времени — резких «вееров» публикаций не будет.',
			'Меняются на вкладке «Настройки каналов» → «Слоты и лимиты».',
		),
		'Когда понадобится маркировка' => array(
			'Анонсы своих статей — без маркировки. Партнёрские ссылки — с маркировкой (вкладка «Маркировка»).',
		),
	);
	?>
	<h2><?php esc_html_e( 'Помощь', 'sad-znaniy' ); ?></h2>
	<?php foreach ( $help as $title => $steps ) : ?>
		<details style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;margin:8px 0;max-width:900px;">
			<summary style="cursor:pointer;font-weight:600;"><?php echo esc_html( $title ); ?></summary>
			<ol style="margin:10px 0 0 20px;">
				<?php foreach ( $steps as $step ) : ?>
					<li style="margin-bottom:6px;"><?php echo esc_html( $step ); ?></li>
				<?php endforeach; ?>
			</ol>
		</details>
	<?php endforeach; ?>
	<?php
}








