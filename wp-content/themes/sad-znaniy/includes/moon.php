<?php
/**
 * Лунный посевной календарь — движок и справочник (Этап 5.6, Партия 1).
 *
 * Считает фазу Луны (синодический цикл) и знак зодиака (эклиптическая долгота
 * Луны) на произвольную дату. Канон интерпретации («что благоприятно») хранится
 * в опции и правится в админке: «Внешний вид → Лунный справочник».
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Крупные фазы (ключ => подпись) для канона и вердиктов.
 *
 * @return array
 */
function sad_znaniy_moon_phases() {
	return array(
		'new'    => __( 'Новолуние', 'sad-znaniy' ),
		'waxing' => __( 'Растущая', 'sad-znaniy' ),
		'full'   => __( 'Полнолуние', 'sad-znaniy' ),
		'waning' => __( 'Убывающая', 'sad-znaniy' ),
	);
}

/**
 * Стихии знаков зодиака (ключ => подпись).
 *
 * @return array
 */
function sad_znaniy_moon_elements() {
	return array(
		'fire'  => __( 'Огонь — плоды', 'sad-znaniy' ),
		'earth' => __( 'Земля — корнеплоды', 'sad-znaniy' ),
		'air'   => __( 'Воздух — цветы', 'sad-znaniy' ),
		'water' => __( 'Вода — листовые', 'sad-znaniy' ),
	);
}

/**
 * Знаки зодиака по порядку (эклиптическая долгота 0° = Овен).
 *
 * @return string[]
 */
function sad_znaniy_moon_zodiac() {
	return array( 'Овен', 'Телец', 'Близнецы', 'Рак', 'Лев', 'Дева', 'Весы', 'Скорпион', 'Стрелец', 'Козерог', 'Водолей', 'Рыбы' );
}

/**
 * Возраст и фаза Луны на дату.
 *
 * Синодический цикл 29,530588853 сут. Эталон новолуния — 2026-01-18 19:52 UTC.
 *
 * @param int $timestamp Метка времени (UTC) на полдень даты.
 * @return array
 */
function sad_znaniy_moon_phase( $timestamp ) {
	$synodic = 29.530588853;
	$ref     = gmmktime( 19, 52, 0, 1, 18, 2026 );
	$days    = ( (int) $timestamp - $ref ) / 86400.0;
	$pos     = fmod( fmod( $days, $synodic ) + $synodic, $synodic );
	$f       = $pos / $synodic;   // 0 — новолуние, 0.5 — полнолуние
	$tol     = 0.5 / $synodic;    // ±полсуток вокруг точной фазы

	$ico = array( '🌑', '🌒', '🌓', '🌔', '🌕', '🌖', '🌗', '🌘' );

	if ( $f <= $tol || $f >= 1 - $tol ) {
		$idx = 0; $name = __( 'Новолуние', 'sad-znaniy' );
	} elseif ( abs( $f - 0.25 ) <= $tol ) {
		$idx = 2; $name = __( 'Первая четверть', 'sad-znaniy' );
	} elseif ( abs( $f - 0.5 ) <= $tol ) {
		$idx = 4; $name = __( 'Полнолуние', 'sad-znaniy' );
	} elseif ( abs( $f - 0.75 ) <= $tol ) {
		$idx = 6; $name = __( 'Последняя четверть', 'sad-znaniy' );
	} elseif ( $f < 0.25 ) {
		$idx = 1; $name = __( 'Растущая молодая', 'sad-znaniy' );
	} elseif ( $f < 0.5 ) {
		$idx = 3; $name = __( 'Растущая', 'sad-znaniy' );
	} elseif ( $f < 0.75 ) {
		$idx = 5; $name = __( 'Убывающая', 'sad-znaniy' );
	} else {
		$idx = 7; $name = __( 'Убывающая старая', 'sad-znaniy' );
	}

	$key = ( 0 === $idx ) ? 'new' : ( 4 === $idx ? 'full' : ( $idx < 4 ? 'waxing' : 'waning' ) );

	return array(
		'age'    => $pos,
		'frac'   => $f,
		'key'    => $key,
		'name'   => $name,
		'icon'   => $ico[ $idx ],
		'waxing' => $f < 0.5,
		'illum'  => (int) round( ( 1 - cos( 2 * M_PI * $f ) ) / 2 * 100 ),
	);
}

/**
 * Эклиптическая долгота Луны (упрощённая формула, точность ~0,5°).
 *
 * @param int $timestamp Метка времени (UTC).
 * @return float Долгота 0–360°.
 */
function sad_znaniy_moon_longitude( $timestamp ) {
	$jd = ( (int) $timestamp ) / 86400.0 + 2440587.5;
	$T  = ( $jd - 2451545.0 ) / 36525.0;

	$lp = 218.3164477 + 481267.88123421 * $T;
	$d  = 297.8501921 + 445267.1114034 * $T;
	$m  = 357.5291092 + 35999.0502909 * $T;
	$mp = 134.9633964 + 477198.8675055 * $T;
	$f  = 93.2720950 + 483202.0175233 * $T;
	$r  = M_PI / 180.0;

	$lon = $lp
		+ 6.288774 * sin( $mp * $r )
		+ 1.274027 * sin( ( 2 * $d - $mp ) * $r )
		+ 0.658314 * sin( 2 * $d * $r )
		+ 0.213618 * sin( 2 * $mp * $r )
		- 0.185116 * sin( $m * $r )
		- 0.114332 * sin( 2 * $f * $r );

	return fmod( fmod( $lon, 360.0 ) + 360.0, 360.0 );
}

/**
 * Знак зодиака Луны на дату.
 *
 * @param int $timestamp Метка времени (UTC).
 * @return array Ключ стихии, индекс, название знака, долгота.
 */
function sad_znaniy_moon_sign( $timestamp ) {
	$lon   = sad_znaniy_moon_longitude( $timestamp );
	$names = sad_znaniy_moon_zodiac();
	$keys  = array_keys( sad_znaniy_moon_elements() );
	$idx   = (int) floor( $lon / 30.0 ) % 12;

	return array(
		'index'     => $idx,
		'name'      => $names[ $idx ],
		'element'   => $keys[ $idx % 4 ],
		'longitude' => $lon,
	);
}

/* ============================ КАНОН ИНТЕРПРЕТАЦИИ ============================ */

/**
 * Канон по умолчанию (традиционная школа лунного посевного календаря).
 *
 * @return array
 */
function sad_znaniy_moon_canon_default() {
	return array(
		'phase_good'   => array(
			'new'    => array(),
			'waxing' => array( 'posev', 'posadka' ),
			'full'   => array(),
			'waning' => array( 'obrezka', 'podkormka', 'sbor', 'zashchita' ),
		),
		'phase_bad'    => array(
			'new'    => array( 'posev', 'posadka', 'obrezka', 'podkormka' ),
			'waxing' => array(),
			'full'   => array( 'posev', 'posadka', 'obrezka' ),
			'waning' => array(),
		),
		'element_good' => array(
			'fire'  => array( 'posev', 'posadka' ),
			'earth' => array( 'posev', 'posadka', 'sbor', 'podkormka' ),
			'air'   => array( 'posev', 'posadka' ),
			'water' => array( 'posev', 'poliv', 'podkormka' ),
		),
	);
}

/**
 * Возвращает канон из опции (с подстановкой значений по умолчанию).
 *
 * @return array
 */
function sad_znaniy_moon_canon() {
	$def = sad_znaniy_moon_canon_default();
	$opt = get_option( 'sad_znaniy_moon_canon', array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}
	foreach ( array_keys( $def ) as $section ) {
		if ( ! isset( $opt[ $section ] ) || ! is_array( $opt[ $section ] ) ) {
			$opt[ $section ] = $def[ $section ];
		}
	}
	return $opt;
}

/**
 * Вердикты по фазе и стихии дня: какие работы благоприятны, какие нет.
 *
 * @param string $phase_key Ключ фазы (new/waxing/full/waning).
 * @param string $element   Ключ стихии (fire/earth/air/water).
 * @return array good[] и bad[] типов работ.
 */
function sad_znaniy_moon_verdict( $phase_key, $element ) {
	$canon = sad_znaniy_moon_canon();
	$pg    = isset( $canon['phase_good'][ $phase_key ] ) ? (array) $canon['phase_good'][ $phase_key ] : array();
	$pb    = isset( $canon['phase_bad'][ $phase_key ] ) ? (array) $canon['phase_bad'][ $phase_key ] : array();
	$eg    = isset( $canon['element_good'][ $element ] ) ? (array) $canon['element_good'][ $element ] : array();

	$good = array();
	$bad  = array();
	foreach ( array_keys( sad_znaniy_work_types() ) as $wt ) {
		if ( in_array( $wt, $pb, true ) ) {
			$bad[] = $wt;
		} elseif ( in_array( $wt, $pg, true ) || in_array( $wt, $eg, true ) ) {
			$good[] = $wt;
		}
	}

	return array( 'good' => $good, 'bad' => $bad );
}

/**
 * Полные данные лунного дня.
 *
 * @param int $year  Год.
 * @param int $month Месяц (1–12).
 * @param int $day   День.
 * @return array
 */
function sad_znaniy_moon_day( $year, $month, $day ) {
	$ts   = gmmktime( 12, 0, 0, $month, $day, $year );
	$phase = sad_znaniy_moon_phase( $ts );
	$sign  = sad_znaniy_moon_sign( $ts );
	$verd  = sad_znaniy_moon_verdict( $phase['key'], $sign['element'] );

	return array(
		'year'  => (int) $year,
		'month' => (int) $month,
		'day'   => (int) $day,
		'ts'    => $ts,
		'phase' => $phase,
		'sign'  => $sign,
		'good'  => $verd['good'],
		'bad'   => $verd['bad'],
	);
}

/* ============================ АДМИНКА ============================ */

/**
 * Регистрирует опцию канона.
 */
function sad_znaniy_moon_register_settings() {
	register_setting(
		'sad_znaniy_moon',
		'sad_znaniy_moon_canon',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sad_znaniy_moon_sanitize_canon',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'sad_znaniy_moon_register_settings' );

/**
 * Очищает канон перед сохранением.
 *
 * @param mixed $input Сырые значения.
 * @return array
 */
function sad_znaniy_moon_sanitize_canon( $input ) {
	$valid    = array_keys( sad_znaniy_work_types() );
	$phases   = array_keys( sad_znaniy_moon_phases() );
	$elements = array_keys( sad_znaniy_moon_elements() );
	$input    = is_array( $input ) ? $input : array();
	$out      = array( 'phase_good' => array(), 'phase_bad' => array(), 'element_good' => array() );

	foreach ( array( 'phase_good', 'phase_bad' ) as $section ) {
		foreach ( $phases as $ph ) {
			$out[ $section ][ $ph ] = array();
			if ( isset( $input[ $section ][ $ph ] ) && is_array( $input[ $section ][ $ph ] ) ) {
				foreach ( $input[ $section ][ $ph ] as $wt ) {
					$wt = sanitize_key( $wt );
					if ( in_array( $wt, $valid, true ) ) {
						$out[ $section ][ $ph ][] = $wt;
					}
				}
			}
		}
	}
	foreach ( $elements as $el ) {
		$out['element_good'][ $el ] = array();
		if ( isset( $input['element_good'][ $el ] ) && is_array( $input['element_good'][ $el ] ) ) {
			foreach ( $input['element_good'][ $el ] as $wt ) {
				$wt = sanitize_key( $wt );
				if ( in_array( $wt, $valid, true ) ) {
					$out['element_good'][ $el ][] = $wt;
				}
			}
		}
	}

	return $out;
}

/**
 * Добавляет страницу «Лунный справочник» под «Внешний вид».
 */
function sad_znaniy_moon_add_page() {
	add_theme_page(
		__( 'Лунный справочник', 'sad-znaniy' ),
		__( 'Лунный справочник', 'sad-znaniy' ),
		'manage_options',
		'sad-znaniy-moon',
		'sad_znaniy_moon_render_page'
	);
}
add_action( 'admin_menu', 'sad_znaniy_moon_add_page' );

/**
 * Сетка чекбоксов «строки × типы работ».
 *
 * @param string $name    Базовое имя поля (с индексами).
 * @param array  $rows    Ключ => подпись (фазы или стихии).
 * @param array  $current Отмеченные значения: rows_key => work_type[].
 * @param array  $work    Подписи типов работ.
 */
function sad_znaniy_moon_grid( $name, $rows, $current, $work ) {
	?>
	<table class="widefat striped" style="max-width:820px;">
		<thead><tr><th></th><?php foreach ( $work as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead>
		<tbody>
		<?php
		foreach ( $rows as $key => $label ) :
			$sel = isset( $current[ $key ] ) ? (array) $current[ $key ] : array();
			?>
			<tr>
				<td><strong><?php echo esc_html( $label ); ?></strong></td>
				<?php foreach ( $work as $wt => $wlabel ) : ?>
					<td style="text-align:center;">
						<input type="checkbox" name="<?php echo esc_attr( $name . '[' . $key . '][]' ); ?>" value="<?php echo esc_attr( $wt ); ?>" <?php checked( in_array( $wt, $sel, true ) ); ?>>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Выводит страницу «Лунный справочник».
 */
function sad_znaniy_moon_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$canon = sad_znaniy_moon_canon();
	$work  = sad_znaniy_work_types();
	$ph    = sad_znaniy_moon_phases();
	$el    = sad_znaniy_moon_elements();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Лунный справочник', 'sad-znaniy' ); ?></h1>
		<p class="description" style="max-width:820px;">
			<?php esc_html_e( 'Канон интерпретации: какие работы благоприятны в зависимости от фазы Луны и стихии знака зодиака. Это ваша экспертная настройка — правьте и сохраняйте.', 'sad-znaniy' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'sad_znaniy_moon' ); ?>

			<h2><?php esc_html_e( 'Фаза Луны — благоприятные работы', 'sad-znaniy' ); ?></h2>
			<?php sad_znaniy_moon_grid( 'sad_znaniy_moon_canon[phase_good]', $ph, $canon['phase_good'], $work ); ?>

			<h2><?php esc_html_e( 'Фаза Луны — неблагоприятные работы', 'sad-znaniy' ); ?></h2>
			<?php sad_znaniy_moon_grid( 'sad_znaniy_moon_canon[phase_bad]', $ph, $canon['phase_bad'], $work ); ?>

			<h2><?php esc_html_e( 'Стихия знака — благоприятные работы', 'sad-znaniy' ); ?></h2>
			<?php sad_znaniy_moon_grid( 'sad_znaniy_moon_canon[element_good]', $el, $canon['element_good'], $work ); ?>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
