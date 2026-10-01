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

<div class="cal-wrap" data-month="<?php echo (int) $month; ?>" data-year="<?php echo (int) $year; ?>">

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
						<div class="t-meta">
							<span>📅 <?php echo esc_html( $d_str ); ?> <?php echo esc_html( mb_strtolower( $month_name, 'UTF-8' ) ); ?></span>
							<?php if ( $t['crop'] ) : ?><span>🌱 <?php echo esc_html( $t['crop'] ); ?></span><?php endif; ?>
							<span>👤 <?php echo esc_html( $exp_label ); ?></span>
						</div>
						<?php if ( $t['hint'] ) : ?>
							<div class="t-hint">💡 <?php echo esc_html( $t['hint'] ); ?></div>
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
		<button class="btn" id="btnPdf">📄 <?php esc_html_e( 'Сохранить в PDF', 'sad-znaniy' ); ?></button>
		<button class="btn" id="btnJpg">🖼 <?php esc_html_e( 'Сохранить в JPG', 'sad-znaniy' ); ?></button>
		<button class="btn btn-dark" id="btnShare">🔗 <?php esc_html_e( 'Поделиться', 'sad-znaniy' ); ?></button>
		<p class="max-link"><?php esc_html_e( 'Ведём блог и короткие напоминания в MAX —', 'sad-znaniy' ); ?> <a href="https://web.max.ru/-76163835891728" target="_blank" rel="noopener"><?php esc_html_e( 'мы есть в MAX →', 'sad-znaniy' ); ?></a></p>
	</div>

</div>

<?php
get_footer();


