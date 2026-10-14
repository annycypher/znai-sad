<?php
/**
 * Template Name: Календарь дачника
 *
 * Умный календарь работ (Этап 5.5, Партия 2): SSR-список месяца + сетка.
 * Переключение месяца — перезагрузка (?month=&year=), фильтры региона/опыта
 * учитываются сервером (шареные ссылки и no-JS), клиент дублирует мгновенно.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$today     = current_datetime();
$cur_year  = (int) $today->format( 'Y' );
$cur_month = (int) $today->format( 'n' );
$cur_day   = (int) $today->format( 'j' );

$year  = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : $cur_year;
$month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : $cur_month;

while ( $month < 1 ) {
	$month += 12;
	$year--;
}
while ( $month > 12 ) {
	$month -= 12;
	$year++;
}
if ( $year < 2020 || $year > 2040 ) {
	$year  = $cur_year;
	$month = $cur_month;
}

$region = isset( $_GET['region'] ) ? sanitize_key( wp_unslash( $_GET['region'] ) ) : '';
if ( ! array_key_exists( $region, sad_znaniy_region_keys() ) ) {
	$region = '';
}
$exp = isset( $_GET['exp'] ) ? sanitize_key( wp_unslash( $_GET['exp'] ) ) : '';
if ( ! in_array( $exp, array( 'new', 'exp', 'all' ), true ) ) {
	$exp = '';
}

// Режимы печати: ?print=1 — чистый лист месяца, ?print=1&all=1 — книжка на год.
$print_mode = ( isset( $_GET['print'] ) && '1' === (string) $_GET['print'] );
$year_book  = ( $print_mode && isset( $_GET['all'] ) && '1' === (string) $_GET['all'] );
$region_lbl = ( '' !== $region ) ? sad_znaniy_option_label( sad_znaniy_region_keys(), $region ) : __( 'Все регионы', 'sad-znaniy' );

$events = sad_znaniy_get_month_events( $year, $month );
$tasks  = array();
foreach ( $events as $ev ) {
	$tasks[] = sad_znaniy_event_data( $ev->ID, $year, $month );
}

global $wp_locale;
$month_name = $wp_locale->month[ zeroise( $month, 2 ) ];
$last_day   = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );

$prev_m = $month - 1;
$prev_y = $year;
$next_m = $month + 1;
$next_y = $year;
if ( $prev_m < 1 ) {
	$prev_m = 12;
	$prev_y--;
}
if ( $next_m > 12 ) {
	$next_m = 1;
	$next_y++;
}

$dow      = array( 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс' );
$first_wd = (int) gmdate( 'N', gmmktime( 0, 0, 0, $month, 1, $year ) );
$offset   = $first_wd - 1;

$difficulty_label = array( 'new' => 'Новичку', 'exp' => 'Опытным', 'all' => 'Всем' );
$priority_label   = array( 'must' => 'Обязательно', 'opt' => 'Желательно', 'weather' => 'По погоде' );
?>

<div class="cal-wrap<?php echo $print_mode ? ' is-print' : ''; ?><?php echo $year_book ? ' is-yearbook' : ''; ?>" data-month="<?php echo (int) $month; ?>" data-year="<?php echo (int) $year; ?>">

	<?php sad_znaniy_banner_slot(); ?>

	<div class="print-head<?php echo $print_mode ? ' is-visible' : ''; ?>">
		<h1><?php printf( esc_html__( 'Календарь работ — %1$s %2$s', 'sad-znaniy' ), esc_html( $month_name ), (int) $year ); ?><?php echo '' !== $region ? ' · ' . esc_html( $region_lbl ) : ''; ?></h1>
		<p><?php echo esc_html( 'znai-sad.ru · ' . $region_lbl . ' · распечатано ' . current_time( 'd.m.Y' ) . ( 'new' === $exp ? ' · для новичка' : '' ) ); ?></p>
	</div>

<?php if ( $year_book ) : ?>
	<?php
	$sz_type_icons = sad_znaniy_work_type_icons();
	?>
	<div class="pm-tools">
		<button type="button" class="btn btn-sm" id="pmAll"><?php esc_html_e( 'Отметить все', 'sad-znaniy' ); ?></button>
		<button type="button" class="btn btn-sm" id="pmNone"><?php esc_html_e( 'Снять все', 'sad-znaniy' ); ?></button>
		<span class="pm-count" id="pmCount"></span>
		<span class="pm-note"><?php esc_html_e( 'На печать идут только отмеченные пункты; снятые не печатаются. Выбор запоминается в этом браузере.', 'sad-znaniy' ); ?></span>
	</div>
	<?php
	for ( $sz_m = 1; $sz_m <= 12; $sz_m++ ) :
		$sz_month_tasks = array();
		foreach ( sad_znaniy_get_month_events( $year, $sz_m ) as $sz_one ) {
			$sz_month_tasks[] = sad_znaniy_event_data( $sz_one->ID, $year, $sz_m );
		}
		$sz_rows = array();
		foreach ( $sz_month_tasks as $sz_row ) {
			if ( $region && ! in_array( 'all', $sz_row['regions'], true ) && ! in_array( $region, $sz_row['regions'], true ) ) {
				continue;
			}
			if ( 'new' === $exp && 'exp' === $sz_row['exp'] ) {
				continue;
			}
			$sz_rows[] = $sz_row;
		}
		if ( ! $sz_rows ) {
			continue;
		}
		$sz_mname = $wp_locale->month[ zeroise( $sz_m, 2 ) ];
		?>
		<section class="print-month">
			<h2><?php echo esc_html( $sz_mname . ' ' . $year ); ?></h2>
			<div class="pm-grid">
				<?php foreach ( $sz_rows as $sz_row ) : ?>
					<?php
					$sz_d    = ( $sz_row['d1'] === $sz_row['d2'] ) ? (string) $sz_row['d1'] : $sz_row['d1'] . '–' . $sz_row['d2'];
					$sz_ico  = isset( $sz_type_icons[ $sz_row['type'] ] ) ? $sz_type_icons[ $sz_row['type'] ] : '🌿';
					$sz_tlbl = sad_znaniy_option_label( sad_znaniy_work_types(), $sz_row['type'] );
					?>
					<article class="pm-card" data-pm-key="<?php echo esc_attr( $year . '-' . $sz_m . '|' . $sz_row['title'] ); ?>">
						<label class="pm-check" title="<?php esc_attr_e( 'Печатать этот пункт', 'sad-znaniy' ); ?>">
							<input type="checkbox" class="pm-cb" checked aria-label="<?php echo esc_attr( 'Печатать: ' . $sz_row['title'] ); ?>">
							<span class="pm-box" aria-hidden="true">☐</span>
						</label>
						<div class="pm-head">
							<span class="pm-ico" aria-hidden="true"><?php echo esc_html( $sz_ico ); ?></span>
							<span class="pm-type"><?php echo esc_html( $sz_tlbl ); ?></span>
						</div>
						<h3 class="pm-title"><?php echo esc_html( $sz_row['title'] ); ?></h3>
						<div class="pm-meta">
							<span>📅 <?php echo esc_html( $sz_d . ' ' . mb_strtolower( $sz_mname, 'UTF-8' ) ); ?></span>
							<?php if ( $sz_row['crop'] ) : ?><span>🌱 <?php echo esc_html( $sz_row['crop'] ); ?></span><?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endfor; ?>
<?php endif; ?>

	<div class="cal-head">
		<div>
			<h1><?php esc_html_e( 'Календарь дачника', 'sad-znaniy' ); ?></h1>
			<p><?php esc_html_e( 'Что делать на участке в вашем регионе — по месяцам и типам работ.', 'sad-znaniy' ); ?></p>
		</div>
		<div class="month-nav">
			<a class="mnav-btn" href="<?php echo esc_url( sad_znaniy_calendar_url( $prev_y, $prev_m, $region, $exp ) ); ?>" aria-label="<?php esc_attr_e( 'Предыдущий месяц', 'sad-znaniy' ); ?>">‹</a>
			<span class="month-title"><?php echo esc_html( $month_name . ' ' . $year ); ?></span>
			<a class="mnav-btn" href="<?php echo esc_url( sad_znaniy_calendar_url( $next_y, $next_m, $region, $exp ) ); ?>" aria-label="<?php esc_attr_e( 'Следующий месяц', 'sad-znaniy' ); ?>">›</a>
		</div>
		<div class="view-toggle" role="tablist">
			<button id="vGrid" class="on"><?php esc_html_e( 'Сетка', 'sad-znaniy' ); ?></button>
			<button id="vList"><?php esc_html_e( 'Список', 'sad-znaniy' ); ?></button>
		</div>
	</div>

	<section class="filters" aria-label="<?php esc_attr_e( 'Фильтры календаря', 'sad-znaniy' ); ?>">
		<div class="f-row">
			<span class="f-label"><?php esc_html_e( 'Регион', 'sad-znaniy' ); ?></span>
			<?php foreach ( sad_znaniy_region_keys() as $r_key => $r_label ) : ?>
				<a class="chip region <?php echo $region === $r_key ? 'on' : ''; ?>" data-region="<?php echo esc_attr( $r_key ); ?>" href="<?php echo esc_url( sad_znaniy_calendar_url( $year, $month, $r_key, $exp ) ); ?>"><?php echo esc_html( $r_label ); ?></a>
			<?php endforeach; ?>
		</div>
		<div class="f-row">
			<span class="f-label"><?php esc_html_e( 'Опыт', 'sad-znaniy' ); ?></span>
			<a class="chip exp <?php echo 'new' === $exp ? 'on' : ''; ?>" data-exp="new" href="<?php echo esc_url( sad_znaniy_calendar_url( $year, $month, $region, 'new' ) ); ?>"><?php esc_html_e( 'Новичок', 'sad-znaniy' ); ?></a>
			<a class="chip exp <?php echo ( 'all' === $exp || '' === $exp ) ? 'on' : ''; ?>" data-exp="all" href="<?php echo esc_url( sad_znaniy_calendar_url( $year, $month, $region, 'all' ) ); ?>"><?php esc_html_e( 'Опытный (показать всё)', 'sad-znaniy' ); ?></a>
		</div>
		<div class="f-row">
			<span class="f-label"><?php esc_html_e( 'Работы', 'sad-znaniy' ); ?></span>
			<?php foreach ( sad_znaniy_work_types() as $w_key => $w_label ) : ?>
				<button class="chip wtype t-<?php echo esc_attr( $w_key ); ?>" data-type="<?php echo esc_attr( $w_key ); ?>"><?php echo esc_html( $w_label ); ?></button>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="progress-row">
		<div class="progress-track"><div class="progress-fill" id="pFill"></div></div>
		<span class="progress-num" id="pNum"><?php printf( '%d из %d', 0, count( $tasks ) ); ?></span>
		<button type="button" class="btn btn-sm" id="resetDone" title="<?php esc_attr_e( 'Снять все отметки выполнения', 'sad-znaniy' ); ?>">↺ <?php esc_html_e( 'Сбросить отметки', 'sad-znaniy' ); ?></button>
	</div>

	<section id="gridView" class="cal-grid-card">
		<div class="grid7" id="grid">
			<?php foreach ( $dow as $d ) : ?>
				<div class="dow"><?php echo esc_html( $d ); ?></div>
			<?php endforeach; ?>
			<?php for ( $i = 0; $i < $offset; $i++ ) : ?>
				<div class="day empty"></div>
			<?php endfor; ?>
			<?php for ( $d = 1; $d <= $last_day; $d++ ) : ?>
				<?php
				$wd      = ( $first_wd + $d - 2 ) % 7 + 1;
				$classes = array( 'day' );
				if ( $wd >= 6 ) {
					$classes[] = 'weekend';
				}
				if ( $d === $cur_day && $month === $cur_month && $year === $cur_year ) {
					$classes[] = 'today';
				}
				$markers = '';
				$seen    = 0;
				foreach ( $tasks as $t ) {
					if ( $t['d1'] <= $d && $d <= $t['d2'] ) {
						if ( $seen < 4 ) {
							$markers .= '<i class="mk ' . esc_attr( $t['type'] ) . '" title="' . esc_attr( $t['title'] ) . '"></i>';
						}
						$seen++;
					}
				}
				?>
				<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-day="<?php echo (int) $d; ?>" role="button" tabindex="0" aria-label="<?php echo esc_attr( $d . ' ' . $month_name ); ?>">
					<span class="num"><?php echo (int) $d; ?></span>
					<?php if ( $markers ) : ?>
						<div class="markers"><?php echo $markers; // phpcs:ignore WordPress.Security.EscapeOutput -- собрано из экранированных частей выше ?></div>
					<?php endif; ?>
				</div>
			<?php endfor; ?>
		</div>
		<div class="legend">
			<?php foreach ( sad_znaniy_work_types() as $w_key => $w_label ) : ?>
				<span><i class="mk <?php echo esc_attr( $w_key ); ?>"></i><?php echo esc_html( $w_label ); ?></span>
			<?php endforeach; ?>
		</div>
		<div class="day-panel" id="dayPanel" hidden>
			<h3 id="dpTitle"></h3>
			<div id="dpTasks"></div>
		</div>
	</section>

	<section id="listView" class="tasks">
		<?php if ( $tasks ) : ?>
			<?php foreach ( $tasks as $t ) : ?>
				<?php
				$type_label = sad_znaniy_option_label( sad_znaniy_work_types(), $t['type'] );
				$pr_label   = isset( $priority_label[ $t['pr'] ] ) ? $priority_label[ $t['pr'] ] : 'По погоде';
				$exp_label  = isset( $difficulty_label[ $t['exp'] ] ) ? $difficulty_label[ $t['exp'] ] : 'Всем';
				$d_str      = ( $t['d1'] === $t['d2'] ) ? (string) $t['d1'] : $t['d1'] . '–' . $t['d2'];
				$regions_a  = implode( ',', $t['regions'] );
				$is_hidden  = '';
				if ( $region && ! in_array( 'all', $t['regions'], true ) && ! in_array( $region, $t['regions'], true ) ) {
					$is_hidden = ' hidden';
				}
				if ( 'new' === $exp && 'exp' === $t['exp'] ) {
					$is_hidden = ' hidden';
				}
				?>
				<article class="task<?php echo esc_attr( $is_hidden ); ?>" data-id="<?php echo esc_attr( $t['id'] ); ?>" data-regions="<?php echo esc_attr( $regions_a ); ?>" data-exp="<?php echo esc_attr( $t['exp'] ); ?>" data-type="<?php echo esc_attr( $t['type'] ); ?>" data-d1="<?php echo esc_attr( $t['d1'] ); ?>" data-d2="<?php echo esc_attr( $t['d2'] ); ?>">
					<button class="cb" aria-label="<?php esc_attr_e( 'Отметить выполненной', 'sad-znaniy' ); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="m5 13 4 4L19 7"/></svg></button>
					<div class="t-body">
						<div class="t-top">
							<span class="t-title"><?php echo esc_html( $t['title'] ); ?></span>
							<span class="t-type <?php echo esc_attr( $t['type'] ); ?>"><?php echo esc_html( $type_label ); ?></span>
							<span class="t-pr <?php echo 'must' === $t['pr'] ? 'must' : ''; ?>"><?php echo esc_html( $pr_label ); ?></span>
						</div>
						<?php $sz_mb = sad_znaniy_moon_badge( $year, $month, (int) $t['d1'] ); ?>
						<div class="t-meta">
							<span>📅 <?php echo esc_html( $d_str ); ?> <?php echo esc_html( mb_strtolower( $month_name, 'UTF-8' ) ); ?></span>
							<?php if ( $t['crop'] ) : ?><span>🌱 <?php echo esc_html( $t['crop'] ); ?></span><?php endif; ?>
							<span>👤 <?php echo esc_html( $exp_label ); ?></span>
							<?php if ( $sz_mb ) : ?><span class="t-moon" title="<?php echo esc_attr( $sz_mb['name'] ); ?>"><?php echo esc_html( $sz_mb['icon'] . ' ' . $sz_mb['name'] ); ?></span><?php endif; ?>
						</div>
						<?php if ( $sz_mb && $sz_mb['unfav'] && $sz_mb['best_day'] ) : ?>
							<div class="t-hint t-moon-hint">🌙 <?php echo esc_html( sprintf( __( 'По лунному календарю лучше %d-го — в этот день Луна благоприятствует посадке.', 'sad-znaniy' ), $sz_mb['best_day'] ) ); ?> <a href="<?php echo esc_url( home_url( '/lunnyj-kalendar/' ) ); ?>"><?php esc_html_e( 'Лунный календарь', 'sad-znaniy' ); ?></a></div>
						<?php endif; ?>
						<?php if ( $t['hint'] ) : ?>
							<div class="t-hint">💡 <?php echo esc_html( $t['hint'] ); ?></div>
						<?php endif; ?>
						<?php
						$t_links = sad_znaniy_event_links( $t['crop_id'] );
						if ( $t_links ) :
							?>
							<div class="t-links"><?php esc_html_e( 'Подробнее:', 'sad-znaniy' ); ?>
								<?php
								foreach ( $t_links as $t_i => $t_link ) :
									echo ( $t_i > 0 ? ', ' : ' ' );
									?>
									<a href="<?php echo esc_url( $t_link[1] ); ?>"><?php echo esc_html( $t_link[0] ); ?></a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="empty-state"><?php esc_html_e( 'События этого месяца ещё наполняются. Загляните в соседние месяцы или подпишитесь на напоминания.', 'sad-znaniy' ); ?></div>
		<?php endif; ?>
	</section>

	<div class="export-row">
		<button class="btn" onclick="window.print()">🖨 <?php esc_html_e( 'Печать', 'sad-znaniy' ); ?></button>
		<button class="btn" id="btnPdf" title="<?php esc_attr_e( 'В диалоге печати выберите «Сохранить как PDF»', 'sad-znaniy' ); ?>">📄 <?php esc_html_e( 'Сохранить в PDF', 'sad-znaniy' ); ?></button>
		<button class="btn" id="btnJpg">🖼 <?php esc_html_e( 'Сохранить в JPG', 'sad-znaniy' ); ?></button>
		<a class="btn" href="<?php echo esc_url( add_query_arg( array( 'print' => '1', 'all' => '1' ) ) ); ?>">📅 <?php esc_html_e( 'Календарь на год', 'sad-znaniy' ); ?></a>
		<?php
		$sz_ics_args = array();
		if ( '' !== $region ) {
			$sz_ics_args['region'] = $region;
		}
		if ( '' !== $exp ) {
			$sz_ics_args['exp'] = $exp;
		}
		if ( ! $year_book ) {
			$sz_ics_args['month'] = (int) $month;
			$sz_ics_args['year']  = (int) $year;
		}
		$sz_ics_url = add_query_arg( $sz_ics_args, home_url( '/kalendar/ical/' ) );
		?>
		<a class="btn" href="<?php echo esc_url( $sz_ics_url ); ?>">🗓 <?php esc_html_e( 'В календарь телефона', 'sad-znaniy' ); ?></a>
		<button class="btn btn-dark" id="btnShare">🔗 <?php esc_html_e( 'Поделиться', 'sad-znaniy' ); ?></button>
		<p class="btn-hint"><?php esc_html_e( 'PDF: в диалоге печати выберите «Сохранить как PDF» — получится лист А4 с чек-листом. «Календарь на год» открывает 12 месяцев подряд для печати одним файлом.', 'sad-znaniy' ); ?></p>
		<p class="max-link"><?php esc_html_e( 'Ведём блог и короткие напоминания в MAX —', 'sad-znaniy' ); ?> <a href="https://web.max.ru/-76163835891728" target="_blank" rel="noopener"><?php esc_html_e( 'мы есть в MAX →', 'sad-znaniy' ); ?></a></p>
	</div>


</div>

<?php
get_footer();


