<?php
/**
 * Главная страница — статичная разметка из макета _design/index.html.
 *
 * Этап 1: все блоки статичные (оживление — Этап 2).
 * Локальные фото-заглушки — из assets/img/ (ноль внешних запросов).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img = get_template_directory_uri() . '/assets/img';

$section_links = array(
	'sad'    => sad_znaniy_section_link( 'sad' ),
	'ogorod' => sad_znaniy_section_link( 'ogorod' ),
	'tsvety' => sad_znaniy_section_link( 'tsvety' ),
	'recipe' => sad_znaniy_recipes_link(),
);
?>

	<!-- ================= HERO ================= -->
	<section class="hero">
		<div class="hero-grid">
			<div class="hero-left">
				<h1>Ваш идеальный сад.<br>Знания. Инструменты. Урожай.</h1>
				<p class="hero-sub">Калькуляторы полива и удобрений, планировщик участка и календарь работ — всё в одном месте, для новичков и опытных дачников.</p>
				<div class="hero-buttons">
					<a href="#tools" class="btn btn-dark">Планировщик <span class="arr">→</span></a>
					<a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>" class="btn btn-light"><?php esc_html_e( 'Календарь работ', 'sad-znaniy' ); ?></a>
				</div>
				<?php $popular_plants = sad_znaniy_get_popular_plants(); ?>
				<?php if ( $popular_plants ) : ?>
					<div class="popular">
						<span class="popular-label"><?php esc_html_e( 'Популярное:', 'sad-znaniy' ); ?></span>
						<?php foreach ( $popular_plants as $popular_plant ) : ?>
							<a href="<?php echo esc_url( get_permalink( $popular_plant ) ); ?>" class="popular-chip"><?php echo esc_html( get_the_title( $popular_plant ) ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="collage" aria-label="Фотографии сада и огорода">
				<div class="collage-grid">
					<img src="<?php echo esc_url( $img . '/hero-beds.jpg' ); ?>" alt="Грядки в ухоженном саду" width="480" height="360" fetchpriority="high">
					<img src="<?php echo esc_url( $img . '/hero-greenhouse.jpg' ); ?>" alt="Теплица с растениями" loading="lazy" width="480" height="360">
					<img src="<?php echo esc_url( $img . '/hero-flowers.jpg' ); ?>" alt="Цветник у дома" loading="lazy" width="480" height="360">
					<img src="<?php echo esc_url( $img . '/hero-harvest.jpg' ); ?>" alt="Урожай овощей" loading="lazy" width="480" height="360">
				</div>
				<a href="#tools" class="glass-cta">
					<span class="tile-ico" aria-hidden="true">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20 3 17V4l6 3 6-3 6 3v13l-6-3-6 3Z"/><path d="M9 7v13M15 4v13"/></svg>
					</span>
					<strong>Планировщик участка</strong>
					<span class="cta-note">Начертите план посадок онлайн</span>
					<span class="circle-go" aria-hidden="true">→</span>
				</a>
			</div>
		</div>
	</section>

	<!-- ================= ИНСТРУМЕНТЫ (2×2) ================= -->
	<section class="section" id="tools" aria-label="Инструменты">
		<div class="section-head"><h2 class="h-cap">Инструменты садовода</h2></div>
		<div class="tools-grid">

			<a href="http://sadznaniy.local/kalkulyatory/" class="tool-card">
				<span class="tile-ico" aria-hidden="true">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01"/></svg>
				</span>
				<h3>Калькуляторы</h3>
				<p>Полив, удобрения, грунт — расчёт под вашу площадь и регион</p>
				<span class="circle-go" aria-hidden="true">→</span>
			</a>

			<a href="#tools" class="tool-card">
				<span class="tile-ico" aria-hidden="true">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20 3 17V4l6 3 6-3 6 3v13l-6-3-6 3Z"/><path d="M9 7v13M15 4v13"/></svg>
				</span>
				<h3>Планировщик участка</h3>
				<p>Начертите участок и получите план посадок с учётом совместимости</p>
				<span class="circle-go" aria-hidden="true">→</span>
			</a>

			<a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>" class="tool-card">
				<span class="tile-ico" aria-hidden="true">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
				</span>
				<h3>Календарь дачника</h3>
				<p>Что делать на участке по месяцам — с поправкой на ваш регион</p>
				<span class="circle-go" aria-hidden="true">→</span>
			</a>

			<a href="#" class="tool-card">
				<span class="tile-ico" aria-hidden="true">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 3v2M12 15v2M5 10h2M17 10h2M7 5l1.4 1.4M15.6 13.6 17 15M7 15l1.4-1.4M15.6 6.4 17 5"/><path d="M8 21h8M10 18h4"/></svg>
				</span>
				<h3>Конструктор букета</h3>
				<p>Соберите букет под повод и характер человека — из наших справочников</p>
				<span class="circle-go" aria-hidden="true">→</span>
			</a>

		</div>

		<div class="ad-slot" aria-hidden="true">Рекламный блок РСЯ — 970×90</div>
	</section>

	<!-- ================= БЛОГ + КАЛЬКУЛЯТОРЫ ================= -->
	<section class="section" id="sadvogorod">
		<div class="blog-grid">

			<div>
				<div class="section-head">
					<h2 class="h-cap"><?php esc_html_e( 'Блог и советы', 'sad-znaniy' ); ?></h2>
					<a href="<?php echo esc_url( sad_znaniy_blog_url() ); ?>" class="btn btn-light" style="padding:8px 18px; min-height:38px; font-size:12px;"><?php esc_html_e( 'Все статьи', 'sad-znaniy' ); ?></a>
				</div>
				<?php
				$blog_query = new WP_Query(
					array(
						'post_type'           => 'post',
						'posts_per_page'      => 2,
						'ignore_sticky_posts' => true,
						'no_found_rows'       => true,
					)
				);
				?>
				<?php if ( $blog_query->have_posts() ) : ?>
					<div class="posts">
						<?php
						while ( $blog_query->have_posts() ) :
							$blog_query->the_post();
							?>
							<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
								<?php if ( has_post_thumbnail() ) : ?>
									<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?></a>
								<?php endif; ?>
								<div class="post-body">
									<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
									<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
									<span class="post-date"><?php echo esc_html( get_the_date() ); ?></span>
								</div>
							</article>
							<?php
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				<?php else : ?>
					<p class="hero-sub"><?php esc_html_e( 'Статей пока нет — напишите первую в админке.', 'sad-znaniy' ); ?></p>
				<?php endif; ?>
			</div>

			<div>
				<div class="section-head"><h2 class="h-sub" style="font-size:15px;">Полезные калькуляторы</h2></div>
				<div class="calc-tiles">
					<a href="http://sadznaniy.local/kalkulyator-poliva/" class="tile">
						<span class="tile-ico" aria-hidden="true">
							<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c3.5 4 6 7.2 6 10.5a6 6 0 0 1-12 0C6 10.2 8.5 7 12 3Z"/></svg>
						</span>
						<strong>Расчёт полива</strong>
						<span class="circle-go" aria-hidden="true">→</span>
					</a>
					<a href="http://sadznaniy.local/kalkulyator-udobreniy/" class="tile">
						<span class="tile-ico" aria-hidden="true">
							<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l-1 4H9L8 3Z"/><rect x="5" y="7" width="14" height="14" rx="2"/><path d="M8 12h8M8 16h5"/></svg>
						</span>
						<strong>Удобрения</strong>
						<span class="circle-go" aria-hidden="true">→</span>
					</a>
					<a href="http://sadznaniy.local/kalkulyator-grunta-i-ph/" class="tile">
						<span class="tile-ico" aria-hidden="true">
							<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 7-3 7-7 0-4.5-4-8-7-13-3 5-7 8.5-7 13 0 4 3 7 7 7Z"/></svg>
						</span>
						<strong>Грунт и pH</strong>
						<span class="circle-go" aria-hidden="true">→</span>
					</a>
					<a href="http://sadznaniy.local/kalkulyatory/" class="tile">
						<span class="tile-ico" aria-hidden="true">
							<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V10"/><path d="M12 10C12 6 9 4 5 4c0 4 3 6 7 6Z"/><path d="M12 13c0-3.5 2.5-5 6-5 0 3.5-2.5 5-6 5Z"/><path d="M6 22h12"/></svg>
						</span>
						<strong>Посев семян</strong>
						<span class="circle-go" aria-hidden="true">→</span>
					</a>
					<a href="#tools" class="tile tile-wide">
						<span class="tile-ico" aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20 3 17V4l6 3 6-3 6 3v13l-6-3-6 3Z"/><path d="M9 7v13M15 4v13"/></svg>
						</span>
						<strong>Планировщик участка — чертите план онлайн</strong>
						<span class="circle-go" aria-hidden="true">→</span>
					</a>
				</div>
			</div>

		</div>
	</section>

	<!-- ================= РАЗДЕЛЫ + КАЛЕНДАРЬ ================= -->
	<section class="section" id="recipes">
		<div class="sx-grid">

			<div>
				<div class="section-head"><h2 class="h-cap" style="font-size:21px;">Разделы</h2></div>
				<div class="sx-tiles">
					<a href="<?php echo esc_url( $section_links['sad'] ? $section_links['sad'] : '#' ); ?>" class="sx-tile">
						<span class="tile-ico" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg></span>
						<strong>Сад</strong>
					</a>
					<a href="<?php echo esc_url( $section_links['ogorod'] ? $section_links['ogorod'] : '#' ); ?>" class="sx-tile">
						<span class="tile-ico" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m14 8 4-4M16 6l2 2"/><path d="m3 21 9-9"/><path d="M15 5l4 4-8 8H7v-4l8-8Z"/></svg></span>
						<strong>Огород</strong>
					</a>
					<a href="<?php echo esc_url( $section_links['tsvety'] ? $section_links['tsvety'] : '#' ); ?>" class="sx-tile">
						<span class="tile-ico" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg></span>
						<strong>Цветы</strong>
					</a>
					<a href="#" class="sx-tile">
						<span class="tile-ico" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V9"/><path d="M12 9C12 5 9.5 3 5.5 3c0 4 2.5 6 6.5 6Z"/><path d="M12 13c0-3.5 2.5-5.5 6.5-5.5 0 4-2.5 5.5-6.5 5.5Z"/></svg></span>
						<strong>Уход</strong>
					</a>
					<a href="#" class="sx-tile">
						<span class="tile-ico" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l-1 4H9L8 3Z"/><rect x="5" y="7" width="14" height="14" rx="2"/><path d="M9 12l2 2 4-4"/></svg></span>
						<strong>Удобрения</strong>
					</a>
					<a href="<?php echo esc_url( $section_links['recipe'] ? $section_links['recipe'] : '#' ); ?>" class="sx-tile">
						<span class="tile-ico" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11h16l-1.5 9a2 2 0 0 1-2 1.6h-9A2 2 0 0 1 5.5 20L4 11Z"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
						<strong>Рецепты</strong>
					</a>
				</div>
			</div>

			<div class="panel-cal" id="calendar">
				<div class="section-head">
					<h2 class="h-cap" style="font-size:21px;">Календарь работ</h2>
					<a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>" class="btn btn-light" style="padding:8px 18px; min-height:38px; font-size:12px;"><?php esc_html_e( 'Весь календарь', 'sad-znaniy' ); ?></a>
				</div>

				<?php
				$events = new WP_Query(
					array(
						'post_type'      => 'calendar_event',
						'posts_per_page' => 30,
						'meta_key'       => '_sz_event_date_from',
						'orderby'        => 'meta_value',
						'order'          => 'ASC',
						'no_found_rows'  => true,
						'meta_query'     => array(
							array(
								'key'     => '_sz_event_date_from',
								'value'   => current_time( 'Y-m-d' ),
								'compare' => '>=',
								'type'    => 'DATE',
							),
						),
					)
				);
				?>
				<?php if ( $events->have_posts() ) : ?>
					<?php
					$event_idx = 0;
					while ( $events->have_posts() ) :
						$events->the_post();
						$event_from   = (string) get_post_meta( get_the_ID(), '_sz_event_date_from', true );
						$event_to     = (string) get_post_meta( get_the_ID(), '_sz_event_date_to', true );
						$event_all     = '1' === (string) get_post_meta( get_the_ID(), '_sz_event_all_regions', true );
						$event_regions = array_filter( array_map( 'sanitize_key', explode( ',', (string) get_post_meta( get_the_ID(), '_sz_event_regions', true ) ) ) );
						$event_date   = sad_znaniy_format_event_date( $event_from );
						$event_term   = sad_znaniy_event_term_text( $event_from, $event_to );
						$event_regions_attr = $event_all ? 'all' : implode( ',', $event_regions );
						?>
						<div class="event<?php echo $event_idx >= 3 ? ' is-extra' : ''; ?>" data-regions="<?php echo esc_attr( $event_regions_attr ); ?>" data-date="<?php echo esc_attr( $event_from ); ?>">
							<div class="date-block" aria-label="<?php echo esc_attr( $event_date['label'] ); ?>"><span class="date-num"><?php echo esc_html( $event_date['num'] ); ?></span><span class="date-month"><?php echo esc_html( $event_date['month'] ); ?></span></div>
							<div class="event-body">
								<h3><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
								<?php if ( $event_term ) : ?>
									<div class="event-meta">
										<span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg><?php echo esc_html( $event_term ); ?></span>
									</div>
								<?php endif; ?>
								<?php if ( $event_all ) : ?>
									<span class="region-tag"><?php esc_html_e( 'Все регионы', 'sad-znaniy' ); ?></span>
								<?php elseif ( $event_regions ) : ?>
									<span class="region-tag"><?php echo esc_html( sad_znaniy_option_label( sad_znaniy_region_keys(), $event_regions[0] ) ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<?php
						$event_idx++;
					endwhile;
					wp_reset_postdata();
					?>
				<?php else : ?>
					<p class="hero-sub"><?php esc_html_e( 'Событий пока нет — добавьте их в разделе «События».', 'sad-znaniy' ); ?></p>
				<?php endif; ?>

				<a href="https://web.max.ru/-76163835891728" class="tg-banner" target="_blank" rel="noopener">
					<span class="tg-icon" aria-hidden="true">
						<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.2 9.2 0 0 1-3.6-.7L3 21l1.3-4A8.3 8.3 0 0 1 3 11.5 8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4Z"/><path d="M8.5 11h7M8.5 14h4"/></svg>
					</span>
					<span>
						<strong><?php esc_html_e( 'Группа в MAX «Сад знаний»', 'sad-znaniy' ); ?></strong>
						<span>Напомним о садовых работах в срок</span>
					</span>
					<span class="circle-go" aria-hidden="true">→</span>
				</a>
			</div>

		</div>
	</section>

	<!-- ================= О ПРОЕКТЕ ================= -->
	<section class="section" id="about">
		<div class="about-panel">
			<div>
				<h2>О проекте</h2>
				<p>«Сад знаний» — полезная база садовода. Мы связываем статьи, калькуляторы и планировщик в единую систему: от выбора растения до сбора урожая.</p>
				<a href="#" class="btn btn-light" style="padding:10px 22px; min-height:42px; font-size:12.5px;">Подробнее</a>
			</div>
			<div class="plan-mini">
				<svg viewBox="0 0 320 200" role="img" aria-label="Схема участка в планировщике">
					<rect width="320" height="200" rx="14" fill="#EFF1F3"/>
					<rect x=".5" y=".5" width="319" height="199" rx="14" fill="none" stroke="#fff" stroke-width="2"/>
					<rect x="205" y="28" width="88" height="58" rx="6" fill="#2E3A31"/>
					<polygon points="200,28 249,6 298,28" fill="#222B25"/>
					<rect x="235" y="50" width="20" height="26" rx="2" fill="#E7F0E9"/>
					<rect x="25" y="32" width="92" height="16" rx="5" fill="#9FB8A5"/>
					<rect x="25" y="58" width="92" height="16" rx="5" fill="#9FB8A5"/>
					<rect x="25" y="84" width="92" height="16" rx="5" fill="#9FB8A5"/>
					<circle cx="160" cy="56" r="15" fill="#7FA689"/><rect x="157" y="68" width="6" height="13" fill="#6E6252"/>
					<circle cx="158" cy="112" r="12" fill="#7FA689" opacity=".85"/><rect x="156" y="121" width="5" height="11" fill="#6E6252"/>
					<rect x="30" y="132" width="70" height="32" rx="6" fill="#fff" stroke="#9FB8A5" stroke-width="2"/>
					<path d="M30 148h70M65 132v32" stroke="#9FB8A5" stroke-width="1.4"/>
					<path d="M122 200 C 132 156, 178 136, 212 98" fill="none" stroke="#D8DDE0" stroke-width="13" stroke-linecap="round"/>
					<text x="248" y="108" font-size="11" fill="#5C645D" font-family="sans-serif">Дом</text>
					<text x="40" y="78" font-size="10" fill="#fff" font-family="sans-serif">Грядки</text>
					<text x="42" y="152" font-size="10" fill="#41544A" font-family="sans-serif">Теплица</text>
				</svg>
				<p style="text-align:center; font-size:13px; margin:9px 0 13px;">Схема участка в планировщике</p>
				<div style="text-align:center;"><a href="#tools" class="btn btn-dark" style="padding:10px 22px; min-height:42px; font-size:12.5px;">Попробовать</a></div>
			</div>
			<div>
				<h2>Контакты</h2>
				<ul class="contact-list">
					<li>
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m2 7 10 7L22 7"/></svg>
						<a href="mailto:info@znai-sad.ru">info@znai-sad.ru</a>
					</li>
					<li>
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.5 2.9.7a2 2 0 0 1 1.6 2Z"/></svg>
						<a href="https://web.max.ru/-76163835891728" target="_blank" rel="noopener"><?php esc_html_e( 'Группа садоводов в MAX', 'sad-znaniy' ); ?></a>
					</li>
					<li>
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
						<span>Работаем по всей России</span>
					</li>
				</ul>
			</div>
		</div>
	</section>

<?php
get_footer();