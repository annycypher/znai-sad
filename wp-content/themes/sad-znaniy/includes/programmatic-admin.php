<?php
/**
 * Экран управления программатик-страницами (Этап 7.1, Партия 2).
 * Вкладка «📊 SEO-Хаб → Программатик» (в 7.2 добавятся соседние вкладки).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Вкладку «Программатик» регистрирует каркас SEO-Хаба
 * (includes/seo-hub.php): меню «📊 SEO-Хаб» → Ключи / Программатик /
 * Индексация / Эффективность.
 */

/**
 * Применяет сохранение к массиву страниц (чистая функция, без записи в БД).
 *
 * @param array $pages Все страницы матрицы.
 * @param array $input Данные формы pg[key][...].
 * @return array ['pages' => array, 'notices' => array]
 */
function sad_znaniy_programmatic_apply_save( $pages, $input ) {
	$limit         = 10;
	$blocked_limit = array();
	$blocked_cann  = array();
	$blocked_empty = array();
	$warn_live     = array();
	$pending_live  = array();

	foreach ( (array) $input as $raw_key => $data ) {
		// Ключи матрицы содержат двоеточия (rm:mid:5), sanitize_key их вырезает.
		$key = preg_replace( '/[^a-z0-9:_-]/', '', (string) $raw_key );
		if ( '' === $key || ! isset( $pages[ $key ] ) || ! is_array( $data ) ) {
			continue;
		}

		$old      = $pages[ $key ];
		$new      = $old;
		$was_live = 'live' === sad_znaniy_programmatic_status( $old );

		$new['intro']           = isset( $data['intro'] ) ? wp_kses_post( $data['intro'] ) : (string) $old['intro'];
		$new['seo_title']       = isset( $data['seo_title'] ) ? sanitize_text_field( $data['seo_title'] ) : (string) $old['seo_title'];
		$new['seo_description'] = isset( $data['seo_description'] ) ? sanitize_text_field( $data['seo_description'] ) : (string) $old['seo_description'];
		$new['enabled']         = empty( $data['enabled'] ) ? 0 : 1;

		if ( ! empty( $data['dismiss'] ) ) {
			$new['cannibal_dismissed'] = 1;
			$new['cannibal']           = array();
		}

		$literal = false !== mb_stripos( (string) $new['intro'], 'НУЖНО РЕШЕНИЕ ВЛАДЕЛЬЦА' );
		$needs   = ( ! empty( $new['cannibal'] ) && empty( $new['cannibal_dismissed'] ) ) || $literal;

		if ( (int) $new['count'] < 5 ) {
			if ( ! empty( $new['enabled'] ) ) {
				$new['enabled']  = 0;
				$blocked_empty[] = (string) $new['phrase'];
			}
		} elseif ( $needs && ! $was_live ) {
			if ( ! empty( $new['enabled'] ) ) {
				$new['enabled'] = 0;
				$blocked_cann[] = (string) $new['phrase'];
			}
		} elseif ( $needs && $was_live ) {
			$warn_live[] = (string) $new['phrase'];
		}

		$new_status = sad_znaniy_programmatic_status( $new );
		if ( 'live' === $new_status ) {
			if ( ! $was_live ) {
				$pending_live[] = $key;
			}
		} else {
			$new['enabled_at'] = '';
		}

		$pages[ $key ] = $new;
	}

	// Сначала учитываем уже включённые за 7 дней (освобождённые слоты уже
	// обнулены выше), затем выдаём оставшиеся слоты новым живым страницам.
	$week_count = sad_znaniy_programmatic_week_live_count( $pages );
	foreach ( $pending_live as $key ) {
		if ( $week_count >= $limit ) {
			$pages[ $key ]['enabled']    = 0;
			$pages[ $key ]['enabled_at'] = '';
			$blocked_limit[]             = (string) $pages[ $key ]['phrase'];
			continue;
		}
		$week_count++;
		$pages[ $key ]['enabled_at'] = current_time( 'mysql' );
	}

	$notices = array();
	if ( $blocked_empty ) {
		$notices[] = array(
			'type' => 'warning',
			'text' => 'Не включены пустышки (<5 событий): ' . implode( '; ', array_slice( $blocked_empty, 0, 5 ) ) . ( count( $blocked_empty ) > 5 ? '…' : '' ) . '.',
		);
	}
	if ( $blocked_cann ) {
		$notices[] = array(
			'type' => 'warning',
			'text' => 'Не включены из-за возможного пересечения со статьями: ' . implode( '; ', array_slice( $blocked_cann, 0, 5 ) ) . ( count( $blocked_cann ) > 5 ? '…' : '' ) . '. Снимите метку «НУЖНО РЕШЕНИЕ ВЛАДЕЛЬЦА» и сохраните снова.',
		);
	}
	if ( $blocked_limit ) {
		$notices[] = array(
			'type' => 'warning',
			'text' => 'Лимит 10 новых живых страниц за 7 дней: отклонены ' . implode( '; ', array_slice( $blocked_limit, 0, 5 ) ) . ( count( $blocked_limit ) > 5 ? '…' : '' ) . '.',
		);
	}
	if ( $warn_live ) {
		$notices[] = array(
			'type' => 'warning',
			'text' => 'У живых страниц найдено возможное пересечение (не отключены автоматически): ' . implode( '; ', array_slice( $warn_live, 0, 5 ) ) . ( count( $warn_live ) > 5 ? '…' : '' ) . '.',
		);
	}
	if ( ! $blocked_empty && ! $blocked_cann && ! $blocked_limit ) {
		$notices[] = array(
			'type' => 'success',
			'text' => 'Сохранено. Новых живых страниц за последние 7 дней: ' . (int) $week_count . '/10.',
		);
	}

	return array(
		'pages'   => $pages,
		'notices' => $notices,
	);
}

/**
 * Сохраняет интро/включение/SEO страниц.
 *
 * @return array Уведомления для экрана.
 */
function sad_znaniy_programmatic_admin_save() {
	if ( ! isset( $_POST['pg'] ) || ! is_array( $_POST['pg'] ) ) {
		return array();
	}

	$pages  = get_option( 'sad_znaniy_programmatic_pages', array() );
	$result = sad_znaniy_programmatic_apply_save( $pages, wp_unslash( $_POST['pg'] ) );
	update_option( 'sad_znaniy_programmatic_pages', $result['pages'], false );

	return $result['notices'];
}

/**
 * Экран «Программатик».
 */
function sad_znaniy_programmatic_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notices = array();
	if ( isset( $_POST['sz_pg_save'] ) && check_admin_referer( 'sz_pg_save', 'sz_pg_nonce' ) ) {
		$notices = sad_znaniy_programmatic_admin_save();
	}

	$all = get_option( 'sad_znaniy_programmatic_pages', array() );
	if ( empty( $all ) ) {
		$all = sad_znaniy_programmatic_matrix();
	}

	$family = isset( $_GET['family'] ) ? sanitize_key( wp_unslash( $_GET['family'] ) ) : '';
	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

	$filtered = sad_znaniy_programmatic_admin_filter( $all, $family, $status, $search );
	$total    = count( $filtered );
	$per_page = 50;
	$max_page = max( 1, (int) ceil( $total / $per_page ) );
	$paged    = min( $paged, $max_page );
	$rows     = array_slice( $filtered, ( $paged - 1 ) * $per_page, $per_page, true );
	$week     = sad_znaniy_programmatic_week_live_count( $all );
	?>
	<div class="wrap">
		<h1>Программатик-SEO</h1>
		<p><b>Для чего:</b> инструмент сам собирает SEO-страницы из календаря под низкочастотные запросы («когда сажать томат в Сибири», «работы в мае»). Вы заполняете календарь событиями — страницы генерируются автоматически, без ручного написания.</p>
		<p>Страницы строятся из данных календаря. <b>Живая</b> = включена + ≥5 событий + интро ≥2 предложений. Только «живые» попадают в sitemap.xml.</p>
		<details style="margin-bottom:14px;">
			<summary style="cursor:pointer;font-weight:600;">Как этим пользоваться (инструкция)</summary>
			<ol style="margin-top:8px;padding-left:20px;">
				<li><b>Страницы:</b> 50 строк на страницу, фильтры по семейству/статусу/поиску. Сохраняется только текущая страница — переходите по страницам и сохраняйте.</li>
				<li><b>Включить:</b> впишите интро (≥2 предложения, факт/цифра/риск) → галочка «Вкл.» → «Сохранить страницу». Живая страница появится в sitemap.xml.</li>
				<li><b>Выключить:</b> снимите «Вкл.» → «Сохранить» — страница исчезает из sitemap (по прямому URL — 404).</li>
				<li><b>Лимит:</b> не больше 10 новых живых страниц за 7 дней. 11-я отклонится с предупреждением; счётчик — в сводке выше.</li>
				<li><b>Пересечение со статьями:</b> метка «НУЖНО РЕШЕНИЕ ВЛАДЕЛЬЦА» блокирует включение. Проверьте статью, поставьте «Снять метку», сохраните, затем включайте.</li>
				<li><b>Пустышки</b> (&lt;5 событий) не включаются. <b>SEO:</b> пустые title/description = автогенерация; заполненные = ручное переопределение.</li>
			</ol>
			<p style="margin-top:6px;">Подробнее — в <b>ADMINGUIDE.md → «12. Программатик-SEO»</b>.</p>
		</details>
		<style>
			.sz-status{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}
			.sz-live{background:#e7f0e9;color:#2e6b4f}.sz-draft{background:#fdf3e0;color:#a9714b}.sz-empty{background:#f1f1f1;color:#999}.sz-needs,.sz-need{background:#fdeaea;color:#a94442}
		</style>
		<?php sad_znaniy_programmatic_admin_notices( $notices ); ?>
		<?php sad_znaniy_programmatic_admin_summary( $all, $week ); ?>
		<?php sad_znaniy_programmatic_admin_filters( $family, $status, $search ); ?>
		<form method="post">
			<?php wp_nonce_field( 'sz_pg_save', 'sz_pg_nonce' ); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>URL / SEO</th><th>Семейство</th><th>Фраза</th><th>Статус</th><th>Событий</th><th>Интро</th><th>Вкл.</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $key => $page ) { sad_znaniy_programmatic_admin_row( $key, $page ); } ?>
				</tbody>
			</table>
			<p class="submit"><button type="submit" name="sz_pg_save" class="button button-primary">Сохранить страницу</button></p>
		</form>
		<?php sad_znaniy_programmatic_admin_pagination( $paged, $max_page, $family, $status, $search ); ?>
	</div>
	<?php
}

/**
 * Подписи семейств.
 *
 * @return array
 */
function sad_znaniy_programmatic_family_labels() {
	return array(
		'region-month' => 'Работы по региону',
		'plant-region' => 'Когда сажать',
		'plant-month'  => 'Уход',
	);
}

/**
 * Подписи статусов.
 *
 * @return array
 */
function sad_znaniy_programmatic_status_labels() {
	return array(
		'live'  => 'живая',
		'draft' => 'черновик',
		'empty' => 'пустая',
		'needs' => 'нужно решение',
	);
}

/**
 * Фильтрует и сортирует страницы для экрана.
 *
 * @param array  $all    Все страницы.
 * @param string $family Семейство.
 * @param string $status Статус.
 * @param string $search Поиск.
 * @return array
 */
function sad_znaniy_programmatic_admin_filter( $all, $family, $status, $search ) {
	$family = (string) $family;
	$status = (string) $status;
	$search = trim( (string) $search );
	$needle = ( '' !== $search ) ? mb_strtolower( $search, 'UTF-8' ) : '';
	$out    = array();

	foreach ( (array) $all as $key => $page ) {
		if ( '' !== $family && (string) $page['family'] !== $family ) {
			continue;
		}

		$current = sad_znaniy_programmatic_status( $page );
		if ( '' !== $status ) {
			if ( 'needs' === $status ) {
				$literal = false !== mb_stripos( (string) $page['intro'], 'НУЖНО РЕШЕНИЕ ВЛАДЕЛЬЦА' );
				$cann    = ! empty( $page['cannibal'] ) && empty( $page['cannibal_dismissed'] );
				if ( ! $literal && ! $cann ) {
					continue;
				}
			} elseif ( $current !== $status ) {
				continue;
			}
		}

		if ( '' !== $needle ) {
			$hay = mb_strtolower( (string) $page['phrase'] . ' ' . (string) $page['url'] . ' ' . $key, 'UTF-8' );
			if ( false === mb_strpos( $hay, $needle ) ) {
				continue;
			}
		}

		$out[ $key ] = $page;
	}

	uasort(
		$out,
		static function ( $a, $b ) {
			return strcmp( (string) $a['family'] . '|' . (string) $a['phrase'], (string) $b['family'] . '|' . (string) $b['phrase'] );
		}
	);

	return $out;
}

/**
 * Выводит уведомления после сохранения.
 *
 * @param array $notices Уведомления.
 */
function sad_znaniy_programmatic_admin_notices( $notices ) {
	foreach ( (array) $notices as $notice ) {
		$type = isset( $notice['type'] ) ? (string) $notice['type'] : 'info';
		if ( ! in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ) {
			$type = 'info';
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) $notice['text'] ) . '</p></div>';
	}
}

/**
 * Сводка по всем страницам.
 *
 * @param array $all  Все страницы.
 * @param int   $week Включено живых за 7 дней.
 */
function sad_znaniy_programmatic_admin_summary( $all, $week ) {
	$counts  = array(
		'live'  => 0,
		'draft' => 0,
		'empty' => 0,
		'needs' => 0,
	);
	$flagged = 0;

	foreach ( (array) $all as $page ) {
		$status = sad_znaniy_programmatic_status( $page );
		if ( isset( $counts[ $status ] ) ) {
			$counts[ $status ]++;
		}
		if ( ! empty( $page['cannibal'] ) && empty( $page['cannibal_dismissed'] ) ) {
			$flagged++;
		}
	}

	echo '<p><b>Всего:</b> ' . (int) count( $all )
		. ' · <b>Живых:</b> ' . (int) $counts['live']
		. ' · <b>Черновиков:</b> ' . (int) $counts['draft']
		. ' · <b>Пустых:</b> ' . (int) $counts['empty']
		. ' · <b>Нужно решение:</b> ' . (int) $counts['needs']
		. ' · <b>С пересечением:</b> ' . (int) $flagged
		. ' · <b>Включено за 7 дней:</b> ' . (int) $week . '/10</p>';
}

/**
 * Фильтры экрана.
 *
 * @param string $family Семейство.
 * @param string $status Статус.
 * @param string $search Поиск.
 */
function sad_znaniy_programmatic_admin_filters( $family, $status, $search ) {
	$families = sad_znaniy_programmatic_family_labels();
	$statuses = sad_znaniy_programmatic_status_labels();
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:10px 0 12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
		<input type="hidden" name="page" value="sad-seo-hub-programmatic">
		<select name="family">
			<option value="">Все семейства</option>
			<?php foreach ( $families as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $family, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="status">
			<option value="">Все статусы</option>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Фраза или URL">
		<button type="submit" class="button">Показать</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sad-seo-hub-programmatic' ) ); ?>">Сбросить</a>
	</form>
	<?php
}

/**
 * Постраничная навигация.
 *
 * @param int    $paged    Текущая страница.
 * @param int    $max_page Всего страниц.
 * @param string $family   Семейство.
 * @param string $status   Статус.
 * @param string $search   Поиск.
 */
function sad_znaniy_programmatic_admin_pagination( $paged, $max_page, $family, $status, $search ) {
	if ( $max_page <= 1 ) {
		return;
	}

	$args = array( 'page' => 'sad-seo-hub-programmatic' );
	if ( '' !== (string) $family ) {
		$args['family'] = $family;
	}
	if ( '' !== (string) $status ) {
		$args['status'] = $status;
	}
	if ( '' !== (string) $search ) {
		$args['s'] = $search;
	}

	$base = add_query_arg( $args, admin_url( 'admin.php' ) ) . '&paged=%#%';
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo paginate_links(
		array(
			'base'      => $base,
			'format'    => '',
			'current'   => (int) $paged,
			'total'     => (int) $max_page,
			'type'      => 'plain',
			'prev_text' => '‹',
			'next_text' => '›',
		)
	);
	echo '</div></div>';
}

/**
 * Строка таблицы программатик-страниц.
 *
 * @param string $key  Ключ страницы.
 * @param array  $page Запись страницы.
 */
function sad_znaniy_programmatic_admin_row( $key, $page ) {
	$status      = sad_znaniy_programmatic_status( $page );
	$families    = sad_znaniy_programmatic_family_labels();
	$statuses    = sad_znaniy_programmatic_status_labels();
	$literal     = false !== mb_stripos( (string) $page['intro'], 'НУЖНО РЕШЕНИЕ ВЛАДЕЛЬЦА' );
	$cannibal    = ! empty( $page['cannibal'] ) && empty( $page['cannibal_dismissed'] );
	$is_live     = ( 'live' === $status );
	$is_empty    = ( (int) $page['count'] < 5 );
	$disable     = $is_empty || ( ( $cannibal || $literal ) && ! $is_live );
	$pfx         = 'pg[' . esc_attr( $key ) . ']';
	$enabled     = ! empty( $page['enabled'] );
	$family      = isset( $families[ $page['family'] ] ) ? $families[ $page['family'] ] : (string) $page['family'];
	$status_text = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	?>
	<tr>
		<td style="max-width:280px;word-break:break-all;">
			<a href="<?php echo esc_url( (string) $page['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $page['url'] ); ?></a><br>
			<input type="text" name="<?php echo esc_attr( $pfx ); ?>[seo_title]" value="<?php echo esc_attr( (string) ( $page['seo_title'] ?? '' ) ); ?>" placeholder="SEO title (переопределение)" style="width:100%;margin-top:5px;">
			<input type="text" name="<?php echo esc_attr( $pfx ); ?>[seo_description]" value="<?php echo esc_attr( (string) ( $page['seo_description'] ?? '' ) ); ?>" placeholder="SEO description" style="width:100%;margin-top:5px;">
		</td>
		<td><?php echo esc_html( $family ); ?></td>
		<td><?php echo esc_html( (string) $page['phrase'] ); ?></td>
		<td>
			<span class="sz-status sz-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_text ); ?></span>
			<?php if ( $literal ) : ?>
				<br><span class="sz-status sz-need">нужно решение владельца</span>
			<?php endif; ?>
			<?php if ( $cannibal ) : ?>
				<div style="margin-top:5px;font-size:12px;line-height:1.45;">
					<strong>Возможное пересечение:</strong>
					<?php foreach ( (array) $page['cannibal'] as $match ) : ?>
						<br>— <a href="<?php echo esc_url( (string) ( $match['url'] ?? '' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) ( $match['title'] ?? '' ) ); ?></a>
					<?php endforeach; ?>
					<br><label style="display:inline-block;margin-top:4px;"><input type="checkbox" name="<?php echo esc_attr( $pfx ); ?>[dismiss]" value="1"> Снять метку</label>
				</div>
			<?php endif; ?>
			<?php if ( $enabled && ! $is_live && ! $is_empty && ! $cannibal && ! $literal ) : ?>
				<br><span style="font-size:11px;color:#a9714b;">включена, но интро &lt;2 предложений</span>
			<?php endif; ?>
			<?php if ( $is_live && $cannibal ) : ?>
				<br><span style="font-size:11px;color:#a9714b;">живая: пересечение не блокирует, но проверьте</span>
			<?php endif; ?>
		</td>
		<td><?php echo (int) $page['count']; ?></td>
		<td><textarea name="<?php echo esc_attr( $pfx ); ?>[intro]" rows="3" style="width:100%;"><?php echo esc_textarea( (string) $page['intro'] ); ?></textarea></td>
		<td><input type="checkbox" name="<?php echo esc_attr( $pfx ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> <?php disabled( $disable ); ?>></td>
	</tr>
	<?php
}

