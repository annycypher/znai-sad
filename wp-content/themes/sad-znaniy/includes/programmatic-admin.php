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
 * Сохраняет интро/включение/SEO страниц.
 */
function sad_znaniy_programmatic_admin_save() {
	if ( ! isset( $_POST['pg'] ) || ! is_array( $_POST['pg'] ) ) {
		return;
	}
	$pages = get_option( 'sad_znaniy_programmatic_pages', array() );
	foreach ( wp_unslash( $_POST['pg'] ) as $key => $data ) {
		$key = sanitize_key( $key );
		if ( ! isset( $pages[ $key ] ) ) {
			continue;
		}
		$pages[ $key ]['intro']           = isset( $data['intro'] ) ? wp_kses_post( $data['intro'] ) : '';
		$pages[ $key ]['enabled']         = empty( $data['enabled'] ) ? 0 : 1;
		$pages[ $key ]['seo_title']       = isset( $data['seo_title'] ) ? sanitize_text_field( $data['seo_title'] ) : '';
		$pages[ $key ]['seo_description'] = isset( $data['seo_description'] ) ? sanitize_text_field( $data['seo_description'] ) : '';
	}
	update_option( 'sad_znaniy_programmatic_pages', $pages, false );
}

/**
 * Экран «Программатик».
 */
function sad_znaniy_programmatic_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['sz_pg_save'] ) && check_admin_referer( 'sz_pg_save', 'sz_pg_nonce' ) ) {
		sad_znaniy_programmatic_admin_save();
		echo '<div class="notice notice-success is-dismissible"><p>Сохранено.</p></div>';
	}

	$pages = get_option( 'sad_znaniy_programmatic_pages', array() );
	if ( empty( $pages ) ) {
		$pages = sad_znaniy_programmatic_matrix();
	}

	$family_labels = array(
		'region-month' => 'Работы по региону',
		'plant-region' => 'Когда сажать',
		'plant-month'  => 'Уход',
	);
	$status_labels = array(
		'live'  => 'живая',
		'draft' => 'черновик',
		'empty' => 'пустая',
	);
	?>
	<div class="wrap">
		<h1>Программатик-SEO</h1>
		<p><b>Для чего:</b> инструмент сам собирает SEO-страницы из календаря под низкочастотные запросы («когда сажать томат в Сибири», «работы в мае»). Вы заполняете календарь событиями — страницы генерируются автоматически, без ручного написания.</p>
		<p>Страницы строятся из данных календаря. <b>Живая</b> = включена + ≥5 событий + интро ≥2 предложений. Только «живые» попадают в sitemap.xml.</p>
		<details style="margin-bottom:14px;">
			<summary style="cursor:pointer;font-weight:600;">Как этим пользоваться (инструкция)</summary>
			<ol style="margin-top:8px;padding-left:20px;">
				<li><b>Включить страницу:</b> впишите интро (≥2 предложения, факт/цифра/риск) → галочка «Вкл.» → «Сохранить». Живая страница появится в sitemap.xml.</li>
				<li><b>Выключить:</b> снимите «Вкл.» → «Сохранить» — страница исчезает из sitemap (по прямому URL — 404).</li>
				<li><b>Добавить регион:</b> добавьте ≥5 событий календаря для этого региона + месяца — строка появится сама.</li>
				<li><b>SEO:</b> пустые title/description = автогенерация; заполненные = ручное переопределение.</li>
				<li><b>Темп:</b> не больше 10 включённых страниц в неделю. Пустышки (&lt;5 событий) не откроются.</li>
			</ol>
			<p style="margin-top:6px;">Подробнее — в <b>ADMINGUIDE.md → «12. Программатик-SEO»</b>.</p>
		</details>
		<style>
			.sz-status{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}
			.sz-live{background:#e7f0e9;color:#2e6b4f}.sz-draft{background:#fdf3e0;color:#a9714b}.sz-empty{background:#f1f1f1;color:#999}
		</style>
		<form method="post">
			<?php wp_nonce_field( 'sz_pg_save', 'sz_pg_nonce' ); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>URL / SEO</th><th>Семейство</th><th>Фраза</th><th>Статус</th><th>Событий</th><th>Интро</th><th>Вкл.</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $pages as $key => $page ) :
					$status = sad_znaniy_programmatic_status( $page );
					$pfx    = 'pg[' . esc_attr( $key ) . ']';
					?>
					<tr>
						<td style="max-width:280px;word-break:break-all;">
							<a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank"><?php echo esc_html( $page['url'] ); ?></a><br>
							<input type="text" name="<?php echo esc_attr( $pfx ); ?>[seo_title]" value="<?php echo esc_attr( $page['seo_title'] ); ?>" placeholder="SEO title (переопределение)" style="width:100%;margin-top:5px;">
							<input type="text" name="<?php echo esc_attr( $pfx ); ?>[seo_description]" value="<?php echo esc_attr( $page['seo_description'] ); ?>" placeholder="SEO description" style="width:100%;margin-top:5px;">
						</td>
						<td><?php echo esc_html( $family_labels[ $page['family'] ] ); ?></td>
						<td><?php echo esc_html( $page['phrase'] ); ?></td>
						<td><span class="sz-status sz-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_labels[ $status ] ); ?></span></td>
						<td><?php echo (int) $page['count']; ?></td>
						<td><textarea name="<?php echo esc_attr( $pfx ); ?>[intro]" rows="3" style="width:100%;"><?php echo esc_textarea( $page['intro'] ); ?></textarea></td>
						<td><input type="checkbox" name="<?php echo esc_attr( $pfx ); ?>[enabled]" value="1" <?php checked( $page['enabled'], 1 ); ?>></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit"><button type="submit" name="sz_pg_save" class="button button-primary">Сохранить</button></p>
		</form>
	</div>
	<?php
}
