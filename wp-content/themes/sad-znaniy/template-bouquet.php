<?php
/**
 * Шаблон страницы «Конструктор букета» (Этап 6.5, Партия 2).
 *
 * @package sad-znaniy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$sz_contract = sad_znaniy_bouquet_contract();
$sz_flowers  = sad_znaniy_bouquet_get_flowers();
$sz_base     = array();
foreach ( $sz_flowers as $sz_slug => $sz_flower ) {
    if ( '' === (string) $sz_flower['variant'] ) {
        $sz_base[ $sz_slug ] = $sz_flower;
    }
}
$sz_month    = (int) current_time( 'n' );
$sz_readonly = ! empty( $sz_contract['readonly'] );
?>
<section class="section bk-hero">
    <div class="bk-decor bk-decor--left" aria-hidden="true">
        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/decor/branch-left.svg' ); ?>" alt="" loading="lazy">
    </div>
    <div class="bk-decor bk-decor--right" aria-hidden="true">
        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/decor/branch-right.svg' ); ?>" alt="" loading="lazy">
    </div>
    <div class="bk-hero__inner">
        <p class="bk-kicker">Язык цветов</p>
        <h1 class="h-cap">Конструктор букета</h1>
        <p class="hero-sub">Соберите букет, который говорит за вас: по поводу, адресату и значению цветов.</p>
        <p class="bk-hero__text">Каждый цветок в европейской и русской традиции имеет значение: роза говорит о любви, гербера — о дружбе, хризантема — об уважении и памяти. Конструктор помогает не угадывать, а выбирать осознанно: он учитывает повод, кому предназначен букет и сочетаемость растений.</p>
        <p class="bk-hero__text">Подбор идёт по матрице «повод × кому», конфликты проверяются по флористике и этикету, а число стеблей — по правилу нечётности. В конце вы получите название букета и сможете сохранить композицию.</p>
        <div class="bk-hero__meta">
            <span><?php echo esc_html( (string) count( $sz_base ) ); ?> цветов и <?php echo esc_html( (string) ( count( $sz_flowers ) - count( $sz_base ) ) ); ?> оттенков</span>
            <span>3–9 стеблей — норма</span>
            <span>PDF с легендой — в Партии 3</span>
        </div>
    </div>
</section>

<div class="section bk-layout">
    <div class="bk-flow">
        <section class="bk-step" aria-labelledby="bkStepTo">
            <div class="bk-step__head"><span class="bk-step__num">1</span><h2 id="bkStepTo" class="h-sub">Кому</h2></div>
            <div class="bk-chips" id="bkRecipients">
                <?php foreach ( sad_znaniy_bouquet_recipients() as $sz_key => $sz_label ) : ?>
                    <button type="button" class="bk-chip" data-group="to" data-value="<?php echo esc_attr( (string) $sz_key ); ?>" aria-pressed="false"><?php echo esc_html( (string) $sz_label ); ?></button>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bk-step" aria-labelledby="bkStepOccasion">
            <div class="bk-step__head"><span class="bk-step__num">2</span><h2 id="bkStepOccasion" class="h-sub">Повод</h2></div>
            <div class="bk-chips" id="bkOccasions">
                <?php foreach ( sad_znaniy_bouquet_occasions() as $sz_key => $sz_label ) : ?>
                    <button type="button" class="bk-chip" data-group="occasion" data-value="<?php echo esc_attr( (string) $sz_key ); ?>" aria-pressed="false"><?php echo esc_html( (string) $sz_label ); ?></button>
                <?php endforeach; ?>
            </div>
        </section>
        <div class="ad-slot" aria-hidden="true">Рекламный блок РСЯ — 970×90</div>

        <section class="bk-step bk-step--flowers" aria-labelledby="bkStepFlowers">
            <div class="bk-step__head"><span class="bk-step__num">3</span><h2 id="bkStepFlowers" class="h-sub">Цветы</h2></div>
            <div class="bk-filter" id="bkFilter" aria-label="Фильтр по значению">
                <button type="button" class="bk-filter__chip is-active" data-filter="all" aria-pressed="true">Все</button>
                <?php foreach ( sad_znaniy_bouquet_categories() as $sz_key => $sz_label ) : ?>
                    <?php if ( 'filler' === $sz_key ) { continue; } ?>
                    <button type="button" class="bk-filter__chip" data-filter="<?php echo esc_attr( (string) $sz_key ); ?>" aria-pressed="false"><?php echo esc_html( (string) $sz_label ); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="bk-recommended" id="bkRecommendedWrap" hidden>
                <div class="bk-subhead"><h3>Рекомендуемые</h3><span class="bk-subhead__note">по матрице «повод × кому»</span></div>
                <div class="bk-grid" id="bkRecommended"></div>
            </div>

            <div class="bk-all">
                <div class="bk-subhead"><h3>Все цветы</h3><span class="bk-subhead__note">нажмите на кружок, чтобы узнать значение</span></div>
                <div class="bk-grid" id="bkAll">
                    <?php foreach ( $sz_base as $sz_flower ) { sad_znaniy_bouquet_render_flower_card( $sz_flower, $sz_month ); } ?>
                </div>
            </div>
        </section>

        <section class="bk-know" aria-labelledby="bkKnow">
            <h2 class="h-sub" id="bkKnow">Полезно знать</h2>
            <div class="also-row">
                <a class="also-link" href="<?php echo esc_url( home_url( '/stati/' ) ); ?>">Статьи о цветах</a>
                <a class="also-link" href="<?php echo esc_url( home_url( '/uhod/' ) ); ?>">Уход за растениями</a>
                <a class="also-link" href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>">Календарь дачника</a>
                <a class="also-link" href="<?php echo esc_url( home_url( '/lunnyj-kalendar/' ) ); ?>">Лунный календарь</a>
            </div>
        </section>

        <div class="ad-slot" aria-hidden="true">Рекламный блок РСЯ — 970×90</div>
    </div>

    <aside class="bk-side" aria-label="Композиция букета">
        <button type="button" class="bk-mobile-toggle" id="bkMobileToggle" aria-expanded="false" aria-controls="bkComp">Собрать букет</button>
        <div class="bk-comp" id="bkComp">
            <div class="bk-comp__head">
                <h2 class="h-sub">Ваш букет</h2>
                <span class="bk-mode" id="bkModeBadge" hidden>Просмотр</span>
            </div>

            <div class="bk-arc" id="bkArc" aria-live="polite"></div>
            <p class="bk-empty" id="bkEmpty">Выберите цветы — начнём с трёх.</p>

            <div class="bk-count" id="bkCount"></div>
            <div class="bk-hint" id="bkHint" role="status"></div>
            <div class="bk-conflicts" id="bkConflicts"></div>
            <div class="bk-solo" id="bkSolo" hidden></div>

            <label class="bk-label" for="bkTitle">Название букета</label>
            <div class="bk-title-row">
                <input type="text" id="bkTitle" class="bk-title" maxlength="120" placeholder="Название появится само" autocomplete="off">
                <button type="button" class="bk-icon-btn" id="bkReroll" title="Другой вариант названия" aria-label="Другой вариант названия">↻</button>
            </div>
            <p class="bk-title-note" id="bkTitleNote">Клик по ↻ — другой вариант. Правка вручную отключает автогенерацию.</p>

            <div class="bk-actions">
                <button type="button" class="btn" id="bkSave">Сохранить</button>
                <button type="button" class="btn btn-light" id="bkShare" disabled title="Появится в Партии 3">Поделиться</button>
                <button type="button" class="btn btn-light" id="bkPdf" disabled title="Появится в Партии 3">PDF</button>
            </div>
            <div class="bk-status" id="bkStatus" role="status"></div>
        </div>
    </aside>
</div>
<div class="bk-card" id="bkCard" role="dialog" aria-modal="false" aria-labelledby="bkCardTitle" hidden>
    <button type="button" class="bk-card__close" id="bkCardClose" aria-label="Закрыть">×</button>
    <div class="bk-card__head">
        <div class="bk-card__media" id="bkCardMedia"></div>
        <div class="bk-card__intro">
            <p class="bk-card__meaning" id="bkCardMeaning"></p>
            <h2 class="bk-card__title" id="bkCardTitle"></h2>
            <p class="bk-card__badges" id="bkCardBadges"></p>
        </div>
    </div>
    <p class="bk-card__desc" id="bkCardDesc"></p>
    <div class="bk-card__variants" id="bkCardVariants" hidden></div>
    <div class="bk-card__actions">
        <button type="button" class="btn" id="bkCardAdd">Добавить в букет</button>
        <a class="btn btn-light" id="bkCardPlant" href="#" hidden>Подробнее о цветке →</a>
    </div>
</div>

<?php
get_footer();