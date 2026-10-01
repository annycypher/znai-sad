<?php
/**
 * Посадочная страница типа работ: /uhod/poliv/, /uhod/obrezka/ и т. д.
 *
 * Уникальный текст хранится в описании термина (правится в админке:
 * «События → Тип работы»). Шаблон добавляет календарную выборку по месяцам,
 * ссылки на инструменты и частые вопросы с микроразметкой FAQPage.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$sz_term = get_queried_object();
$sz_slug = ( $sz_term && isset( $sz_term->slug ) ) ? (string) $sz_term->slug : '';

if ( '' === $sz_slug || ! taxonomy_exists( 'work_type' ) ) {
	?>
	<section class="section"><h1 class="h-cap"><?php esc_html_e( 'Страница не найдена', 'sad-znaniy' ); ?></h1></section>
	<?php
	get_footer();
	return;
}

$sz_types   = sad_znaniy_work_types();
$sz_name    = isset( $sz_types[ $sz_slug ] ) ? $sz_types[ $sz_slug ] : $sz_term->name;
$sz_colors  = sad_znaniy_work_type_colors();
$sz_color   = isset( $sz_colors[ $sz_slug ] ) ? $sz_colors[ $sz_slug ] : '#3FA46F';
$sz_h1      = sad_znaniy_work_type_h1( $sz_slug );
$sz_tool    = sad_znaniy_work_type_tool( $sz_slug );
$sz_faq     = sad_znaniy_work_type_faq( $sz_slug );
$sz_desc    = term_description();
$sz_regions = sad_znaniy_region_keys();

$sz_events = get_posts(
	array(
		'post_type'      => 'calendar_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_sz_event_date_from',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'work_type',
				'field'    => 'slug',
				'terms'    => $sz_slug,
			),
		),
	)
);

$sz_months = array();
foreach ( $sz_events as $sz_event ) {
	$sz_from  = (string) get_post_meta( $sz_event->ID, '_sz_event_date_from', true );
	$sz_month = $sz_from ? (int) substr( $sz_from, 5, 2 ) : 0;
	if ( $sz_month ) {
		$sz_months[ $sz_month ][] = $sz_event;
	}
}
ksort( $sz_months );
?>

	<section class="section">
		<p class="calc-note">
			<a class="calc-link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Главная', 'sad-znaniy' ); ?></a>
			· <a class="calc-link" href="<?php echo esc_url( home_url( '/uhod/' ) ); ?>"><?php esc_html_e( 'Уход за садом', 'sad-znaniy' ); ?></a>
		</p>

		<div class="section-head">
			<h1 class="h-cap">
				<span aria-hidden="true" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $sz_color ); ?>;margin-right:10px;"></span>
				<?php echo esc_html( $sz_h1 ? $sz_h1 : $sz_name ); ?>
			</h1>
		</div>

		<?php if ( $sz_desc ) : ?>
			<div class="hero-sub" style="max-width:none;margin-bottom:24px;"><?php echo wp_kses_post( $sz_desc ); ?></div>
		<?php endif; ?>

		<?php if ( $sz_months ) : ?>
			<div class="section-head"><h2 class="h-cap" style="font-size:19px;"><?php esc_html_e( 'Когда это делают', 'sad-znaniy' ); ?></h2></div>
			<p class="calc-note" style="margin-bottom:14px;"><?php esc_html_e( 'Работы из календаря дачника. Месяц кликабелен — оттуда видно весь план на месяц по вашему региону.', 'sad-znaniy' ); ?></p>

			<?php foreach ( $sz_months as $sz_num => $sz_list ) : ?>
				<h3 style="font-size:15px;margin:18px 0 8px;">
					<a class="calc-link" href="<?php echo esc_url( sad_znaniy_calendar_url( null, $sz_num ) ); ?>"><?php echo esc_html( date_i18n( 'F', mktime( 12, 0, 0, (int) $sz_num, 15 ) ) ); ?></a>
				</h3>
				<ul class="calc-facts">
					<?php foreach ( $sz_list as $sz_event ) : ?>
						<?php
						$sz_from_raw = (string) get_post_meta( $sz_event->ID, '_sz_event_date_from', true );
						$sz_to_raw   = (string) get_post_meta( $sz_event->ID, '_sz_event_date_to', true );
						$sz_from_lbl = $sz_from_raw ? date_i18n( 'j F', strtotime( $sz_from_raw . ' 12:00:00' ) ) : '';
						$sz_to_lbl   = ( $sz_to_raw && $sz_to_raw !== $sz_from_raw ) ? date_i18n( 'j F', strtotime( $sz_to_raw . ' 12:00:00' ) ) : '';
						$sz_hint     = (string) get_post_meta( $sz_event->ID, '_sz_event_weather_hint', true );
						$sz_all      = (int) get_post_meta( $sz_event->ID, '_sz_event_all_regions', true );
						$sz_reg_list = array();

						if ( $sz_all ) {
							$sz_reg_list[] = __( 'все регионы', 'sad-znaniy' );
						} else {
							$sz_codes = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $sz_event->ID, '_sz_event_regions', true ) ) ) );
							foreach ( $sz_codes as $sz_code ) {
								if ( isset( $sz_regions[ $sz_code ] ) ) {
									$sz_reg_list[] = $sz_regions[ $sz_code ];
								}
							}
						}
						?>
						<li>
							<b><?php echo esc_html( get_the_title( $sz_event ) ); ?></b>
							<?php if ( $sz_from_lbl ) : ?>
								— <?php echo esc_html( $sz_from_lbl ); ?><?php echo $sz_to_lbl ? ' — ' . esc_html( $sz_to_lbl ) : ''; ?>
							<?php endif; ?>
							<?php if ( $sz_reg_list ) : ?>
								<span class="calc-note"> (<?php echo esc_html( implode( ', ', $sz_reg_list ) ); ?>)</span>
							<?php endif; ?>
							<?php if ( $sz_hint ) : ?>
								<br><span class="calc-note"><?php echo esc_html( $sz_hint ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="calc-warn">
				<h3><?php esc_html_e( 'Пока пусто', 'sad-znaniy' ); ?></h3>
				<p><?php esc_html_e( 'Работы этого типа ещё не занесены в календарь. Добавьте события в разделе «События» — они появятся здесь автоматически.', 'sad-znaniy' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="section-head"><h2 class="h-cap" style="font-size:19px;"><?php esc_html_e( 'Посчитать', 'sad-znaniy' ); ?></h2></div>
		<p class="calc-links">
			<?php if ( $sz_tool ) : ?>
				<a class="calc-link" href="<?php echo esc_url( $sz_tool['url'] ); ?>"><?php echo esc_html( $sz_tool['label'] ); ?></a>
			<?php endif; ?>
			<a class="calc-link" href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>"><?php esc_html_e( 'Календарь дачника по месяцам', 'sad-znaniy' ); ?></a>
			<a class="calc-link" href="<?php echo esc_url( home_url( '/rasteniya/' ) ); ?>"><?php esc_html_e( 'База знаний о растениях', 'sad-znaniy' ); ?></a>
			<a class="calc-link" href="<?php echo esc_url( home_url( '/stati/' ) ); ?>"><?php esc_html_e( 'Статьи по уходу', 'sad-znaniy' ); ?></a>
		</p>

		<?php if ( $sz_faq ) : ?>
			<div class="section-head"><h2 class="h-cap" style="font-size:19px;"><?php esc_html_e( 'Частые вопросы', 'sad-znaniy' ); ?></h2></div>
			<details class="calc-how">
				<summary><?php esc_html_e( 'Короткие ответы на главные вопросы', 'sad-znaniy' ); ?></summary>
				<?php foreach ( $sz_faq as $sz_q => $sz_a ) : ?>
					<p><b><?php echo esc_html( $sz_q ); ?></b><br><?php echo esc_html( $sz_a ); ?></p>
				<?php endforeach; ?>
			</details>
			<script type="application/ld+json">
			<?php
			$sz_json = array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => array(),
			);
			foreach ( $sz_faq as $sz_q => $sz_a ) {
				$sz_json['mainEntity'][] = array(
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( $sz_q ),
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $sz_a ),
					),
				);
			}
			echo wp_json_encode( $sz_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			?>
			</script>
		<?php endif; ?>

		<div class="section-head"><h2 class="h-cap" style="font-size:19px;"><?php esc_html_e( 'Другие виды работ', 'sad-znaniy' ); ?></h2></div>
		<div class="sx-tiles">
			<?php foreach ( $sz_types as $sz_key => $sz_label ) : ?>
				<?php
				if ( $sz_key === $sz_slug ) {
					continue;
				}
				$sz_link = sad_znaniy_work_type_link( $sz_key );
				if ( ! $sz_link ) {
					continue;
				}
				?>
				<a href="<?php echo esc_url( $sz_link ); ?>" class="sx-tile">
					<span class="tile-ico" aria-hidden="true" style="background:<?php echo esc_attr( isset( $sz_colors[ $sz_key ] ) ? $sz_colors[ $sz_key ] : '#3FA46F' ); ?>;"></span>
					<strong><?php echo esc_html( $sz_label ); ?></strong>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

<?php
get_footer();
