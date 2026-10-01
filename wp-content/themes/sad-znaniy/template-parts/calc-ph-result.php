<?php
/**
 * Результат расчёта калькулятора грунта и pH.
 *
 * Ожидает в области видимости массив $calc из sad_znaniy_ph_calc().
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $calc ) || ! is_array( $calc ) ) {
	return;
}

$soils  = sad_znaniy_water_soils();
$config = sad_znaniy_ph_options();
$plants = $calc['plants'];

$decimals = ( abs( $calc['total'] ) < 100 ) ? 1 : 0;
$fmt      = static function ( $value, $dec = 0 ) {
	return number_format_i18n( $value, $dec );
};

/**
 * Печатает список растений с диапазоном pH и ссылкой на карточку.
 *
 * @param array $rows Строки растений.
 */
$plants_list = static function ( $rows ) {
	if ( ! $rows ) {
		echo '<p class="calc-note">' . esc_html__( 'Ничего из базы знаний сюда не попадает.', 'sad-znaniy' ) . '</p>';
		return;
	}
	echo '<ul class="calc-plants-list">';
	foreach ( $rows as $row ) {
		printf(
			'<li><a href="%1$s">%2$s</a> — pH %3$s</li>',
			esc_url( $row['url'] ),
			esc_html( $row['title'] ),
			esc_html( $row['range'] )
		);
	}
	echo '</ul>';
};
?>
<div class="calc-out" aria-live="polite">
	<div class="calc-main">
		<span class="calc-num"><?php echo esc_html( $fmt( $calc['total'], $decimals ) ); ?></span>
		<span class="calc-unit">
			<?php
			echo esc_html(
				'' !== $calc['method']
					? __( 'г материала на всю площадь', 'sad-znaniy' )
					: __( '— корректировать не нужно', 'sad-znaniy' )
			);
			?>
		</span>
	</div>

	<p class="calc-sum">
		<?php
		printf(
			/* translators: 1: тип почвы, 2: текущий pH, 3: целевой pH, 4: площадь */
			esc_html__( '%1$s · pH %2$s → %3$s · %4$s м²', 'sad-znaniy' ),
			esc_html( $soils[ $calc['soil'] ] ),
			esc_html( $fmt( $calc['current'], 1 ) ),
			esc_html( $fmt( $calc['target'], 1 ) ),
			esc_html( $fmt( $calc['qty'], 0 ) )
		);
		?>
	</p>

	<?php if ( '' !== $calc['method'] ) : ?>
		<ul class="calc-facts">
			<li>
				<?php
				printf(
					/* translators: 1: граммы на м², 2: материал */
					esc_html__( 'На 1 м² — %1$s г: %2$s', 'sad-znaniy' ),
					'<b>' . esc_html( $fmt( $calc['dose'], 0 ) ) . '</b>',
					esc_html( mb_strtolower( sad_znaniy_ph_methods()[ $calc['method'] ], 'UTF-8' ) )
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: 1: стаканы, 2: вёдра */
					esc_html__( 'Всего: около %1$s стаканов, то есть %2$s вёдер (по %3$s кг)', 'sad-znaniy' ),
					'<b>' . esc_html( $fmt( $calc['glasses'], 1 ) ) . '</b>',
					esc_html( $fmt( $calc['buckets'], 2 ) ),
					esc_html( $fmt( (float) $config['measure']['bucket'] / 1000, 1 ) )
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: 1: норма, 2: сдвиг pH, 3: почва */
					esc_html__( 'Норма для этого типа почвы: %1$s г/м² на каждый 1,0 pH, сдвиг — %2$s.', 'sad-znaniy' ),
					'<b>' . esc_html( $fmt( $calc['rate'], 0 ) ) . '</b>',
					esc_html( $fmt( abs( $calc['delta'] ), 1 ) )
				);
				?>
			</li>
		</ul>
	<?php endif; ?>

	<details class="calc-how">
		<summary><?php esc_html_e( 'Как получилась эта цифра', 'sad-znaniy' ); ?></summary>
		<ul>
			<?php foreach ( $calc['lines'] as $line ) : ?>
				<li><?php echo wp_kses_post( $line ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="calc-note"><?php esc_html_e( 'Нормы по типам почвы правятся в админке: «Внешний вид → Сад знаний → Калькулятор грунта и pH».', 'sad-znaniy' ); ?></p>
	</details>

	<div class="calc-plants">
		<h3><?php esc_html_e( 'Что растёт при вашем pH', 'sad-znaniy' ); ?></h3>

		<h4>
			<?php
			printf(
				/* translators: %s: текущий pH */
				esc_html__( 'Подходит прямо сейчас (pH %s)', 'sad-znaniy' ),
				esc_html( $fmt( $calc['current'], 1 ) )
			);
			?>
		</h4>
		<?php $plants_list( $plants['now'] ); ?>

		<?php if ( $plants['after'] ) : ?>
			<h4>
				<?php
				printf(
					/* translators: %s: целевой pH */
					esc_html__( 'Подойдёт после коррекции до pH %s', 'sad-znaniy' ),
					esc_html( $fmt( $calc['target'], 1 ) )
				);
				?>
			</h4>
			<?php $plants_list( $plants['after'] ); ?>
		<?php endif; ?>

		<?php if ( $plants['no'] ) : ?>
			<h4><?php esc_html_e( 'Не подходит — им нужен другой pH', 'sad-znaniy' ); ?></h4>
			<?php $plants_list( $plants['no'] ); ?>
		<?php endif; ?>

		<p class="calc-note"><?php esc_html_e( 'Диапазоны pH берутся из карточек растений базы знаний — заполните их в разделе «Растения», и список будет полным.', 'sad-znaniy' ); ?></p>
	</div>

	<div class="calc-warn">
		<h3><?php esc_html_e( 'Не забудьте', 'sad-znaniy' ); ?></h3>
		<ul>
			<?php foreach ( $calc['warnings'] as $warning ) : ?>
				<li><?php echo wp_kses_post( $warning ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
