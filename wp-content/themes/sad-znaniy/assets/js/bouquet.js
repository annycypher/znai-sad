/**
 * Конструктор букета — клиентская часть (Этап 6.5, Партия 2).
 *
 * Подбор, конфликты и названия считает сервер (includes/bouquet.php),
 * клиент отправляет состояние в REST и рисует ответ. Правила не дублируются.
 */
(function () {
    'use strict';

    var data = window.sadZnaniyBouquet;
    if (!data || !data.flowers) {
        return;
    }

    var flowers = {};
    data.flowers.forEach(function (flower) {
        flowers[flower.slug] = flower;
    });

    var state = {
        to: '',
        occasion: '',
        items: {},
        title: '',
        generated: true,
        reroll: 0,
        readonly: !!data.readonly,
        savedId: '',
        filter: 'all',
        recommended: []
    };

    var fetchTimer = null;
    var fetchController = null;
    var activeAnchor = null;

    function $(id) {
        return document.getElementById(id);
    }

    var els = {
        recipients: $('bkRecipients'),
        occasions: $('bkOccasions'),
        filter: $('bkFilter'),
        recommendedWrap: $('bkRecommendedWrap'),
        recommended: $('bkRecommended'),
        all: $('bkAll'),
        arc: $('bkArc'),
        empty: $('bkEmpty'),
        count: $('bkCount'),
        hint: $('bkHint'),
        conflicts: $('bkConflicts'),
        solo: $('bkSolo'),
        title: $('bkTitle'),
        titleNote: $('bkTitleNote'),
        reroll: $('bkReroll'),
        save: $('bkSave'),
        share: $('bkShare'),
        pdf: $('bkPdf'),
        status: $('bkStatus'),
        mode: $('bkModeBadge'),
        card: $('bkCard'),
        cardClose: $('bkCardClose'),
        cardMedia: $('bkCardMedia'),
        cardMeaning: $('bkCardMeaning'),
        cardTitle: $('bkCardTitle'),
        cardBadges: $('bkCardBadges'),
        cardDesc: $('bkCardDesc'),
        cardVariants: $('bkCardVariants'),
        cardAdd: $('bkCardAdd'),
        cardPlant: $('bkCardPlant'),
        mobileToggle: $('bkMobileToggle'),
        side: document.querySelector('.bk-side')
    };

    function totalStems() {
        var total = 0;
        Object.keys(state.items).forEach(function (slug) {
            total += state.items[slug];
        });
        return total;
    }

    function setStatus(text, isError) {
        if (!els.status) return;
        els.status.textContent = text || '';
        if (isError) {
            els.status.classList.add('is-error');
        } else {
            els.status.classList.remove('is-error');
        }
    }

    function setChip(group, value) {
        var wrap = group === 'to' ? els.recipients : els.occasions;
        if (!wrap) return;
        var chips = wrap.querySelectorAll('.bk-chip');
        for (var i = 0; i < chips.length; i++) {
            var on = chips[i].getAttribute('data-value') === value;
            chips[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        state[group] = value || '';
    }

    function plural(n, one, few, many) {
        var mod10 = n % 10;
        var mod100 = n % 100;
        if (mod10 === 1 && mod100 !== 11) return one;
        if (2 <= mod10 && 4 >= mod10 && (12 > mod100 || 14 < mod100)) return few;
        return many;
    }

    function setMedia(container, flower, letterClass) {
        if (!container) return;
        container.textContent = '';
        if (flower.photo) {
            var img = document.createElement('img');
            img.src = flower.photo;
            img.alt = '';
            img.width = 88;
            img.height = 88;
            img.loading = 'lazy';
            container.appendChild(img);
            return;
        }
        var span = document.createElement('span');
        span.className = letterClass || 'bk-flower__letter';
        span.setAttribute('aria-hidden', 'true');
        span.textContent = flower.initial || '';
        container.appendChild(span);
    }

    function addBadge(container, text) {
        if (!container || !text) return;
        var span = document.createElement('span');
        span.className = 'bk-badge';
        span.textContent = text;
        container.appendChild(span);
    }

    function isMobile() {
        return 860 >= window.innerWidth;
    }
    function positionCard(anchor) {
        if (isMobile() || !anchor || !els.card) {
            els.card.style.left = '';
            els.card.style.top = '';
            return;
        }
        var rect = anchor.getBoundingClientRect();
        var cardRect = els.card.getBoundingClientRect();
        var gap = 12;
        var left = rect.right + gap;
        var top = rect.top;
        if (window.innerWidth - 12 < left + cardRect.width) {
            left = rect.left - cardRect.width - gap;
        }
        if (12 > left) left = 12;
        if (window.innerHeight - 12 < top + cardRect.height) {
            top = window.innerHeight - cardRect.height - 12;
        }
        if (12 > top) top = 12;
        els.card.style.left = left + 'px';
        els.card.style.top = top + 'px';
    }

    function onCardKey(event) {
        if (event.key === 'Escape') closeCard();
    }

    function onOutsideClick(event) {
        if (!els.card || !event.target.closest) return;
        if (els.card.contains(event.target)) return;
        if (event.target.closest('.bk-flower')) return;
        closeCard();
    }

    function closeCard() {
        if (!els.card) return;
        els.card.hidden = true;
        document.removeEventListener('keydown', onCardKey);
        document.removeEventListener('click', onOutsideClick);
        if (activeAnchor && typeof activeAnchor.focus === 'function') {
            activeAnchor.focus();
        }
        activeAnchor = null;
    }

    function openCard(slug, anchor) {
        var flower = flowers[slug];
        if (!flower || !els.card) return;

        activeAnchor = anchor || null;
        setMedia(els.cardMedia, flower, 'bk-flower__letter');
        els.cardMeaning.textContent = flower.meaning || '';
        els.cardTitle.textContent = flower.title || '';
        els.cardDesc.textContent = flower.desc || '';

        els.cardBadges.textContent = '';
        if (flower.rare) addBadge(els.cardBadges, '✦ редкий');
        if (flower.season && flower.season.length) {
            var inSeason = flower.season.indexOf(Number(data.currentMonth)) !== -1;
            addBadge(els.cardBadges, inSeason ? 'в сезоне' : 'вне сезона');
        }
        (flower.cats || []).forEach(function (cat) {
            if (data.categories && data.categories[cat]) addBadge(els.cardBadges, data.categories[cat]);
        });

        if (flower.plant_url) {
            els.cardPlant.href = flower.plant_url;
            els.cardPlant.hidden = false;
        } else {
            els.cardPlant.hidden = true;
            els.cardPlant.removeAttribute('href');
        }

        els.cardVariants.textContent = '';
        var variants = data.flowers.filter(function (item) { return item.variant === slug; });
        if (variants.length) {
            variants.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'bk-variant';
                btn.textContent = item.title;
                btn.addEventListener('click', function () { openCard(item.slug, anchor); });
                els.cardVariants.appendChild(btn);
            });
            els.cardVariants.hidden = false;
        } else {
            els.cardVariants.hidden = true;
        }

        els.cardAdd.setAttribute('data-slug', slug);
        els.cardAdd.disabled = state.readonly;
        els.cardAdd.textContent = state.readonly ? 'Просмотр' : 'Добавить в букет';

        els.card.hidden = false;
        positionCard(anchor);
        els.cardClose.focus();
        document.addEventListener('keydown', onCardKey);
        setTimeout(function () { document.addEventListener('click', onOutsideClick); }, 0);
    }

    function initCard() {
        if (els.cardClose) els.cardClose.addEventListener('click', closeCard);
        if (els.cardAdd) {
            els.cardAdd.addEventListener('click', function () {
                var slug = els.cardAdd.getAttribute('data-slug');
                if (slug) {
                    add(slug);
                    closeCard();
                }
            });
        }
        document.addEventListener('click', function (event) {
            if (!event.target.closest) return;
            var card = event.target.closest('.bk-flower');
            if (!card) return;
            openCard(card.getAttribute('data-slug'), card);
        });
    }

    function initChips() {
        [els.recipients, els.occasions].forEach(function (wrap) {
            if (!wrap) return;
            wrap.addEventListener('click', function (event) {
                if (!event.target.closest) return;
                var chip = event.target.closest('.bk-chip');
                if (!chip || !wrap.contains(chip)) return;
                var group = chip.getAttribute('data-group');
                var value = chip.getAttribute('data-value');
                var wasOn = chip.getAttribute('aria-pressed') === 'true';
                setChip(group, wasOn ? '' : value);
                scheduleRefresh(false);
            });
        });
    }

    function initFilter() {
        if (!els.filter) return;
        els.filter.addEventListener('click', function (event) {
            if (!event.target.closest) return;
            var chip = event.target.closest('.bk-filter__chip');
            if (!chip) return;
            var chips = els.filter.querySelectorAll('.bk-filter__chip');
            for (var i = 0; i < chips.length; i++) {
                var on = chips[i] === chip;
                chips[i].classList.toggle('is-active', on);
                chips[i].setAttribute('aria-pressed', on ? 'true' : 'false');
            }
            state.filter = chip.getAttribute('data-filter') || 'all';
            applyFilter();
        });
    }

    function applyFilter() {
        var cards = document.querySelectorAll('.bk-flower');
        for (var i = 0; i < cards.length; i++) {
            var flower = flowers[cards[i].getAttribute('data-slug')];
            var match = state.filter === 'all' || (flower && flower.cats.indexOf(state.filter) !== -1);
            cards[i].hidden = !match;
        }
        if (!els.recommendedWrap || !els.recommended) return;
        var visible = 0;
        var recCards = els.recommended.querySelectorAll('.bk-flower');
        for (var j = 0; j < recCards.length; j++) {
            if (!recCards[j].hidden) visible += 1;
        }
        els.recommendedWrap.hidden = recCards.length === 0 || visible === 0;
    }

    function applyRecommended(list) {
        if (!els.recommended || !els.all) return;
        var moved = els.recommended.querySelectorAll('.bk-flower');
        for (var i = moved.length - 1; 0 <= i; i--) {
            els.all.appendChild(moved[i]);
        }
        list.forEach(function (slug) {
            var card = els.all.querySelector('.bk-flower[data-slug="' + slug + '"]');
            if (card) els.recommended.appendChild(card);
        });
        if (els.recommendedWrap) {
            els.recommendedWrap.hidden = list.length === 0;
        }
        applyFilter();
    }

    function syncAdded() {
        var cards = document.querySelectorAll('.bk-flower');
        for (var i = 0; i < cards.length; i++) {
            cards[i].classList.toggle('is-added', !!state.items[cards[i].getAttribute('data-slug')]);
        }
        if (els.mobileToggle) {
            var total = totalStems();
            if (!(els.side && els.side.classList.contains('is-open'))) {
                els.mobileToggle.textContent = 0 < total ? ('Собрать букет (' + total + ')') : 'Собрать букет';
            }
        }
    }

    function renderComposition() {
        if (!els.arc) return;
        els.arc.textContent = '';
        var slugs = Object.keys(state.items);
        var total = totalStems();
        if (els.empty) els.empty.hidden = 0 < total;
        slugs.forEach(function (slug) {
            var flower = flowers[slug];
            if (!flower) return;
            var stem = document.createElement('button');
            stem.type = 'button';
            stem.className = 'bk-stem' + (total === 1 ? ' is-solo' : '');
            stem.setAttribute('data-slug', slug);
            stem.setAttribute('aria-label', 'Убрать один: ' + flower.title);
            stem.title = 'Убрать один';
            var media = document.createElement('span');
            media.className = 'bk-stem__media';
            setMedia(media, flower, 'bk-stem__letter');
            stem.appendChild(media);
            var badge = document.createElement('span');
            badge.className = 'bk-stem__count';
            badge.textContent = String(state.items[slug]);
            stem.appendChild(badge);
            stem.addEventListener('click', function () { remove(slug); });
            els.arc.appendChild(stem);
        });
        if (els.count) {
            els.count.textContent = '';
            var strong = document.createElement('strong');
            strong.textContent = String(total);
            els.count.appendChild(strong);
            els.count.appendChild(document.createTextNode(' ' + plural(total, 'стебель', 'стебля', 'стеблей')));
        }
        syncAdded();
        renderSolo();
        requestAnimationFrame(positionStems);
    }

    function positionStems() {
        if (!els.arc) return;
        var stems = els.arc.querySelectorAll('.bk-stem');
        var n = stems.length;
        if (!n) return;
        var maxSpread = 240;
        var step = 1 < n ? maxSpread / (n - 1) : 0;
        for (var i = 0; i < n; i++) {
            var x = n === 1 ? 0 : -maxSpread / 2 + i * step;
            var y = Math.abs(x) * 0.08;
            stems[i].style.setProperty('--bk-x', x.toFixed(1) + 'px');
            stems[i].style.setProperty('--bk-y', y.toFixed(1) + 'px');
        }
    }

    function renderSolo() {
        if (!els.solo) return;
        var total = totalStems();
        if (total !== 1) {
            els.solo.hidden = true;
            return;
        }
        var slug = Object.keys(state.items)[0];
        var flower = flowers[slug];
        var text = data.hints && data.hints.solo ? data.hints.solo : '';
        if (flower) text = text.replace('{flower}', flower.title);
        els.solo.textContent = text;
        els.solo.hidden = false;
        if (els.hint) {
            els.hint.className = 'bk-hint is-solo';
            els.hint.textContent = '1 стебель — режим солитёра.';
        }
    }
    function pulseStem(slug) {
        var stem = els.arc ? els.arc.querySelector('.bk-stem[data-slug="' + slug + '"]') : null;
        if (stem) {
            stem.classList.add('is-pulse');
            stem.addEventListener('animationend', function () { stem.classList.remove('is-pulse'); }, { once: true });
        }
        var card = document.querySelector('.bk-flower[data-slug="' + slug + '"]');
        if (card) {
            card.classList.add('is-pulse');
            card.addEventListener('animationend', function () { card.classList.remove('is-pulse'); }, { once: true });
        }
    }

    function add(slug) {
        if (state.readonly) {
            setStatus('Это просмотр чужого букета.', true);
            return;
        }
        var flower = flowers[slug];
        if (!flower) return;
        if (Number(data.limits.stems) <= totalStems()) {
            setStatus('Лимит ' + data.limits.stems + ' стеблей.', true);
            return;
        }
        state.items[slug] = (state.items[slug] || 0) + 1;
        renderComposition();
        pulseStem(slug);
        setStatus('Добавлено: ' + flower.title);
        scheduleRefresh(true);
    }

    function remove(slug) {
        if (state.readonly) return;
        if (!state.items[slug]) return;
        state.items[slug] -= 1;
        if (state.items[slug] <= 0) delete state.items[slug];
        renderComposition();
        scheduleRefresh(true);
    }

    function scheduleRefresh(immediate) {
        if (fetchTimer) clearTimeout(fetchTimer);
        fetchTimer = setTimeout(refresh, immediate ? 60 : 160);
    }

    function refresh() {
        if (!data.recommendUrl) return;
        var params = new URLSearchParams();
        if (state.occasion) params.set('occasion', state.occasion);
        if (state.to) params.set('to', state.to);
        var parts = [];
        Object.keys(state.items).forEach(function (slug) {
            parts.push(slug + ':' + state.items[slug]);
        });
        if (parts.length) params.set('items', parts.join(','));
        params.set('reroll', String(state.reroll));

        if (fetchController) fetchController.abort();
        fetchController = ('AbortController' in window) ? new AbortController() : null;
        var options = { method: 'GET', credentials: 'same-origin' };
        if (fetchController) options.signal = fetchController.signal;
        if (data.loggedIn && data.nonce) options.headers = { 'X-WP-Nonce': data.nonce };

        fetch(data.recommendUrl + '?' + params.toString(), options)
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (payload) {
                state.recommended = Array.isArray(payload.recommended) ? payload.recommended : [];
                if (state.generated && typeof payload.title === 'string') {
                    state.title = payload.title;
                    if (els.title && document.activeElement !== els.title) {
                        els.title.value = payload.title;
                    }
                }
                renderConflicts(payload.conflicts || []);
                renderOddness(payload.oddness || null);
                applyRecommended(state.recommended);
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') return;
                setStatus('Не удалось обновить подбор. Проверьте соединение.', true);
            });
    }

    function renderConflicts(list) {
        if (!els.conflicts) return;
        els.conflicts.textContent = '';
        (list || []).slice(0, 3).forEach(function (conflict) {
            var box = document.createElement('div');
            box.className = 'bk-conflict bk-conflict--' + (conflict.type === 'hard' ? 'hard' : 'soft');
            var text = document.createElement('span');
            text.className = 'bk-conflict__text';
            text.textContent = conflict.text || '';
            box.appendChild(text);

            var slug = null;
            (conflict.slugs || []).some(function (item) {
                if (state.items[item]) {
                    slug = item;
                    return true;
                }
                return false;
            });

            if (slug && !state.readonly) {
                var fix = document.createElement('button');
                fix.type = 'button';
                fix.className = 'bk-conflict__fix';
                fix.textContent = 'Убрать';
                fix.addEventListener('click', function () { remove(slug); });
                box.appendChild(fix);
            }
            els.conflicts.appendChild(box);
        });
    }

    function renderOddness(odd) {
        if (!els.hint) return;
        els.hint.className = 'bk-hint';
        if (!odd) {
            els.hint.classList.add('is-empty');
            els.hint.textContent = data.hints && data.hints.empty ? data.hints.empty : '';
            if (els.solo) els.solo.hidden = true;
            return;
        }
        if (odd.solo) {
            renderSolo();
            return;
        }
        els.hint.classList.add('is-' + (odd.state || 'empty'));
        els.hint.textContent = odd.hint || '';
        if (els.solo) els.solo.hidden = true;
    }
    function setTitleValue() {
        if (!els.title) return;
        if (document.activeElement !== els.title) {
            els.title.value = state.title || '';
        }
    }

    function save() {
        if (state.readonly) {
            setStatus('Это просмотр чужого букета.', true);
            return;
        }
        var payload = {
            version: 1,
            title: els.title ? els.title.value.trim() : state.title,
            generated: state.generated,
            occasion: state.occasion,
            to: state.to,
            solo: totalStems() === 1,
            items: Object.keys(state.items).map(function (slug) {
                return { slug: slug, count: state.items[slug] };
            }),
            reroll: state.reroll
        };
        if (state.savedId) payload.id = state.savedId;

        if (!data.loggedIn) {
            try {
                window.localStorage.setItem('sz_bouquet_v1', JSON.stringify(payload));
                setStatus('Сохранено в браузере. Войдите, чтобы сохранить на сервере и скачать PDF.');
            } catch (error) {
                setStatus('Не удалось сохранить в браузере: хранилище недоступно.', true);
            }
            return;
        }

        if (!data.saveUrl) {
            setStatus('Сохранение недоступно.', true);
            return;
        }

        els.save.disabled = true;
        fetch(data.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': data.nonce || ''
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            })
            .then(function (result) {
                els.save.disabled = false;
                if (!result.ok) {
                    var msg = result.body && result.body.message ? result.body.message : 'Не удалось сохранить букет.';
                    setStatus(msg, true);
                    return;
                }
                state.savedId = result.body && result.body.bouquet ? result.body.bouquet.id : '';
                setStatus('Сохранено на сервере.');
                if (state.savedId) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('bouquet', state.savedId);
                    window.history.replaceState({}, '', url.toString());
                }
            })
            .catch(function () {
                els.save.disabled = false;
                setStatus('Не удалось сохранить: проверьте соединение.', true);
            });
    }

    function applySaved(saved, readonly) {
        if (!saved) return;
        state.readonly = !!readonly;
        state.to = saved.to || '';
        state.occasion = saved.occasion || '';
        state.items = {};
        (saved.items || []).forEach(function (item) {
            if (item && item.slug && flowers[item.slug]) {
                state.items[item.slug] = Math.max(1, parseInt(item.count, 10) || 1);
            }
        });
        state.title = saved.title || '';
        state.generated = saved.generated !== false;
        state.reroll = parseInt(saved.reroll, 10) || 0;
        state.savedId = saved.id || '';
        setChip('to', state.to);
        setChip('occasion', state.occasion);
        setTitleValue();
        if (els.title) els.title.readOnly = state.readonly;
        if (els.reroll) els.reroll.disabled = state.readonly;
        if (els.save) els.save.disabled = state.readonly;
        if (els.mode) els.mode.hidden = !state.readonly;
        if (state.readonly) {
            if (els.cardAdd) els.cardAdd.disabled = true;
            if (els.titleNote) els.titleNote.textContent = 'Просмотр букета. Действия недоступны.';
        }
        renderComposition();
        scheduleRefresh(true);
    }

    function restoreLocal() {
        try {
            var raw = window.localStorage.getItem('sz_bouquet_v1');
            if (!raw) return false;
            var saved = JSON.parse(raw);
            if (!saved || !saved.items) return false;
            applySaved(saved, false);
            setStatus('Восстановлен букет из браузера.');
            return true;
        } catch (error) {
            return false;
        }
    }

    function init() {
        if (!els.all) return;
        initChips();
        initFilter();
        initCard();

        if (els.mobileToggle && els.side) {
            els.mobileToggle.addEventListener('click', function () {
                var open = els.side.classList.toggle('is-open');
                els.mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                var total = totalStems();
                els.mobileToggle.textContent = open ? 'Свернуть' : (0 < total ? ('Собрать букет (' + total + ')') : 'Собрать букет');
            });
        }

        if (els.title) {
            els.title.addEventListener('input', function () {
                state.title = els.title.value;
                state.generated = false;
                if (els.titleNote) els.titleNote.textContent = 'Автогенерация отключена — название сохранится как есть.';
            });
        }

        if (els.reroll) {
            els.reroll.addEventListener('click', function () {
                if (state.readonly) return;
                state.generated = true;
                state.reroll += 1;
                if (els.titleNote) els.titleNote.textContent = 'Автогенерация включена.';
                scheduleRefresh(true);
            });
        }

        if (els.save) els.save.addEventListener('click', save);

        renderComposition();
        renderOddness(null);

        if (data.saved) {
            applySaved(data.saved, data.readonly);
            if (data.readonly) setStatus('Просмотр букета по ссылке.');
        } else if (!restoreLocal()) {
            if (window.location.search.indexOf('bouquet=') !== -1) {
                setStatus('Букет не найден или ссылка устарела.', true);
            }
        }

        applyFilter();
    }

    init();
    document.documentElement.classList.add('bk-js-ready');
})();