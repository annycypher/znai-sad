<?php
/**
 * Калькуляторы «Сад знаний» (Этап 4).
 *
 * Полив: все нормативы и коэффициенты лежат в опциях темы (правит владелец
 * в админке), расчёт выполняет PHP — форма отправляется методом GET, поэтому
 * ссылку с расчётом можно сохранить и переслать, а без JS инструмент работает.
 *
 * Все числа в настройках помечены для вычитки владельцем: источники — типовые
 * агрономические нормы (проверить перед публикацией).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Нормативы калькулятора полива по умолчанию.
 *
 * norms — литры за один полив (на 1 м² или на 1 растение),
 * days  — базовая периодичность в днях,
 * vol/days у почвы и сезона — множители объёма и периодичности,
 * region — множитель периодичности (меньше 1 = поливать чаще).
 *
 * @return array
 */
function sad_znaniy_water_defaults() {
	return array(
		'norms'  => array(
			'beds'  => 10,
			'green' => 12,
			'lawn'  => 15,
			'bush'  => 10,
			'tree'  => 40,
		),
		'days'   => array(
			'beds'  => 3,
			'green' => 2,
			'lawn'  => 5,
			'bush'  => 7,
			'tree'  => 10,
		),
		'soil'   => array(
			'sand' => array(
				'vol'  => 0.8,
				'days' => 0.8,
			),
			'loam' => array(
				'vol'  => 1.0,
				'days' => 1.0,
			),
			'clay' => array(
				'vol'  => 1.1,
				'days' => 1.25,
			),
			'peat' => array(
				'vol'  => 1.0,
				'days' => 1.1,
			),
		),
		'season' => array(
			'cool' => array(
				'vol'  => 0.9,
				'days' => 1.25,
			),
			'mid'  => array(
				'vol'  => 1.0,
				'days' => 1.0,
			),
			'hot'  => array(
				'vol'  => 1.1,
				'days' => 0.75,
			),
		),
		'region' => array(
			'south' => 0.85,
			'mid'   => 1.0,
			'ural'  => 1.05,
			'sib'   => 1.15,
			'dv'    => 1.25,
		),
		'can'    => 10,
		'drip'   => 3,
	);
}

/**
 * Человекочитаемые подписи типов посадок.
 *
 * @return array
 */
function sad_znaniy_water_types() {
	return array(
		'beds'  => __( 'Грядки и огород', 'sad-znaniy' ),
		'green' => __( 'Теплица', 'sad-znaniy' ),
		'lawn'  => __( 'Газон', 'sad-znaniy' ),
		'bush'  => __( 'Кустарники (ягодные)', 'sad-znaniy' ),
		'tree'  => __( 'Плодовые деревья', 'sad-znaniy' ),
	);
}

/**
 * Подписи типов почвы.
 *
 * @return array
 */
function sad_znaniy_water_soils() {
	return array(
		'sand' => __( 'Песчаная', 'sad-znaniy' ),
		'loam' => __( 'Суглинок (обычная)', 'sad-znaniy' ),
		'clay' => __( 'Глинистая', 'sad-znaniy' ),
		'peat' => __( 'Торфяная', 'sad-znaniy' ),
	);
}

/**
 * Подписи сезонов (погоды на неделю).
 *
 * @return array
 */
function sad_znaniy_water_seasons() {
	return array(
		'cool' => __( 'Прохладно (до +20 °C)', 'sad-znaniy' ),
		'mid'  => __( 'Обычное лето (+20…+28 °C)', 'sad-znaniy' ),
		'hot'  => __( 'Жара (выше +28 °C)', 'sad-znaniy' ),
	);
}

/**
 * Нормативы полива из опций темы (глубокое слияние с умолчаниями).
 *
 * @return array
 */
function sad_znaniy_water_calc_options() {
	$saved = get_option( 'sz_calc_water', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return sad_znaniy_array_merge_deep( sad_znaniy_water_defaults(), $saved );
}

/**
 * Рекурсивно накладывает сохранённые значения на значения по умолчанию.
 *
 * @param array $base    База.
 * @param array $overlay Наложение.
 * @return array
 */
function sad_znaniy_array_merge_deep( $base, $overlay ) {
	foreach ( $overlay as $key => $value ) {
		if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
			$base[ $key ] = sad_znaniy_array_merge_deep( $base[ $key ], $value );
		} elseif ( array_key_exists( $key, $base ) && ! is_array( $value ) ) {
			$base[ $key ] = $value;
		}
	}

	return $base;
}

/**
 * Очищает нормативы калькулятора полива, пришедшие из админки.
 *
 * @param mixed $input Сырые значения.
 * @return array
 */
function sad_znaniy_water_sanitize( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$default = sad_znaniy_water_defaults();
	$out     = array();

	$ranges = array(
		'norms'  => array( 1, 200 ),
		'days'   => array( 1, 30 ),
		'soil'   => array( 0.3, 3 ),
		'season' => array( 0.3, 3 ),
		'region' => array( 0.3, 3 ),
	);

	foreach ( array( 'norms', 'days' ) as $group ) {
		foreach ( $default[ $group ] as $key => $def ) {
			$value         = isset( $input[ $group ][ $key ] ) ? (float) $input[ $group ][ $key ] : $def;
			$value         = min( $ranges[ $group ][1], max( $ranges[ $group ][0], $value ) );
			$out[ $group ][ $key ] = $value;
		}
	}

	foreach ( array( 'soil', 'season' ) as $group ) {
		foreach ( $default[ $group ] as $key => $def ) {
			foreach ( array( 'vol', 'days' ) as $field ) {
				$value = isset( $input[ $group ][ $key ][ $field ] ) ? (float) $input[ $group ][ $key ][ $field ] : $def[ $field ];
				$out[ $group ][ $key ][ $field ] = min( $ranges[ $group ][1], max( $ranges[ $group ][0], $value ) );
			}
		}
	}

	foreach ( $default['region'] as $key => $def ) {
		$value = isset( $input['region'][ $key ] ) ? (float) $input['region'][ $key ] : $def;
		$out['region'][ $key ] = min( $ranges['region'][1], max( $ranges['region'][0], $value ) );
	}

	$out['can']  = min( 50, max( 1, isset( $input['can'] ) ? (float) $input['can'] : $default['can'] ) );
	$out['drip'] = min( 20, max( 0.5, isset( $input['drip'] ) ? (float) $input['drip'] : $default['drip'] ) );

	return $out;
}

/**
 * Шорткод «[sz_calc_water]» — калькулятор полива.
 *
 * Параметры расчёта приходят в адресе страницы (GET), поэтому ссылкой
 * с готовым расчётом можно поделиться, а без JS инструмент работает.
 *
 * @param array $atts Атрибуты шорткода.
 * @return string
 */
function sad_znaniy_water_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'title' => '' ),
		$atts,
		'sz_calc_water'
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- это публичный расчёт, nonce не нужен.
	$args = array(
		'type'   => isset( $_GET['sz_type'] ) ? sanitize_key( wp_unslash( $_GET['sz_type'] ) ) : 'beds',
		'qty'    => isset( $_GET['sz_qty'] ) ? (float) wp_unslash( $_GET['sz_qty'] ) : 10,
		'soil'   => isset( $_GET['sz_soil'] ) ? sanitize_key( wp_unslash( $_GET['sz_soil'] ) ) : 'loam',
		'season' => isset( $_GET['sz_season'] ) ? sanitize_key( wp_unslash( $_GET['sz_season'] ) ) : 'mid',
		'region' => isset( $_GET['sz_region'] ) ? sanitize_key( wp_unslash( $_GET['sz_region'] ) ) : 'mid',
		'method' => isset( $_GET['sz_method'] ) ? sanitize_key( wp_unslash( $_GET['sz_method'] ) ) : 'can',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$calc = sad_znaniy_water_calc( $args );
	sad_znaniy_calc_enqueue();

	$types   = sad_znaniy_water_types();
	$soils   = sad_znaniy_water_soils();
	$seasons = sad_znaniy_water_seasons();
	$regions = sad_znaniy_region_keys();

	ob_start();
	?>
	<div class="calc">
		<?php if ( '' !== $atts['title'] ) : ?>
			<h2 class="calc-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<form class="calc-form" method="get" action="">
			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Что поливаем', 'sad-znaniy' ); ?></span>
					<select name="sz_type">
						<?php foreach ( $types as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="calc-field">
					<span><?php echo $calc['per_plant'] ? esc_html__( 'Сколько растений', 'sad-znaniy' ) : esc_html__( 'Площадь, м²', 'sad-znaniy' ); ?></span>
					<input type="number" name="sz_qty" min="1" max="1000" step="1" value="<?php echo esc_attr( (int) $calc['qty'] ); ?>">
				</label>
			</div>

			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Почва', 'sad-znaniy' ); ?></span>
					<select name="sz_soil">
						<?php foreach ( $soils as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['soil'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="calc-field">
					<span><?php esc_html_e( 'Погода на неделе', 'sad-znaniy' ); ?></span>
					<select name="sz_season">
						<?php foreach ( $seasons as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['season'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Регион', 'sad-znaniy' ); ?></span>
					<select name="sz_region" data-region="1" data-has-region="<?php echo isset( $_GET['sz_region'] ) ? '1' : '0'; ?>">
						<?php foreach ( $regions as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['region'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="calc-field">
					<span><?php esc_html_e( 'Как поливаете', 'sad-znaniy' ); ?></span>
					<select name="sz_method">
						<option value="can" <?php selected( $calc['method'], 'can' ); ?>><?php esc_html_e( 'Лейкой / ведром', 'sad-znaniy' ); ?></option>
						<option value="hose" <?php selected( $calc['method'], 'hose' ); ?>><?php esc_html_e( 'Из шланга', 'sad-znaniy' ); ?></option>
						<option value="drip" <?php selected( $calc['method'], 'drip' ); ?>><?php esc_html_e( 'Капельный полив', 'sad-znaniy' ); ?></option>
					</select>
				</label>
			</div>

			<p class="calc-actions">
				<button type="submit" class="btn btn-dark"><?php esc_html_e( 'Рассчитать', 'sad-znaniy' ); ?><span class="arr">→</span></button>
				<span class="calc-hint"><?php esc_html_e( 'Ссылку с расчётом можно сохранить и переслать — все параметры передаются в адресе страницы.', 'sad-znaniy' ); ?></span>
			</p>
		</form>
		<?php
		$tpl = get_template_directory() . '/template-parts/calc-water-result.php';
		if ( file_exists( $tpl ) ) {
			include $tpl;
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}
/**
 * Поля нормативов калькулятора полива для страницы настроек темы.
 *
 * @param array $o Текущие нормативы.
 */
function sad_znaniy_water_admin_fields( $o ) {
	$types   = sad_znaniy_water_types();
	$soils   = sad_znaniy_water_soils();
	$seasons = sad_znaniy_water_seasons();
	$regions = sad_znaniy_region_keys();
	$name    = 'sad_znaniy_options[sz_calc_water]';

	/**
	 * Печатает числовое поле настройки.
	 *
	 * @param string $path  Путь внутри опции.
	 * @param float  $value Значение.
	 * @param string $step  Шаг.
	 */
	$field = function ( $path, $value, $step = '0.05' ) use ( $name ) {
		printf(
			'<input type="number" step="%1$s" name="%2$s" value="%3$s" style="width:92px;">',
			esc_attr( $step ),
			esc_attr( $name . $path ),
			esc_attr( $value )
		);
	};
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Норма за один полив, л', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $types as $key => $label ) : ?>
					<label style="display:inline-block;margin:0 16px 10px 0;font-size:12px;">
						<?php echo esc_html( $label ); ?><br>
						<?php $field( '[norms][' . $key . ']', $o['norms'][ $key ], '0.5' ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'На 1 м² — грядки, теплица, газон; на одно растение — кустарники и деревья. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Поливать раз в … дней', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $types as $key => $label ) : ?>
					<label style="display:inline-block;margin:0 16px 10px 0;font-size:12px;">
						<?php echo esc_html( $label ); ?><br>
						<?php $field( '[days][' . $key . ']', $o['days'][ $key ], '1' ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Базовый интервал при обычной погоде и суглинке. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Поправка на почву', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $soils as $key => $label ) : ?>
					<div style="margin-bottom:8px;">
						<strong style="font-size:12px;"><?php echo esc_html( $label ); ?></strong> —
						<?php esc_html_e( 'объём', 'sad-znaniy' ); ?> <?php $field( '[soil][' . $key . '][vol]', $o['soil'][ $key ]['vol'] ); ?>
						<?php esc_html_e( 'интервал', 'sad-znaniy' ); ?> <?php $field( '[soil][' . $key . '][days]', $o['soil'][ $key ]['days'] ); ?>
					</div>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Меньше 1 — поливать чаще и меньшим объёмом (песок), больше 1 — реже (глина). ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Поправка на погоду', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $seasons as $key => $label ) : ?>
					<div style="margin-bottom:8px;">
						<strong style="font-size:12px;"><?php echo esc_html( $label ); ?></strong> —
						<?php esc_html_e( 'объём', 'sad-znaniy' ); ?> <?php $field( '[season][' . $key . '][vol]', $o['season'][ $key ]['vol'] ); ?>
						<?php esc_html_e( 'интервал', 'sad-znaniy' ); ?> <?php $field( '[season][' . $key . '][days]', $o['season'][ $key ]['days'] ); ?>
					</div>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'В жару интервал меньше 1 (поливаем чаще), в прохладу — больше. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Поправка на регион', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $regions as $key => $label ) : ?>
					<label style="display:inline-block;margin:0 16px 10px 0;font-size:12px;">
						<?php echo esc_html( $label ); ?><br>
						<?php $field( '[region][' . $key . ']', $o['region'][ $key ] ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Множитель интервала: для Юга меньше 1 (суховеи), для Дальнего Востока больше 1 (влажно). ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Бытовые мерки', 'sad-znaniy' ); ?></th>
			<td>
				<label style="font-size:12px;">
					<?php esc_html_e( 'Объём лейки, л', 'sad-znaniy' ); ?><br>
					<?php $field( '[can]', $o['can'], '0.5' ); ?>
				</label>
				<label style="font-size:12px;margin-left:18px;">
					<?php esc_html_e( 'Капельный полив, л/час на 1 м²', 'sad-znaniy' ); ?><br>
					<?php $field( '[drip]', $o['drip'] ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Нужны, чтобы переводить литры в лейки и минуты полива. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}


function sad_znaniy_water_calc( $args ) {
	$o = sad_znaniy_water_calc_options();

	$types   = sad_znaniy_water_types();
	$soils   = sad_znaniy_water_soils();
	$seasons = sad_znaniy_water_seasons();
	$regions = sad_znaniy_region_keys();

	$args = wp_parse_args(
		$args,
		array(
			'type'   => 'beds',
			'qty'    => 10,
			'soil'   => 'loam',
			'season' => 'mid',
			'region' => 'mid',
			'method' => 'can',
		)
	);

	$type   = isset( $types[ $args['type'] ] ) ? $args['type'] : 'beds';
	$soil   = isset( $soils[ $args['soil'] ] ) ? $args['soil'] : 'loam';
	$season = isset( $seasons[ $args['season'] ] ) ? $args['season'] : 'mid';
	$region = isset( $regions[ $args['region'] ] ) ? $args['region'] : 'mid';
	$method = in_array( $args['method'], array( 'can', 'hose', 'drip' ), true ) ? $args['method'] : 'can';

	$per_plant = in_array( $type, array( 'bush', 'tree' ), true );
	$qty       = (float) $args['qty'];
	$qty       = min( 1000, max( 1, $qty ) );

	$base_vol  = (float) $o['norms'][ $type ];
	$soil_vol  = (float) $o['soil'][ $soil ]['vol'];
	$season_vol = (float) $o['season'][ $season ]['vol'];

	$per_unit = $base_vol * $soil_vol * $season_vol;
	$liters   = $per_unit * $qty;
	$liters_r = ( $liters >= 20 ) ? round( $liters ) : round( $liters, 1 );

	$days = (float) $o['days'][ $type ]
		* (float) $o['soil'][ $soil ]['days']
		* (float) $o['season'][ $season ]['days']
		* (float) $o['region'][ $region ];
	$days = max( 1, (int) round( $days ) );

	$per_week = $liters * ( 7 / $days );
	$cans     = $liters / max( 0.1, (float) $o['can'] );
	$drip_min = ( (float) $o['drip'] > 0 ) ? ( $liters / (float) $o['drip'] * 60 ) : 0;

	$lines = array();
	$lines[] = sprintf(
		/* translators: 1: норма на единицу, 2: почва, 3: сезон */
		__( '%1$s л на %2$s × %3$s (почва) × %4$s (погода) = %5$s л за один полив', 'sad-znaniy' ),
		rtrim( rtrim( number_format( $base_vol, 2, ',', '' ), '0' ), ',' ),
		$per_plant ? __( 'растение', 'sad-znaniy' ) : __( '1 м²', 'sad-znaniy' ),
		number_format( $soil_vol, 2, ',', '' ),
		number_format( $season_vol, 2, ',', '' ),
		number_format( $per_unit, 2, ',', '' )
	);
	$lines[] = sprintf(
		/* translators: 1: множитель почвы, 2: множитель погоды, 3: множитель региона */
		__( 'Периодичность: %1$s дн. × %2$s (почва) × %3$s (погода) × %4$s (регион) → поливать примерно раз в %5$d дн.', 'sad-znaniy' ),
		number_format( (float) $o['days'][ $type ], 2, ',', '' ),
		number_format( (float) $o['soil'][ $soil ]['days'], 2, ',', '' ),
		number_format( (float) $o['season'][ $season ]['days'], 2, ',', '' ),
		number_format( (float) $o['region'][ $region ], 2, ',', '' ),
		$days
	);

	$warnings = array();
	$warnings[] = __( 'Поливайте утром (до 10:00) или вечером (после 18:00): в жару капли на листьях работают как линзы.', 'sad-znaniy' );

	if ( 'green' === $type ) {
		$warnings[] = __( 'В теплице лейте только под корень и после полива проветрите — иначе ждите грибок.', 'sad-znaniy' );
	}
	if ( 'sand' === $soil ) {
		$warnings[] = __( 'Песчаная почва не держит влагу: лучше поливать чаще и меньшими порциями.', 'sad-znaniy' );
	}
	if ( 'clay' === $soil ) {
		$warnings[] = __( 'На глине вода уходит медленно: проверьте, не застаивается ли она в бороздах.', 'sad-znaniy' );
	}
	if ( 'hot' === $season ) {
		$warnings[] = __( 'В жару мульча заметно снижает испарение — с ней объём полива можно уменьшить.', 'sad-znaniy' );
	}
	if ( 'dv' === $region ) {
		$warnings[] = __( 'На Дальнем Востоке влажно и часто туманно: сначала проверьте влажность почвы, потом лейте.', 'sad-znaniy' );
	}
	$warnings[] = __( 'Проверка перед поливом: сожмите горсть земли с глубины 10 см — если комок лепится и не рассыпается, полив можно отложить.', 'sad-znaniy' );

	return array(
		'type'       => $type,
		'per_plant'  => $per_plant,
		'qty'        => $qty,
		'soil'       => $soil,
		'season'     => $season,
		'region'     => $region,
		'method'     => $method,
		'per_unit'   => $per_unit,
		'liters'     => $liters,
		'liters_txt' => $liters_r,
		'days'       => $days,
		'per_week'   => $per_week,
		'cans'       => $cans,
		'drip_min'   => $drip_min,
		'lines'      => $lines,
		'warnings'   => $warnings,
	);
}

/* ==========================================================
   КАЛЬКУЛЯТОР УДОБРЕНИЙ (Этап 4, инструмент №2)
   ========================================================== */

/**
 * Цели подкормки: сколько действующего вещества нужно на 1 м² за одну подкормку.
 *
 * n — азот (N), p — фосфор (P₂O₅), k — калий (K₂O), г/м².
 * Ведущий элемент (по нему считается доза) — первый в списке.
 *
 * @return array
 */
function sad_znaniy_fert_defaults() {
	return array(
		'npk'     => array(
			'grow'   => array(
				'n' => 10,
				'p' => 5,
				'k' => 5,
			),
			'bloom'  => array(
				'n' => 5,
				'p' => 10,
				'k' => 12,
			),
			'autumn' => array(
				'n' => 0,
				'p' => 12,
				'k' => 15,
			),
		),
		'crop'    => array(
			'veg'    => 1.0,
			'root'   => 0.8,
			'berry'  => 1.0,
			'flower' => 0.7,
			'lawn'   => 1.3,
			'tree'   => 1.2,
		),
		'fert'    => array(
			'npk'   => array(
				'n' => 16,
				'p' => 16,
				'k' => 16,
			),
			'urea'  => array(
				'n' => 46,
				'p' => 0,
				'k' => 0,
			),
			'nitro' => array(
				'n' => 34,
				'p' => 0,
				'k' => 0,
			),
			'super' => array(
				'n' => 0,
				'p' => 20,
				'k' => 0,
			),
			'kaliy' => array(
				'n' => 0,
				'p' => 0,
				'k' => 50,
			),
			'ash'   => array(
				'n' => 0,
				'p' => 3,
				'k' => 10,
			),
		),
		'measure' => array(
			'tbsp'     => 17,
			'matchbox' => 20,
			'glass'    => 200,
		),
	);
}

/**
 * Подписи целей подкормки.
 *
 * @return array
 */
function sad_znaniy_fert_goals() {
	return array(
		'grow'   => __( 'Рост (нужен азот)', 'sad-znaniy' ),
		'bloom'  => __( 'Цветение и плодоношение', 'sad-znaniy' ),
		'autumn' => __( 'Осенняя подкормка (без азота)', 'sad-znaniy' ),
	);
}

/**
 * Подписи групп культур.
 *
 * @return array
 */
function sad_znaniy_fert_crops() {
	return array(
		'veg'    => __( 'Овощи (томат, огурец, перец, капуста)', 'sad-znaniy' ),
		'root'   => __( 'Корнеплоды (морковь, свёкла, картофель)', 'sad-znaniy' ),
		'berry'  => __( 'Ягодники (клубника, малина, смородина)', 'sad-znaniy' ),
		'flower' => __( 'Цветы и декоративные', 'sad-znaniy' ),
		'lawn'   => __( 'Газон', 'sad-znaniy' ),
		'tree'   => __( 'Плодовые деревья', 'sad-znaniy' ),
	);
}

/**
 * Список удобрений с подписями (проценты действующего вещества — в настройках).
 *
 * @return array
 */
function sad_znaniy_fert_list() {
	return array(
		'npk'   => __( 'Нитроаммофоска (16-16-16)', 'sad-znaniy' ),
		'urea'  => __( 'Мочевина (карбамид)', 'sad-znaniy' ),
		'nitro' => __( 'Аммиачная селитра', 'sad-znaniy' ),
		'super' => __( 'Суперфосфат', 'sad-znaniy' ),
		'kaliy' => __( 'Сульфат калия', 'sad-znaniy' ),
		'ash'   => __( 'Зола древесная', 'sad-znaniy' ),
	);
}

/**
 * Удобрения, которые растворяют в воде для подкормки.
 *
 * @return array
 */
function sad_znaniy_fert_soluble() {
	return array( 'npk', 'urea', 'nitro', 'kaliy' );
}

/**
 * Нормативы калькулятора удобрений из опций темы.
 *
 * @return array
 */
function sad_znaniy_fert_options() {
	$saved = get_option( 'sz_calc_fert', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return sad_znaniy_array_merge_deep( sad_znaniy_fert_defaults(), $saved );
}

/**
 * Очищает нормативы удобрений из админки.
 *
 * @param mixed $input Сырые значения.
 * @return array
 */
function sad_znaniy_fert_sanitize( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$default = sad_znaniy_fert_defaults();
	$out     = array();

	foreach ( $default['npk'] as $goal => $row ) {
		foreach ( array( 'n', 'p', 'k' ) as $nutrient ) {
			$value = isset( $input['npk'][ $goal ][ $nutrient ] ) ? (float) $input['npk'][ $goal ][ $nutrient ] : $row[ $nutrient ];
			$out['npk'][ $goal ][ $nutrient ] = min( 100, max( 0, $value ) );
		}
	}

	foreach ( $default['crop'] as $crop => $factor ) {
		$value = isset( $input['crop'][ $crop ] ) ? (float) $input['crop'][ $crop ] : $factor;
		$out['crop'][ $crop ] = min( 3, max( 0.2, $value ) );
	}

	foreach ( $default['fert'] as $fert => $row ) {
		foreach ( array( 'n', 'p', 'k' ) as $nutrient ) {
			$value = isset( $input['fert'][ $fert ][ $nutrient ] ) ? (float) $input['fert'][ $fert ][ $nutrient ] : $row[ $nutrient ];
			$out['fert'][ $fert ][ $nutrient ] = min( 80, max( 0, $value ) );
		}
	}

	foreach ( $default['measure'] as $key => $grams ) {
		$value = isset( $input['measure'][ $key ] ) ? (float) $input['measure'][ $key ] : $grams;
		$out['measure'][ $key ] = min( 1000, max( 1, $value ) );
	}

	return $out;
}

add_shortcode( 'sz_calc_water', 'sad_znaniy_water_shortcode' );

/**
 * Считает дозу удобрения под цель, культуру и площадь.
 *
 * @param array $args goal, crop, fert, qty.
 * @return array Результат расчёта.
 */
function sad_znaniy_fert_calc( $args ) {
	$o = sad_znaniy_fert_options();

	$goals = sad_znaniy_fert_goals();
	$crops = sad_znaniy_fert_crops();
	$ferts = sad_znaniy_fert_list();

	$args = wp_parse_args(
		$args,
		array(
			'goal' => 'grow',
			'crop' => 'veg',
			'fert' => 'npk',
			'qty'  => 10,
		)
	);

	$goal = isset( $goals[ $args['goal'] ] ) ? $args['goal'] : 'grow';
	$crop = isset( $crops[ $args['crop'] ] ) ? $args['crop'] : 'veg';
	$fert = isset( $ferts[ $args['fert'] ] ) ? $args['fert'] : 'npk';
	$qty  = min( 1000, max( 1, (float) $args['qty'] ) );

	$need  = $o['npk'][ $goal ];
	$coef  = (float) $o['crop'][ $crop ];
	$share = $o['fert'][ $fert ];

	$target = array(
		'n' => $need['n'] * $coef,
		'p' => $need['p'] * $coef,
		'k' => $need['k'] * $coef,
	);

	// Ведущий элемент: по нему считаем дозу удобрения.
	$lead = 'n';
	if ( 'bloom' === $goal ) {
		$lead = 'k';
	} elseif ( 'autumn' === $goal ) {
		$lead = 'p';
	}

	$lead_percent = (float) $share[ $lead ] / 100;
	$dose         = ( $lead_percent > 0 ) ? ( $target[ $lead ] / $lead_percent ) : 0;

	$delivered = array(
		'n' => $dose * ( (float) $share['n'] / 100 ),
		'p' => $dose * ( (float) $share['p'] / 100 ),
		'k' => $dose * ( (float) $share['k'] / 100 ),
	);

	$total    = $dose * $qty;
	$tbsp     = $total / max( 1, (float) $o['measure']['tbsp'] );
	$matchbox = $total / max( 1, (float) $o['measure']['matchbox'] );
	$glass    = $total / max( 1, (float) $o['measure']['glass'] );

	$nutrients = array(
		'n' => __( 'азот (N)', 'sad-znaniy' ),
		'p' => __( 'фосфор (P₂O₅)', 'sad-znaniy' ),
		'k' => __( 'калий (K₂O)', 'sad-znaniy' ),
	);

	$lines = array();
	$lines[] = sprintf(
		/* translators: 1: цель, 2: ведущий элемент, 3: норма, 4: поправка культуры */
		__( 'Цель «%1$s»: нужно %2$s — %3$s г/м², с поправкой на культуру (%4$s) — %5$s г/м².', 'sad-znaniy' ),
		$goals[ $goal ],
		$nutrients[ $lead ],
		rtrim( rtrim( number_format( $need[ $lead ], 2, ',', '' ), '0' ), ',' ),
		number_format( $coef, 2, ',', '' ),
		number_format( $target[ $lead ], 2, ',', '' )
	);
	$lines[] = sprintf(
		/* translators: 1: удобрение, 2: процент ведущего элемента, 3: доза */
		__( 'В %1$s этого элемента %2$s %%, значит на 1 м² нужно %3$s г удобрения.', 'sad-znaniy' ),
		$ferts[ $fert ],
		number_format( (float) $share[ $lead ], 1, ',', '' ),
		number_format( $dose, 1, ',', '' )
	);

	$warnings = array();
	if ( 0 === (int) $target['n'] && 'autumn' === $goal ) {
		$warnings[] = __( 'Осенью азот не вносим: он гонит рост, а побеги не успевают вызреть к зиме.', 'sad-znaniy' );
	}
	$warnings[] = __( 'Вносите только по влажной почве и сразу полейте: сухое удобрение обжигает корни.', 'sad-znaniy' );
	$warnings[] = __( 'Не смешивайте азотные удобрения с известью и золой в один приём — теряется азот.', 'sad-znaniy' );
	$warnings[] = __( 'Не превышайте дозу: избыток азота даёт жирный лист вместо плодов и накапливает нитраты.', 'sad-znaniy' );

	if ( 'ash' === $fert ) {
		$warnings[] = __( 'Зола раскисляет почву: на щелочных грунтах её лучше не применять.', 'sad-znaniy' );
	}
	if ( 'lawn' === $crop ) {
		$warnings[] = __( 'Газон подкармливают по сухой траве и поливают, иначе останутся пятна ожогов.', 'sad-znaniy' );
	}

	$soluble = in_array( $fert, sad_znaniy_fert_soluble(), true );
	$per_can = $dose; // на ведро 10 л, которого хватает примерно на 1 м².

	return array(
		'goal'       => $goal,
		'crop'       => $crop,
		'fert'       => $fert,
		'qty'        => $qty,
		'lead'       => $lead,
		'target'     => $target,
		'delivered'  => $delivered,
		'dose'       => $dose,
		'total'      => $total,
		'tbsp'       => $tbsp,
		'matchbox'   => $matchbox,
		'glass'      => $glass,
		'soluble'    => $soluble,
		'per_can'    => $per_can,
		'nutrients'  => $nutrients,
		'lines'      => $lines,
		'warnings'   => $warnings,
	);
}

/**
 * Подключает общий скрипт калькуляторов (регион из календаря + пересчёт).
 */
function sad_znaniy_calc_enqueue() {
	static $loaded = false;
	if ( $loaded ) {
		return;
	}
	$loaded = true;

	$rel = 'assets/js/calc.js';
	wp_enqueue_script(
		'sad-znaniy-calc',
		get_template_directory_uri() . '/' . $rel,
		array(),
		sad_znaniy_asset_ver( $rel ),
		true
	);
}

/**
 * Шорткод «[sz_calc_fert]» — калькулятор удобрений.
 *
 * @param array $atts Атрибуты шорткода.
 * @return string
 */
function sad_znaniy_fert_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'title' => '' ),
		$atts,
		'sz_calc_fert'
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- публичный расчёт.
	$args = array(
		'goal' => isset( $_GET['szf_goal'] ) ? sanitize_key( wp_unslash( $_GET['szf_goal'] ) ) : 'grow',
		'crop' => isset( $_GET['szf_crop'] ) ? sanitize_key( wp_unslash( $_GET['szf_crop'] ) ) : 'veg',
		'fert' => isset( $_GET['szf_fert'] ) ? sanitize_key( wp_unslash( $_GET['szf_fert'] ) ) : 'npk',
		'qty'  => isset( $_GET['szf_qty'] ) ? (float) wp_unslash( $_GET['szf_qty'] ) : 10,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$calc = sad_znaniy_fert_calc( $args );
	sad_znaniy_calc_enqueue();

	$goals = sad_znaniy_fert_goals();
	$crops = sad_znaniy_fert_crops();
	$ferts = sad_znaniy_fert_list();

	ob_start();
	?>
	<div class="calc">
		<?php if ( '' !== $atts['title'] ) : ?>
			<h2 class="calc-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<form class="calc-form" method="get" action="">
			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Цель подкормки', 'sad-znaniy' ); ?></span>
					<select name="szf_goal">
						<?php foreach ( $goals as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['goal'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="calc-field">
					<span><?php esc_html_e( 'Что подкармливаем', 'sad-znaniy' ); ?></span>
					<select name="szf_crop">
						<?php foreach ( $crops as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['crop'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Чем подкармливаем', 'sad-znaniy' ); ?></span>
					<select name="szf_fert">
						<?php foreach ( $ferts as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['fert'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="calc-field">
					<span><?php esc_html_e( 'Площадь, м²', 'sad-znaniy' ); ?></span>
					<input type="number" name="szf_qty" min="1" max="1000" step="1" value="<?php echo esc_attr( (int) $calc['qty'] ); ?>">
				</label>
			</div>

			<p class="calc-actions">
				<button type="submit" class="btn btn-dark"><?php esc_html_e( 'Рассчитать', 'sad-znaniy' ); ?><span class="arr">→</span></button>
				<span class="calc-hint"><?php esc_html_e( 'Расчёт по действующему веществу: считаем граммы удобрения, а не «ложки на глаз».', 'sad-znaniy' ); ?></span>
			</p>
		</form>
		<?php
		$tpl = get_template_directory() . '/template-parts/calc-fert-result.php';
		if ( file_exists( $tpl ) ) {
			include $tpl;
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'sz_calc_fert', 'sad_znaniy_fert_shortcode' );

/**
 * Поля нормативов калькулятора удобрений для страницы настроек темы.
 *
 * @param array $o Текущие нормативы.
 */
function sad_znaniy_fert_admin_fields( $o ) {
	$goals   = sad_znaniy_fert_goals();
	$crops   = sad_znaniy_fert_crops();
	$ferts   = sad_znaniy_fert_list();
	$name    = 'sad_znaniy_options[sz_calc_fert]';
	$nutr    = array(
		'n' => __( 'N', 'sad-znaniy' ),
		'p' => __( 'P', 'sad-znaniy' ),
		'k' => __( 'K', 'sad-znaniy' ),
	);

	$field = function ( $path, $value, $step = '0.5' ) use ( $name ) {
		printf(
			'<input type="number" step="%1$s" name="%2$s" value="%3$s" style="width:76px;">',
			esc_attr( $step ),
			esc_attr( $name . $path ),
			esc_attr( $value )
		);
	};
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Нужно действующего вещества, г/м²', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $goals as $goal => $label ) : ?>
					<div style="margin-bottom:8px;font-size:12px;">
						<strong><?php echo esc_html( $label ); ?></strong> —
						<?php foreach ( $nutr as $key => $short ) : ?>
							<?php echo esc_html( $short ); ?> <?php $field( '[npk][' . $goal . '][' . $key . ']', $o['npk'][ $goal ][ $key ], '1' ); ?>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Доза считается по ведущему элементу цели: рост — азот, цветение — калий, осень — фосфор. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Поправка на группу культур', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $crops as $crop => $label ) : ?>
					<label style="display:inline-block;margin:0 16px 10px 0;font-size:12px;">
						<?php echo esc_html( $label ); ?><br>
						<?php $field( '[crop][' . $crop . ']', $o['crop'][ $crop ], '0.05' ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Во сколько раз норму умножаем для этой группы. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Проценты действующего вещества в удобрениях', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $ferts as $fert => $label ) : ?>
					<div style="margin-bottom:8px;font-size:12px;">
						<strong><?php echo esc_html( $label ); ?></strong> —
						<?php foreach ( $nutr as $key => $short ) : ?>
							<?php echo esc_html( $short ); ?> <?php $field( '[fert][' . $fert . '][' . $key . ']', $o['fert'][ $fert ][ $key ], '1' ); ?>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Берите с упаковки вашего удобрения — цифры у производителей различаются. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Бытовые мерки, г', 'sad-znaniy' ); ?></th>
			<td>
				<label style="font-size:12px;">
					<?php esc_html_e( 'Ст. ложка', 'sad-znaniy' ); ?><br>
					<?php $field( '[measure][tbsp]', $o['measure']['tbsp'], '0.5' ); ?>
				</label>
				<label style="font-size:12px;margin-left:18px;">
					<?php esc_html_e( 'Спичечный коробок', 'sad-znaniy' ); ?><br>
					<?php $field( '[measure][matchbox]', $o['measure']['matchbox'], '0.5' ); ?>
				</label>
				<label style="font-size:12px;margin-left:18px;">
					<?php esc_html_e( 'Стакан', 'sad-znaniy' ); ?><br>
					<?php $field( '[measure][glass]', $o['measure']['glass'], '5' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Нужны, чтобы переводить граммы в понятные ложки и коробки. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/* ==========================================================
   КАЛЬКУЛЯТОР ГРУНТА И pH (Этап 4, инструмент №3)
   ========================================================== */

/**
 * Нормативы раскисления и подкисления по типам почвы.
 *
 * lime — известь/доломитовая мука, sulfur — коллоидная сера:
 * граммы на 1 м² на сдвиг pH на 1,0. measures — бытовые мерки (г).
 *
 * @return array
 */
function sad_znaniy_ph_defaults() {
	return array(
		'lime'    => array(
			'sand' => 200,
			'loam' => 300,
			'clay' => 400,
			'peat' => 500,
		),
		'sulfur'  => array(
			'sand' => 30,
			'loam' => 50,
			'clay' => 70,
			'peat' => 40,
		),
		'measure' => array(
			'glass'  => 200,
			'bucket' => 10000,
		),
	);
}

/**
 * Подписи материалов для коррекции pH.
 *
 * @return array
 */
function sad_znaniy_ph_methods() {
	return array(
		'lime'   => __( 'Известь или доломитовая мука (раскислить)', 'sad-znaniy' ),
		'sulfur' => __( 'Коллоидная сера (подкислить)', 'sad-znaniy' ),
	);
}

/**
 * Нормативы калькулятора pH из опций темы.
 *
 * @return array
 */
function sad_znaniy_ph_options() {
	$saved = get_option( 'sz_calc_ph', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return sad_znaniy_array_merge_deep( sad_znaniy_ph_defaults(), $saved );
}

/**
 * Очищает нормативы pH из админки.
 *
 * @param mixed $input Сырые значения.
 * @return array
 */
function sad_znaniy_ph_sanitize( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$default = sad_znaniy_ph_defaults();
	$out     = array();

	foreach ( array( 'lime', 'sulfur' ) as $group ) {
		foreach ( $default[ $group ] as $soil => $grams ) {
			$value = isset( $input[ $group ][ $soil ] ) ? (float) $input[ $group ][ $soil ] : $grams;
			$out[ $group ][ $soil ] = min( 5000, max( 1, $value ) );
		}
	}

	foreach ( $default['measure'] as $key => $grams ) {
		$value = isset( $input['measure'][ $key ] ) ? (float) $input['measure'][ $key ] : $grams;
		$out['measure'][ $key ] = min( 50000, max( 1, $value ) );
	}

	return $out;
}

/**
 * Растения базы знаний с диапазоном pH и их отношением к текущему pH.
 *
 * @param float $current Текущий pH.
 * @param float $target  Целевой pH.
 * @return array ['now' => array[], 'after' => array[], 'no' => array[]]
 */
function sad_znaniy_ph_plants( $current, $target ) {
	$plants = get_posts(
		array(
			'post_type'      => 'plant',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	$out = array(
		'now'   => array(),
		'after' => array(),
		'no'    => array(),
	);

	foreach ( $plants as $plant ) {
		$from = (string) get_post_meta( $plant->ID, '_sz_plant_ph_from', true );
		$to   = (string) get_post_meta( $plant->ID, '_sz_plant_ph_to', true );
		if ( '' === $from || '' === $to ) {
			continue;
		}

		$from = (float) $from;
		$to   = (float) $to;
		$row  = array(
			'title' => get_the_title( $plant ),
			'url'   => get_permalink( $plant ),
			'range' => number_format_i18n( $from, 1 ) . '–' . number_format_i18n( $to, 1 ),
		);

		if ( $current >= $from && $current <= $to ) {
			$out['now'][] = $row;
		} elseif ( $target >= $from && $target <= $to ) {
			$out['after'][] = $row;
		} else {
			$out['no'][] = $row;
		}
	}

	return $out;
}

/**
 * Считает, сколько материала нужно, чтобы сдвинуть pH.
 *
 * @param array $args soil, current, target, qty.
 * @return array Результат расчёта.
 */
function sad_znaniy_ph_calc( $args ) {
	$o     = sad_znaniy_ph_options();
	$soils = sad_znaniy_water_soils();

	$args = wp_parse_args(
		$args,
		array(
			'soil'    => 'loam',
			'current' => 5.5,
			'target'  => 6.5,
			'qty'     => 10,
		)
	);

	$soil    = isset( $soils[ $args['soil'] ] ) ? $args['soil'] : 'loam';
	$current = min( 9.0, max( 3.0, (float) $args['current'] ) );
	$target  = min( 9.0, max( 3.0, (float) $args['target'] ) );
	$qty     = min( 10000, max( 1, (float) $args['qty'] ) );

	$delta  = round( $target - $current, 2 );
	$method = ( $delta > 0 ) ? 'lime' : ( ( $delta < 0 ) ? 'sulfur' : '' );
	$rate   = ( '' !== $method ) ? (float) $o[ $method ][ $soil ] : 0;
	$dose   = abs( $delta ) * $rate;
	$total  = $dose * $qty;

	$methods  = sad_znaniy_ph_methods();
	$glasses  = $total / max( 1, (float) $o['measure']['glass'] );
	$buckets  = $total / max( 1, (float) $o['measure']['bucket'] );

	$lines = array();
	if ( '' === $method ) {
		$lines[] = __( 'Текущий pH совпадает с целевым — корректировать не нужно.', 'sad-znaniy' );
	} else {
		$lines[] = sprintf(
			/* translators: 1: текущий pH, 2: целевой pH, 3: сдвиг, 4: материал, 5: норма */
			__( 'Сдвиг %1$s → %2$s, то есть на %3$s. Для этой почвы норма «%4$s» — %5$s г/м² на каждый 1,0 pH.', 'sad-znaniy' ),
			number_format_i18n( $current, 1 ),
			number_format_i18n( $target, 1 ),
			number_format_i18n( abs( $delta ), 1 ),
			$methods[ $method ],
			number_format_i18n( $rate, 0 )
		);
		$lines[] = sprintf(
			/* translators: 1: сдвиг, 2: норма, 3: доза */
			__( '%1$s × %2$s г = %3$s г на 1 м².', 'sad-znaniy' ),
			number_format_i18n( abs( $delta ), 1 ),
			number_format_i18n( $rate, 0 ),
			number_format_i18n( $dose, 0 )
		);
	}

	$warnings = array();
	if ( 'lime' === $method ) {
		$warnings[] = __( 'Известь и доломитовую муку вносят осенью под перекопку, раз в 3–5 лет; большую дозу дробят на два приёма.', 'sad-znaniy' );
		$warnings[] = __( 'Не вносите известь вместе с азотными удобрениями и навозом — часть азота теряется.', 'sad-znaniy' );
	}
	if ( 'sulfur' === $method ) {
		$warnings[] = __( 'Сера работает медленно: эффект заметен через 6–12 месяцев, в один сезон повторять нельзя.', 'sad-znaniy' );
	}
	$warnings[] = __( 'Не выравнивайте pH по всему участку: гортензии, голубике и рододендронам нужна кислая почва — оставьте им отдельную зону.', 'sad-znaniy' );
	$warnings[] = __( 'pH проверяйте раз в 2–3 года (лакмусовая бумага или pH-метр), а не «на глаз».', 'sad-znaniy' );

	return array(
		'soil'     => $soil,
		'current'  => $current,
		'target'   => $target,
		'delta'    => $delta,
		'method'   => $method,
		'rate'     => $rate,
		'dose'     => $dose,
		'total'    => $total,
		'glasses'  => $glasses,
		'buckets'  => $buckets,
		'lines'    => $lines,
		'warnings' => $warnings,
		'plants'   => sad_znaniy_ph_plants( $current, $target ),
	);
}

/**
 * Шорткод «[sz_calc_ph]» — калькулятор грунта и pH.
 *
 * @param array $atts Атрибуты шорткода.
 * @return string
 */
function sad_znaniy_ph_shortcode( $atts ) {
	$atts = shortcode_atts(
		array( 'title' => '' ),
		$atts,
		'sz_calc_ph'
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- публичный расчёт.
	$args = array(
		'soil'    => isset( $_GET['szp_soil'] ) ? sanitize_key( wp_unslash( $_GET['szp_soil'] ) ) : 'loam',
		'current' => isset( $_GET['szp_current'] ) ? (float) wp_unslash( $_GET['szp_current'] ) : 5.5,
		'target'  => isset( $_GET['szp_target'] ) ? (float) wp_unslash( $_GET['szp_target'] ) : 6.5,
		'qty'     => isset( $_GET['szp_qty'] ) ? (float) wp_unslash( $_GET['szp_qty'] ) : 10,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$calc  = sad_znaniy_ph_calc( $args );
	$soils = sad_znaniy_water_soils();

	sad_znaniy_calc_enqueue();

	ob_start();
	?>
	<div class="calc">
		<?php if ( '' !== $atts['title'] ) : ?>
			<h2 class="calc-title"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>

		<form class="calc-form" method="get" action="">
			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Тип почвы', 'sad-znaniy' ); ?></span>
					<select name="szp_soil">
						<?php foreach ( $soils as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $calc['soil'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<label class="calc-field">
					<span><?php esc_html_e( 'Площадь, м²', 'sad-znaniy' ); ?></span>
					<input type="number" name="szp_qty" min="1" max="10000" step="1" value="<?php echo esc_attr( (int) $calc['qty'] ); ?>">
				</label>
			</div>

			<div class="calc-row">
				<label class="calc-field">
					<span><?php esc_html_e( 'Текущий pH', 'sad-znaniy' ); ?></span>
					<input type="number" name="szp_current" min="3" max="9" step="0.1" value="<?php echo esc_attr( number_format( $calc['current'], 1, '.', '' ) ); ?>">
				</label>

				<label class="calc-field">
					<span><?php esc_html_e( 'Целевой pH', 'sad-znaniy' ); ?></span>
					<input type="number" name="szp_target" min="3" max="9" step="0.1" value="<?php echo esc_attr( number_format( $calc['target'], 1, '.', '' ) ); ?>">
				</label>
			</div>

			<p class="calc-actions">
				<button type="submit" class="btn btn-dark"><?php esc_html_e( 'Рассчитать', 'sad-znaniy' ); ?><span class="arr">→</span></button>
				<span class="calc-hint"><?php esc_html_e( 'Ниже — что из растений базы знаний подойдёт при вашем pH прямо сейчас, а что после коррекции.', 'sad-znaniy' ); ?></span>
			</p>
		</form>
		<?php
		$tpl = get_template_directory() . '/template-parts/calc-ph-result.php';
		if ( file_exists( $tpl ) ) {
			include $tpl;
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'sz_calc_ph', 'sad_znaniy_ph_shortcode' );

/**
 * Поля нормативов калькулятора грунта и pH для страницы настроек темы.
 *
 * @param array $o Текущие нормативы.
 */
function sad_znaniy_ph_admin_fields( $o ) {
	$soils = sad_znaniy_water_soils();
	$name  = 'sad_znaniy_options[sz_calc_ph]';

	$field = function ( $path, $value, $step = '10' ) use ( $name ) {
		printf(
			'<input type="number" step="%1$s" name="%2$s" value="%3$s" style="width:92px;">',
			esc_attr( $step ),
			esc_attr( $name . $path ),
			esc_attr( $value )
		);
	};
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Раскислить: г/м² на 1,0 pH', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $soils as $key => $label ) : ?>
					<label style="display:inline-block;margin:0 16px 10px 0;font-size:12px;">
						<?php echo esc_html( $label ); ?><br>
						<?php $field( '[lime][' . $key . ']', $o['lime'][ $key ] ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Сколько извести или доломитовой муки нужно на 1 м², чтобы поднять pH на единицу. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Подкислить: г/м² на 1,0 pH', 'sad-znaniy' ); ?></th>
			<td>
				<?php foreach ( $soils as $key => $label ) : ?>
					<label style="display:inline-block;margin:0 16px 10px 0;font-size:12px;">
						<?php echo esc_html( $label ); ?><br>
						<?php $field( '[sulfur][' . $key . ']', $o['sulfur'][ $key ] ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Сколько коллоидной серы нужно на 1 м², чтобы опустить pH на единицу. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Бытовые мерки, г', 'sad-znaniy' ); ?></th>
			<td>
				<label style="font-size:12px;">
					<?php esc_html_e( 'Стакан', 'sad-znaniy' ); ?><br>
					<?php $field( '[measure][glass]', $o['measure']['glass'], '5' ); ?>
				</label>
				<label style="font-size:12px;margin-left:18px;">
					<?php esc_html_e( 'Ведро', 'sad-znaniy' ); ?><br>
					<?php $field( '[measure][bucket]', $o['measure']['bucket'], '100' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Чтобы переводить килограммы в понятные меры. ФАКТ-ПРОВЕРКА.', 'sad-znaniy' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}





