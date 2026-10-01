<?php
/**
 * SEO-Хаб → Ключи (Этап 7.2, Партия 1).
 *
 * Импорт CSV-выгрузки Вордстата, авторазметка фраз по редактируемым
 * правилам на программатик-URL, таблица с фильтрами и экспорт карты
 * (programmatic-map: CSV + Markdown).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Формы названий месяцев для поиска в фразе (1–12).
 *
 * @return array Номер месяца => набор словоформ.
 */
function sad_znaniy_seo_month_forms() {
	return array(
		1  => array( 'январь', 'января', 'январе' ),
		2  => array( 'февраль', 'февраля', 'феврале' ),
		3  => array( 'март', 'марта', 'марте' ),
		4  => array( 'апрель', 'апреля', 'апреле' ),
		5  => array( 'май', 'мая', 'мае' ),
		6  => array( 'июнь', 'июня', 'июне' ),
		7  => array( 'июль', 'июля', 'июле' ),
		8  => array( 'август', 'августа', 'августе' ),
		9  => array( 'сентябрь', 'сентября', 'сентябре' ),
		10 => array( 'октябрь', 'октября', 'октябре' ),
		11 => array( 'ноябрь', 'ноября', 'ноябре' ),
		12 => array( 'декабрь', 'декабря', 'декабре' ),
	);
}

/**
 * Формы названий регионов для поиска в фразе.
 *
 * @return array Ключ региона => набор словоформ.
 */
function sad_znaniy_seo_region_forms() {
	return array(
		'south' => array( 'юг', 'юга', 'юге', 'южный', 'южная' ),
		'mid'   => array( 'средняя полоса', 'средней полосе', 'средней полосы', 'подмосковье' ),
		'ural'  => array( 'урал', 'урала', 'урале', 'уральский', 'уральском' ),
		'sib'   => array( 'сибирь', 'сибири', 'сибирский', 'сибирском' ),
		'dv'    => array( 'дальний восток', 'дальнем востоке', 'дальнего востока', 'приморье' ),
	);
}

/**
 * Словарь культур из CPT «Растение»: ID => словоформы (название + основа + слаг).
 *
 * @return array
 */
function sad_znaniy_seo_plant_forms() {
	static $forms = null;
	if ( null !== $forms ) {
		return $forms;
	}

	$forms  = array();
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

	foreach ( $plants as $plant ) {
		$title = mb_strtolower( trim( $plant->post_title ), 'UTF-8' );
		$list  = array( $title );

		// Основа без окончания: «томаты», «огурцы», «гортензии».
		$len = mb_strlen( $title, 'UTF-8' );
		if ( $len > 4 ) {
			$list[] = mb_substr( $title, 0, max( 4, $len - 2 ), 'UTF-8' );
		}

		// Слаги растения (латиница) — как слова.
		foreach ( explode( ' ', str_replace( '-', ' ', (string) $plant->post_name ) ) as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) > 3 ) {
				$list[] = $word;
			}
		}

		$forms[ (int) $plant->ID ] = array_values( array_unique( $list ) );
	}

	return $forms;
}

/**
 * Есть ли слово в фразе (проверка по левой границе, чтобы ловить словоформы).
 *
 * @param string $phrase Фраза в нижнем регистре.
 * @param string $needle Слово/основа в нижнем регистре.
 * @return bool
 */
function sad_znaniy_seo_phrase_has( $phrase, $needle ) {
	if ( '' === $needle ) {
		return false;
	}
	$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $needle, '/' ) . '/u';
	return (bool) preg_match( $pattern, $phrase );
}

/**
 * Правила авторазметки по умолчанию (редактируются на экране «Ключи»).
 *
 * @return string
 */
function sad_znaniy_seo_rules_default() {
	return implode(
		"\n",
		array(
			'работы, что делать, календарь, посевные > kalendar',
			'когда сажать, когда сеять, высаживать, посадка, сроки посадки, сроки посева > kogda-sazhat',
			'подкормка, подкормить, полив, поливать, обрезка, обрезать, уход за, удобрение > uhod',
		)
	);
}

/**
 * Разобранные правила авторазметки из опции sz_seo_rules.
 *
 * Формат строки: «слово1, слово2 > шаблон» (шаблон: kalendar, kogda-sazhat, uhod).
 * Строки, начинающиеся с #, — комментарии.
 *
 * @return array Список правил ['keywords' => string[], 'template' => string].
 */
function sad_znaniy_seo_rules() {
	$raw = get_option( 'sz_seo_rules', '' );
	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		$raw = sad_znaniy_seo_rules_default();
	}

	$allowed = array( 'kalendar', 'kogda-sazhat', 'uhod' );
	$rules   = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line || str_starts_with( $line, '#' ) ) {
			continue;
		}
		$parts = explode( '>', $line, 2 );
		if ( count( $parts ) < 2 ) {
			continue;
		}

		$keywords = array();
		foreach ( explode( ',', $parts[0] ) as $kw ) {
			$kw = mb_strtolower( trim( $kw ), 'UTF-8' );
			if ( '' !== $kw ) {
				$keywords[] = $kw;
			}
		}

		$template = sanitize_key( trim( $parts[1] ) );
		if ( $keywords && in_array( $template, $allowed, true ) ) {
			$rules[] = array(
				'keywords' => $keywords,
				'template' => $template,
			);
		}
	}

	return $rules;
}

/**
 * Индекс программатик-страниц: URL => статус (live/draft/empty).
 *
 * @return array
 */
function sad_znaniy_seo_pages_index() {
	static $index = null;
	if ( null !== $index ) {
		return $index;
	}

	$index = array();
	$pages = get_option( 'sad_znaniy_programmatic_pages', array() );
	foreach ( (array) $pages as $page ) {
		if ( ! empty( $page['url'] ) ) {
			$index[ (string) $page['url'] ] = sad_znaniy_programmatic_status( $page );
		}
	}

	return $index;
}

/**
 * Собирает URL программатик-страницы по шаблону и найденным сущностям.
 *
 * @param string $template Шаблон (kalendar / kogda-sazhat / uhod).
 * @param string $region   Ключ региона ('' — нет).
 * @param int    $month    Месяц (0 — нет).
 * @param int    $plant_id ID растения (0 — нет).
 * @return string URL или '' (если не хватает данных или страницы нет в матрице).
 */
function sad_znaniy_seo_build_url( $template, $region, $month, $plant_id ) {
	$months = sad_znaniy_month_slugs();
	$url    = '';

	if ( 'kalendar' === $template && $region && $month ) {
		$url = home_url( '/kalendar/' . sad_znaniy_region_key_to_slug( $region ) . '/' . $months[ $month ] . '/' );
	} elseif ( 'kogda-sazhat' === $template && $plant_id && $region ) {
		$slug = get_post_field( 'post_name', $plant_id );
		if ( $slug ) {
			$url = home_url( '/kogda-sazhat/' . $slug . '/' . sad_znaniy_region_key_to_slug( $region ) . '/' );
		}
	} elseif ( 'uhod' === $template && $plant_id && $month ) {
		$slug = get_post_field( 'post_name', $plant_id );
		if ( $slug ) {
			$url = home_url( '/uhod/' . $slug . '/' . $months[ $month ] . '/' );
		}
	}

	// Страницы нет в матрице — разметка будет «manual».
	return ( '' !== $url && isset( sad_znaniy_seo_pages_index()[ $url ] ) ) ? $url : '';
}

/**
 * Авторазметка одной фразы: ищет регион, месяц, культуру и применяет правила.
 *
 * @param string $phrase Фраза.
 * @return array ['url' => string, 'template' => string].
 */
function sad_znaniy_seo_match( $phrase ) {
	$phrase_l = mb_strtolower( trim( (string) $phrase ), 'UTF-8' );
	$none     = array(
		'url'      => '',
		'template' => '',
	);

	if ( '' === $phrase_l ) {
		return $none;
	}

	$month = 0;
	foreach ( sad_znaniy_seo_month_forms() as $num => $forms ) {
		foreach ( $forms as $form ) {
			if ( sad_znaniy_seo_phrase_has( $phrase_l, $form ) ) {
				$month = (int) $num;
				break 2;
			}
		}
	}

	$region = '';
	foreach ( sad_znaniy_seo_region_forms() as $key => $forms ) {
		foreach ( $forms as $form ) {
			if ( sad_znaniy_seo_phrase_has( $phrase_l, $form ) ) {
				$region = $key;
				break 2;
			}
		}
	}

	$plant_id = 0;
	foreach ( sad_znaniy_seo_plant_forms() as $pid => $forms ) {
		foreach ( $forms as $form ) {
			if ( sad_znaniy_seo_phrase_has( $phrase_l, $form ) ) {
				$plant_id = (int) $pid;
				break 2;
			}
		}
	}

	foreach ( sad_znaniy_seo_rules() as $rule ) {
		$hit = false;
		foreach ( $rule['keywords'] as $kw ) {
			if ( sad_znaniy_seo_phrase_has( $phrase_l, $kw ) ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit ) {
			continue;
		}

		$url = sad_znaniy_seo_build_url( $rule['template'], $region, $month, $plant_id );
		if ( '' !== $url ) {
			return array(
				'url'      => $url,
				'template' => $rule['template'],
			);
		}
	}

	return $none;
}

/**
 * Статус ключа: weak (частота < 10) / live / manual.
 *
 * @param int    $freq Частота.
 * @param string $url  Размеченный URL.
 * @return string
 */
function sad_znaniy_seo_status_for( $freq, $url ) {
	if ( (int) $freq < 10 ) {
		return 'weak';
	}
	return '' !== $url ? 'live' : 'manual';
}

/**
 * Разбирает CSV-выгрузку Вордстата и сохраняет фразы (дедуп по фразе).
 *
 * Формат: фраза + показы (разделитель «;», «,» или табуляция). Первая
 * строка-заголовок распознаётся и пропускается.
 *
 * @param string $text Содержимое CSV.
 * @return array ['added' => int, 'updated' => int, 'skipped' => int].
 */
function sad_znaniy_seo_keys_import( $text ) {
	global $wpdb;

	$t     = sad_znaniy_seo_tables();
	$table = $t['keys'];

	$text  = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	$lines = preg_split( '/\n/', $text );
	$delim = '';

	$stats = array(
		'added'   => 0,
		'updated' => 0,
		'skipped' => 0,
	);

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		if ( '' === $delim ) {
			if ( substr_count( $line, ';' ) >= 1 ) {
				$delim = ';';
			} elseif ( substr_count( $line, "\t" ) >= 1 ) {
				$delim = "\t";
			} else {
				$delim = ',';
			}
		}

		$row = str_getcsv( $line, $delim, '"' );
		$row = array_map( 'trim', (array) $row );

		$phrase = isset( $row[0] ) ? $row[0] : '';
		if ( '' === $phrase ) {
			$stats['skipped']++;
			continue;
		}

		// Заголовок выгрузки: текст без цифр.
		if ( preg_match( '/^(фраза|phrase|запрос|слово|ключ|word)/ui', $phrase ) && ! $stats['added'] && ! $stats['updated'] ) {
			$stats['skipped']++;
			continue;
		}

		$freq = 0;
		foreach ( $row as $cell ) {
			$digits = preg_replace( '/[^\d]/', '', (string) $cell );
			if ( '' !== $digits ) {
				$freq = max( $freq, (int) $digits );
			}
		}
		if ( $freq < 1 ) {
			$stats['skipped']++;
			continue;
		}

		$phrase = mb_strtolower( preg_replace( '/\s+/u', ' ', $phrase ), 'UTF-8' );
		if ( mb_strlen( $phrase, 'UTF-8' ) > 191 ) {
			$phrase = mb_substr( $phrase, 0, 191, 'UTF-8' );
		}

		$affected = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (phrase, freq, created) VALUES (%s, %d, %s)
				 ON DUPLICATE KEY UPDATE freq = GREATEST(freq, VALUES(freq)), created = VALUES(created)",
				$phrase,
				$freq,
				current_time( 'mysql' )
			)
		);

		if ( 2 === (int) $affected ) {
			$stats['updated']++;
		} else {
			$stats['added']++;
		}
	}

	sad_znaniy_seo_recalc();
	return $stats;
}

/**
 * Пересчитывает разметку (matched_url + status) для всех ключей.
 */
function sad_znaniy_seo_recalc() {
	global $wpdb;

	$t     = sad_znaniy_seo_tables();
	$table = $t['keys'];
	$rows  = $wpdb->get_results( "SELECT id, phrase, freq FROM {$table}" );

	foreach ( (array) $rows as $row ) {
		$match  = sad_znaniy_seo_match( $row->phrase );
		$status = sad_znaniy_seo_status_for( (int) $row->freq, $match['url'] );
		$wpdb->update(
			$table,
			array(
				'matched_url' => $match['url'],
				'status'      => $status,
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}

/**
 * Подписи статусов ключей.
 *
 * @return array
 */
function sad_znaniy_seo_key_status_labels() {
	return array(
		'live'   => __( 'живая', 'sad-znaniy' ),
		'weak'   => __( 'слабая', 'sad-znaniy' ),
		'manual' => __( 'manual', 'sad-znaniy' ),
	);
}

/**
 * Подписи семейств программатик-страниц.
 *
 * @return array
 */
function sad_znaniy_seo_family_labels() {
	return array(
		'region-month' => __( 'Работы по региону', 'sad-znaniy' ),
		'plant-region' => __( 'Когда сажать', 'sad-znaniy' ),
		'plant-month'  => __( 'Уход', 'sad-znaniy' ),
	);
}

/**
 * Запрос к таблице ключей с фильтрами, поиском и сортировкой по частоте.
 *
 * @param array $args Фильтры: status (all/live/weak/manual), s, order (ASC/DESC), limit.
 * @return object[]
 */
function sad_znaniy_seo_keys_query( $args = array() ) {
	global $wpdb;

	$t     = sad_znaniy_seo_tables();
	$table = $t['keys'];

	$args = wp_parse_args(
		$args,
		array(
			'status' => 'all',
			's'      => '',
			'order'  => 'DESC',
			'limit'  => 0,
		)
	);

	$where  = '1=1';
	$params = array();

	if ( in_array( $args['status'], array( 'live', 'weak', 'manual' ), true ) ) {
		$where    .= ' AND status = %s';
		$params[] = $args['status'];
	}

	$search = trim( (string) $args['s'] );
	if ( '' !== $search ) {
		$where    .= ' AND phrase LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}

	$order = ( 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';
	$sql   = "SELECT * FROM {$table} WHERE {$where} ORDER BY freq {$order}, phrase ASC";

	if ( (int) $args['limit'] > 0 ) {
		$sql .= ' LIMIT ' . (int) $args['limit'];
	}

	if ( $params ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql собран из фиксированных частей.
		$sql = $wpdb->prepare( $sql, $params );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- подготовлено выше.
	return $wpdb->get_results( $sql );
}

/**
 * Счётчики ключей по статусам.
 *
 * @return array all/live/weak/manual => int.
 */
function sad_znaniy_seo_keys_counts() {
	global $wpdb;

	$t     = sad_znaniy_seo_tables();
	$table = $t['keys'];
	$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A );

	$out = array(
		'all'    => 0,
		'live'   => 0,
		'weak'   => 0,
		'manual' => 0,
	);

	foreach ( (array) $rows as $row ) {
		$status = (string) $row['status'];
		if ( isset( $out[ $status ] ) ) {
			$out[ $status ] = (int) $row['c'];
		}
		$out['all'] += (int) $row['c'];
	}

	return $out;
}

/**
 * Данные карты программатика: страницы из матрицы + полная сетка семейства 1.
 *
 * @return array ['pages' => array[], 'grid' => array[]].
 */
function sad_znaniy_seo_map_rows() {
	$pages = get_option( 'sad_znaniy_programmatic_pages', array() );
	$out   = array();

	foreach ( (array) $pages as $page ) {
		$out[] = array(
			'url'     => (string) $page['url'],
			'family'  => (string) $page['family'],
			'phrase'  => (string) $page['phrase'],
			'count'   => (int) $page['count'],
			'status'  => sad_znaniy_programmatic_status( $page ),
			'enabled' => empty( $page['enabled'] ) ? 0 : 1,
			'intro'   => (string) $page['intro'],
		);
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return strcmp( $a['url'], $b['url'] );
		}
	);

	$exists = array();
	foreach ( $out as $row ) {
		$exists[ $row['url'] ] = $row;
	}

	// Полная сетка семейства 1: 5 регионов × 12 месяцев = 60.
	$months = sad_znaniy_month_slugs();
	$grid   = array();
	foreach ( sad_znaniy_region_slugs() as $region_key => $region_slug ) {
		foreach ( $months as $num => $month_slug ) {
			$url    = home_url( '/kalendar/' . $region_slug . '/' . $month_slug . '/' );
			$row    = isset( $exists[ $url ] ) ? $exists[ $url ] : null;
			$grid[] = array(
				'url'    => $url,
				'region' => sad_znaniy_option_label( sad_znaniy_region_keys(), $region_key ),
				'month'  => (int) $num,
				'count'  => $row ? (int) $row['count'] : 0,
				'status' => $row ? $row['status'] : 'none',
			);
		}
	}

	return array(
		'pages' => $out,
		'grid'  => $grid,
	);
}

/**
 * Экранирует ячейку Markdown-таблицы.
 *
 * @param string $text Текст.
 * @return string
 */
function sad_znaniy_seo_md_cell( $text ) {
	return str_replace(
		array( '|', "\r\n", "\r", "\n" ),
		array( '\|', '<br>', '<br>', '<br>' ),
		(string) $text
	);
}

/**
 * Отдаёт CSV-файл на скачивание и завершает запрос.
 *
 * @param string $filename Имя файла.
 * @param array  $rows     Строки (массивы ячеек).
 */
function sad_znaniy_seo_send_csv( $filename, $rows ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$fh = fopen( 'php://output', 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" );
	foreach ( $rows as $row ) {
		fputcsv( $fh, $row, ';', '"', '\\' );
	}
	fclose( $fh );
	exit;
}

/**
 * Отдаёт текстовый файл (Markdown) на скачивание и завершает запрос.
 *
 * @param string $filename Имя файла.
 * @param string $content  Содержимое.
 * @param string $mime     MIME-тип.
 */
function sad_znaniy_seo_send_text( $filename, $content, $mime = 'text/markdown' ) {
	nocache_headers();
	header( 'Content-Type: ' . $mime . '; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- это файл на скачивание.
	exit;
}

/**
 * Собирает Markdown-файл карты программатика (programmatic-map.md).
 *
 * @return string
 */
function sad_znaniy_seo_map_markdown() {
	$map  = sad_znaniy_seo_map_rows();
	$fam  = sad_znaniy_seo_family_labels();
	$stat = array(
		'live'  => 'живая',
		'draft' => 'черновик',
		'empty' => 'пустая',
		'none'  => 'нет данных',
	);

	$counts = array(
		'live'  => 0,
		'draft' => 0,
		'empty' => 0,
	);
	foreach ( $map['pages'] as $row ) {
		if ( isset( $counts[ $row['status'] ] ) ) {
			$counts[ $row['status'] ]++;
		}
	}

	$lines   = array();
	$lines[] = '# programmatic-map — карта программатик-страниц «Сад знаний»';
	$lines[] = '';
	$lines[] = 'Сгенерировано: ' . current_time( 'Y-m-d H:i' ) . ' (экран «📊 SEO-Хаб → Ключи», экспорт).';
	$lines[] = '';
	$lines[] = sprintf(
		'Всего страниц: %d (живых: %d, черновиков: %d, пустых: %d).',
		count( $map['pages'] ),
		$counts['live'],
		$counts['draft'],
		$counts['empty']
	);
	$lines[] = '';
	$lines[] = '## Страницы (из данных календаря)';
	$lines[] = '';
	$lines[] = '| URL | Семейство | Фраза | Событий | Статус | Вкл. | Интро |';
	$lines[] = '|---|---|---|---|---|---|---|';

	foreach ( $map['pages'] as $row ) {
		$lines[] = '| ' . $row['url']
			. ' | ' . ( isset( $fam[ $row['family'] ] ) ? $fam[ $row['family'] ] : $row['family'] )
			. ' | ' . sad_znaniy_seo_md_cell( $row['phrase'] )
			. ' | ' . $row['count']
			. ' | ' . $stat[ $row['status'] ]
			. ' | ' . ( $row['enabled'] ? 'да' : 'нет' )
			. ' | ' . sad_znaniy_seo_md_cell( $row['intro'] ) . ' |';
	}

	$lines[] = '';
	$lines[] = '## Полная матрица семейства 1 (5 регионов × 12 месяцев = 60)';
	$lines[] = '';
	$lines[] = '| Регион | Месяц | URL | Событий | Статус |';
	$lines[] = '|---|---|---|---|---|';

	foreach ( $map['grid'] as $cell ) {
		$lines[] = '| ' . sad_znaniy_seo_md_cell( $cell['region'] )
			. ' | ' . $cell['month']
			. ' | ' . $cell['url']
			. ' | ' . $cell['count']
			. ' | ' . $stat[ $cell['status'] ] . ' |';
	}

	$keys = sad_znaniy_seo_keys_query( array( 'order' => 'DESC' ) );
	if ( $keys ) {
		$lines[] = '';
		$lines[] = '## Фразы (Вордстат)';
		$lines[] = '';
		$lines[] = '| Фраза | Частота | URL | Статус |';
		$lines[] = '|---|---|---|---|';
		foreach ( $keys as $key ) {
			$lines[] = '| ' . sad_znaniy_seo_md_cell( $key->phrase )
				. ' | ' . (int) $key->freq
				. ' | ' . (string) $key->matched_url
				. ' | ' . (string) $key->status . ' |';
		}
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Обработчик экспорта хаба (admin-post): ключи CSV и карта CSV/Markdown.
 */
function sad_znaniy_seo_export_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'sad-znaniy' ) );
	}
	check_admin_referer( 'sz_seo_export' );

	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
	$date = gmdate( 'Y-m-d' );

	if ( 'keys' === $type ) {
		$rows = array( array( 'Фраза', 'Частота', 'URL', 'Статус' ) );
		foreach ( sad_znaniy_seo_keys_query() as $key ) {
			$rows[] = array( $key->phrase, (string) $key->freq, (string) $key->matched_url, (string) $key->status );
		}
		sad_znaniy_seo_send_csv( 'seo-keys-' . $date . '.csv', $rows );
	}

	if ( 'map-csv' === $type ) {
		$map  = sad_znaniy_seo_map_rows();
		$fam  = sad_znaniy_seo_family_labels();
		$rows = array( array( 'URL', 'Семейство', 'Фраза', 'Событий', 'Статус', 'Вкл.', 'Интро' ) );

		foreach ( $map['pages'] as $row ) {
			$rows[] = array(
				$row['url'],
				isset( $fam[ $row['family'] ] ) ? $fam[ $row['family'] ] : $row['family'],
				$row['phrase'],
				(string) $row['count'],
				$row['status'],
				$row['enabled'] ? 'да' : 'нет',
				str_replace( array( "\r\n", "\r", "\n" ), ' ', $row['intro'] ),
			);
		}

		sad_znaniy_seo_send_csv( 'programmatic-map-' . $date . '.csv', $rows );
	}

	if ( 'map-md' === $type ) {
		sad_znaniy_seo_send_text( 'programmatic-map-' . $date . '.md', sad_znaniy_seo_map_markdown() );
	}

	wp_die( esc_html__( 'Неизвестный тип экспорта.', 'sad-znaniy' ) );
}
add_action( 'admin_post_sad_znaniy_seo_export', 'sad_znaniy_seo_export_handler' );

/**
 * Экран «📊 SEO-Хаб → Ключи».
 */
function sad_znaniy_seo_hub_keys_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$notice = '';
	$tables = sad_znaniy_seo_tables();

	// Импорт CSV-выгрузки (файлом или вставкой текста).
	if ( isset( $_POST['sz_keys_import'] ) && check_admin_referer( 'sz_keys_import', 'sz_keys_nonce' ) ) {
		$text = isset( $_POST['sz_keys_text'] ) ? wp_unslash( $_POST['sz_keys_text'] ) : '';

		$tmp = isset( $_FILES['sz_keys_file']['tmp_name'] ) ? (string) $_FILES['sz_keys_file']['tmp_name'] : '';
		if ( '' !== $tmp && is_uploaded_file( $tmp ) ) {
			$file_text = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- локальный временный файл загрузки.
			if ( false !== $file_text && '' !== trim( (string) $file_text ) ) {
				$text = $file_text;
			}
		}

		if ( '' === trim( (string) $text ) ) {
			$notice = '<div class="notice notice-warning"><p>Нет данных: приложите CSV-файл или вставьте строки в поле вставки.</p></div>';
		} else {
			$stats  = sad_znaniy_seo_keys_import( $text );
			$notice = '<div class="notice notice-success is-dismissible"><p>Импорт готов: добавлено '
				. (int) $stats['added'] . ', обновлено ' . (int) $stats['updated'] . ', пропущено ' . (int) $stats['skipped'] . '.</p></div>';
			sad_znaniy_seo_log( 'Импорт ключей: +' . (int) $stats['added'] . ' / ~' . (int) $stats['updated'] );
		}
	}

	// Сохранение правил авторазметки (+ пересчёт).
	if ( isset( $_POST['sz_keys_rules'] ) && check_admin_referer( 'sz_keys_rules', 'sz_keys_rules_nonce' ) ) {
		$new_rules = isset( $_POST['sz_seo_rules'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sz_seo_rules'] ) ) : '';
		update_option( 'sz_seo_rules', $new_rules, false );
		sad_znaniy_seo_recalc();
		$notice = '<div class="notice notice-success is-dismissible"><p>Правила сохранены, разметка пересчитана.</p></div>';
	}

	// Пересчёт разметки вручную.
	if ( isset( $_POST['sz_keys_recalc'] ) && check_admin_referer( 'sz_keys_recalc', 'sz_keys_recalc_nonce' ) ) {
		sad_znaniy_seo_recalc();
		$notice = '<div class="notice notice-success is-dismissible"><p>Разметка пересчитана по текущим правилам.</p></div>';
	}

	// Очистка списка ключей.
	if ( isset( $_POST['sz_keys_clear'] ) && check_admin_referer( 'sz_keys_clear', 'sz_keys_clear_nonce' ) ) {
		$wpdb->query( "TRUNCATE TABLE {$tables['keys']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- имя таблицы задано кодом.
		$notice = '<div class="notice notice-success is-dismissible"><p>Список ключей очищен.</p></div>';
	}

	sad_znaniy_seo_keys_render( $notice );
}

/**
 * Отрисовка экрана «Ключи»: инструкция, импорт, правила, экспорт, таблица.
 *
 * Фильтры берутся из GET: sz_status, sz_q, sz_order.
 *
 * @param string $notice Готовое уведомление (HTML).
 */
function sad_znaniy_seo_keys_render( $notice = '' ) {
	$status = isset( $_GET['sz_status'] ) ? sanitize_key( wp_unslash( $_GET['sz_status'] ) ) : 'all';
	$search = isset( $_GET['sz_q'] ) ? sanitize_text_field( wp_unslash( $_GET['sz_q'] ) ) : '';
	$order  = ( isset( $_GET['sz_order'] ) && 'asc' === strtolower( (string) $_GET['sz_order'] ) ) ? 'ASC' : 'DESC';

	$counts = sad_znaniy_seo_keys_counts();
	$keys   = sad_znaniy_seo_keys_query(
		array(
			'status' => $status,
			's'      => $search,
			'order'  => $order,
		)
	);
	$labels = sad_znaniy_seo_key_status_labels();
	$raw    = get_option( 'sz_seo_rules', '' );
	$raw    = ( is_string( $raw ) && '' !== trim( $raw ) ) ? $raw : sad_znaniy_seo_rules_default();
	$export = admin_url( 'admin-post.php?action=sad_znaniy_seo_export' );
	$nonce  = wp_create_nonce( 'sz_seo_export' );
	$base   = admin_url( 'admin.php?page=sad-seo-hub' );
	?>
	<div class="wrap">
		<h1>SEO-Хаб → Ключи (семантика)</h1>

		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- сформировано кодом выше. ?>

		<p><b>Для чего:</b> вы загружаете выгрузку Вордстата — инструмент сам размечает фразы на программатик-страницы и показывает, где выдача «слабая» (частота меньше 10). Это список задач: что включать и какое интро усиливать.</p>

		<?php
		sad_znaniy_seo_help_box(
			'Как этим пользоваться (инструкция)',
			array(
				'<b>Где взять CSV:</b> wordstat.yandex.ru → введите фразу → «Скачать» → выберите <b>CSV</b> (XLSX не принимается — сохраните как CSV).',
				'<b>Импорт:</b> приложите файл или вставьте строки «фраза;показы» в поле вставки → «Импортировать». Дубли по фразе обновляются, не дублируются.',
				'<b>Порог слабости:</b> частота меньше <b>10</b> — фраза серая («слабая»), включать такие страницы не спешите.',
				'<b>Авторазметка:</b> фразы со словом месяца/региона/культуры получают готовый URL программатик-страницы. Если совпадений нет — статус «manual».',
				'<b>Правила:</b> строки вида «слово1, слово2 &gt; шаблон» (kalendar / kogda-sazhat / uhod). Меняются здесь, после сохранения разметка пересчитывается.',
				'<b>Экспорт:</b> «Карта (Markdown)» — файл programmatic-map для сверки и хранения в проекте.',
			),
			'«13. SEO-Хаб»'
		);
		?>

		<h2>1. Импорт выгрузки Вордстата</h2>
		<form method="post" enctype="multipart/form-data" style="margin-bottom:10px;">
			<?php wp_nonce_field( 'sz_keys_import', 'sz_keys_nonce' ); ?>
			<p>
				<input type="file" name="sz_keys_file" accept=".csv,.txt">
				<span class="description">файл CSV (фраза;показы)</span>
			</p>
			<p>
				<textarea name="sz_keys_text" rows="4" style="width:100%;max-width:760px;" placeholder="или вставьте строки:&#10;когда сажать огурцы в сибири;1240&#10;работы в саду в мае;890"></textarea>
			</p>
			<p class="submit">
				<button type="submit" name="sz_keys_import" class="button button-primary">Импортировать</button>
			</p>
		</form>

		<h2>2. Правила авторазметки</h2>
		<form method="post" style="margin-bottom:10px;">
			<?php wp_nonce_field( 'sz_keys_rules', 'sz_keys_rules_nonce' ); ?>
			<textarea name="sz_seo_rules" rows="4" style="width:100%;max-width:760px;"><?php echo esc_textarea( $raw ); ?></textarea>
			<p class="description">Формат строки: «слово1, слово2 &gt; шаблон». Шаблоны: kalendar (регион+месяц), kogda-sazhat (культура+регион), uhod (культура+месяц).</p>
			<p class="submit">
				<button type="submit" name="sz_keys_rules" class="button">Сохранить правила и пересчитать</button>
			</p>
		</form>

		<form method="post" style="display:inline;">
			<?php wp_nonce_field( 'sz_keys_recalc', 'sz_keys_recalc_nonce' ); ?>
			<button type="submit" name="sz_keys_recalc" class="button">Пересчитать разметку</button>
		</form>
		<form method="post" style="display:inline;" onsubmit="return confirm('Очистить весь список ключей? Действие необратимо.');">
			<?php wp_nonce_field( 'sz_keys_clear', 'sz_keys_clear_nonce' ); ?>
			<button type="submit" name="sz_keys_clear" class="button">Очистить список</button>
		</form>

		<h2>3. Экспорт карты</h2>
		<p>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'type' => 'map-md', '_wpnonce' => $nonce ), $export ) ); ?>">Карта (Markdown)</a>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'type' => 'map-csv', '_wpnonce' => $nonce ), $export ) ); ?>">Карта (CSV)</a>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'type' => 'keys', '_wpnonce' => $nonce ), $export ) ); ?>">Ключи (CSV)</a>
		</p>

		<h2>4. Ключи</h2>
		<p>
			Всего: <b><?php echo (int) $counts['all']; ?></b>
			· <span style="color:#2e6b4f;">живых: <?php echo (int) $counts['live']; ?></span>
			· <span style="color:#999;">слабых: <?php echo (int) $counts['weak']; ?></span>
			· manual: <?php echo (int) $counts['manual']; ?>
		</p>

		<form method="get" style="margin-bottom:10px;">
			<input type="hidden" name="page" value="sad-seo-hub">
			<select name="sz_status">
				<?php foreach ( array( 'all' => 'все', 'live' => 'живая', 'weak' => 'слабая', 'manual' => 'manual' ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="sz_q" value="<?php echo esc_attr( $search ); ?>" placeholder="поиск по фразе">
			<select name="sz_order">
				<option value="desc" <?php selected( $order, 'DESC' ); ?>>частота ↓</option>
				<option value="asc" <?php selected( $order, 'ASC' ); ?>>частота ↑</option>
			</select>
			<button type="submit" class="button">Фильтр</button>
			<a class="button" href="<?php echo esc_url( $base ); ?>">Сбросить</a>
		</form>

		<table class="widefat striped">
			<thead>
				<tr>
					<th>Фраза</th>
					<th style="width:90px;">
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'sad-seo-hub', 'sz_order' => ( 'ASC' === $order ? 'desc' : 'asc' ), 'sz_status' => $status, 'sz_q' => $search ), admin_url( 'admin.php' ) ) ); ?>">Частота</a>
					</th>
					<th style="width:38%;">URL программатик-страницы</th>
					<th style="width:90px;">Статус</th>
					<th style="width:110px;">Страница</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $keys ) : ?>
				<tr><td colspan="5">Пока пусто — импортируйте выгрузку Вордстата.</td></tr>
			<?php else : ?>
				<?php foreach ( $keys as $key ) : ?>
					<tr <?php echo ( 'weak' === $key->status ) ? 'style="color:#999;"' : ''; ?>>
						<td><?php echo esc_html( $key->phrase ); ?></td>
						<td><?php echo (int) $key->freq; ?></td>
						<td><?php echo ( '' !== (string) $key->matched_url ) ? esc_html( $key->matched_url ) : '—'; ?></td>
						<td><span class="sz-status sz-<?php echo esc_attr( $key->status ); ?>"><?php echo esc_html( isset( $labels[ $key->status ] ) ? $labels[ $key->status ] : $key->status ); ?></span></td>
						<td>
							<?php if ( '' !== (string) $key->matched_url ) : ?>
								<a href="<?php echo esc_url( $key->matched_url ); ?>" target="_blank" rel="noopener">открыть</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<style>
			.sz-status{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}
			.sz-live{background:#e7f0e9;color:#2e6b4f}.sz-weak{background:#f1f1f1;color:#999}.sz-manual{background:#fdf3e0;color:#a9714b}
		</style>
	</div>
	<?php
}

