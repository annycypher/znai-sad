<?php
/**
 * Результат расчёта калькулятора посева семян.
 *
 * Ожидает в области видимости массив $calc из sad_znaniy_sow_calc().
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $calc ) || ! is_array( $calc ) ) {
	return;
}

$regions = sad_znaniy_region_keys();
$crops   = sad_znaniy_sow_crops();

/**
 * Русская форма слова «день» для числа.
 *
 * @param int $number Число дней.
 * @return string
 */
$days_word = static function ( $number ) {
	$number = abs( (int) $number );
	$tail   = $number % 100;
	if ( $tail > 10 && $tail < 20 ) {
		return __( 'дней', 'sad-znaniy' );
	}
	$tail = $number % 10;
	if ( 1 === $tail ) {
		return __( 'день', 'sad-znaniy' );
	}
	if ( $tail >= 2 && $tail <= 4 ) {
		return __( 'дня', 'sad-znaniy' );
	}

	return __( 'дней', 'sad-znaniy' );
};

$days_to_sow = (int) $calc['days_to_sow'];

if ( $days_to_sow > 0 ) {
	$count_text = sprintf(
		/* translators: 1: число, 2: форма слова «день» */
		__( 'До посева — %1$s %2$s.', 'sad-znaniy' ),
		number_format_i18n( $days_to_sow ),
		$days_word( $days_to_sow )
	);
} elseif ( 0 === $days_to_sow ) {
	$count_text = __( 'Сеять можно сегодня.', 'sad-znaniy' );
} else {
	$count_text = sprintf(
		/* translators: %s: число */
		__( 'Срок посева прошёл %s тому назад.', 'sad-znaniy' ),
		number_format_i18n( abs( $days_to_sow ) ) . ' ' . $days_word( $days_to_sow )
	);
}

$plant_label = '';
if ( $days_to_sow < 0 && '' !== $calc['next_sow_label'] ) {
	$count_text .= ' ' . sprintf(
		/* translators: %s: дата */
		__( 'Следующий сезон — %s.', 'sad-znaniy' ),
		$calc['next_sow_label']
	);
}
?>
<div class="calc-out" aria-live="polite">
	<div class="calc-main">
		<span class="calc-num"><?php echo esc_html( $calc['sow_label'] ); ?></span>
		<span class="calc-unit">
			<?php
			echo esc_html(
				( 'rassada' === $calc['method'] )
					? __( '— сеять на рассаду', 'sad-znaniy' )
					: __( '— сеять прямо в грунт', 'sad-znaniy' )
			);
			?>
		</span>
	</div>

	<p class="calc-sum">
		<?php
		printf(
			/* translators: 1: регион, 2: культура */
			esc_html__( '%1$s · %2$s', 'sad-znaniy' ),
			esc_html( $regions[ $calc['region'] ] ),
			esc_html( $crops[ $calc['crop'] ] )
		);
		?>
	</p>

	<p class="calc-count"><?php echo esc_html( $count_text ); ?></p>

	<ul class="calc-facts">
		<li>
			<?php
			printf(
				/* translators: %s: дата */
				esc_html__( 'Высадка в грунт: %s', 'sad-znaniy' ),
				'<b>' . esc_html( $calc['plant_label'] ) . '</b>'
			);
			?>
		</li>
		<li>
			<?php
			if ( $calc['transplant'] > 0 ) {
				printf(
					/* translators: 1: дней, 2: форма слова */
					esc_html__( 'Рассада: %1$s %2$s до высадки', 'sad-znaniy' ),
					'<b>' . esc_html( number_format_i18n( $calc['transplant'] ) ) . '</b>',
					esc_html( $days_word( $calc['transplant'] ) )
				);
			} else {
				esc_html_e( 'Без рассады: семена сразу в открытый грунт', 'sad-znaniy' );
			}
			?>
		</li>
		<li>
			<?php
			printf(
				/* translators: %s: дата */
				esc_html__( 'Заморозки в регионе — до %s', 'sad-znaniy' ),
				'<b>' . esc_html( $calc['frost_label'] ) . '</b>'
			);
			?>
		</li>
	</ul>

	<details class="calc-how">
		<summary><?php esc_html_e( 'Как получилась эта дата', 'sad-znaniy' ); ?></summary>
		<ul>
			<?php foreach ( $calc['lines'] as $line ) : ?>
				<li><?php echo wp_kses_post( $line ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="calc-note"><?php esc_html_e( 'Сроки по регионам и возраст рассады правятся в админке: «Внешний вид → Сад знаний → Калькулятор посева семян».', 'sad-znaniy' ); ?></p>
	</details>

	<p class="calc-links">
		<?php
		if ( $calc['calendar_url'] ) {
			printf(
				'<a class="calc-link" href="%1$s">%2$s</a>',
				esc_url( $calc['calendar_url'] ),
				esc_html__( 'Что делать в этом месяце по календарю', 'sad-znaniy' )
			);
		}
		if ( $calc['plant_url'] ) {
			printf(
				'<a class="calc-link" href="%1$s">%2$s</a>',
				esc_url( $calc['plant_url'] ),
				sprintf(
					/* translators: %s: культура */
					esc_html__( 'Карточка: %s', 'sad-znaniy' ),
					esc_html( $crops[ $calc['crop'] ] )
				)
			);
		}
		?>
	</p>

	<div class="calc-warn">
		<h3><?php esc_html_e( 'Не забудьте', 'sad-znaniy' ); ?></h3>
		<ul>
			<?php foreach ( $calc['warnings'] as $warning ) : ?>
				<li><?php echo wp_kses_post( $warning ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
