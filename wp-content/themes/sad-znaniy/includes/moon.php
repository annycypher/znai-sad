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
	$seo   = sad_znaniy_moon_seo_option();
	$names = sad_znaniy_moon_month_names();
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

			<h2><?php esc_html_e( 'SEO и интро по месяцам', 'sad-znaniy' ); ?></h2>
			<p class="description" style="max-width:820px;"><?php esc_html_e( 'Оставьте поле пустым — подставится автогенерация. Интро — 2–3 абзаца своего текста на месяц (разделяйте пустой строкой).', 'sad-znaniy' ); ?></p>
			<table class="widefat striped" style="max-width:900px;">
				<thead><tr><th style="width:110px;"></th><th><?php esc_html_e( 'Title', 'sad-znaniy' ); ?></th><th><?php esc_html_e( 'Description', 'sad-znaniy' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $names as $sz_m => $sz_nm ) : $sz_s = isset( $seo[ $sz_m ] ) ? $seo[ $sz_m ] : array(); ?>
					<tr>
						<td><strong><?php echo esc_html( $sz_nm[0] ); ?></strong></td>
						<td><input type="text" class="widefat" name="sad_znaniy_moon_seo[<?php echo (int) $sz_m; ?>][title]" value="<?php echo esc_attr( isset( $sz_s['title'] ) ? $sz_s['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'авто', 'sad-znaniy' ); ?>"></td>
						<td><textarea class="widefat" rows="2" name="sad_znaniy_moon_seo[<?php echo (int) $sz_m; ?>][desc]" placeholder="<?php esc_attr_e( 'авто', 'sad-znaniy' ); ?>"><?php echo esc_textarea( isset( $sz_s['desc'] ) ? $sz_s['desc'] : '' ); ?></textarea></td>
					</tr>
					<tr>
						<td></td>
						<td colspan="2"><textarea class="widefat" rows="4" name="sad_znaniy_moon_seo[<?php echo (int) $sz_m; ?>][intro]" placeholder="<?php esc_attr_e( 'интро (пусто — без блока)', 'sad-znaniy' ); ?>"><?php echo esc_textarea( isset( $sz_s['intro'] ) ? $sz_s['intro'] : '' ); ?></textarea></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ============================ СТРАНИЦА И SEO ============================ */

/**
 * Названия месяцев: с заглавной, в именительном (для «на май») и в предложном.
 *
 * @return array
 */
function sad_znaniy_moon_month_names() {
	return array(
		1  => array( 'Январь', 'январь', 'январе' ),
		2  => array( 'Февраль', 'февраль', 'феврале' ),
		3  => array( 'Март', 'март', 'марте' ),
		4  => array( 'Апрель', 'апрель', 'апреле' ),
		5  => array( 'Май', 'май', 'мае' ),
		6  => array( 'Июнь', 'июнь', 'июне' ),
		7  => array( 'Июль', 'июль', 'июле' ),
		8  => array( 'Август', 'август', 'августе' ),
		9  => array( 'Сентябрь', 'сентябрь', 'сентябре' ),
		10 => array( 'Октябрь', 'октябрь', 'октябре' ),
		11 => array( 'Ноябрь', 'ноябрь', 'ноябре' ),
		12 => array( 'Декабрь', 'декабрь', 'декабре' ),
	);
}

/**
 * Склонение числительных: 1 день / 2 дня / 5 дней.
 *
 * @param int    $n    Число.
 * @param string $one  Форма для 1.
 * @param string $few  Форма для 2–4.
 * @param string $many Форма для остальных.
 * @return string
 */
function sad_znaniy_moon_plural( $n, $one, $few, $many ) {
	$n  = abs( (int) $n ) % 100;
	$n1 = $n % 10;
	if ( $n > 10 && $n < 20 ) {
		return $many;
	}
	if ( 1 === $n1 ) {
		return $one;
	}
	if ( $n1 >= 2 && $n1 <= 4 ) {
		return $few;
	}
	return $many;
}

/**
 * Текущий контекст страницы: месяц и год из GET или по умолчанию.
 *
 * @return array
 */
function sad_znaniy_moon_context() {
	$month = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : (int) current_time( 'n' );
	$year  = isset( $_GET['y'] ) ? absint( $_GET['y'] ) : (int) current_time( 'Y' );
	if ( $month < 1 || $month > 12 ) {
		$month = (int) current_time( 'n' );
	}
	if ( $year < 2026 || $year > 2028 ) {
		$year = (int) current_time( 'Y' );
	}
	return array( 'month' => $month, 'year' => $year );
}

/**
 * Дни месяца с данными Луны.
 *
 * @param int $year  Год.
 * @param int $month Месяц (1–12).
 * @return array
 */
function sad_znaniy_moon_month_days( $year, $month ) {
	$nd   = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );
	$days = array();
	for ( $d = 1; $d <= $nd; $d++ ) {
		$days[] = sad_znaniy_moon_day( $year, $month, $d );
	}
	return $days;
}

/**
 * Сколько дней месяца благоприятны для посева/посадки.
 *
 * @param int $year  Год.
 * @param int $month Месяц.
 * @return int
 */
function sad_znaniy_moon_good_count( $year, $month ) {
	$n = 0;
	foreach ( sad_znaniy_moon_month_days( $year, $month ) as $day ) {
		if ( in_array( 'posadka', $day['good'], true ) || in_array( 'posev', $day['good'], true ) ) {
			$n++;
		}
	}
	return $n;
}

/**
 * SEO-опции страницы (переопределения по месяцам).
 *
 * @return array
 */
function sad_znaniy_moon_seo_option() {
	$opt = get_option( 'sad_znaniy_moon_seo', array() );
	return is_array( $opt ) ? $opt : array();
}

/**
 * SEO-заголовок месяца (автогенерация или ручное переопределение).
 *
 * @param int $month Месяц.
 * @param int $year  Год.
 * @return string
 */
function sad_znaniy_moon_seo_title( $month, $year ) {
	$opt = sad_znaniy_moon_seo_option();
	if ( ! empty( $opt[ $month ]['title'] ) ) {
		return $opt[ $month ]['title'];
	}
	$names = sad_znaniy_moon_month_names();
	return 'Лунный посевной календарь на ' . $names[ $month ][1] . ' ' . $year . ': благоприятные дни по культурам';
}

/**
 * SEO-описание месяца (автогенерация или ручное переопределение).
 *
 * @param int $month Месяц.
 * @param int $year  Год.
 * @return string
 */
function sad_znaniy_moon_seo_description( $month, $year ) {
	$opt = sad_znaniy_moon_seo_option();
	if ( ! empty( $opt[ $month ]['desc'] ) ) {
		return $opt[ $month ]['desc'];
	}
	$names = sad_znaniy_moon_month_names();
	$n     = sad_znaniy_moon_good_count( $year, $month );
	return $n . ' ' . sad_znaniy_moon_plural( $n, 'благоприятный день', 'благоприятных дня', 'благоприятных дней' ) . ' для посадки в ' . $names[ $month ][2] . ' ' . $year . '. Фазы луны, знаки зодиака, запрещённые дни и совмещение с климатом региона.';
}

/**
 * Интро месяца.
 *
 * @param int $month Месяц.
 * @return string
 */
function sad_znaniy_moon_intro( $month ) {
	$opt = sad_znaniy_moon_seo_option();
	return ! empty( $opt[ $month ]['intro'] ) ? $opt[ $month ]['intro'] : '';
}

/**
 * Представительская культура месяца (для FAQ).
 *
 * @return array
 */
function sad_znaniy_moon_month_crop() {
	return array(
		1  => 'рассада',          2  => 'томаты на рассаду', 3  => 'рассада',
		4  => 'редис и зелень',   5  => 'огурцы и томаты',  6  => 'перец и кабачки',
		7  => 'томаты',           8  => 'сидераты',         9  => 'озимый чеснок',
		10 => 'чеснок и лук',     11 => 'лук и чеснок',     12 => 'рассада',
	);
}

/**
 * FAQ месяца (3 вопроса).
 *
 * @param int $year  Год.
 * @param int $month Месяц.
 * @return array
 */
function sad_znaniy_moon_faq( $year, $month ) {
	$names      = sad_znaniy_moon_month_names();
	$crop       = sad_znaniy_moon_month_crop();
	$days       = sad_znaniy_moon_month_days( $year, $month );
	$good_dates = array();
	$bad_dates  = array();
	foreach ( $days as $d ) {
		if ( in_array( 'posev', $d['good'], true ) || in_array( 'posadka', $d['good'], true ) ) {
			$good_dates[] = $d['day'];
		}
		if ( 'new' === $d['phase']['key'] || 'full' === $d['phase']['key'] ) {
			$bad_dates[] = $d['day'];
		}
	}
	$gd = $good_dates ? implode( ', ', array_slice( $good_dates, 0, 6 ) ) : '—';
	$bd = $bad_dates ? implode( ', ', $bad_dates ) : '';

	return array(
		array(
			'q' => 'Когда сеять ' . $crop[ $month ] . ' по лунному календарю?',
			'a' => 'Благоприятные дни в ' . $names[ $month ][2] . ' ' . $year . ': ' . $gd . '. Ориентируйтесь также на погоду и агрономические сроки вашего региона.',
		),
		array(
			'q' => 'Какие дни в ' . $names[ $month ][2] . ' нельзя работать в саду?',
			'a' => $bd ? ( 'Дни покоя — новолуние и полнолуние: ' . $bd . '. В эти дни не сеют, не сажают и не обрезают.' ) : 'В этом месяце ярко выраженных дней покоя нет.',
		),
		array(
			'q' => 'Совпадает ли лунный календарь с климатом региона?',
			'a' => 'Луна от региона не зависит, а сроки посева — зависят. Совместите лунный календарь с Календарём дачника: если луна «за», а в вашем регионе ещё рано — ждём.',
		),
	);
}

/**
 * JSON-LD страницы месяца: ItemList + FAQPage + BreadcrumbList.
 *
 * @param int $month Месяц.
 * @param int $year  Год.
 * @return string
 */
function sad_znaniy_moon_schema( $month, $year ) {
	$names    = sad_znaniy_moon_month_names();
	$base     = home_url( '/lunnyj-kalendar/' );
	$page_url = $base . $names[ $month ][1] . '/';

	$items = array();
	foreach ( sad_znaniy_moon_month_days( $year, $month ) as $i => $d ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $d['day'] . ' ' . $names[ $month ][2] . ' — ' . $d['phase']['name'] . ', ' . $d['sign']['name'],
			'url'      => $page_url . '#d' . $d['day'],
		);
	}

	$faq = array();
	foreach ( sad_znaniy_moon_faq( $year, $month ) as $q ) {
		$faq[] = array(
			'@type'          => 'Question',
			'name'           => $q['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $q['a'] ),
		);
	}

	$graph = array(
		array( '@type' => 'ItemList', 'name' => 'Дни ' . $names[ $month ][1] . ' ' . $year, 'itemListElement' => $items ),
		array( '@type' => 'FAQPage', 'mainEntity' => $faq ),
		array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => home_url( '/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Лунный календарь', 'item' => $base ),
				array( '@type' => 'ListItem', 'position' => 3, 'name' => $names[ $month ][0] . ' ' . $year, 'item' => $page_url ),
			),
		),
	);

	return wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE );
}

/**
 * Является ли текущий запрос страницей лунного календаря.
 *
 * @return bool
 */
function sad_znaniy_moon_is_page() {
	return is_page_template( 'template-lunar.php' );
}

/**
 * Подменяет <title> на странице лунного календаря.
 *
 * @param array $parts Части заголовка.
 * @return array
 */
function sad_znaniy_moon_title_parts( $parts ) {
	if ( sad_znaniy_moon_is_page() ) {
		$c               = sad_znaniy_moon_context();
		$parts['title']  = sad_znaniy_moon_seo_title( $c['month'], $c['year'] );
		unset( $parts['site'], $parts['tagline'] );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'sad_znaniy_moon_title_parts' );

/**
 * Печатает meta description и JSON-LD на странице лунного календаря.
 */
function sad_znaniy_moon_head() {
	if ( ! sad_znaniy_moon_is_page() ) {
		return;
	}
	$c = sad_znaniy_moon_context();
	echo '<meta name="description" content="' . esc_attr( sad_znaniy_moon_seo_description( $c['month'], $c['year'] ) ) . '" />' . "\n";
	echo '<script type="application/ld+json">' . sad_znaniy_moon_schema( $c['month'], $c['year'] ) . '</script>' . "\n";
}
add_action( 'wp_head', 'sad_znaniy_moon_head', 3 );

/**
 * Подключает стили и скрипт страницы лунного календаря.
 */
function sad_znaniy_moon_assets() {
	if ( ! is_page_template( 'template-lunar.php' ) ) {
		return;
	}
	$dir = get_template_directory();
	wp_enqueue_style( 'sad-znaniy-lunar', get_template_directory_uri() . '/assets/css/lunar.css', array(), filemtime( $dir . '/assets/css/lunar.css' ) );
	wp_enqueue_script( 'sad-znaniy-moon', get_template_directory_uri() . '/assets/js/moon.js', array(), filemtime( $dir . '/assets/js/moon.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'sad_znaniy_moon_assets' );

/**
 * Регистрирует опцию SEO/интро страницы.
 */
function sad_znaniy_moon_register_seo() {
	register_setting(
		'sad_znaniy_moon',
		'sad_znaniy_moon_seo',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sad_znaniy_moon_sanitize_seo',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'sad_znaniy_moon_register_seo' );

/**
 * Очищает SEO/интро перед сохранением.
 *
 * @param mixed $input Сырые значения.
 * @return array
 */
function sad_znaniy_moon_sanitize_seo( $input ) {
	$input = is_array( $input ) ? $input : array();
	$out   = array();
	foreach ( array_keys( sad_znaniy_moon_month_names() ) as $m ) {
		$row        = isset( $input[ $m ] ) && is_array( $input[ $m ] ) ? $input[ $m ] : array();
		$out[ $m ] = array(
			'title' => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
			'desc'  => isset( $row['desc'] ) ? sanitize_textarea_field( $row['desc'] ) : '',
			'intro' => isset( $row['intro'] ) ? sanitize_textarea_field( $row['intro'] ) : '',
		);
	}
	return $out;
}
