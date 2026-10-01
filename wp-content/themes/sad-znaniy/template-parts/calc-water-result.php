<?php
/**
 * Результат расчёта калькулятора полива.
 *
 * Ожидает в области видимости массив $calc из sad_znaniy_water_calc().
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $calc ) || ! is_array( $calc ) ) {
	return;
}

$type_label = sad_znaniy_option_label( sad_znaniy_water_types(), $calc['type'] );
$soil_label = sad_znaniy_option_label( sad_znaniy_water_soils(), $calc['soil'] );
$sea_label  = sad_znaniy_option_label( sad_znaniy_water_seasons(), $calc['season'] );
$reg_label  = sad_znaniy_option_label( sad_znaniy_region_keys(), $calc['region'] );
$options    = sad_znaniy_water_calc_options();

$decimals     = ( $calc['liters_txt'] < 20 ) ? 1 : 0;
$per_unit_txt = number_format_i18n( $calc['per_unit'], 2 );
$week_txt     = number_format_i18n( $calc['per_week'], ( $calc['per_week'] < 20 ) ? 1 : 0 );
$cans_txt     = number_format_i18n( $calc['cans'], ( $calc['cans'] < 10 ) ? 1 : 0 );
$per_week_n   = $calc['per_week'] > 0 ? round( 7 / $calc['days'], 1 ) : 0;
?>
<div class="calc-out" aria-live="polite">
	<div class="calc-main">
		<span class="calc-num"><?php echo esc_html( number_format_i18n( $calc['liters_txt'], $decimals ) ); ?></span>
		<span class="calc-unit"><?php esc_html_e( 'л за один полив', 'sad-znaniy' ); ?></span>
	</div>

	<p class="calc-sum">
		<?php
		printf(
			/* translators: 1: что поливаем, 2: почва, 3: погода, 4: регион, 5: количество (м² или растений) */
			esc_html__( '%1$s · %2$s почва · %3$s · %4$s · %5$s', 'sad-znaniy' ),
			esc_html( $type_label ),
			esc_html( mb_strtolower( $soil_label, 'UTF-8' ) ),
			esc_html( mb_strtolower( $sea_label, 'UTF-8' ) ),
			esc_html( $reg_label ),
			esc_html( $calc['per_plant']
				? sprintf( _n( '1 растение', '%s растений', (int) $calc['qty'], 'sad-znaniy' ), number_format_i18n( $calc['qty'] ) )
				: sprintf( _n( '1 м²', '%s м²', (int) $calc['qty'], 'sad-znaniy' ), number_format_i18n( $calc['qty'] ) ) )
		);
		?>
	</p>

	<ul class="calc-facts">
		<li>
			<?php
			printf(
				/* translators: 1: число дней, 2: раз в неделю */
				esc_html__( 'Поливать примерно раз в %1$s дн. — это около %2$s раз в неделю', 'sad-znaniy' ),
				'<b>' . (int) $calc['days'] . '</b>',
				'<b>' . esc_html( number_format_i18n( $per_week_n, 1 ) ) . '</b>'
			);
			?>
		</li>
		<li>
			<?php
			printf(
				/* translators: %s: литры за неделю */
				esc_html__( 'За неделю уйдёт примерно %s л', 'sad-znaniy' ),
				'<b>' . esc_html( $week_txt ) . '</b>'
			);
			?>
		</li>
		<li>
			<?php if ( 'drip' === $calc['method'] ) : ?>
				<?php
				printf(
					/* translators: 1: минуты капельного полива, 2: расход */
					esc_html__( 'Это около %1$s мин. капельного полива (расход %2$s л/час на 1 м²)', 'sad-znaniy' ),
					'<b>' . esc_html( number_format_i18n( $calc['drip_min'], 0 ) ) . '</b>',
					esc_html( number_format_i18n( (float) $options['drip'], 1 ) )
				);
				?>
			<?php else : ?>
				<?php
				printf(
					/* translators: 1: число леек, 2: объём лейки */
					esc_html__( 'Это около %1$s леек по %2$s л', 'sad-znaniy' ),
					'<b>' . esc_html( $cans_txt ) . '</b>',
					esc_html( number_format_i18n( (float) $options['can'], 0 ) )
				);
				?>
			<?php endif; ?>
		</li>
		<li>
			<?php
			printf(
				/* translators: 1: литры на единицу, 2: единица измерения */
				esc_html__( 'На %2$s приходится около %1$s л', 'sad-znaniy' ),
				'<b>' . esc_html( $per_unit_txt ) . '</b>',
				esc_html( $calc['per_plant'] ? __( 'одно растение', 'sad-znaniy' ) : __( '1 м²', 'sad-znaniy' ) )
			);
			?>
		</li>
	</ul>

	<details class="calc-how">
		<summary><?php esc_html_e( 'Как получилась эта цифра', 'sad-znaniy' ); ?></summary>
		<ul>
			<?php foreach ( $calc['lines'] as $line ) : ?>
				<li><?php echo wp_kses_post( $line ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="calc-note"><?php esc_html_e( 'Все нормативы можно изменить в админке: «Внешний вид → Сад знаний → Калькулятор полива».', 'sad-znaniy' ); ?></p>
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
