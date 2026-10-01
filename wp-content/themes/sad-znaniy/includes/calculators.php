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
 * Подключает скрипт калькулятора (регион из календаря + мгновенный пересчёт).
 */
function sad_znaniy_water_enqueue() {
	static $loaded = false;
	if ( $loaded ) {
		return;
	}
	$loaded = true;

	$rel = 'assets/js/water-calc.js';
	wp_enqueue_script(
		'sad-znaniy-water-calc',
		get_template_directory_uri() . '/' . $rel,
		array(),
		sad_znaniy_asset_ver( $rel ),
		true
	);
	wp_localize_script(
		'sad-znaniy-water-calc',
		'sadZnaniyWater',
		array(
			'lsKey'     => 'sad_znaniy_cal',
			'hasRegion' => isset( $_GET['sz_region'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- параметр только для расчёта.
		)
	);
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
	sad_znaniy_water_enqueue();

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
					<select name="sz_region">
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

add_shortcode( 'sz_calc_water', 'sad_znaniy_water_shortcode' );
