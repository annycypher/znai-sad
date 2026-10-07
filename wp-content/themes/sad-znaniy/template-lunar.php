<?php
/**
 * Template Name: Лунный календарь
 *
 * Страница лунного посевного календаря (Этап 5.6, Партия 2).
 * Список дней рендерится на сервере (SSR) — контент виден роботу.
 * Переключение месяца — ссылка с GET ?m=; фильтр по культуре усиливает JS.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sz_ctx    = sad_znaniy_moon_context();
$sz_month  = $sz_ctx['month'];
$sz_year   = $sz_ctx['year'];
$sz_names  = sad_znaniy_moon_month_names();
$sz_days   = sad_znaniy_moon_month_days( $sz_year, $sz_month );
$sz_good   = sad_znaniy_moon_good_count( $sz_year, $sz_month );
$sz_intro  = sad_znaniy_moon_intro( $sz_month );
$sz_faq    = sad_znaniy_moon_faq( $sz_year, $sz_month );
$sz_work   = sad_znaniy_work_types();
$sz_page     = get_permalink();
$sz_cats     = sad_znaniy_moon_element_category();
$sz_culture  = isset( $_GET['culture'] ) ? sanitize_key( wp_unslash( $_GET['culture'] ) ) : '';
if ( ! array_key_exists( $sz_culture, sad_znaniy_lunar_categories() ) ) {
	$sz_culture = '';
}

$sz_today      = current_datetime();
$sz_is_cur     = ( (int) $sz_today->format( 'n' ) === $sz_month && (int) $sz_today->format( 'Y' ) === $sz_year );
$sz_cur_day    = (int) $sz_today->format( 'j' );
$sz_real_today = sad_znaniy_moon_day( (int) $sz_today->format( 'Y' ), (int) $sz_today->format( 'n' ), (int) $sz_today->format( 'j' ) );
?>

<section class="section lunar">
	<div class="section-head lunar-head">
		<div class="article-head">
			<h1 class="h-cap"><?php echo esc_html( 'Лунный посевной календарь на ' . $sz_names[ $sz_month ][1] . ' ' . $sz_year ); ?></h1>
			<div class="meta-line">
				<?php echo esc_html( $sz_good . ' ' . sad_znaniy_moon_plural( $sz_good, 'благоприятный день', 'благоприятных дня', 'благоприятных дней' ) . ' для посадки' ); ?>
			</div>
		</div>
		<div class="lunar-today">
			<span class="lunar-today__ico" aria-hidden="true"><?php echo esc_html( $sz_real_today['phase']['icon'] ); ?></span>
			<span>
				<strong><?php echo esc_html( $sz_real_today['phase']['name'] ); ?></strong><br>
				<span class="lunar-today__sub"><?php echo esc_html( 'Луна в знаке ' . $sz_real_today['sign']['name'] ); ?></span>
			</span>
		</div>
	</div>

	<nav class="lunar-months" aria-label="<?php esc_attr_e( 'Выбор месяца', 'sad-znaniy' ); ?>">
		<?php foreach ( $sz_names as $sz_m => $sz_nm ) : ?>
			<?php $sz_chip_url = $sz_culture ? add_query_arg( 'culture', $sz_culture, sad_znaniy_moon_month_url( $sz_m ) ) : sad_znaniy_moon_month_url( $sz_m ); ?>
			<a class="lunar-chip<?php echo $sz_m === $sz_month ? ' on' : ''; ?>" href="<?php echo esc_url( $sz_chip_url ); ?>"><?php echo esc_html( $sz_nm[0] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $sz_intro ) : ?>
		<div class="lunar-intro"><?php echo wp_kses_post( wpautop( $sz_intro ) ); ?></div>
	<?php endif; ?>

	<div class="lunar-panel">
		<label class="lunar-panel__label" for="moonCrop"><?php esc_html_e( 'Показать дни для культуры', 'sad-znaniy' ); ?></label>
		<select id="moonCrop" data-moon-crop>
			<option value=""><?php esc_html_e( 'Все культуры', 'sad-znaniy' ); ?></option>
			<?php
			$sz_cat_hint = array(
				'plod'  => __( 'Плоды (томаты, перец, огурцы…)', 'sad-znaniy' ),
				'koren' => __( 'Корнеплоды (морковь, свёкла, лук…)', 'sad-znaniy' ),
				'list'  => __( 'Листовые (капуста, салат, зелень…)', 'sad-znaniy' ),
				'cvet'  => __( 'Цветы', 'sad-znaniy' ),
			);
			foreach ( sad_znaniy_lunar_categories() as $sz_ck => $sz_cl ) :
				?>
				<option value="<?php echo esc_attr( $sz_ck ); ?>" <?php selected( $sz_culture, $sz_ck ); ?>><?php echo esc_html( isset( $sz_cat_hint[ $sz_ck ] ) ? $sz_cat_hint[ $sz_ck ] : $sz_cl ); ?></option>
			<?php endforeach; ?>
			<?php
			foreach ( sad_znaniy_lunar_categories() as $sz_ck => $sz_cl ) :
				$sz_plants = sad_znaniy_moon_plants_by_category( $sz_ck );
				if ( ! $sz_plants ) {
					continue;
				}
				?>
				<optgroup label="<?php echo esc_attr( $sz_cl ); ?>">
					<?php foreach ( $sz_plants as $sz_pid => $sz_ptitle ) : ?>
						<option value="<?php echo esc_attr( $sz_ck ); ?>"><?php echo esc_html( $sz_ptitle ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="lunar-legend">
		<span><i class="lunar-sq ok"></i><?php esc_html_e( 'Благоприятно', 'sad-znaniy' ); ?></span>
		<span><i class="lunar-sq no"></i><?php esc_html_e( 'Не рекомендуется', 'sad-znaniy' ); ?></span>
		<span class="lunar-el"><i class="el-fire"></i><?php esc_html_e( 'Огонь — плоды', 'sad-znaniy' ); ?></span>
		<span class="lunar-el"><i class="el-earth"></i><?php esc_html_e( 'Земля — корнеплоды', 'sad-znaniy' ); ?></span>
		<span class="lunar-el"><i class="el-air"></i><?php esc_html_e( 'Воздух — цветы', 'sad-znaniy' ); ?></span>
		<span class="lunar-el"><i class="el-water"></i><?php esc_html_e( 'Вода — листовые', 'sad-znaniy' ); ?></span>
	</div>

	<div class="lunar-days" id="moonDays">
		<?php
		foreach ( $sz_days as $sz_d ) :
			$sz_spec   = ( 'new' === $sz_d['phase']['key'] || 'full' === $sz_d['phase']['key'] );
			$sz_istd   = ( $sz_is_cur && $sz_d['day'] === $sz_cur_day );
			$sz_past   = ( $sz_is_cur && $sz_d['day'] < $sz_cur_day );
			$sz_goodtx = $sz_d['good'] ? implode( ', ', array_map( function ( $w ) use ( $sz_work ) { return $sz_work[ $w ]; }, $sz_d['good'] ) ) : '—';
			$sz_badtx  = $sz_d['bad'] ? implode( ', ', array_map( function ( $w ) use ( $sz_work ) { return $sz_work[ $w ]; }, $sz_d['bad'] ) ) : '';
			$sz_cat    = $sz_cats[ $sz_d['sign']['element'] ];
			?>
			<div class="lunar-day<?php echo $sz_spec ? ' spec' : ''; ?><?php echo $sz_istd ? ' today' : ''; ?>"
				id="d<?php echo (int) $sz_d['day']; ?>"
				data-moon-cat="<?php echo esc_attr( $sz_cat ); ?>"
				<?php echo $sz_past ? 'style="opacity:.55"' : ''; ?>
				<?php echo $sz_spec ? 'data-spec="' . esc_attr( $sz_d['phase']['name'] ) . '"' : ''; ?>>
				<div class="lunar-day__top">
					<span class="lunar-day__num"><?php echo (int) $sz_d['day']; ?></span>
					<span class="lunar-day__moon" aria-hidden="true"><?php echo esc_html( $sz_d['phase']['icon'] ); ?></span>
				</div>
				<div class="lunar-day__sign"><i class="el-<?php echo esc_attr( $sz_d['sign']['element'] ); ?>"></i><?php echo esc_html( $sz_d['sign']['name'] ); ?><?php echo $sz_istd ? ' · ' . esc_html__( 'сегодня', 'sad-znaniy' ) : ''; ?></div>
				<div class="lunar-day__good"><b><?php esc_html_e( 'Благоприятно', 'sad-znaniy' ); ?></b><?php echo esc_html( $sz_goodtx ); ?></div>
				<?php if ( $sz_badtx ) : ?>
					<div class="lunar-day__bad"><b><?php esc_html_e( 'Не рекомендуется', 'sad-znaniy' ); ?></b><?php echo esc_html( $sz_badtx ); ?></div>
				<?php endif; ?>
				<div class="lunar-day__phase"><?php echo esc_html( $sz_d['phase']['name'] ); ?> · <?php echo (int) $sz_d['phase']['illum']; ?>%</div>
			</div>
		<?php endforeach; ?>
	</div>

	<section class="lunar-faq">
		<h2><?php esc_html_e( 'Частые вопросы', 'sad-znaniy' ); ?></h2>
		<?php foreach ( $sz_faq as $sz_q ) : ?>
			<details>
				<summary><?php echo esc_html( $sz_q['q'] ); ?></summary>
				<p><?php echo wp_kses_post( $sz_q['a'] ); ?></p>
			</details>
		<?php endforeach; ?>
	</section>

	<section class="lunar-faq lunar-links">
		<h2><?php esc_html_e( 'Смотрите также', 'sad-znaniy' ); ?></h2>
		<ul>
			<li><a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>"><?php esc_html_e( 'Календарь дачника — работы по месяцам и регионам →', 'sad-znaniy' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/rasteniya/' ) ); ?>"><?php esc_html_e( 'База знаний: растения с параметрами и совместимостью →', 'sad-znaniy' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/stati/' ) ); ?>"><?php esc_html_e( 'Статьи о посеве, уходе и сборе урожая →', 'sad-znaniy' ); ?></a></li>
		</ul>
	</section>

	<p class="lunar-max"><?php esc_html_e( 'Ведём блог и короткие напоминания в MAX —', 'sad-znaniy' ); ?> <a href="https://web.max.ru/-76163835891728" target="_blank" rel="noopener"><?php esc_html_e( 'мы есть в MAX →', 'sad-znaniy' ); ?></a></p>
</section>

<?php
get_footer();
