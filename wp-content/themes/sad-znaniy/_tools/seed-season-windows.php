<?php
/**
 * Генератор сезонных окон (Этап 5.7) — создаёт ЧЕРНОВИКИ событий календаря
 * по климатическим группам регионов. Идемпотентно: не дублирует уже имеющиеся
 * (в том числе черновики) осенние окна по культуре и региону.
 *
 * Запуск: wp eval-file _tools/seed-season-windows.php
 *
 * @package sad-znaniy
 */

$sz_year = 2026;

$sz_groups = array(
	'yug' => array(
		'regions'    => array( 'south' ),
		'podzimny'   => array( '11-05', '11-15' ),
		'ozim'       => array( '10-20', '10-31' ),
		'saplings'   => array( '10-10', '11-05' ),
		'perennial'  => array( '10-15', '11-05' ),
		'strawberry' => array( '08-20', '09-15' ),
	),
	'mid' => array(
		'regions'    => array( 'mid', 'ural' ),
		'podzimny'   => array( '10-05', '10-15' ),
		'ozim'       => array( '10-05', '10-15' ),
		'saplings'   => array( '09-20', '10-10' ),
		'perennial'  => array( '09-25', '10-10' ),
		'strawberry' => array( '08-05', '09-05' ),
	),
	'sib' => array(
		'regions'    => array( 'sib', 'dv' ),
		'podzimny'   => array( '09-25', '10-05' ),
		'ozim'       => array( '09-20', '09-30' ),
		'saplings'   => array( '09-05', '09-25' ),
		'perennial'  => array( '09-10', '09-25' ),
		'strawberry' => array( '07-20', '08-15' ),
	),
);

$sz_hints = array(
	'podzimny'  => 'Сеять по подмёрзшей почве, семена сухие, грядку замульчировать',
	'ozim'      => 'За 3–4 недели до устойчивых морозов, после посадки замульчировать',
	'saplings'  => 'До первых заморозков: запас 3–4 недели до укоренения',
	'perennial' => 'За 3–4 недели до устойчивых морозов, мульчирование обязательно',
);

// Культура => [ тип, работы, заголовок, текст ]
$sz_crops = array(
	// Подзимний посев (семена).
	155 => array( 'podzimny', 'posev', 'Подзимний посев моркови', 'Борозды 1–2 см, семена сухие, сверху мульча из торфа слоем 2–3 см. Всходы появятся в мае — на 1–2 недели раньше весеннего посева.' ),
	162 => array( 'podzimny', 'posev', 'Подзимний посев свёклы', 'Глубина 2–3 см, семена сухие, грядку мульчируют. Подзимний посев даёт более ранний урожай и меньше цветушных растений.' ),
	445 => array( 'podzimny', 'posev', 'Подзимний посев петрушки', 'Глубина 1–1,5 см, мульча 2–3 см. Зелень будет готова на 1–2 недели раньше, чем при весеннем посеве.' ),
	449 => array( 'podzimny', 'posev', 'Подзимний посев укропа', 'Семена сухие, глубина 1–2 см, грядку замульчировать торфом. Всходы ранние и дружные.' ),
	447 => array( 'podzimny', 'posev', 'Подзимний посев салата', 'Глубина 0,5–1 см, посев по подмёрзшей почве, мульча. Крепкие всходы появляются уже в апреле.' ),
	489 => array( 'podzimny', 'posev', 'Подзимний посев шпината', 'Глубина 2 см, мульча слоем 2 см. Ранняя зелень готова на 10–14 дней раньше весенней.' ),
	446 => array( 'podzimny', 'posev', 'Подзимний посев редиса', 'Глубина 1–1,5 см, мульча. Всходы в апреле, корнеплоды успевают налиться до жары и не стрелкуются.' ),
	480 => array( 'podzimny', 'posev', 'Подзимний посев дайкона', 'Глубина 2 см, сеют по подмёрзшей почве. Корнеплоды успевают набрать массу до летней жары.' ),
	488 => array( 'podzimny', 'posev', 'Подзимний посев сельдерея', 'Посев поверхностный (0,5 см — семена всходят на свету), мульча. Всходы ранние, требуется прореживание.' ),
	487 => array( 'podzimny', 'posev', 'Подзимний посев репы', 'Глубина 1–2 см, мульча 2 см. Ранние корнеплоды до появления блошки.' ),
	486 => array( 'podzimny', 'posev', 'Подзимний посев редьки', 'Глубина 2 см, семена сухие, мульча. Всходы ранние, корнеплоды сочные.' ),
	// Озимые лук и чеснок.
	156 => array( 'ozim', 'posadka', 'Посадка озимого лука-севка', 'Севок на глубину 3–4 см, шаг 8–10 см, между рядами 20–25 см. После устойчивых заморозков грядку мульчируют торфом слоем 3 см.' ),
	157 => array( 'ozim', 'posadka', 'Посадка озимого чеснока', 'Зубки донцем вниз на глубину 4–6 см, шаг 10–12 см, между рядами 25 см. Грядку мульчируют перегноем или торфом.' ),
);

// Осенняя посадка саженцев (деревья и ягодники).
$sz_sapling_names = array(
	160 => 'яблони',
	286 => 'груши',
	161 => 'вишни',
	476 => 'черешни',
	479 => 'сливы',
	434 => 'абрикоса',
	438 => 'смородины',
	437 => 'крыжовника',
	439 => 'жимолости',
	436 => 'голубики',
	477 => 'облепихи',
	165 => 'малины',
	435 => 'ежевики',
);
$sz_sapling_text = 'Саженец ставят так, чтобы корневая шейка была на 3–5 см выше уровня почвы; после посадки — обильный полив (2–3 ведра) и мульчирование приствольного круга.';

foreach ( $sz_sapling_names as $sz_sid => $sz_sname ) {
	$sz_crops[ $sz_sid ] = array( 'saplings', 'posadka', 'Посадка саженцев ' . $sz_sname . ' осенью', $sz_sapling_text );
}

// Осенняя посадка и деление многолетников и луковичных.
$sz_peren_names = array(
	12  => 'роз',
	433 => 'тюльпанов',
	474 => 'лилейников',
	430 => 'ирисов',
	427 => 'флоксов',
	472 => 'хост',
	470 => 'эхинацеи',
	432 => 'лаванды',
	431 => 'клематисов',
	10  => 'гортензии метельчатой',
	429 => 'гортензии древовидной',
	473 => 'хризантем',
);
$sz_peren_text = 'Делёнки и луковицы высаживают так, чтобы корневая шейка (или точка роста луковицы) была на 2–3 см ниже уровня почвы; посадку мульчируют торфом или перегноем.';

foreach ( $sz_peren_names as $sz_pid => $sz_pname ) {
	$sz_crops[ $sz_pid ] = array( 'perennial', 'posadka', 'Осенняя посадка и деление ' . $sz_pname, $sz_peren_text );
}

/**
 * Есть ли у культуры осеннее сезонное окно (посев/посадка) для региона.
 *
 * @param int    $crop_id ID растения.
 * @param string $region  Ключ региона.
 * @return bool
 */
function sz_crop_has_autumn_window( $crop_id, $region ) {
	$events = get_posts(
		array(
			'post_type'      => 'calendar_event',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_sz_event_crop',
					'value' => (int) $crop_id,
				),
			),
		)
	);

	foreach ( $events as $eid ) {
		$from = (string) get_post_meta( $eid, '_sz_event_date_from', true );
		$m    = (int) substr( $from, 5, 2 );
		if ( $m < 8 || $m > 11 ) {
			continue;
		}
		$wt   = wp_get_post_terms( $eid, 'work_type', array( 'fields' => 'slugs' ) );
		$slug = ( is_array( $wt ) && $wt ) ? (string) $wt[0] : '';
		if ( ! in_array( $slug, array( 'posev', 'posadka' ), true ) ) {
			continue;
		}
		$all  = '1' === (string) get_post_meta( $eid, '_sz_event_all_regions', true );
		$regs = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $eid, '_sz_event_regions', true ) ) ) );
		if ( $all || in_array( $region, $regs, true ) ) {
			return true;
		}
	}

	return false;
}

$sz_created = array();
$sz_skipped = 0;

foreach ( $sz_crops as $sz_crop_id => $sz_c ) {
	list( $sz_type, $sz_work, $sz_title, $sz_text ) = $sz_c;

	foreach ( $sz_groups as $sz_g ) {
		$sz_need = array();
		foreach ( $sz_g['regions'] as $sz_reg ) {
			if ( ! sz_crop_has_autumn_window( $sz_crop_id, $sz_reg ) ) {
				$sz_need[] = $sz_reg;
			}
		}
		if ( ! $sz_need ) {
			$sz_skipped++;
			continue;
		}

		$sz_from = $sz_year . '-' . $sz_g[ $sz_type ][0];
		$sz_to   = $sz_year . '-' . $sz_g[ $sz_type ][1];

		$sz_new = wp_insert_post(
			array(
				'post_type'    => 'calendar_event',
				'post_status'  => 'draft',
				'post_title'   => $sz_title,
				'post_content' => $sz_text,
			),
			true
		);

		if ( is_wp_error( $sz_new ) ) {
			echo 'ОШИБКА: ' . $sz_title . ' — ' . $sz_new->get_error_message() . "\n";
			continue;
		}

		update_post_meta( $sz_new, '_sz_event_date_from', $sz_from );
		update_post_meta( $sz_new, '_sz_event_date_to', $sz_to );
		update_post_meta( $sz_new, '_sz_event_regions', implode( ',', $sz_need ) );
		update_post_meta( $sz_new, '_sz_event_all_regions', '' );
		update_post_meta( $sz_new, '_sz_event_crop', (int) $sz_crop_id );
		update_post_meta( $sz_new, '_sz_event_difficulty', 'all' );
		update_post_meta( $sz_new, '_sz_event_priority', in_array( $sz_type, array( 'podzimny', 'ozim' ), true ) ? 'must' : 'opt' );
		update_post_meta( $sz_new, '_sz_event_weather_hint', isset( $sz_hints[ $sz_type ] ) ? $sz_hints[ $sz_type ] : '' );
		update_post_meta( $sz_new, '_sz_event_recurring', '1' );
		wp_set_object_terms( $sz_new, $sz_work, 'work_type' );

		$sz_created[] = $sz_new . ' | ' . $sz_from . '..' . $sz_to . ' | ' . $sz_title . ' | ' . implode( ',', $sz_need );
	}
}

echo 'создано черновиков: ' . count( $sz_created ) . ' (групп уже с окном: ' . $sz_skipped . ")\n";
echo implode( "\n", $sz_created ) . "\n";

