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
<div class="bk-bg" aria-hidden="true"></div>
<section class="section bk-hero">
    <div class="bk-hero__inner">
        <p class="bk-kicker">Язык цветов</p>
        <h1 class="h-cap">Конструктор букета</h1>
        <p class="hero-sub">Соберите букет, который говорит за вас: по поводу, адресату и значению цветов.</p>
        <div class="bk-hero__meta">
            <span><?php echo esc_html( (string) count( $sz_base ) ); ?> цветов и <?php echo esc_html( (string) ( count( $sz_flowers ) - count( $sz_base ) ) ); ?> оттенков</span>
            <span>3–9 стеблей — норма</span>
            <span>PDF с легендой и QR</span>
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
                <button type="button" class="btn btn-light" id="bkShare" disabled title="Сохраните букет после входа">Поделиться</button>
                <button type="button" class="btn btn-light" id="bkPdf">PDF</button>
                <button type="button" class="btn btn-light" id="bkMail" disabled title="Сохраните букет после входа">✉ Отправить</button>
                <button type="button" class="btn btn-light" id="bkCopy" hidden>Создать копию себе</button>
            </div>
            <div class="bk-status" id="bkStatus" role="status"></div>
        </div>
    </aside>
</div>

<section class="section bk-seo" aria-labelledby="bkSeoTitle">
    <h2 class="bk-seo__title" id="bkSeoTitle">Язык цветов: как собрать букет, который говорит за вас</h2>
    <p class="bk-seo__lead">Каждый цветок в европейской и русской традиции имеет значение: роза говорит о любви, гербера — о дружбе, хризантема — об уважении и памяти. Конструктор помогает не угадывать, а выбирать осознанно: он учитывает повод, кому предназначен букет и сочетаемость растений.</p>
    <p class="bk-seo__lead">Подбор идёт по матрице «повод × кому», конфликты проверяются по флористике и этикету, а число стеблей — по правилу нечётности. В конце вы получите название букета и сможете сохранить композицию.</p>
    <p>Конструктор не продаёт цветы — он помогает собрать композицию и объяснить её смысл. Готовый букет можно сохранить в кабинете, открыть по ссылке, отправить на e-mail или скачать PDF с легендой и QR-кодом.</p>

    <h3>Что означает цветок: шпаргалка по значениям</h3>
    <p>Ниже — двенадцать базовых цветов, на которых построен подбор. Полное описание с оттенками и сезонностью — на странице каждого цветка.</p>
    <ul class="bk-seo__meanings">
        <?php $sz_seo_i = 0; ?>
        <?php foreach ( $sz_base as $sz_flower ) : ?>
            <?php if ( $sz_seo_i >= 12 ) { break; } ?>
            <?php $sz_seo_i++; ?>
            <li>
                <?php if ( ! empty( $sz_flower['url'] ) ) : ?>
                    <a href="<?php echo esc_url( (string) $sz_flower['url'] ); ?>"><?php echo esc_html( (string) $sz_flower['title'] ); ?></a>
                <?php else : ?>
                    <strong><?php echo esc_html( (string) $sz_flower['title'] ); ?></strong>
                <?php endif; ?>
                — <?php echo esc_html( (string) $sz_flower['meaning'] ); ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Три правила, которые соблюдает конструктор</h3>
    <ol class="bk-seo__rules">
        <li><strong>Нечётное число стеблей.</strong> Букет из 3–9 стеблей выглядит живым; чётное число традиционно связывают с ритуальными букетами, поэтому конструктор подсказывает добавить третий цветок. Минимум — один стебель, максимум — 25.</li>
        <li><strong>Сочетаемость.</strong> Проверяются флористические конфликты: ландыш не стоит в одном букете с розами, тюльпанами и нарциссами — он быстро вянет и токсичен для соседей; гвоздика спорит с романтическими поводами и 8 Марта; белые лилии и хризантемы несут траурный подтекст и не подходят для свадьбы и признания.</li>
        <li><strong>Уместность повода и адресата.</strong> Маме, учителю, коллеге и любимой подходят разные цветы — матрица «повод × кому» подсказывает уместные варианты и выносит их в «Рекомендуемые».</li>
    </ol>

    <h3>Букет по поводу и адресату</h3>
    <p>Если не хочется начинать с чистого листа, откройте готовый сценарий — повод и адресат подставятся в конструктор автоматически:</p>
    <ul class="bk-seo__links">
        <li><a href="<?php echo esc_url( add_query_arg( 'to', 'mama', sad_znaniy_bouquet_page_url() ) ); ?>">какие цветы подарить маме</a></li>
        <li><a href="<?php echo esc_url( add_query_arg( 'occasion', 'wedding', sad_znaniy_bouquet_page_url() ) ); ?>">букет на свадьбу или годовщину</a></li>
        <li><a href="<?php echo esc_url( add_query_arg( array( 'to' => 'teacher', 'occasion' => 'teacher_day' ), sad_znaniy_bouquet_page_url() ) ); ?>">цветы учителю на 1 сентября и День учителя</a></li>
        <li><a href="<?php echo esc_url( add_query_arg( array( 'to' => 'beloved', 'occasion' => 'march8' ), sad_znaniy_bouquet_page_url() ) ); ?>">букет любимой на 8 Марта</a></li>
        <li><a href="<?php echo esc_url( add_query_arg( array( 'to' => 'colleague', 'occasion' => 'birthday' ), sad_znaniy_bouquet_page_url() ) ); ?>">цветы коллеге на день рождения</a></li>
        <li><a href="<?php echo esc_url( add_query_arg( 'occasion', 'housewarming', sad_znaniy_bouquet_page_url() ) ); ?>">композиция на новоселье</a></li>
    </ul>

    <h3>Как сохранить букет и скачать PDF</h3>
    <p>Сборка бесплатна и не требует регистрации: вход нужен только для сохранения. Букет появится в личном кабинете, получит ссылку для просмотра и PDF на две страницы A4 — композиция с легендой значений, QR-код на интерактивную версию и памятка по уходу: как подрезать стебли, менять воду и держать букет в прохладе.</p>

    <h3>Частые вопросы</h3>
    <dl class="bk-seo__faq">
        <dt>Нужна ли регистрация, чтобы собрать букет?</dt>
        <dd>Нет. Собирать и экспериментировать можно без входа; регистрация нужна для сохранения букета, ссылки для просмотра и PDF.</dd>
        <dt>Сколько цветов можно добавить?</dt>
        <dd>От одного до 25 стеблей. Норма — 3–9, а число стеблей лучше делать нечётным.</dd>
        <dt>Что делать, если появился конфликт?</dt>
        <dd>Нажмите «Убрать конфликтный цветок» — конструктор предложит замену с близким значением.</dd>
        <dt>Можно ли начать с конкретного цветка?</dt>
        <dd>Да. На странице цветка нажмите «Собрать букет» — он откроется в конструкторе с уже добавленным цветком.</dd>
    </dl>

    <h3>Куда идти дальше</h3>
    <ul class="bk-seo__links">
        <li><a href="<?php echo esc_url( home_url( '/rasteniya/' ) ); ?>">база растений с агротехникой</a></li>
        <li><a href="<?php echo esc_url( home_url( '/stati/' ) ); ?>">статьи о цветах и уходе</a></li>
        <li><a href="<?php echo esc_url( home_url( '/kalendar/' ) ); ?>">календарь работ дачника</a></li>
        <li><a href="<?php echo esc_url( home_url( '/lunnyj-kalendar/' ) ); ?>">лунный календарь садовода</a></li>
        <li><a href="<?php echo esc_url( home_url( '/planirovshchik/' ) ); ?>">планировщик участка</a></li>
    </ul>
</section>

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

<div class="bk-gate" id="bkGate" hidden>
    <div class="bk-gate__back" data-gate-close></div>
    <div class="bk-gate__card" role="dialog" aria-modal="true" aria-labelledby="bkGateTitle">
        <button type="button" class="bk-gate__x" data-gate-close aria-label="Закрыть">✕</button>
        <h2 id="bkGateTitle">Сохраните букет и скачайте PDF</h2>
        <p class="bk-gate__text">Войдите или создайте бесплатный аккаунт — букет сохранится в кабинете, а PDF будет с легендой, QR и уходом за цветами. Собрать букет можно и без входа.</p>
        <?php if ( ! is_user_logged_in() ) : ?>
            <?php
            wp_login_form(
                array(
                    'redirect'       => esc_url( add_query_arg( 'print', '1', get_permalink() ) ),
                    'label_username' => __( 'Логин или e-mail', 'sad-znaniy' ),
                    'label_password' => __( 'Пароль', 'sad-znaniy' ),
                    'label_remember' => __( 'Запомнить меня', 'sad-znaniy' ),
                    'label_log_in'   => __( 'Войти и скачать PDF', 'sad-znaniy' ),
                    'remember'       => true,
                )
            );
            ?>
        <?php endif; ?>
        <?php if ( get_option( 'users_can_register' ) ) : ?>
            <p class="bk-gate__reg">Нет аккаунта? <a href="<?php echo esc_url( add_query_arg( 'redirect_to', add_query_arg( 'print', '1', get_permalink() ), wp_registration_url() ) ); ?>">Создать аккаунт</a></p>
        <?php else : ?>
            <p class="bk-gate__reg bk-gate__note">Регистрация новых пользователей на сайте пока выключена. Владелец включает её в «Настройки → Общие → Членство: регистрация разрешена».</p>
        <?php endif; ?>
    </div>
</div>
<div class="bk-print" id="bkPrint" aria-hidden="true">
    <section class="bk-print__page">
        <header class="bk-print__head">
            <div class="bk-print__brand">
                <svg class="bk-print__logo" viewBox="0 0 44.17 51.39" aria-hidden="true"><use href="#logo-sad"/></svg>
                <span class="bk-print__brand-txt"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> · znai-sad.ru</span>
            </div>
            <div class="bk-print__user" id="bkPrintUser"></div>
        </header>
        <h2 class="bk-print__title" id="bkPrintTitle"></h2>
        <p class="bk-print__sub" id="bkPrintSub"></p>
        <div class="bk-print__composition">
            <img class="bk-print__img" id="bkPrintImg" alt="Композиция букета">
        </div>
        <div class="bk-print__legend" id="bkPrintLegend"></div>
        <div class="bk-print__foot">
            <div class="bk-print__qr-wrap">
                <img class="bk-print__qr" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/qr-site.svg' ); ?>" alt="QR-код: znai-sad.ru" width="300" height="300">
                <span>Наведите камеру — откроется сайт</span>
            </div>
            <div class="bk-print__ad">Рекламный блок · место под баннер</div>
        </div>
        <div class="bk-print__decor bk-print__decor--left" aria-hidden="true"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/decor/branch-left.svg' ); ?>" alt=""></div>
        <div class="bk-print__decor bk-print__decor--right" aria-hidden="true"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/decor/branch-right.svg' ); ?>" alt=""></div>
    </section>
    <section class="bk-print__page">
        <h2 class="bk-print__h2">Значения цветов</h2>
        <div class="bk-print__flowers" id="bkPrintFlowers"></div>
        <h2 class="bk-print__h2">Уход и полезное</h2>
        <ul class="bk-print__links" id="bkPrintLinks"></ul>
        <div class="bk-print__tools">
            <strong>Больше инструментов — на znai-sad.ru</strong>
            <span>База растений · Календарь дачника · Лунный календарь · Калькуляторы</span>
        </div>
        <footer class="bk-print__footer">Собрано в конструкторе букета «Сад знаний» · znai-sad.ru · интерактивная версия — на сайте</footer>
    </section>
</div>
<?php
get_footer();