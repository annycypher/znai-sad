<?php
/**
 * Вспомогательные функции темы «Сад знаний».
 *
 * Наборы значений (регионы, свет, полив) и форматирование дат —
 * единый источник для метабоксов, колонок админки и шаблонов.
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Список регионов для событий календаря.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_regions() {
	return array(
		'yug'       => __( 'Юг', 'sad-znaniy' ),
		'srednyaya' => __( 'Средняя полоса', 'sad-znaniy' ),
		'ural'      => __( 'Урал', 'sad-znaniy' ),
		'sibir'     => __( 'Сибирь', 'sad-znaniy' ),
		'dalniy'    => __( 'Дальний Восток', 'sad-znaniy' ),
		'vse'       => __( 'Все регионы', 'sad-znaniy' ),
	);
}

/**
 * Подпись региона по ключу.
 *
 * @param string $key Ключ региона.
 * @return string
 */
function sad_znaniy_region_label( $key ) {
	$regions = sad_znaniy_regions();
	return isset( $regions[ $key ] ) ? $regions[ $key ] : '';
}

/**
 * Варианты освещённости растения.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_light_options() {
	return array(
		'sun'     => __( 'Солнце', 'sad-znaniy' ),
		'partial' => __( 'Полутень', 'sad-znaniy' ),
		'shade'   => __( 'Тень', 'sad-znaniy' ),
	);
}

/**
 * Варианты полива растения.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_water_options() {
	return array(
		'low'      => __( 'Редкий', 'sad-znaniy' ),
		'moderate' => __( 'Умеренный', 'sad-znaniy' ),
		'high'     => __( 'Обильный', 'sad-znaniy' ),
	);
}

/**
 * Подпись значения из произвольной карты «ключ => подпись».
 *
 * @param array  $map Карта значений.
 * @param string $key Ключ.
 * @return string
 */
function sad_znaniy_option_label( $map, $key ) {
	return isset( $map[ $key ] ) ? $map[ $key ] : '';
}

/**
 * Русские названия месяцев в родительном падеже (для дат событий).
 *
 * @return array Номер месяца => название.
 */
function sad_znaniy_months_genitive() {
	return array(
		1  => __( 'января', 'sad-znaniy' ),
		2  => __( 'февраля', 'sad-znaniy' ),
		3  => __( 'марта', 'sad-znaniy' ),
		4  => __( 'апреля', 'sad-znaniy' ),
		5  => __( 'мая', 'sad-znaniy' ),
		6  => __( 'июня', 'sad-znaniy' ),
		7  => __( 'июля', 'sad-znaniy' ),
		8  => __( 'августа', 'sad-znaniy' ),
		9  => __( 'сентября', 'sad-znaniy' ),
		10 => __( 'октября', 'sad-znaniy' ),
		11 => __( 'ноября', 'sad-znaniy' ),
		12 => __( 'декабря', 'sad-znaniy' ),
	);
}

/**
 * Разбирает дату события (Y-m-d) на части для блока даты.
 *
 * @param string $date Дата в формате Y-m-d.
 * @return array [num, month, label].
 */
function sad_znaniy_format_event_date( $date ) {
	$empty = array(
		'num'   => '',
		'month' => '',
		'label' => '',
	);

	$date = trim( (string) $date );
	if ( '' === $date ) {
		return $empty;
	}

	$time = strtotime( $date );
	if ( false === $time ) {
		return $empty;
	}

	$num     = gmdate( 'd', $time );
	$months  = sad_znaniy_months_genitive();
	$month_n = (int) gmdate( 'n', $time );
	$month   = isset( $months[ $month_n ] ) ? $months[ $month_n ] : '';

	return array(
		'num'   => $num,
		'month' => $month,
		'label' => trim( $num . ' ' . $month ),
	);
}

/**
 * Текст срока события («1 июня — 10 июня» или «с 1 июня»).
 *
 * @param string $from Дата начала (Y-m-d).
 * @param string $to   Дата окончания (Y-m-d), необязательно.
 * @return string
 */
function sad_znaniy_event_term_text( $from, $to ) {
	$f = sad_znaniy_format_event_date( $from );
	$t = sad_znaniy_format_event_date( $to );

	if ( '' !== $f['label'] && '' !== $t['label'] ) {
		/* translators: 1: дата начала, 2: дата окончания */
		return sprintf( __( '%1$s — %2$s', 'sad-znaniy' ), $f['label'], $t['label'] );
	}

	if ( '' !== $f['label'] ) {
		/* translators: %s: дата начала */
		return sprintf( __( 'с %s', 'sad-znaniy' ), $f['label'] );
	}

	return '';
}

/**
 * Ссылка на архив термина по слагу (или пустая строка).
 *
 * @param string $slug     Слаг термина.
 * @param string $taxonomy Таксономия.
 * @return string
 */
function sad_znaniy_term_link( $slug, $taxonomy ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : $link;
}

/**
 * Ссылка на раздел базы знаний (таксономия «Раздел»).
 *
 * @param string $slug Слаг раздела.
 * @return string
 */
function sad_znaniy_section_link( $slug ) {
	return sad_znaniy_term_link( $slug, 'plant_section' );
}

/**
 * Ссылка на рубрику «Рецепты».
 *
 * @return string
 */
function sad_znaniy_recipes_link() {
	return sad_znaniy_term_link( 'retsepty', 'category' );
}

/**
 * Ссылка на страницу «Все статьи» (страница записей), иначе — на главную.
 *
 * @return string
 */
function sad_znaniy_blog_url() {
	$page_for_posts = (int) get_option( 'page_for_posts' );
	if ( $page_for_posts ) {
		$link = get_permalink( $page_for_posts );
		if ( $link ) {
			return $link;
		}
	}

	return home_url( '/' );
}

/**
 * Ссылка на страницу «Политика конфиденциальности».
 *
 * @return string
 */
function sad_znaniy_privacy_url() {
	$page = get_page_by_path( 'privacy-policy' );
	if ( $page ) {
		$link = get_permalink( $page );
		if ( $link ) {
			return $link;
		}
	}

	return home_url( '/privacy-policy/' );
}

/**
 * Убирает префикс «Архивы:» у заголовков архивов типов записей.
 *
 * @param string $title Заголовок архива.
 * @return string
 */
function sad_znaniy_clean_archive_title( $title ) {
	if ( is_post_type_archive() ) {
		$title = post_type_archive_title( '', false );
	}

	return $title;
}
add_filter( 'get_the_archive_title', 'sad_znaniy_clean_archive_title' );

/**
 * Выбранные в настройках темы «популярные» растения для чипов на главной.
 *
 * @param int $limit Сколько максимум растений вывести.
 * @return WP_Post[] Список записей.
 */
function sad_znaniy_get_popular_plants( $limit = 6 ) {
	$options = get_option( 'sad_znaniy_options', array() );
	$ids     = isset( $options['popular_plants'] ) ? (array) $options['popular_plants'] : array();
	$ids     = array_filter( array_map( 'absint', $ids ) );

	if ( ! $ids ) {
		return array();
	}

	$ids = array_slice( $ids, 0, (int) $limit );

	$plants = get_posts(
		array(
			'post_type'      => 'plant',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $ids ),
			'no_found_rows'  => true,
		)
	);

	return $plants;
}

/**
 * Регионы для календаря (Этап 5.5). Ключи — как в демо kalendar-demo.html.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_region_keys() {
	return array(
		'south' => __( 'Юг', 'sad-znaniy' ),
		'mid'   => __( 'Средняя полоса', 'sad-znaniy' ),
		'ural'  => __( 'Урал', 'sad-znaniy' ),
		'sib'   => __( 'Сибирь', 'sad-znaniy' ),
		'dv'    => __( 'Дальний Восток', 'sad-znaniy' ),
	);
}

/**
 * Типы работ календаря (таксономия work_type).
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_work_types() {
	return array(
		'posev'      => __( 'Посев', 'sad-znaniy' ),
		'posadka'    => __( 'Посадка', 'sad-znaniy' ),
		'poliv'      => __( 'Полив', 'sad-znaniy' ),
		'podkormka'  => __( 'Подкормка', 'sad-znaniy' ),
		'obrezka'    => __( 'Обрезка', 'sad-znaniy' ),
		'zashchita'  => __( 'Защита', 'sad-znaniy' ),
		'sbor'       => __( 'Сбор', 'sad-znaniy' ),
		'podgotovka' => __( 'Подготовка', 'sad-znaniy' ),
	);
}

/**
 * Старые англоязычные слаги типов работ → человеческие (миграция URL).
 *
 * @return array Старый слаг => новый слаг.
 */
function sad_znaniy_work_type_slug_map() {
	return array(
		'sow'     => 'posev',
		'plant'   => 'posadka',
		'water'   => 'poliv',
		'feed'    => 'podkormka',
		'prune'   => 'obrezka',
		'protect' => 'zashchita',
		'harvest' => 'sbor',
		'prep'    => 'podgotovka',
	);
}

/**
 * Цвета типов работ (из демо kalendar-demo.html, переменные --c-*).
 *
 * @return array Ключ => HEX.
 */
function sad_znaniy_work_type_colors() {
	return array(
		'posev'      => '#E8B34B',
		'posadka'    => '#3FA46F',
		'poliv'      => '#4A90D9',
		'podkormka'  => '#9B6FD0',
		'obrezka'    => '#A9714B',
		'zashchita'  => '#D14D57',
		'sbor'       => '#E07B39',
		'podgotovka' => '#7C8B93',
	);
}

/**
 * Ссылка на посадочную страницу типа работ (/uhod/poliv/).
 *
 * @param string $slug Слаг типа работы.
 * @return string
 */
function sad_znaniy_work_type_link( $slug ) {
	$term = get_term_by( 'slug', $slug, 'work_type' );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$link = get_term_link( $term );

	return is_wp_error( $link ) ? '' : $link;
}

/**
 * Заголовок H1 посадочной страницы типа работ.
 *
 * @param string $slug Слаг типа работы.
 * @return string
 */
function sad_znaniy_work_type_h1( $slug ) {
	$titles = array(
		'poliv'      => __( 'Полив: сколько, когда и как часто', 'sad-znaniy' ),
		'obrezka'    => __( 'Обрезка: сроки, техника и типичные ошибки', 'sad-znaniy' ),
		'podkormka'  => __( 'Подкормка: чем и когда кормить растения', 'sad-znaniy' ),
		'posev'      => __( 'Посев: когда сеять семена и на рассаду', 'sad-znaniy' ),
		'posadka'    => __( 'Посадка: сроки и правила приживаемости', 'sad-znaniy' ),
		'zashchita'  => __( 'Защита растений: от болезней и вредителей', 'sad-znaniy' ),
		'sbor'       => __( 'Сбор урожая: когда снимать и как хранить', 'sad-znaniy' ),
		'podgotovka' => __( 'Подготовка: грядки, почва и сезонные работы', 'sad-znaniy' ),
	);

	return isset( $titles[ $slug ] ) ? $titles[ $slug ] : '';
}

/**
 * Инструмент-помощник для типа работ (калькулятор).
 *
 * @param string $slug Слаг типа работы.
 * @return array Массив url/label или пустой массив.
 */
function sad_znaniy_work_type_tool( $slug ) {
	$tools = array(
		'poliv'      => array( '/kalkulyator-poliva/', __( 'Калькулятор полива: сколько литров нужно вашим грядкам', 'sad-znaniy' ) ),
		'podkormka'  => array( '/kalkulyator-udobreniy/', __( 'Калькулятор удобрений: граммы по действующему веществу', 'sad-znaniy' ) ),
		'posev'      => array( '/kalkulyator-poseva/', __( 'Калькулятор посева: когда сеять в вашем регионе', 'sad-znaniy' ) ),
		'posadka'    => array( '/kalkulyator-poseva/', __( 'Калькулятор посева: сроки высадки и рассады', 'sad-znaniy' ) ),
		'podgotovka' => array( '/kalkulyator-grunta-i-ph/', __( 'Калькулятор грунта и pH: что внести в почву', 'sad-znaniy' ) ),
	);

	if ( ! isset( $tools[ $slug ] ) ) {
		return array();
	}

	return array(
		'url'   => home_url( $tools[ $slug ][0] ),
		'label' => $tools[ $slug ][1],
	);
}

/**
 * Частые вопросы для посадочной страницы типа работ (FAQ + микроразметка).
 *
 * @param string $slug Слаг типа работы.
 * @return array Список пар «вопрос» => «ответ».
 */
function sad_znaniy_work_type_faq( $slug ) {
	$faq = array(
		'poliv' => array(
			__( 'Как понять, что пора поливать?', 'sad-znaniy' ) => __( 'Проверьте почву пальцем или щупом на глубине 5–7 см: если сухо — поливайте. Опущенные листья без тургора означают, что пересушка уже началась и полив нужен был раньше.', 'sad-znaniy' ),
			__( 'Поливать утром или вечером?', 'sad-znaniy' ) => __( 'Лучше рано утром: до жары влага успевает уйти к корням, а листья обсохнуть. Вечерний полив тоже возможен, но не по листьям — мокрые листья на ночь открывают дорогу грибным болезням.', 'sad-znaniy' ),
			__( 'Сколько литров лить под куст?', 'sad-znaniy' ) => __( 'Средний ориентир — 10–12 л на 1 м² грядки. Томат в теплице в жару берёт до 4–5 л на куст в день, газон — 15–20 л на 1 м² раз в 3–4 дня. Точный расчёт по культуре, почве и погоде даёт наш калькулятор полива.', 'sad-znaniy' ),
		),
		'obrezka' => array(
			__( 'Когда лучше обрезать деревья и кустарники?', 'sad-znaniy' ) => __( 'Основную формирующую обрезку делают в конце зимы — начале весны, до распускания почек: растение спит, раны зарастают быстро. Санитарную обрезку (сушь, поломанные ветки) можно проводить в любой тёплый день.', 'sad-znaniy' ),
			__( 'Как отличить санитарную обрезку от формирующей?', 'sad-znaniy' ) => __( 'Санитарная убирает больное, сухое и поломанное. Формирующая задаёт скелет кроны и равномерную освещённость. Омолаживающая возвращает продуктивность старым кустам, укорачивая ветки до сильных молодых побегов.', 'sad-znaniy' ),
			__( 'Чем обрабатывать срезы?', 'sad-znaniy' ) => __( 'Срезы толще 2 см замазывают садовым варом или краской на натуральной олифе. В мороз замазка ложится плохо и трескается, поэтому зимнюю обрезку с крупными срезами лучше отложить или наносить тонким слоем в тёплый день.', 'sad-znaniy' ),
		),
		'podkormka' => array(
			__( 'Когда подкормки бесполезны?', 'sad-znaniy' ) => __( 'В сухой почве: гранулы не растворяются, а корни без влаги не усваивают питание. Порядок всегда один — сначала полив, потом подкормка, иначе высокая концентрация солей обжигает корни.', 'sad-znaniy' ),
			__( 'Сколько азота давать весной?', 'sad-znaniy' ) => __( 'Примерно 8–12 г действующего вещества на 1 м² под овощные грядки в начале роста. Переизбыток азота даёт мощную ботву без плодов, поэтому вторую азотную подкормку обычно не делают — переходят на калий и фосфор.', 'sad-znaniy' ),
			__( 'Можно ли смешивать удобрения в одной бочке?', 'sad-znaniy' ) => __( 'Не все: кальциевую селитру нельзя соединять с сульфатами и фосфорными удобрениями — выпадает осадок, питание становится недоступным. Порядок растворения: фосфор, затем калий, азот последним. Граммы по действующему веществу считает калькулятор удобрений.', 'sad-znaniy' ),
		),
		'posev' => array(
			__( 'Как выбрать день посева?', 'sad-znaniy' ) => __( 'Считайте назад от даты высадки в грунт для своего региона: томату нужно около 55 дней рассады, огурцу — 28. Сев «по календарю» без поправки на регион — главная причина переросшей рассады.', 'sad-znaniy' ),
			__( 'Почему семена не всходят?', 'sad-znaniy' ) => __( 'Чаще всего дело в температуре и глубине: при 18 °C томат всходит за 8–10 дней, при 25 °C — за 4–5. Семена, заглублённые больше чем на 1 см, тратят силы на подъём вместо роста. Почва должна быть влажной, но не мокрой.', 'sad-znaniy' ),
			__( 'Нужно ли замачивать семена перед посевом?', 'sad-znaniy' ) => __( 'Дражированные и инкрустированные — нет, у них уже есть оболочка. Обычные семена моркови, петрушки и лука замачивают на 10–12 часов: оболочка плотная, и без замачивания всходы растягиваются на две недели.', 'sad-znaniy' ),
		),
		'posadka' => array(
			__( 'Когда высаживать рассаду в грунт?', 'sad-znaniy' ) => __( 'Когда минует угроза возвратных заморозков, а почва прогреется до +10–12 °C на глубине 10 см. В средней полосе это конец мая — начало июня; теплолюбивые высаживают под лутрасил.', 'sad-znaniy' ),
			__( 'Что положить в лунку при посадке?', 'sad-znaniy' ) => __( 'Перегной или компост (одну-две горсти), золу как источник калия, при необходимости 15–20 г суперфосфата. Свежий навоз в лунку не кладут: он обжигает молодые корни.', 'sad-znaniy' ),
			__( 'Почему рассада не приживается?', 'sad-znaniy' ) => __( 'Причины — пересушенный земляной ком, посадка в сухую непролитую почву и палящее солнце в первые дни. Полейте лунку заранее, высаживайте вечером или в пасмурный день и притените саженцы на 2–3 дня.', 'sad-znaniy' ),
		),
		'zashchita' => array(
			__( 'Когда начинать профилактику болезней?', 'sad-znaniy' ) => __( 'Весной, до распускания почек: профилактика в этот момент дешевле и безопаснее, чем борьба с уже вспыхнувшей инфекцией. Летом достаточно регулярного осмотра и удаления первых поражённых листьев.', 'sad-znaniy' ),
			__( 'Народные средства работают?', 'sad-znaniy' ) => __( 'Как профилактика на ранних стадиях — да: настой чеснока, зольный и мыльный растворы против тли. Когда вредитель уже размножился, нужен препарат из разрешённого списка, иначе обработка только поддерживает колонию.', 'sad-znaniy' ),
			__( 'Как не навредить пчёлам и полезным насекомым?', 'sad-znaniy' ) => __( 'Не опрыскивайте в период цветения, обработку проводите вечером и не трогайте растения с распустившимися цветками. Часть препаратов нельзя применять при температуре выше +25 °C — всегда читайте этикетку.', 'sad-znaniy' ),
		),
		'sbor' => array(
			__( 'Как понять, что урожай готов?', 'sad-znaniy' ) => __( 'По признакам конкретной культуры: томат — ровный цвет и лёгкий отрыв от плодоножки, огурец — упругость и мелкие семена, корнеплод — размер по сорту и подсохшая ботва.', 'sad-znaniy' ),
			__( 'Собирать утром или вечером?', 'sad-znaniy' ) => __( 'Ягоды и зелень — утром, после высыхания росы: так они держат форму и лучше хранятся. Корнеплоды — в сухую погоду, чтобы не налипала земля. Мокрый урожай хранится хуже всего.', 'sad-znaniy' ),
			__( 'Что делать, чтобы урожай хранился дольше?', 'sad-znaniy' ) => __( 'Охладите собранное в течение часа, не мойте перед хранением и не смешивайте партии: один подгнивший плод заражает соседние. Тыква и капуста хранятся в сухом прохладном месте, ягоды — только в холоде.', 'sad-znaniy' ),
		),
		'podgotovka' => array(
			__( 'Обязательна ли осенняя перекопка?', 'sad-znaniy' ) => __( 'На тяжёлых почвах — да: переворот пласта выносит вредителей на мороз и улучшает структуру. На лёгких почвах перекопку заменяют посевом сидератов и мульчированием, чтобы не разрушать структуру.', 'sad-znaniy' ),
			__( 'Когда известковать почву?', 'sad-znaniy' ) => __( 'Осенью, под перекопку: известь должна прореагировать за зиму. Весной и прямо перед посадкой её не вносят — свежая известь обжигает корни. Точную дозу по типу почвы считает калькулятор грунта и pH.', 'sad-znaniy' ),
			__( 'Что сеять после уборки урожая?', 'sad-znaniy' ) => __( 'Сидераты: горчицу, фацелию, рожь, вику. Корни рыхлят почву, растения забирают лишний азот и подавляют сорняки. Заделывают зелень до цветения — тогда она разлагается быстро и без запаха.', 'sad-znaniy' ),
		),
	);

	return isset( $faq[ $slug ] ) ? $faq[ $slug ] : array();
}

/**
 * Уровни сложности события.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_difficulty_options() {
	return array(
		'new' => __( 'Новичок', 'sad-znaniy' ),
		'exp' => __( 'Опытный', 'sad-znaniy' ),
		'all' => __( 'Всем', 'sad-znaniy' ),
	);
}

/**
 * Приоритеты события.
 *
 * @return array Ключ => подпись.
 */
function sad_znaniy_priority_options() {
	return array(
		'must'    => __( 'Обязательно', 'sad-znaniy' ),
		'opt'     => __( 'Желательно', 'sad-znaniy' ),
		'weather' => __( 'По погоде', 'sad-znaniy' ),
	);
}

/**
 * Возвращает опубликованные события, пересекающиеся с заданным месяцем.
 *
 * @param int $year  Год.
 * @param int $month Месяц (1–12).
 * @return WP_Post[]
 */
function sad_znaniy_get_month_events( $year, $month ) {
	$events = get_posts(
		array(
			'post_type'      => 'calendar_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_sz_event_date_from',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	$last_day = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );
	$first    = sprintf( '%04d-%02d-01', $year, $month );
	$last     = sprintf( '%04d-%02d-%02d', $year, $month, $last_day );

	$result = array();
	foreach ( $events as $event ) {
		$from = (string) get_post_meta( $event->ID, '_sz_event_date_from', true );
		$to   = (string) get_post_meta( $event->ID, '_sz_event_date_to', true );
		if ( '' === $from ) {
			continue;
		}
		$to_eff = '' !== $to ? $to : $from;
		if ( $from <= $last && $to_eff >= $first ) {
			$result[] = $event;
		}
	}

	return $result;
}

/**
 * Нормализует данные события для рендера карточки календаря.
 *
 * @param int $post_id ID события.
 * @param int $year    Год отображаемого месяца.
 * @param int $month   Месяц (1–12).
 * @return array
 */
function sad_znaniy_event_data( $post_id, $year, $month ) {
	$from = (string) get_post_meta( $post_id, '_sz_event_date_from', true );
	$to   = (string) get_post_meta( $post_id, '_sz_event_date_to', true );

	$all_regions = '1' === (string) get_post_meta( $post_id, '_sz_event_all_regions', true );
	$regions_raw = (string) get_post_meta( $post_id, '_sz_event_regions', true );
	$regions     = $all_regions
		? array( 'all' )
		: array_values( array_filter( array_map( 'sanitize_key', explode( ',', $regions_raw ) ) ) );

	$crop_id = (int) get_post_meta( $post_id, '_sz_event_crop', true );
	$crop    = $crop_id ? get_the_title( $crop_id ) : '';

	$terms = get_the_terms( $post_id, 'work_type' );
	$type  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : 'podgotovka';

	// Дни месяца для отображения (событие может выходить за границы месяца).
	$last_day = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );
	$d1       = 1;
	$d2       = $last_day;

	if ( '' !== $from ) {
		$y1 = (int) substr( $from, 0, 4 );
		$m1 = (int) substr( $from, 5, 2 );
		$d1 = (int) substr( $from, 8, 2 );
		if ( $y1 < $year || ( $y1 === $year && $m1 < $month ) ) {
			$d1 = 1;
		}
	}
	if ( '' !== $to ) {
		$y2 = (int) substr( $to, 0, 4 );
		$m2 = (int) substr( $to, 5, 2 );
		if ( $y2 > $year || ( $y2 === $year && $m2 > $month ) ) {
			$d2 = $last_day;
		} else {
			$d2 = (int) substr( $to, 8, 2 );
		}
	} else {
		$d2 = $d1;
	}

	return array(
		'id'       => (int) $post_id,
		'title'    => get_the_title( $post_id ),
		'type'     => $type,
		'd1'       => $d1,
		'd2'       => $d2,
		'crop'     => $crop,
		'crop_id'  => $crop_id,
		'exp'      => (string) get_post_meta( $post_id, '_sz_event_difficulty', true ),
		'pr'       => (string) get_post_meta( $post_id, '_sz_event_priority', true ),
		'hint'     => (string) get_post_meta( $post_id, '_sz_event_weather_hint', true ),
		'regions'  => $regions,
		'permalink'=> get_permalink( $post_id ),
	);
}

/**
 * Строит URL страницы календаря с параметрами.
 *
 * @param int|null    $year   Год.
 * @param int|null    $month  Месяц.
 * @param string      $region Ключ региона.
 * @param string      $exp    Ключ опыта (new/exp/all).
 * @return string
 */
function sad_znaniy_calendar_url( $year = null, $month = null, $region = '', $exp = '' ) {
	$url  = home_url( '/kalendar/' );
	$args = array();
	if ( $year && $month ) {
		$args['year']  = $year;
		$args['month'] = $month;
	}
	if ( '' !== $region ) {
		$args['region'] = $region;
	}
	if ( '' !== $exp ) {
		$args['exp'] = $exp;
	}

	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Ссылки блока «Подробнее» у события: растение + раздел + статьи по культуре.
 *
 * @param int $crop_id ID растения-культуры (0 — если не задана).
 * @return array[] Список пар [подпись, URL] (до 3).
 */
function sad_znaniy_event_links( $crop_id ) {
	$links = array();
	$crop_id = (int) $crop_id;

	if ( $crop_id ) {
		$title = get_the_title( $crop_id );
		if ( $title && 'publish' === get_post_status( $crop_id ) ) {
			$links[] = array( 'Растение «' . $title . '»', get_permalink( $crop_id ) );
		}

		$sections = get_the_terms( $crop_id, 'plant_section' );
		if ( $sections && ! is_wp_error( $sections ) ) {
			$links[] = array( 'Раздел «' . $sections[0]->name . '»', get_term_link( $sections[0] ) );
		}

		if ( $title ) {
			$articles = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					's'              => $title,
					'posts_per_page' => 2,
					'no_found_rows'  => true,
				)
			);
			foreach ( $articles as $article ) {
				if ( (int) $article->ID === $crop_id ) {
					continue;
				}
				$links[] = array( get_the_title( $article ), get_permalink( $article ) );
			}
		}
	}

	return array_slice( $links, 0, 3 );
}

/**
 * SEO-слаги регионов для программатик-URL (Этап 7.1).
 *
 * @return array Внутренний ключ => URL-слаг.
 */
function sad_znaniy_region_slugs() {
	return array(
		'south' => 'yug',
		'mid'   => 'srednyaya-polosa',
		'ural'  => 'ural',
		'sib'   => 'sibir',
		'dv'    => 'dalniy-vostok',
	);
}

/**
 * URL-слаг региона → внутренний ключ (принимает и слаг, и сам ключ).
 *
 * @param string $slug Слаг из URL.
 * @return string Внутренний ключ или ''.
 */
function sad_znaniy_region_slug_to_key( $slug ) {
	$slug = sanitize_key( $slug );
	$slugs = sad_znaniy_region_slugs();
	$key = array_search( $slug, $slugs, true );
	if ( false !== $key ) {
		return $key;
	}
	if ( array_key_exists( $slug, sad_znaniy_region_keys() ) ) {
		return $slug;
	}
	return '';
}

/**
 * Внутренний ключ региона → URL-слаг.
 *
 * @param string $key Внутренний ключ.
 * @return string URL-слаг.
 */
function sad_znaniy_region_key_to_slug( $key ) {
	$slugs = sad_znaniy_region_slugs();
	return isset( $slugs[ $key ] ) ? $slugs[ $key ] : $key;
}

/**
 * SEO-слаги месяцев (транслитерация) для программатик-URL.
 *
 * @return array Номер месяца (1–12) => URL-слаг.
 */
function sad_znaniy_month_slugs() {
	return array(
		1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel',
		5 => 'maj', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust',
		9 => 'sentyabr', 10 => 'oktyabr', 11 => 'noyabr', 12 => 'dekabr',
	);
}

/**
 * URL-слаг месяца → номер (1–12), 0 если не найден.
 *
 * @param string $slug Слаг из URL.
 * @return int
 */
function sad_znaniy_month_slug_to_num( $slug ) {
	$map = array_flip( sad_znaniy_month_slugs() );
	return isset( $map[ $slug ] ) ? (int) $map[ $slug ] : 0;
}
