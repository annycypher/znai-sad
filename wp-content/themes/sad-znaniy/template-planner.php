<?php
/**
 * Template Name: Планировщик участка
 *
 * Этап 6 «Планировщик участка» (Партия 1): каркас интерфейса по демо
 * _templates/planner-demo.html — тулбар, палитра, canvas, панели, мастер,
 * мобильный док и лист печати. Логика — assets/js/planner.js,
 * стили — assets/css/planner.css (всё в области #plnRoot).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pln_regions = sad_znaniy_planner_regions();
$pln_region  = sad_znaniy_current_region();
if ( '' === $pln_region ) {
	$pln_region = 'mid';
}

get_header();
?>
<div class="pln" id="plnRoot">

	<header class="topbar">
		<a class="pln-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">Сад <span>знаний</span></a>
		<div class="plan-name">
			<input id="planName" value="Мой участок" aria-label="Название плана">
			<span class="saved-mark" id="savedMark">сохранено</span>
		</div>
		<div class="pln-actions">
			<button type="button" class="tb hide-m" id="btnUndo" disabled title="Отменить (Ctrl+Z)" aria-label="Отменить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg></button>
			<button type="button" class="tb hide-m" id="btnRedo" disabled title="Вернуть (Ctrl+Y)" aria-label="Вернуть"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg></button>
			<button type="button" class="tb on" id="btnDims" title="Размерные линии" aria-label="Размерные линии"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z"/><path d="m7.5 10.5 2 2M10.5 7.5l2 2M13.5 4.5l2 2M4.5 13.5l2 2"/></svg><span>Размеры</span></button>
			<label class="pln-region" for="plnRegion"><span class="pln-region-lbl">Регион</span>
				<select id="plnRegion">
					<?php foreach ( $pln_regions as $pln_r ) : ?>
						<option value="<?php echo esc_attr( $pln_r['key'] ); ?>"<?php echo selected( $pln_region, $pln_r['key'], false ); ?>><?php echo esc_html( $pln_r['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" class="tb pln-share hide-m" id="btnShare" title="Поделиться планом" aria-label="Поделиться">🔗 <span>Поделиться</span></button>
			<button type="button" class="tb hide-m" id="btnJpg" title="Сохранить как JPG" aria-label="Сохранить как JPG"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg><span>JPG</span></button>
			<button type="button" class="tb hide-m" id="btnPrint" title="Печать / PDF" aria-label="Печать или PDF"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="8"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/></svg><span>Печать/PDF</span></button>
			<button type="button" class="tb hide-m" id="btnIcs" title="В календарь телефона (.ics)" aria-label="В календарь телефона">🗓 <span>Календарь</span></button>
			<button type="button" class="tb dark" id="btnSave" title="Сохранить" aria-label="Сохранить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg><span>Сохранить</span></button>
		</div>
	</header>
	<div class="app">
		<aside class="side side-left" id="paneLeft">
			<div class="pane-title">Элементы участка</div>
			<div class="palette">
				<div class="pal-item" data-type="bed"><span class="pal-ico i-bed"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="8" width="18" height="8" rx="2"/><path d="M3 12h18"/></svg></span><small>Грядка</small></div>
				<div class="pal-item" data-type="tree"><span class="pal-ico i-tree"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="M12 15v7M9 22h6"/></svg></span><small>Дерево</small></div>
				<div class="pal-item" data-type="bush"><span class="pal-ico i-bush"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="13" r="5"/><circle cx="15.5" cy="13.5" r="4.5"/></svg></span><small>Куст</small></div>
				<div class="pal-item" data-type="house"><span class="pal-ico i-house"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 9-8 9 8v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V11Z"/><path d="M9 21v-8h6v8"/></svg></span><small>Дом</small></div>
				<div class="pal-item" data-type="build"><span class="pal-ico i-build"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="1"/><path d="M4 12h16M12 4v16"/></svg></span><small>Постройка</small></div>
				<div class="pal-item" data-type="green"><span class="pal-ico i-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 14c4-8 12-8 16 0-4 8-12 8-16 0Z"/><path d="M8 20c1-2 2-3 4-4s3-2 4-4"/></svg></span><small>Теплица</small></div>
				<div class="pal-item" data-type="comp"><span class="pal-ico i-comp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 3h10l-1 5H8L7 3Z"/><rect x="5" y="8" width="14" height="13" rx="2"/><path d="M9 13h6"/></svg></span><small>Компост</small></div>
				<div class="pal-item" data-type="path"><span class="pal-ico i-path"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 21c2-6 4-9 8-12s6-5 8-6"/><path d="M6 19c1.5-4.5 3.5-7 7-9.5S18.5 6 20 5" stroke-dasharray="2 3"/></svg></span><small>Дорожка</small></div>
				<div class="pal-item" data-type="water"><span class="pal-ico i-water"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c3.5 4 6 7.2 6 10.5a6 6 0 0 1-12 0C6 10.2 8.5 7 12 3Z"/></svg></span><small>Вода</small></div>
				<div class="pal-item" data-type="rest"><span class="pal-ico i-rest"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a8 8 0 0 1 8 8H4a8 8 0 0 1 8-8Z"/><path d="M12 11v9M8 21h8"/></svg></span><small>Зона отдыха</small></div>
			</div>
			<p class="pln-hint-text">
				💡 Тяните за <b>угол элемента</b> — размер, за <b>центр</b> — перенос.<br>
				Тяните за <b>угол участка</b> — изменить размер участка.<br>
				Alt+клик — копия · колесо/щипок — зум · пустое место — панорама
			</p>
		</aside>
		<main class="canvas-wrap">
			<div class="board-shell" id="shell">
				<canvas id="board"></canvas>
				<div class="zoom">
					<button type="button" class="zbtn" id="zin" aria-label="Приблизить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></button>
					<button type="button" class="zbtn" id="zout" aria-label="Отдалить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14"/></svg></button>
					<button type="button" class="zbtn" id="zfit" aria-label="Вписать"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></button>
				</div>
				<div class="toasts" id="toasts"></div>
				<div class="hint-bar" id="hintBar">
					<span class="hint-chip">Выберите элемент · <b>колесо / щипок</b> — зум · <b>углы участка</b> — размер участка · <b>пустое место</b> — панорама</span>
				</div>
				<div class="bottomline" id="bottomline" hidden></div>
			</div>

			<div class="wizard" id="wizard">
				<div class="wiz-card">
					<div class="wiz-step on" data-step="1">
						<h2>Давайте спланируем участок 🌱</h2>
						<p class="sub">Два числа — и будет стартовая схема. Всё потом можно менять.</p>
						<div class="wiz-size">
							<input type="number" id="wW" value="20" min="4" max="100" aria-label="Ширина участка"> ×
							<input type="number" id="wH" value="30" min="4" max="100" aria-label="Длина участка"> метров
						</div>
						<div class="wiz-nav"><button type="button" class="wbtn" id="wNext1">Далее →</button></div>
					</div>
					<div class="wiz-step" data-step="2">
						<h2>Где стоит дом?</h2>
						<p class="sub">Кликните на схеме — дом поставится. Тень учтём в подсказках.</p>
						<div class="wiz-nav"><button type="button" class="wbtn light" id="wBack2">← Назад</button><button type="button" class="wbtn" id="wNext2">Далее →</button></div>
					</div>
					<div class="wiz-step" data-step="3">
						<h2>Что хотите выращивать?</h2>
						<div class="wiz-crops" id="wizCrops"></div>
						<div class="wiz-nav"><button type="button" class="wbtn light" id="wBack3">← Назад</button><button type="button" class="wbtn" id="wFinish">Создать план ✓</button></div>
					</div>
					<div class="wiz-dots"><i class="on"></i><i></i><i></i></div>
				</div>
			</div>
		</main>
		<aside class="side side-right" id="paneRight">
			<div class="pane-title">Свойства выбранного</div>
			<div id="selCard" class="selected-card" hidden>
				<div class="sel-title" id="selTitle">Грядка</div>
				<div class="sel-size" id="selSizeRow">
					Ш × Д, м: <input type="number" id="selW" step="0.5" min="0.5" aria-label="Ширина элемента"> × <input type="number" id="selH" step="0.5" min="0.5" aria-label="Длина элемента">
				</div>
				<div class="crop-pick" id="cropPick">
					<label class="pane-title" for="cropSearch" style="margin-bottom:6px">Культура</label>
					<input class="crop-input" id="cropSearch" placeholder="Начните вводить…" autocomplete="off">
					<div class="crop-list" id="cropList" hidden></div>
				</div>
				<button type="button" class="btn-dup" id="selDup">⧉ Дублировать</button>
				<button type="button" class="btn-del" id="selDel">Удалить элемент</button>
			</div>
			<div id="plotCard" class="plot-card" hidden>
				<div class="sel-title">🌱 Участок</div>
				<div class="sel-size">
					Ш × Д, м: <input type="number" id="plW" step="0.5" min="4" max="100" aria-label="Ширина участка"> × <input type="number" id="plH" step="0.5" min="4" max="100" aria-label="Длина участка">
				</div>
				<div class="pln-muted-note">Тяните за углы участка на плане — размер меняется в реальном времени</div>
			</div>
			<div id="selEmpty" hidden class="pln-muted-note">Кликните по элементу на плане.</div>
			<div class="pane-title" style="margin-top:18px">Сводка участка</div>
			<div id="summary"></div>
		</aside>
	</div>

	<nav class="m-bottom">
		<button type="button" class="m-btn" id="mPal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Элементы</button>
		<button type="button" class="m-btn" id="mUndo" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>Отменить</button>
		<button type="button" class="m-btn on" id="mBoard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8"/></svg>План</button>
		<button type="button" class="m-btn" id="mDims"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z"/></svg>Размеры</button>
		<button type="button" class="m-btn" id="mExport"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>Экспорт</button>
		<button type="button" class="m-btn" id="mSum"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11H5a2 2 0 0 0-2 2v7h6v-9ZM14 7h-4a2 2 0 0 0-2 2v11h6V7Z"/><path d="M19 3h-5a2 2 0 0 0-2 2v15h7V3Z" opacity=".4"/></svg>Сводка</button>
	</nav>

	<div class="export-sheet" id="exportSheet">
		<button type="button" class="tb dark" id="sShare">🔗 Поделиться</button>
		<button type="button" class="tb" id="sJpg">🖼 JPG</button>
		<button type="button" class="tb" id="sPrint">🖨 Печать / PDF</button>
		<button type="button" class="tb" id="sIcs">🗓 Календарь (.ics)</button>
		<button type="button" class="tb" id="sClose">✕ Закрыть</button>
	</div>
	<div class="pln-gate" id="plnGate" hidden>
		<div class="pln-gate-back" data-gate-close></div>
		<div class="pln-gate-card" role="dialog" aria-modal="true" aria-labelledby="plnGateTitle">
			<button type="button" class="pln-gate-x" data-gate-close aria-label="Закрыть">✕</button>
			<h2 id="plnGateTitle">Сохраните план и распечатайте</h2>
			<p class="pln-gate-text">Войдите или создайте бесплатный аккаунт — план будет храниться в личном кабинете, а напоминания о работах придут в календарь телефона. Картинку JPG можно сохранить и без входа.</p>
			<?php
			if ( ! is_user_logged_in() ) {
				wp_login_form(
					array(
						'redirect'       => esc_url( add_query_arg( 'print', '1', get_permalink() ) ),
						'label_username' => __( 'Логин или e-mail', 'sad-znaniy' ),
						'label_password' => __( 'Пароль', 'sad-znaniy' ),
						'label_remember' => __( 'Запомнить меня', 'sad-znaniy' ),
						'label_log_in'   => __( 'Войти и продолжить', 'sad-znaniy' ),
						'remember'       => true,
					)
				);
			}
			?>
			<?php if ( get_option( 'users_can_register' ) ) : ?>
				<p class="pln-gate-reg">Нет аккаунта? <a href="<?php echo esc_url( add_query_arg( 'redirect_to', add_query_arg( 'print', '1', get_permalink() ), wp_registration_url() ) ); ?>">Создать аккаунт</a></p>
			<?php else : ?>
				<p class="pln-gate-reg pln-muted-note">Регистрация новых пользователей на сайте пока выключена. Владелец включает её в «Настройки → Общие → Членство: регистрация разрешена».</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="print-doc" id="printDoc">
		<div class="pp">
			<div class="pd-head">
				<div class="pd-brand">
					<svg class="pd-logo" viewBox="0 0 44.17 51.39" aria-hidden="true"><use href="#logo-sad"/></svg>
					<span class="pd-brand-txt">Сад знаний · znai-sad.ru</span>
				</div>
				<div class="pd-user" id="pdUser"></div>
			</div>
			<div class="pd-h" id="pdName">Мой участок</div>
			<div class="pd-sub" id="pdSub"></div>
			<img class="plan-img" id="pdImg" alt="План участка">
			<div class="pd-stats" id="pdStats"></div>
			<div class="pd-legend" id="pdLegend"></div>
			<div class="pd-foot">Сад знаний · znai-sad.ru · интерактивная версия — на сайте</div>
		</div>
		<div class="pp">
			<h3>Сводка участка</h3>
			<table id="pdTable"></table>
			<h3>Культуры в плане</h3>
			<table id="pdCrops"></table>
			<div id="pdConflicts"></div>
			<div class="pd-note">План сформирован планировщиком «Сад знаний». Рекомендации по культурам — в карточках растений на сайте.</div>
		</div>
		<div class="pp">
			<h3>Рекомендации по культурам плана</h3>
			<table id="pdCare"></table>
			<div class="pd-note">⚠ Сроки и дозы ориентировочные — сверьтесь с карточкой культуры и вашим регионом.</div>
			<div class="pd-foot">Сад знаний · znai-sad.ru</div>
		</div>
		<div class="pp">
			<div class="pi-tools">
				<h3>Больше инструментов — на znai-sad.ru</h3>
				<div class="pi-grid">
					<?php foreach ( sad_znaniy_planner_tools() as $pln_tool ) : ?>
						<div class="pi-card">
							<span class="pi-ico <?php echo esc_attr( $pln_tool['icon'] ); ?>"><?php echo sad_znaniy_planner_tool_icon( $pln_tool['icon'] ); ?></span>
							<b><?php echo esc_html( $pln_tool['title'] ); ?></b>
							<p><?php echo esc_html( $pln_tool['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="pi-qr">
					<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/qr-site.svg' ); ?>" alt="QR-код: znai-sad.ru" width="300" height="300">
					<p>Наведите камеру — откроется сайт</p>
				</div>
				<div class="pi-card pi-save">
					<b>Сохраните план навсегда.</b> Зарегистрируйтесь — планы хранятся в личном кабинете, а напоминания о работах приходят в календарь телефона:
					<a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>">календарь дачника</a>.
				</div>
			</div>
			<div class="pd-foot">Сад знаний · znai-sad.ru · интерактивная версия — на сайте</div>
		</div>
	</div>




</div>
<?php
get_footer();
