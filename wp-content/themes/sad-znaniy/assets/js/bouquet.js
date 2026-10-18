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
        side: document.querySelector('.bk-side'),
        gate: $('bkGate'),
        mail: $('bkMail'),
        copy: $('bkCopy'),
        printDoc: $('bkPrint'),
        printUser: $('bkPrintUser'),
        printTitle: $('bkPrintTitle'),
        printSub: $('bkPrintSub'),
        printImg: $('bkPrintImg'),
        printLegend: $('bkPrintLegend'),
        printFlowers: $('bkPrintFlowers'),
        printLinks: $('bkPrintLinks')
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

    function updateMobileToggle() {
        if (!els.mobileToggle) return;
        if (els.side && els.side.classList.contains('is-open')) {
            els.mobileToggle.textContent = 'Свернуть';
            return;
        }
        var total = totalStems();
        els.mobileToggle.textContent = 0 < total
            ? ('Ваш букет · ' + total + ' ' + plural(total, 'стебель', 'стебля', 'стеблей'))
            : 'Ваш букет · начнём с трёх';
    }

    function syncAdded() {
        var cards = document.querySelectorAll('.bk-flower');
        for (var i = 0; i < cards.length; i++) {
            cards[i].classList.toggle('is-added', !!state.items[cards[i].getAttribute('data-slug')]);
        }
        updateMobileToggle();
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
                updateActionState();
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
        updateActionState();
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

    function openGate() {
        if (!els.gate) return;
        els.gate.hidden = false;
        document.body.classList.add('bk-gate-open');
        var close = els.gate.querySelector('.bk-gate__x');
        if (close) close.focus();
        document.addEventListener('keydown', gateKey);
    }

    function closeGate() {
        if (!els.gate) return;
        els.gate.hidden = true;
        document.body.classList.remove('bk-gate-open');
        document.removeEventListener('keydown', gateKey);
    }

    function gateKey(event) {
        if (event.key === 'Escape') closeGate();
    }

    function printAction() {
        if (!data.loggedIn && data.gate && data.gate.required) {
            openGate();
            return;
        }
        doPrint();
    }

    function renderPrintDoc() {
        var title = (els.title && els.title.value.trim()) || state.title || 'Мой букет';
        var stems = totalStems();
        var occasion = state.occasion && data.occasions[state.occasion] ? data.occasions[state.occasion] : 'без повода';
        var recipient = state.to && data.recipients[state.to] ? data.recipients[state.to] : 'без адресата';
        var date = new Date().toLocaleDateString('ru');
        var slugs = Object.keys(state.items);

        if (els.printTitle) els.printTitle.textContent = title;
        if (els.printSub) els.printSub.textContent = occasion + ' · ' + recipient + ' · ' + stems + ' ' + plural(stems, 'стебель', 'стебля', 'стеблей') + ' · ' + date;

        if (els.printUser) {
            els.printUser.textContent = '';
            var line1 = document.createElement('div');
            var line2 = document.createElement('div');
            line1.textContent = data.loggedIn && data.userName ? ('Букет подготовил: ' + data.userName) : 'Создано в конструкторе «Сад знаний»';
            line2.textContent = date;
            els.printUser.appendChild(line1);
            els.printUser.appendChild(line2);
        }

        if (els.printLegend) {
            els.printLegend.textContent = '';
            if (!slugs.length) {
                var empty = document.createElement('span');
                empty.textContent = 'Букет пока не собран.';
                els.printLegend.appendChild(empty);
            }
            slugs.slice(0, 10).forEach(function (slug) {
                var flower = flowers[slug];
                if (!flower) return;
                var span = document.createElement('span');
                var initial = document.createElement('i');
                initial.textContent = flower.initial || '';
                span.appendChild(initial);
                span.appendChild(document.createTextNode(flower.title + ' — ' + flower.meaning + (1 < state.items[slug] ? ' ×' + state.items[slug] : '')));
                els.printLegend.appendChild(span);
            });
        }

        if (els.printFlowers) {
            els.printFlowers.textContent = '';
            slugs.slice(0, 12).forEach(function (slug) {
                var flower = flowers[slug];
                if (!flower) return;
                var box = document.createElement('div');
                box.className = 'bk-print__flower';
                var head = document.createElement('div');
                var name = document.createElement('b');
                name.textContent = flower.title;
                var meaning = document.createElement('em');
                meaning.textContent = ' · ' + flower.meaning;
                head.appendChild(name);
                head.appendChild(meaning);
                box.appendChild(head);
                var desc = document.createElement('p');
                desc.textContent = flower.desc || '';
                box.appendChild(desc);
                if (flower.plant_url) {
                    var link = document.createElement('a');
                    link.href = flower.plant_url;
                    link.textContent = 'Уход за ' + flower.title.toLowerCase() + ' →';
                    box.appendChild(link);
                }
                els.printFlowers.appendChild(box);
            });
            if (!slugs.length) {
                var note = document.createElement('p');
                note.textContent = 'Добавьте цветы на странице конструктора — и здесь появятся их значения.';
                els.printFlowers.appendChild(note);
            }
        }

        if (els.printLinks) {
            els.printLinks.textContent = '';
            var links = [
                ['Статьи о цветах', '/stati/'],
                ['Уход за растениями', '/uhod/'],
                ['Календарь дачника', '/kalendar/'],
                ['Лунный календарь', '/lunnyj-kalendar/']
            ];
            links.forEach(function (item) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = window.location.origin + item[1];
                a.textContent = item[0];
                li.appendChild(a);
                els.printLinks.appendChild(li);
            });
        }
    }

    function loadImage(src) {
        return new Promise(function (resolve) {
            if (!src) {
                resolve(null);
                return;
            }
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = src;
        });
    }

    function renderCompositionCanvas() {
        var width = 1200;
        var height = 520;
        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        var slugs = Object.keys(state.items);
        if (!slugs.length) {
            ctx.fillStyle = '#5C645D';
            ctx.font = '600 28px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Букет пока не собран', width / 2, height / 2);
            return Promise.resolve(canvas);
        }

        return Promise.all(slugs.map(function (slug) {
            return loadImage(flowers[slug] ? flowers[slug].photo : '');
        })).then(function (images) {
            var n = slugs.length;
            var maxSpread = 900;
            var step = 1 < n ? maxSpread / (n - 1) : 0;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            slugs.forEach(function (slug, index) {
                var flower = flowers[slug];
                var radius = n === 1 ? 100 : 64;
                var x = n === 1 ? width / 2 : width / 2 - maxSpread / 2 + index * step;
                var y = height / 2 + Math.abs(x - width / 2) * 0.12;
                var image = images[index];
                var count = state.items[slug];

                ctx.save();
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.closePath();
                ctx.clip();
                if (image) {
                    var size = Math.min(image.width, image.height);
                    var sx = (image.width - size) / 2;
                    var sy = (image.height - size) / 2;
                    ctx.drawImage(image, sx, sy, size, size, x - radius, y - radius, radius * 2, radius * 2);
                } else {
                    ctx.fillStyle = '#EDF7EE';
                    ctx.fillRect(x - radius, y - radius, radius * 2, radius * 2);
                    ctx.fillStyle = '#68B96E';
                    ctx.font = '700 ' + Math.round(radius * 0.8) + 'px sans-serif';
                    ctx.fillText(flower ? flower.initial : '', x, y + 2);
                }
                ctx.restore();

                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.lineWidth = 6;
                ctx.strokeStyle = '#ffffff';
                ctx.stroke();
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#68B96E';
                ctx.stroke();

                if (count) {
                    var bx = x + radius * 0.62;
                    var by = y + radius * 0.62;
                    ctx.beginPath();
                    ctx.arc(bx, by, 18, 0, Math.PI * 2);
                    ctx.fillStyle = '#68B96E';
                    ctx.fill();
                    ctx.fillStyle = '#ffffff';
                    ctx.font = '700 16px sans-serif';
                    ctx.fillText(String(count), bx, by + 1);
                }
            });

            return canvas;
        });
    }

    function doPrint() {
        closeGate();
        renderPrintDoc();
        renderCompositionCanvas().then(function (canvas) {
            if (els.printImg) {
                els.printImg.src = canvas.toDataURL('image/png');
                els.printImg.width = canvas.width;
                els.printImg.height = canvas.height;
            }
            requestAnimationFrame(function () {
                setTimeout(function () { window.print(); }, 120);
            });
        });
    }

    function shareAction() {
        if (!state.savedId) {
            setStatus('Сохраните букет после входа — появится ссылка.', true);
            return;
        }
        var url = data.pageUrl + (data.pageUrl.indexOf('?') === -1 ? '?' : '&') + 'bouquet=' + encodeURIComponent(state.savedId);
        if (navigator.share) {
            navigator.share({
                title: 'Букет — Сад знаний',
                text: 'Мой букет: ' + ((els.title && els.title.value.trim()) || state.title),
                url: url
            }).catch(function () { copyLink(url); });
            return;
        }
        copyLink(url);
    }

    function copyLink(url) {
        var done = function () { setStatus('Ссылка скопирована: ' + url); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Скопируйте ссылку:', url); });
        } else {
            window.prompt('Скопируйте ссылку:', url);
        }
    }

    function mailAction() {
        if (!data.loggedIn) {
            setStatus('Войдите, чтобы отправить письмо.', true);
            return;
        }
        if (!state.savedId) {
            setStatus('Сначала сохраните букет.', true);
            return;
        }
        var to = window.prompt('E-mail получателя', '');
        if (!to || !to.trim()) return;
        to = to.trim();
        els.mail.disabled = true;
        fetch(data.mailUrl + '?id=' + encodeURIComponent(state.savedId), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': data.nonce || ''
            },
            body: JSON.stringify({ to: to })
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            })
            .then(function (result) {
                els.mail.disabled = false;
                if (!result.ok) {
                    var msg = result.body && result.body.message ? result.body.message : 'Письмо не ушло.';
                    setStatus(msg, true);
                    return;
                }
                setStatus('Письмо отправлено на ' + (result.body.sent_to || to) + '.');
            })
            .catch(function () {
                els.mail.disabled = false;
                setStatus('Письмо не ушло: проверьте соединение.', true);
            });
    }

    function updateActionState() {
        if (!state.readonly) {
            if (els.save) els.save.hidden = false;
            if (els.share) {
                els.share.hidden = false;
                els.share.disabled = !state.savedId;
            }
            if (els.mail) {
                els.mail.hidden = false;
                els.mail.disabled = !state.savedId;
            }
            if (els.copy) els.copy.hidden = true;
        } else {
            if (els.save) els.save.hidden = true;
            if (els.share) els.share.hidden = true;
            if (els.mail) els.mail.hidden = true;
            if (els.copy) els.copy.hidden = false;
        }
        if (els.pdf) els.pdf.disabled = false;
    }

    function applyEditable() {
        state.readonly = false;
        if (els.title) els.title.readOnly = false;
        if (els.reroll) els.reroll.disabled = false;
        if (els.cardAdd) els.cardAdd.disabled = false;
        updateActionState();
    }

    function copyAction() {
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

        if (!data.loggedIn) {
            try {
                window.localStorage.setItem('sz_bouquet_v1', JSON.stringify(payload));
            } catch (error) {}
            state.savedId = '';
            applyEditable();
            window.history.replaceState({}, '', window.location.pathname);
            setStatus('Копия создана в браузере.');
            return;
        }

        if (!data.saveUrl) {
            setStatus('Сохранение недоступно.', true);
            return;
        }
        if (els.copy) els.copy.disabled = true;
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
                if (els.copy) els.copy.disabled = false;
                if (!result.ok) {
                    var msg = result.body && result.body.message ? result.body.message : 'Не удалось создать копию.';
                    setStatus(msg, true);
                    return;
                }
                state.savedId = result.body && result.body.bouquet ? result.body.bouquet.id : '';
                applyEditable();
                if (state.savedId) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('bouquet', state.savedId);
                    window.history.replaceState({}, '', url.toString());
                }
                updateActionState();
                setStatus('Копия сохранена в кабинете.');
            })
            .catch(function () {
                if (els.copy) els.copy.disabled = false;
                setStatus('Не удалось создать копию.', true);
            });
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
                updateMobileToggle();
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

        Array.prototype.forEach.call(document.querySelectorAll('[data-gate-close]'), function (el) {
            el.addEventListener('click', closeGate);
        });
        if (els.pdf) els.pdf.addEventListener('click', printAction);
        if (els.share) els.share.addEventListener('click', shareAction);
        if (els.mail) els.mail.addEventListener('click', mailAction);
        if (els.copy) els.copy.addEventListener('click', copyAction);

        renderComposition();
        renderOddness(null);
        updateMobileToggle();

        var params = new URLSearchParams(window.location.search);
        var hasQuery = false;
        var toParam = params.get('to');
        if (toParam && data.recipients[toParam]) {
            setChip('to', toParam);
            hasQuery = true;
        }
        var occasionParam = params.get('occasion');
        if (occasionParam && data.occasions[occasionParam]) {
            setChip('occasion', occasionParam);
            hasQuery = true;
        }

        if (data.saved) {
            applySaved(data.saved, data.readonly);
            if (data.readonly) setStatus('Просмотр букета по ссылке.');
        } else if (!restoreLocal()) {
            if (window.location.search.indexOf('bouquet=') !== -1) {
                setStatus('Букет не найден или ссылка устарела.', true);
            }
        }

        if (!data.saved && hasQuery) {
            scheduleRefresh(true);
        }

        var flowerParam = params.get('flower');
        if (!data.saved && flowerParam && flowers[flowerParam] && !state.readonly) {
            add(flowerParam);
            var flowerCard = document.querySelector('.bk-flower[data-slug="' + flowerParam + '"]');
            if (flowerCard) {
                if (flowerCard.scrollIntoView) flowerCard.scrollIntoView({ block: 'center' });
                openCard(flowerParam, flowerCard);
            }
        }

        applyFilter();
        updateActionState();

        if (/[?&]print=1/.test(window.location.search) && (data.loggedIn || !data.gate || !data.gate.required)) {
            setTimeout(printAction, 400);
        }
    }

    init();
    document.documentElement.classList.add('bk-js-ready');
})();