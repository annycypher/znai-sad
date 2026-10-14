<?php

// Только CLI: скрипты разработки не исполняются из браузера (Этап 7.3, S2).
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	exit( 'Скрипт запускается только из командной строки.' );
}
/**
 * СЕЗОННЫЕ ОКНА КУЛЬТУР — генератор/синхронизатор черновиков (Этап 5.7, модель v2).
 *
 * Идемпотентно: сверяет события по ключу «культура + набор регионов» и
 *   • обновляет даты/текст, если модель изменилась;
 *   • создаёт недостающие;
 *   • удаляет исключённые (косточковые в Сибири и на ДВ).
 * Исходные события владельца (с набором регионов «ВСЕ» или другим) не трогает.
 *
 * Запуск: wp eval-file _tools/sync-season-windows.php
 *
 * @package sad-znaniy
 */

$sz_year = 2026;

// Канон подзимнего посева (добавляется в конец текста каждого события).
$sz_podzimny_canon = ' Сеют по устойчивому похолоданию: днём стабильно ниже +5 °C, ночами земля схватывается. Календарные окна — ориентир, смотреть по погоде.';

// Климатические группы: даты «ММ-ДД» по типам окон.
$sz_groups = array(
	'yug' => array(
		'regions'    => array( 'south' ),
		'podzimny'   => array( '11-05', '11-15' ),
		'ozim'       => array( '10-20', '10-31' ),
		'saplings'   => array( '10-10', '11-05' ),
		'perennial'  => array( '10-15', '11-05' ),
		'melko'      => array( '10-05', '10-20' ),
		'strawberry' => array( '08-20', '09-15' ),
	),
	'mid' => array(
		'regions'    => array( 'mid', 'ural' ),
		'podzimny'   => array( '10-05', '10-15' ),
		'ozim'       => array( '10-05', '10-15' ),
		'saplings'   => array( '09-20', '10-10' ),
		'perennial'  => array( '09-25', '10-10' ),
		'melko'      => array( '09-10', '09-25' ),
		'strawberry' => array( '08-05', '09-05' ),
	),
	'sib' => array(
		'regions'    => array( 'sib', 'dv' ),
		'podzimny'   => array( '10-05', '10-15' ),
		'ozim'       => array( '09-25', '10-10' ),
		'saplings'   => array( '09-10', '09-30' ),
		'perennial'  => array( '09-10', '09-25' ),
		'melko'      => array( '09-01', '09-15' ),
		'strawberry' => array( '07-20', '08-15' ),
	),
);

$sz_hints = array(
	'podzimny'  => 'Сеять по подмёрзшей почве (днём ниже +5 °C), семена сухие, грядку замульчировать',
	'ozim'      => 'За 3–4 недели до устойчивых морозов: укорениться, но не тронуться в рост',
	'saplings'  => 'Осенняя посадка: запас 3–4 недели до морозов на укоренение',
	'perennial' => 'За 3–4 недели до устойчивых морозов, мульчирование обязательно',
	'melko'     => 'Мелколуковичные сажают за 3–4 недели до устойчивых морозов',
);

// Культура => [ тип, работы, заголовок, текст ]
$sz_crops = array(
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
	156 => array( 'ozim', 'posadka', 'Посадка озимого лука-севка', 'Севок на глубину 3–4 см, шаг 8–10 см, между рядами 20–25 см. После устойчивых заморозков грядку мульчируют торфом слоем 3 см.' ),
	157 => array( 'ozim', 'posadka', 'Посадка озимого чеснока', 'Зубки донцем вниз на глубину 4–6 см, шаг 10–12 см, между рядами 25 см. Грядку мульчируют перегноем или торфом.' ),
);

// Осенняя посадка саженцев. Косточковые — только Юг и Средняя/Урал:
// в Сибири и на ДВ осенняя посадка вымерзает.
$sz_stone_crops   = array( 161, 476, 479, 434 ); // вишня, черешня, слива, абрикос
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
$sz_stone_note   = ' В Сибири и на Дальнем Востоке косточковые сажают только весной — осенняя посадка вымерзает.';

foreach ( $sz_sapling_names as $sz_sid => $sz_sname ) {
	$sz_text = $sz_sapling_text . ( in_array( (int) $sz_sid, $sz_stone_crops, true ) ? $sz_stone_note : '' );
	$sz_crops[ $sz_sid ] = array( 'saplings', 'posadka', 'Посадка саженцев ' . $sz_sname . ' осенью', $sz_text );
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

// Мелколуковичные — только для тех карточек, что уже есть на сайте (иначе пропускаем).
global $wpdb;
$sz_melko_ids  = array();
$sz_melko_text = 'Мелколуковичные (крокус, нарцисс, гиацинт, мускари) сажают на глубину 2–3 высоты луковицы, шаг 8–10 см; после посадки грядку мульчируют торфом или компостом. Размножают и пересаживают их в период покоя.';
foreach ( array( 'крокус', 'нарцисс', 'гиацинт', 'мускари' ) as $sz_needle ) {
	$sz_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'plant' AND post_status IN ('publish','draft','pending','future') AND post_title LIKE %s",
			'%' . $wpdb->esc_like( $sz_needle ) . '%'
		)
	);
	foreach ( (array) $sz_ids as $sz_one ) {
		$sz_melko_ids[ (int) $sz_one ] = true;
	}
}
foreach ( array_keys( $sz_melko_ids ) as $sz_mid ) {
	$sz_crops[ $sz_mid ] = array( 'melko', 'posadka', 'Посадка мелколуковичных', $sz_melko_text );
}

// Растения, добавленные позже (сопоставление по названию) — тип окна по культуре.
$sz_late = array(
	'лилия'   => array( 'perennial', 'posadka', 'Осенняя посадка и деление лилий' ),
	'примула' => array( 'perennial', 'posadka', 'Осенняя посадка и деление примул' ),
	'астильба' => array( 'perennial', 'posadka', 'Осенняя посадка и деление астильб' ),
	'бадан'   => array( 'perennial', 'posadka', 'Осенняя посадка и деление баданов' ),
	'мята'    => array( 'perennial', 'posadka', 'Осенняя посадка и деление мяты' ),
	'мелисса' => array( 'perennial', 'posadka', 'Осенняя посадка и деление мелиссы' ),
	'тимьян'  => array( 'perennial', 'posadka', 'Осенняя посадка и деление тимьяна' ),
	'чабрец'  => array( 'perennial', 'posadka', 'Осенняя посадка и деление тимьяна' ),
	'виноград' => array( 'saplings', 'posadka', 'Посадка саженцев винограда осенью' ),
);
foreach ( $sz_late as $sz_needle => $sz_meta ) {
	$sz_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'plant' AND post_status IN ('publish','draft','pending','future') AND post_title LIKE %s",
			'%' . $wpdb->esc_like( $sz_needle ) . '%'
		)
	);
	foreach ( (array) $sz_ids as $sz_one ) {
		$sz_crops[ (int) $sz_one ] = array( $sz_meta[0], $sz_meta[1], $sz_meta[2], ( 'saplings' === $sz_meta[0] ) ? $sz_sapling_text : $sz_peren_text );
	}
}

echo 'мелколуковичные на сайте: ' . ( $sz_melko_ids ? implode( ',', array_keys( $sz_melko_ids ) ) : 'пока нет — события будут созданы, когда появятся карточки' ) . "\n";

// Укрытие роз — подготовительное событие (не сезонное окно культуры).
$sz_cover = array(
	array( array( 'south' ), '11-15', '11-30' ),
	array( array( 'mid', 'ural', 'sib', 'dv' ), '10-25', '11-10' ),
);

/**
 * Индекс осенних событий культуры: работа → ключ регионов → ID.
 *
 * @param int $crop_id ID растения.
 * @return array
 */
function sz_autumn_index( $crop_id ) {
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

	$index = array();
	foreach ( $events as $eid ) {
		$from = (string) get_post_meta( $eid, '_sz_event_date_from', true );
		$m    = (int) substr( $from, 5, 2 );
		if ( $m < 8 || $m > 11 ) {
			continue;
		}
		$wt   = wp_get_post_terms( $eid, 'work_type', array( 'fields' => 'slugs' ) );
		$slug = ( is_array( $wt ) && $wt ) ? (string) $wt[0] : '';
		$all  = '1' === (string) get_post_meta( $eid, '_sz_event_all_regions', true );
		$regs = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( $eid, '_sz_event_regions', true ) ) ) );
		sort( $regs );
		$key = $all ? 'ALL' : implode( ',', $regs );

		$index[ $slug ][ $key ][] = (int) $eid;
	}
	return $index;
}

/**
 * Записывает мета-поля сезонного события.
 *
 * @param int    $eid     ID события.
 * @param int    $crop_id Культура.
 * @param string $work    Тип работ.
 * @param array  $regions Регионы.
 * @param string $from    Начало.
 * @param string $to      Конец.
 * @param string $hint    Погодная подсказка.
 * @param string $prio    Приоритет.
 */
function sz_write_event_meta( $eid, $crop_id, $work, $regions, $from, $to, $hint, $prio ) {
	update_post_meta( $eid, '_sz_event_date_from', $from );
	update_post_meta( $eid, '_sz_event_date_to', $to );
	update_post_meta( $eid, '_sz_event_regions', implode( ',', (array) $regions ) );
	update_post_meta( $eid, '_sz_event_all_regions', '' );
	update_post_meta( $eid, '_sz_event_crop', (int) $crop_id );
	update_post_meta( $eid, '_sz_event_difficulty', 'all' );
	update_post_meta( $eid, '_sz_event_priority', $prio );
	update_post_meta( $eid, '_sz_event_weather_hint', $hint );
	update_post_meta( $eid, '_sz_event_recurring', '1' );
	wp_set_object_terms( $eid, $work, 'work_type' );
}

$sz_stat = array( 'created' => 0, 'updated' => 0, 'deleted' => 0 );
$sz_rows = array(); // для сводки: тип => [ культура => [ группа => даты ] ]

/**
 * Синхронизирует одно целевое окно: обновляет существующее или создаёт новое.
 *
 * @param array  $index  Индекс событий культуры.
 * @param string $work   Тип работ.
 * @param array  $regs   Регионы.
 * @param string $title  Заголовок.
 * @param string $text   Текст.
 * @param string $from   Начало.
 * @param string $to     Конец.
 * @param int    $crop   Культура.
 * @param array  $hint   Погодная подсказка/приоритет.
 * @param array  $stat   Счётчики (по ссылке).
 * @return string Статус ('updated' | 'created' | 'removed' | '—').
 */
function sz_sync_target( $index, $work, $regs, $title, $text, $from, $to, $crop, $hint, $prio, &$stat ) {
	$key   = implode( ',', $regs );
	$found = isset( $index[ $work ][ $key ] ) ? $index[ $work ][ $key ] : array();

	if ( $found ) {
		$first = (int) array_shift( $found );
		foreach ( $found as $extra ) {
			wp_delete_post( (int) $extra, true );
			$stat['deleted']++;
		}
		// Статус не понижаем: уже опубликованное окно остаётся опубликованным.
		$status = ( 'publish' === get_post_status( $first ) ) ? 'publish' : 'draft';
		wp_update_post(
			array(
				'ID'           => $first,
				'post_title'   => $title,
				'post_content' => $text,
				'post_status'  => $status,
			)
		);
		sz_write_event_meta( $first, $crop, $work, $regs, $from, $to, $hint, $prio );
		$stat['updated']++;
		return 'updated';
	}

	$new = wp_insert_post(
		array(
			'post_type'    => 'calendar_event',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => $text,
		),
		true
	);
	if ( is_wp_error( $new ) ) {
		echo 'ОШИБКА: ' . $title . ' — ' . $new->get_error_message() . "\n";
		return 'error';
	}
	sz_write_event_meta( $new, $crop, $work, $regs, $from, $to, $hint, $prio );
	$stat['created']++;
	return 'created';
}

$sz_fmt = function ( $d ) {
	return substr( (string) $d, 8, 2 ) . '.' . substr( (string) $d, 5, 2 );
};

$sz_cover_title = 'Подготовка роз к зиме: укрытие';
$sz_cover_text  = 'Укрывают в сухую погоду при устойчивых −3…−5 °C: снимают листья с нижней части куста, окучивают торфом или компостом на 15–20 см, побеги пригибают и укрывают лапником или каркасом с агроволокном. Раньше времени не укрывать — выпреет.';

$sz_gkeys = array( 'yug', 'mid', 'sib' );
$sz_valid = array();

foreach ( $sz_crops as $sz_crop_id => $sz_c ) {
	list( $sz_type, $sz_work, $sz_title, $sz_text ) = $sz_c;

	$sz_index   = sz_autumn_index( $sz_crop_id );
	$sz_cropttl = (string) get_the_title( $sz_crop_id );

	foreach ( $sz_gkeys as $sz_gk ) {
		$sz_g  = $sz_groups[ $sz_gk ];
		$sz_rs = $sz_g['regions'];
		sort( $sz_rs );
		$sz_key = implode( ',', $sz_rs );

		// Косточковые в Сибири и на ДВ осенью не сажают — событие удаляем.
		if ( 'saplings' === $sz_type && in_array( (int) $sz_crop_id, $sz_stone_crops, true ) && 'sib' === $sz_gk ) {
			if ( ! empty( $sz_index[ $sz_work ][ $sz_key ] ) ) {
				foreach ( $sz_index[ $sz_work ][ $sz_key ] as $sz_eid ) {
					wp_delete_post( (int) $sz_eid, true );
					$sz_stat['deleted']++;
				}
			}
			$sz_rows[ $sz_type ][ $sz_cropttl ][ $sz_gk ] = 'только весной';
			continue;
		}

		$sz_from      = $sz_year . '-' . $sz_g[ $sz_type ][0];
		$sz_to        = $sz_year . '-' . $sz_g[ $sz_type ][1];
		$sz_text_full = $sz_text . ( 'podzimny' === $sz_type ? $sz_podzimny_canon : '' );
		$sz_prio      = in_array( $sz_type, array( 'podzimny', 'ozim', 'melko' ), true ) ? 'must' : 'opt';

		sz_sync_target(
			$sz_index,
			$sz_work,
			$sz_rs,
			$sz_title,
			$sz_text_full,
			$sz_from,
			$sz_to,
			$sz_crop_id,
			isset( $sz_hints[ $sz_type ] ) ? $sz_hints[ $sz_type ] : '',
			$sz_prio,
			$sz_stat
		);

		$sz_valid[ $sz_crop_id ][ $sz_work ][ $sz_key ] = true;

		$sz_rows[ $sz_type ][ $sz_cropttl ][ $sz_gk ] = $sz_fmt( $sz_from ) . '–' . $sz_fmt( $sz_to );
	}
}

// Укрытие роз — отдельным блоком (подготовительная работа).
$sz_rose_index = sz_autumn_index( 12 );
foreach ( $sz_cover as $sz_cv ) {
	$sz_rs = $sz_cv[0];
	sort( $sz_rs );
	$sz_valid[12]['podgotovka'][ implode( ',', $sz_rs ) ] = true;
	sz_sync_target(
		$sz_rose_index,
		'podgotovka',
		$sz_rs,
		$sz_cover_title,
		$sz_cover_text,
		$sz_year . '-' . $sz_cv[1],
		$sz_year . '-' . $sz_cv[2],
		12,
		'Укрывать в сухую погоду при −3…−5 °C',
		'opt',
		$sz_stat
	);
}

// Устаревшие точечные окна: события культуры с набором регионов, которого больше нет в модели.
// Например, старые окна чеснока «Сибирь», «Сибирь и Урал», «Средняя полоса», «ДВ» по отдельности.
$sz_clean = 0;
foreach ( $sz_crops as $sz_crop_id => $sz_c ) {
	list( , $sz_work ) = $sz_c;
	$sz_index = sz_autumn_index( $sz_crop_id );
	if ( empty( $sz_index[ $sz_work ] ) ) {
		continue;
	}
	foreach ( $sz_index[ $sz_work ] as $sz_key => $sz_ids ) {
		if ( ! empty( $sz_valid[ $sz_crop_id ][ $sz_work ][ $sz_key ] ) ) {
			continue;
		}
		foreach ( (array) $sz_ids as $sz_eid ) {
			echo '  удаляю устаревшее окно #' . $sz_eid . ' | ' . get_the_title( $sz_eid ) . ' | регионы: ' . $sz_key . ' | ' . get_post_meta( $sz_eid, '_sz_event_date_from', true ) . " |\n";
			wp_delete_post( (int) $sz_eid, true );
			$sz_stat['deleted']++;
			$sz_clean++;
		}
	}
}
echo 'устаревших точечных окон удалено: ' . $sz_clean . "\n";

echo "\n=== ИТОГИ ===\n";
echo 'создано: ' . $sz_stat['created'] . ' · обновлено: ' . $sz_stat['updated'] . ' · удалено: ' . $sz_stat['deleted'] . "\n";

$sz_titles = array(
	'podzimny'  => 'ПОДЗИМНИЙ ПОСЕВ',
	'ozim'      => 'ОЗИМЫЕ: ЛУК И ЧЕСНОК',
	'saplings'  => 'САЖЕНЦЫ',
	'perennial' => 'МНОГОЛЕТНИКИ И ЛУКОВИЧНЫЕ',
	'melko'     => 'МЕЛКОЛУКОВИЧНЫЕ',
);

foreach ( $sz_titles as $sz_tk => $sz_tt ) {
	if ( empty( $sz_rows[ $sz_tk ] ) ) {
		echo "\n### " . $sz_tt . "\n(событий нет)\n";
		continue;
	}
	echo "\n### " . $sz_tt . "\n| Культура | Юг | Средняя+Урал | Сибирь+ДВ |\n|---|---|---|---|\n";
	foreach ( $sz_rows[ $sz_tk ] as $sz_cttl => $sz_dates ) {
		echo '| ' . $sz_cttl . ' | ' . ( isset( $sz_dates['yug'] ) ? $sz_dates['yug'] : '—' )
			. ' | ' . ( isset( $sz_dates['mid'] ) ? $sz_dates['mid'] : '—' )
			. ' | ' . ( isset( $sz_dates['sib'] ) ? $sz_dates['sib'] : '—' ) . " |\n";
	}
}
echo "\n### ПОДГОТОВКА РОЗ (укрытие, тип «Подготовка»)\n";
echo '| Регионы | Даты |' . "\n|---|---\n";
foreach ( $sz_cover as $sz_cv ) {
	echo '| ' . implode( '+', $sz_cv[0] ) . ' | ' . $sz_fmt( $sz_year . '-' . $sz_cv[1] ) . '–' . $sz_fmt( $sz_year . '-' . $sz_cv[2] ) . " |\n";
}



