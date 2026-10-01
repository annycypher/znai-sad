<?php
/**
 * Результат расчёта калькулятора удобрений.
 *
 * Ожидает в области видимости массив $calc из sad_znaniy_fert_calc().
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $calc ) || ! is_array( $calc ) ) {
	return;
}

$goals  = sad_znaniy_fert_goals();
$crops  = sad_znaniy_fert_crops();
$ferts  = sad_znaniy_fert_list();
$config = sad_znaniy_fert_options();

$extra = array(
	'n' => __( 'мочевина или аммиачная селитра', 'sad-znaniy' ),
	'p' => __( 'суперфосфат', 'sad-znaniy' ),
	'k' => __( 'сульфат калия или зола', 'sad-znaniy' ),
);

$fmt = static function ( $value ) {
	return rtrim( rtrim( number_format_i18n( $value, 2 ), '0' ), ',' );
};

$gaps   = array();
$excess = array();
foreach ( array( 'n', 'p', 'k' ) as $nutrient ) {
	if ( $calc['target'][ $nutrient ] <= 0.05 ) {
		continue;
	}
	if ( $calc['delivered'][ $nutrient ] < ( $calc['target'][ $nutrient ] - 0.05 ) ) {
		$gaps[] = sprintf(
			/* translators: 1: элемент, 2: сколько даст удобрение, 3: сколько нужно, 4: чем добрать */
			esc_html__( '%1$s: даст %2$s г/м² при нужных %3$s г/м² — добрать можно так: %4$s', 'sad-znaniy' ),
			esc_html( $calc['nutrients'][ $nutrient ] ),
			esc_html( $fmt( $calc['delivered'][ $nutrient ] ) ),
			esc_html( $fmt( $calc['target'][ $nutrient ] ) ),
			esc_html( $extra[ $nutrient ] )
		);
	} elseif ( $calc['delivered'][ $nutrient ] > ( $calc['target'][ $nutrient ] * 1.5 ) ) {
		$excess[] = sprintf(
			/* translators: 1: элемент, 2: во сколько раз больше цели, 3: сколько даст, 4: сколько нужно */
			esc_html__( '%1$s: даст %2$s г/м², а цели хватило бы %3$s г/м² — это перебор, лучше взять удобрение с меньшей долей этого элемента', 'sad-znaniy' ),
			esc_html( $calc['nutrients'][ $nutrient ] ),
			esc_html( $fmt( $calc['delivered'][ $nutrient ] ) ),
			esc_html( $fmt( $calc['target'][ $nutrient ] ) )
		);
	}
}

$decimals = ( $calc['total'] < 100 ) ? 1 : 0;
?>
<div class="calc-out" aria-live="polite">
	<div class="calc-main">
		<span class="calc-num"><?php echo esc_html( number_format_i18n( $calc['total'], $decimals ) ); ?></span>
		<span class="calc-unit"><?php esc_html_e( 'г удобрения на всю площадь', 'sad-znaniy' ); ?></span>
	</div>

	<p class="calc-sum">
		<?php
		printf(
			/* translators: 1: цель, 2: культура, 3: удобрение, 4: площадь */
			esc_html__( '%1$s · %2$s · %3$s · %4$s м²', 'sad-znaniy' ),
			esc_html( $goals[ $calc['goal'] ] ),
			esc_html( $crops[ $calc['crop'] ] ),
			esc_html( $ferts[ $calc['fert'] ] ),
			esc_html( number_format_i18n( $calc['qty'] ) )
		);
		?>
	</p>

	<ul class="calc-facts">
		<li>
			<?php
			printf(
				/* translators: 1: граммы на м², 2: ложки */
				esc_html__( 'На 1 м² — %1$s г, это примерно %2$s ст. ложек', 'sad-znaniy' ),
				'<b>' . esc_html( number_format_i18n( $calc['dose'], 1 ) ) . '</b>',
				esc_html( number_format_i18n( $calc['dose'] / max( 1, (float) $config['measure']['tbsp'] ), 1 ) )
			);
			?>
		</li>
		<li>
			<?php
			printf(
				/* translators: 1: ложки, 2: коробки, 3: стаканы */
				esc_html__( 'Всего: %1$s ст. ложек, или %2$s спичечных коробков, или %3$s стаканов', 'sad-znaniy' ),
				'<b>' . esc_html( number_format_i18n( $calc['tbsp'], 1 ) ) . '</b>',
				esc_html( number_format_i18n( $calc['matchbox'], 1 ) ),
				esc_html( number_format_i18n( $calc['glass'], 2 ) )
			);
			?>
		</li>
		<?php if ( $calc['soluble'] ) : ?>
			<li>
				<?php
				printf(
					/* translators: %s: граммы на ведро */
					esc_html__( 'Для подкормки раствором: %s г на ведро 10 л (ведра хватает примерно на 1 м²)', 'sad-znaniy' ),
					'<b>' . esc_html( number_format_i18n( $calc['per_can'], 1 ) ) . '</b>'
				);
				?>
			</li>
		<?php else : ?>
			<li><?php esc_html_e( 'Это удобрение вносят сухим под перекопку или в борозды, а не раствором.', 'sad-znaniy' ); ?></li>
		<?php endif; ?>
		<?php if ( $gaps ) : ?>
			<li>
				<?php esc_html_e( 'Чего не хватает одним удобрением:', 'sad-znaniy' ); ?>
				<ul>
					<?php foreach ( $gaps as $gap ) : ?>
						<li><?php echo wp_kses_post( $gap ); ?></li>
					<?php endforeach; ?>
				</ul>
			</li>
		<?php endif; ?>
		<?php if ( $excess ) : ?>
			<li>
				<?php esc_html_e( 'Где будет перебор:', 'sad-znaniy' ); ?>
				<ul>
					<?php foreach ( $excess as $row ) : ?>
						<li><?php echo wp_kses_post( $row ); ?></li>
					<?php endforeach; ?>
				</ul>
			</li>
		<?php endif; ?>
	</ul>

	<details class="calc-how">
		<summary><?php esc_html_e( 'Как получилась эта цифра', 'sad-znaniy' ); ?></summary>
		<ul>
			<?php foreach ( $calc['lines'] as $line ) : ?>
				<li><?php echo wp_kses_post( $line ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="calc-note"><?php esc_html_e( 'Проценты действующего вещества и нормы правятся в админке: «Внешний вид → Сад знаний → Калькулятор удобрений».', 'sad-znaniy' ); ?></p>
	</details>

	<div class="calc-warn">
		<h3><?php esc_html_e( 'Не забудьте', 'sad-znaniy' ); ?></h3>
		<ul>
			<?php foreach ( $calc['warnings'] as $warning ) : ?>
				<li><?php echo wp_kses_post( $warning ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
