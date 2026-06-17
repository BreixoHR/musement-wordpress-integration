(function () {
    function post(action, payload) {
        const data = new FormData();
        data.append('action', action);
        data.append('nonce', MUSEMENT.nonce);

        Object.keys(payload || {}).forEach(function (key) {
            data.append(key, payload[key]);
        });

        return fetch(MUSEMENT.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
        }).then(function (response) {
            return response.json();
        });
    }

    function setStatus(widget, message, isError) {
        const status = widget.querySelector('.musement-widget__status');
        status.textContent = message || '';
        status.hidden = !message;
        status.classList.toggle('is-error', !!isError);
    }

    function renderActivity(activity) {
        const article = document.createElement('article');
        article.className = 'musement-tour';
        article.dataset.uuid = activity.uuid || '';
        article.dataset.title = activity.title || 'Tour';

        article.innerHTML =
            '<div class="musement-tour__media"></div>' +
            '<div class="musement-tour__body">' +
                '<h3 class="musement-tour__title"></h3>' +
                '<p class="musement-tour__description"></p>' +
                '<div class="musement-tour__meta"></div>' +
                '<div class="musement-tour__actions">' +
                    '<button class="musement-tour__button" type="button">Ver Disponibilidad</button>' +
                '</div>' +
                // calendar first
                '<div class="musement-tour__calendar"></div>' +
                // slots after calendar
                '<div class="musement-tour__slots"></div>' +
                // change day button hidden initially
                '<button class="musement-change-day musement-tour__button" style="display:none; margin-top:10px;">Cambiar día</button>' +
            '</div>';
        
        // Attach listener to change day button
        const changeBtn = article.querySelector('.musement-change-day');
        if (changeBtn) {
            changeBtn.addEventListener('click', function () {
                const calendar = article.querySelector('.musement-tour__calendar');
                const slots = article.querySelector('.musement-tour__slots');
                if (calendar) calendar.style.display = '';
                if (slots) slots.innerHTML = '';
                changeBtn.style.display = 'none';
            });
        }

        const media = article.querySelector('.musement-tour__media');

        if (activity.image) {
            const image = document.createElement('img');
            image.className = 'musement-tour__image';
            image.src = activity.image;
            image.alt = '';
            image.loading = 'lazy';
            media.appendChild(image);
        } else {
            const placeholder = document.createElement('div');
            placeholder.className = 'musement-tour__image musement-tour__image--empty';
            media.appendChild(placeholder);
        }

        article.querySelector('.musement-tour__title').textContent = activity.title || 'Tour';
        article.querySelector('.musement-tour__description').textContent = activity.description || 'Consulta la disponibilidad para ver horarios.';
        article.querySelector('.musement-tour__meta').textContent = activity.price ? 'Desde ' + activity.price : '';
        const wpPrice = MUSEMENT.price;

        article.querySelector('.musement-tour__meta').textContent =
            activity.price?.formatted
                || activity.price?.value
                ? 'Desde ' + (activity.price.formatted || activity.price.value + ' ' + (activity.price.currency || 'EUR'))
                : '';
        
        return article;
    }

    function productPrice(product) {
        const price = product?.price || {};

        const value = Number(price.value || 0);

        return {
            value: value,
            currency: price.currency || 'EUR',
            formatted: price.formatted || (value ? value.toFixed(2) + ' EUR' : '0.00 EUR')
        };
    }

    function firstBookableProduct(slot) {
        const products = slot.products || [];

        return products.find(function (product) {
            return product.product_identifier;
        }) || null;
    }

    function addToCart(card, slot, product, quantity, button,selectedLanguage) {
        const price = productPrice(product);
        console.log(" PRECIO DESDE AÑADIR CARRO:  ", price);
        console.log("PRODUCT", product );
        console.log("SLOT", slot );
        button.disabled = true;
        button.textContent = 'Anadiendo...';

        const language = selectedLanguage || (slot.languages && slot.languages[0] ? slot.languages[0].code : '');

        post('musement_cart_add', {
            activity_uuid: card.dataset.uuid || '',
            title: card.dataset.title || '',
            date: card._selectedDate || '',
            time: slot.time || '',
            product_identifier: product.product_identifier || '',
            product_name: product.name || '',

            option_name: slot.option_name || '',
            option_feature_code: slot.option_feature_code || '',
            option_type: slot.option_type || '',

            holder_code: product.holder_code || '',
            holder_code_normalized: product.holder_code_normalized || '',

            availability: product.availability || slot.availability || 0,

            language: language,
            pickup: '',
            quantity: quantity,
            unit_price_formatted: price.formatted || '',
            unit_price: Number(price.value || 0),
            musement_price: Number(price.value || 0),
            currency: price.currency
        }).then(function (json) {
            if (!json || !json.success) {
                throw new Error(json && json.data ? json.data.message : '');
            }

            button.textContent = 'Anadido';
            updateCartBadge(json.data);
        }).catch(function () {
            button.textContent = 'No se pudo anadir';
        }).finally(function () {
            setTimeout(function () {
                button.disabled = false;
                button.textContent = 'Anadir';
            }, 1200);
        });
    }

    function getCalendarState(card) {
        if (!card._calendarState) {
            const today = new Date();

            card._calendarState = {
                year: today.getFullYear(),
                month: today.getMonth(),
                available: new Set(),
                card: card
            };
        }

        return card._calendarState;
    }

    function renderSlot(card, slot) {
        const wrap = document.createElement('div');
        wrap.className = 'musement-slot';

        const time = document.createElement('h4');
        time.className = 'musement-slot__time';
        time.textContent = slot.time || 'Horario';
        wrap.appendChild(time);

        (slot.products || []).forEach(function(product) {
            const row = document.createElement('div');
            row.className = 'musement-slot__product';

            const info = document.createElement('div');
            info.className = 'musement-slot__info';

            const title = document.createElement('strong');
            title.textContent = product.name || 'Entrada';

            const price = document.createElement('span');
            price.textContent = product.price?.formatted || '';

            info.appendChild(title);
            info.appendChild(document.createElement('br'));
            info.appendChild(price);

            // Selector de idioma
            if (slot.languages && slot.languages.length > 0) {
                const languageDiv = document.createElement('div');
                languageDiv.className = 'musement-slot__language';
                languageDiv.style.marginTop = '8px';
                
                const label = document.createElement('label');
                label.style.fontSize = '12px';
                label.style.color = '#475569';
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.gap = '6px';
                label.innerHTML = '🌐 Idioma: ';
                
                const select = document.createElement('select');
                select.className = 'musement-language-select';
                select.style.padding = '4px 24px 4px 6px';
                select.style.borderRadius = '4px';
                select.style.border = '1px solid #cbd5e1';
                select.style.fontSize = '12px';
                select.style.minWidth = '100px';
                select.style.appearance = 'auto';
                
                slot.languages.forEach(function(lang) {
                    const option = document.createElement('option');
                    option.value = lang.code || lang;
                    option.textContent = lang.name || lang.code || lang;
                    select.appendChild(option);
                });
                
                label.appendChild(select);
                languageDiv.appendChild(label);
                info.appendChild(languageDiv);
            }

            // CANTIDAD
            const qty = document.createElement('input');
            qty.type = 'number';
            qty.min = 1;
            qty.value = 1;

            // 🔥 VERIFICAR DISPONIBILIDAD
            // availability = -1 significa "stock ilimitado"
            // availability = 0 significa "agotado"
            // availability > 0 significa "disponible hasta X unidades"
            const isAvailable = slot.availability === -1 || slot.availability > 0;
            const productAvailable = product.availability === -1 || product.availability > 0;
            const hasAvailability = isAvailable && productAvailable;

            // BOTÓN
            const btn = document.createElement('button');
            btn.className = 'musement-tour__button';
            btn.textContent = hasAvailability ? 'Añadir' : 'Agotado';
            btn.disabled = !hasAvailability;
            
            if (!hasAvailability) {
                btn.style.backgroundColor = '#ccc';
                btn.style.cursor = 'not-allowed';
            }

            btn.addEventListener('click', function() {
                if (!hasAvailability) return;
                
                const languageSelect = row.querySelector('.musement-language-select');
                const selectedLanguage = languageSelect ? languageSelect.value : (slot.languages && slot.languages[0] ? slot.languages[0].code : '');
                
                console.log('🌐 Idioma seleccionado:', selectedLanguage);
                console.log('📦 Producto:', product.name);

                addToCart(
                    card,
                    slot,
                    product,
                    Number(qty.value || 1),
                    btn,
                    selectedLanguage
                );
            });

            row.appendChild(info);
            row.appendChild(qty);
            row.appendChild(btn);
            wrap.appendChild(row);
        });

        return wrap;
    }

    function renderSlots(card, slots) {
        const container = card.querySelector('.musement-tour__slots');

        container.innerHTML = '';

        if (!slots.length) {
            container.textContent = 'No hay horarios disponibles';
            return;
        }

        slots.forEach(slot => {
            container.appendChild(renderSlot(card, slot));
        });
    }

    function loadMonthAvailability(card) {
        const state = getCalendarState(card);

        const date = `${state.year}-${String(state.month + 1).padStart(2, '0')}-01`;

        return post('musement_availability', {
            uuid: card.dataset.uuid,
            date
        }).then(json => {

            const slots = json?.data?.slots || [];

            // guardamos días disponibles
            state.available = new Set(slots.map(s => s.date));

            // IMPORTANTE: render usando state correcto
            renderCalendar(card, state);
        });
    }

    function buildAvailabilitySet(days) {
        return new Set(days.map(d => d.date));
    }

    function generateMonth(year, month) {
        const date = new Date(year, month, 1);
        const days = [];

        while (date.getMonth() === month) {
            days.push(new Date(date));
            date.setDate(date.getDate() + 1);
        }

        return days;
    }

    function formatISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function renderCalendarGrid(container, state, availableSet, onSelect) {
        container.innerHTML = '';

        const days = generateMonth(state.year, state.month);

        const wrapper = document.createElement('div');

        // NAV BAR
        const nav = document.createElement('div');
        nav.className = 'musement-calendar-nav';

        nav.innerHTML = `
            <button class="prev">‹</button>
            <strong>${state.year} - ${state.month + 1}</strong>
            <button class="next">›</button>
        `;

        wrapper.appendChild(nav);

        // GRID
        const grid = document.createElement('div');
        grid.className = 'musement-calendar-grid';

        const weekdays = ['L','M','X','J','V','S','D'];

        weekdays.forEach(d => {
            const el = document.createElement('div');
            el.className = 'musement-calendar-grid__header';
            el.textContent = d;
            grid.appendChild(el);
        });

        const startOffset = (days[0].getDay() + 6) % 7;

        for (let i = 0; i < startOffset; i++) {
            grid.appendChild(document.createElement('div'));
        }

        days.forEach(date => {
            const iso = formatISO(date);//  date.toISOString().split('T')[0];

            const cell = document.createElement('button');
            cell.className = 'musement-calendar-grid__day';

            const isAvailable = availableSet.has(iso);

            cell.disabled = !isAvailable;
            cell.classList.toggle('is-available', isAvailable);

            cell.innerHTML = `
                <span>${date.getDate()}</span>
                ${isAvailable ? '<small class="dot"></small>' : ''}
            `;

            if (isAvailable) {
                cell.addEventListener('click', () => onSelect(iso));
            }

            grid.appendChild(cell);
        });

        wrapper.appendChild(grid);
        container.appendChild(wrapper);

        return {
            nav,
        };
    }

    function renderCalendar(card, state) {
        const container = card.querySelector('.musement-tour__calendar');

        const days = generateMonth(state.year, state.month);

        const wrapper = document.createElement('div');

        const nav = document.createElement('div');
        nav.className = 'musement-calendar-nav';

        nav.innerHTML = `
            <button class="prev">‹</button>
            <strong>${state.year} - ${state.month + 1}</strong>
            <button class="next">›</button>
        `;

        nav.querySelector('.prev').onclick = () => {
            state.month--;
            if (state.month < 0) {
                state.month = 11;
                state.year--;
            }
            loadMonthAvailability(card);
        };

        nav.querySelector('.next').onclick = () => {
            state.month++;
            if (state.month > 11) {
                state.month = 0;
                state.year++;
            }
            loadMonthAvailability(card);
        };

        const grid = document.createElement('div');
        grid.className = 'musement-calendar-grid';

        const weekdays = ['L','M','X','J','V','S','D'];

        weekdays.forEach(d => {
            const el = document.createElement('div');
            el.className = 'musement-calendar-grid__header';
            el.textContent = d;
            grid.appendChild(el);
        });

        const offset = (days[0].getDay() + 6) % 7;
        for (let i = 0; i < offset; i++) {
            grid.appendChild(document.createElement('div'));
        }

        days.forEach(date => {
            const iso = formatISO(date);
            const available = state.available.has(iso);

            const cell = document.createElement('button');
            cell.className = 'musement-calendar-grid__day';
            cell.disabled = !available;

            cell.innerHTML = `
                <span>${date.getDate()}</span>
                ${available ? '<small class="dot"></small>' : ''}
            `;
            cell.addEventListener('click', () => {
                alert('Fecha seleccionada: ' + iso);
            });

            grid.appendChild(cell);
        });

        wrapper.appendChild(nav);
        wrapper.appendChild(grid);

        container.innerHTML = '';
        container.appendChild(wrapper);
    }

    function loadSlotsForDate(card, date) {
        const container = card.querySelector('.musement-tour__slots');

        container.textContent = 'Cargando horarios...';

        post('musement_availability', {
            uuid: card.dataset.uuid,
            date
        }).then(json => {

            const slots = json?.data?.slots || [];

            container.innerHTML = '';

            if (!slots.length) {
                container.textContent = 'No hay horarios disponibles';
                return;
            }

            slots.forEach(slot => {
                container.appendChild(renderSlot(card, slot));
            });
        });
    }

    function createCalendarState() {
        const today = new Date();

        return {
            year: today.getFullYear(),
            month: today.getMonth()
        };
    }

    function changeMonth(state, delta) {
        state.month += delta;

        if (state.month < 0) {
            state.month = 11;
            state.year--;
        }

        if (state.month > 11) {
            state.month = 0;
            state.year++;
        }
    }

    function unlockFlatpickrPrevMonth(instance) {
        if (!instance || !instance.prevMonthNav) return;

        instance.prevMonthNav.classList.remove('flatpickr-disabled');
        instance.prevMonthNav.removeAttribute('aria-disabled');
        instance.prevMonthNav.removeAttribute('tabindex');
    }

    /*
    function loadAvailability(card) {

        const calendar = card.querySelector('.musement-tour__calendar');
        const button = card.querySelector('.musement-tour__button');

        button.disabled = true;

        calendar.innerHTML = '';

        const input = document.createElement('input');
        calendar.appendChild(input);

        // limpiar instancia anterior
        if (card._fp) {
            card._fp.destroy();
            card._fp = null;
        }

        flatpickr.localize(flatpickr.l10ns.es);
        const uuid2 = card.dataset.uuid;
        const month = String(monthIndex + 1).padStart(2, '0');
        const ym = year + '-' + month;

        card._fp = flatpickr(input, {
            inline: true,
            locale: 'es',
            dateFormat: 'Y-m-d',

            onReady(selectedDates, dateStr, instance) {

                const year = instance.currentYear;
                const monthIndex = instance.currentMonth;

                loadMonthAvailability(card, year, monthIndex);
            },

            onMonthChange(selectedDates, dateStr, instance) {
                loadMonthAvailability(card, instance.currentYear, instance.currentMonth);
            },

            onDayCreate: function (dObj, dStr, fp, dayElem) {
                const date = dayElem.dateObj.toISOString().slice(0, 10);

                if (fp._availability && fp._availability[date]) {
                    dayElem.classList.add('musement-day-' + fp._availability[date]);
                }
            }
        });

        button.disabled = false;
    }
    */ 

    function loadAvailability(card) {

        const calendar = card.querySelector('.musement-tour__calendar');
        const button = card.querySelector('.musement-tour__button');

        button.disabled = true;
        calendar.innerHTML = '';

        const input = document.createElement('input');
        calendar.appendChild(input);

        if (card._fp) {
            card._fp.destroy();
            card._fp = null;
        }

        flatpickr.localize(flatpickr.l10ns.es);

        const uuid = card.dataset.uuid;

        card._fp = flatpickr(input, {
            inline: true,
            locale: 'es',
            dateFormat: 'Y-m-d',

            onReady(selectedDates, dateStr, instance) {
                console.log('YEAR', instance.currentYear);
                console.log('MONTH', instance.currentMonth);
                unlockFlatpickrPrevMonth(instance);
                loadMonthAvailability(card, instance.currentYear, instance.currentMonth);
            },

            onMonthChange(selectedDates, dateStr, instance) {
                unlockFlatpickrPrevMonth(instance);
                loadMonthAvailability(card, instance.currentYear, instance.currentMonth);
            },

            onChange(selectedDates, dateStr, instance) {

                console.log("DIA CLICADO:", dateStr);
                card._selectedDate = dateStr;

                post('musement_day_availability', {
                    uuid: uuid,
                    date: dateStr
                })
                .then(function (json) {

                    if (!json.success) {
                        renderSlots(card, []);
                        return;
                    }

                    console.log("RESPUESTA COMPLETA :", json);

                    const data = json.data;
                    let slots = [];

                   if (data && data.groups) {
            data.groups.forEach(function(group) {
                (group.slots || []).forEach(function(slot) {
                    slots.push({
                        time: slot.time,
                        availability: slot.availability,
                        languages: slot.languages || [],
                        products: (slot.products || []).map(function(product) {
                            return {
                                product_identifier: product.product_id || product.product_identifier,
                                name: product.name,
                                availability: product.availability,
                                price: {
                                    value: product.retail_price?.value || 0,
                                    currency: product.retail_price?.currency || 'EUR',
                                    formatted: product.retail_price?.formatted_value || ''
                                }
                            };
                        })
                    });
                });
            });
        }
        // Si la respuesta es un array (formato antiguo)
        else if (Array.isArray(data) && data.length > 0) {
            data.forEach(function(day) {
                (day.groups || []).forEach(function(group) {
                    (group.slots || []).forEach(function(slot) {
                        slots.push({
                            time: slot.time,
                            availability: slot.availability,
                            languages: slot.languages || [],
                            products: (slot.products || []).map(function(product) {
                                return {
                                    product_identifier: product.product_id,
                                    name: product.name,
                                    availability: product.availability,
                                    price: {
                                        value: product.retail_price?.value || 0,
                                        currency: product.retail_price?.currency || 'EUR',
                                        formatted: product.retail_price?.formatted_value || ''
                                    }
                                };
                            })
                        });
                    });
                });
            });
        }

                // Hide calendar and show 'Cambiar día' button after selecting a date
                const calendar = card.querySelector('.musement-tour__calendar');
                const changeBtn = card.querySelector('.musement-change-day');
                if (calendar) calendar.style.display = 'none';
                if (changeBtn) changeBtn.style.display = '';
                console.log("SLOTS NORMALIZADOS:", slots);
                renderSlots(card, slots);

                })
                .catch(function (err) {
                    console.error('Error cargando slots', err);
                });

            },

            onDayCreate(dObj, dStr, fp, dayElem) {
                // Use local date parts to avoid timezone offsets from toISOString
                const iso =
                    dayElem.dateObj.getFullYear() + '-' +
                    String(dayElem.dateObj.getMonth() + 1).padStart(2, '0') + '-' +
                    String(dayElem.dateObj.getDate()).padStart(2, '0');

                const status = fp._availability && fp._availability[iso];

                // use console.log so it appears in most consoles
                console.log('flatpickr onDayCreate', iso, status);

                if (!status) {
                    return;
                }

                dayElem.classList.add('musement-day-' + status);
                dayElem.setAttribute('title', status === 'full' ? 'Agotado' : 'Disponible');

                if (status === 'full') {
                    dayElem.classList.add('flatpickr-disabled');
                    dayElem.setAttribute('aria-disabled', 'true');
                } else {
                    dayElem.classList.remove('flatpickr-disabled');
                    dayElem.removeAttribute('aria-disabled');
                }

            }
        });

        button.disabled = false;
    }

    function loadMonthAvailability(card, year, monthIndex) {

        const uuid = card.dataset.uuid;

        const month = String(monthIndex + 1).padStart(2, '0');
        const ym = `${year}-${month}`;

        post('musement_month_availability', {
            uuid: uuid,
            month: ym
        })
        .then(function (json) {

            if (!json.success) return;

            const fp = card._fp;
            if (!fp) return;

            console.log("DIAS DE DISPO: " ,json.data);

            // Fill availability for the whole month so onDayCreate
            // always finds a value (avoid undefined on initial render)
            const raw = json.data.days || {};
            const filled = {};
            const y = parseInt(fp.currentYear, 10);
            const m = parseInt(fp.currentMonth, 10); // 0-11
            const d = new Date(y, m, 1);

            while (d.getMonth() === m) {
                const iso =
                    d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');

                filled[iso] = raw[iso] || 'full';
                d.setDate(d.getDate() + 1);
            }

            fp._availability = filled;
            fp.redraw();
        })
        .catch(function () {
            console.error('Error cargando mes');
        });
    }

    function initWidget(widget) {
        const list = widget.querySelector('.musement-tours');
        const cityId = widget.dataset.cityId || '';
        const limit = widget.dataset.limit || '12';

        const payload = {
            limit: limit,
        };
        
        // Si hay city_id, enviarlo
        if (cityId) {
            payload.city_id = cityId;
        }

        post('musement_activities', payload).then(function (json) {
            const activities = json && json.success && json.data ? json.data.activities || [] : [];
            const fragment = document.createDocumentFragment();

            list.innerHTML = '';

            if (!activities.length) {
                setStatus(widget, 'No hay tours disponibles para esta ciudad.', true);
                return;
            }

            setStatus(widget, '');
            activities.forEach(function (activity) {
                fragment.appendChild(renderActivity(activity));
            });
            list.appendChild(fragment);
        }).catch(function () {
            setStatus(widget, 'No se pudieron cargar los tours.', true);
        });

        widget.addEventListener('click', function (event) {
            if (!event.target.classList.contains('musement-tour__button')) {
                return;
            }
            loadAvailability(event.target.closest('.musement-tour'));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.musement-widget').forEach(initWidget);
        document.querySelectorAll('[data-musement-checkout]').forEach(initCheckout);
        ensureCartBar();
    });

    function money(value, currency) {
        return Number(value || 0).toFixed(2) + ' ' + (currency || 'EUR');
    }

    function updateCartBadge(cart) {
        const badge = document.querySelector('.musement-cart-bar__count');
        const total = document.querySelector('.musement-cart-bar__total');
        const items = cart && cart.items ? cart.items : [];
        const summary = cart && cart.summary ? cart.summary : { total: 0, currency: 'EUR' };

        if (badge) {
            badge.textContent = String(items.length);
        }

        if (total) {
            total.textContent = money(summary.total, summary.currency);
        }
    }

    function ensureCartBar() {
        if (document.querySelector('.musement-cart-bar')) {
            return;
        }

        const bar = document.createElement('div');
        bar.className = 'musement-cart-bar';
        bar.innerHTML =
            '<span><strong class="musement-cart-bar__count">0</strong> producto(s)</span>' +
            '<span class="musement-cart-bar__total">0.00 EUR</span>' +
            '<a class="musement-cart-bar__checkout" href="' + (MUSEMENT.checkoutUrl || '#') + '">Checkout</a>';
        document.body.appendChild(bar);

        post('musement_remote_cart_get').then(function (json) {
            console.log("MUSEMENT REMOTE CART 168 : ", json);
            MUSEMENT.cartUuid = json.data.uuid;
        });

        post('musement_cart_get').then(function (json) {

            console.log("MUSEMENT CART: ", json);
            if (json && json.success) {
                updateCartBadge(json.data);
            }
        });
    }

    function renderCheckout(checkout, cart) {
        const box = checkout.querySelector('.musement-checkout__items');
        const status = checkout.querySelector('.musement-checkout__status');
        const items = cart.items || [];
        const summary = cart.summary || { total: 0, currency: 'EUR' };

        box.innerHTML = '';

        if (!items.length) {
            status.textContent = 'El carrito esta vacio.';
            checkout.querySelector('.musement-checkout__form').hidden = true;
            return;
        }

        status.textContent = '';
        checkout.querySelector('.musement-checkout__form').hidden = false;

        items.forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'musement-checkout__item';
            row.innerHTML =
                '<div><strong></strong><span></span></div>' +
                '<button type="button">Quitar</button>';
            row.querySelector('strong').textContent = item.title || 'Tour';
            row.querySelector('span').textContent = [
                item.date,
                item.time,
                item.product_name,
                'x' + item.quantity,
                item.unit_price_formatted || money(Number(item.unit_price || 0) * Number(item.quantity || 1), item.currency)
            ].filter(Boolean).join(' - ');
            row.querySelector('button').addEventListener('click', function () {
                post('musement_cart_remove', { key: item.key }).then(function (json) {
                    if (json && json.success) {
                        renderCheckout(checkout, json.data);
                        updateCartBadge(json.data);
                        window.location.reload();
                    }
                });
            });
            box.appendChild(row);
        });
        

        const total = document.createElement('div');
        const submit = checkout.querySelector('.musement-checkout__submit');

        total.className = 'musement-checkout__total';
        total.textContent = 'Total: ' + money(summary.total, summary.currency);
        box.appendChild(total);

        if (submit) {
            submit.textContent = 'Pagar ' + money(summary.total, summary.currency);
        }
    }

    function initPaymentAccordion(checkout) {
        const payment = checkout.querySelector('[data-musement-payment]');

        if (!payment) {
            return;
        }

        const input = payment.querySelector('input[name="payment_method"]');
        const items = Array.prototype.slice.call(payment.querySelectorAll('.musement-payment__item'));

        payment.addEventListener('click', function (event) {
            const button = event.target.closest('.musement-payment__header');

            if (!button) {
                return;
            }

            const method = button.dataset.paymentMethod || 'stripe';

            if (input) {
                input.value = method;
            }

            items.forEach(function (item) {
                const itemButton = item.querySelector('.musement-payment__header');
                const body = item.querySelector('.musement-payment__body');
                const isActive = itemButton === button;

                item.classList.toggle('is-active', isActive);
                itemButton.setAttribute('aria-expanded', isActive ? 'true' : 'false');

                if (body) {
                    body.hidden = !isActive;
                }
            });
        });
    }

    function checkoutPaymentErrors(checkout) {
        return checkout.querySelector('.musement-payment-errors');
    }

    function setPaymentError(checkout, message) {
        const errors = checkoutPaymentErrors(checkout);

        if (!errors) {
            return;
        }

        errors.textContent = message || '';
        errors.hidden = !message;
    }

    function stripeReturnUrl(orderReference) {
        const url = new URL(window.location.href);

        if (orderReference) {
            url.searchParams.set('musement_order', orderReference);
        }

        url.searchParams.set('payment_status', 'stripe_return');

        return url.toString();
    }

    function mountStripePaymentElement(checkout, paymentData) {
        const container = checkout.querySelector('#musement-payment-stripe');
        const submit = checkout.querySelector('.musement-checkout__submit');

        if (!container) {
            throw new Error('No se encontro el contenedor de pago de Stripe.');
        }

        if (!paymentData.stripe_publishable_key) {
            throw new Error('Falta la clave publica de Stripe.');
        }

        if (typeof Stripe !== 'function') {
            throw new Error('Stripe.js no esta disponible.');
        }

        if (checkout._stripePayment && checkout._stripePayment.clientSecret === paymentData.client_secret) {
            return checkout._stripePayment;
        }

        container.innerHTML = '';
        setPaymentError(checkout, '');

        const stripe = Stripe(paymentData.stripe_publishable_key);
        const elements = stripe.elements({
            clientSecret: paymentData.client_secret
        });
        const paymentElement = elements.create('payment');

        paymentElement.mount(container);

        checkout._stripePayment = {
            stripe: stripe,
            elements: elements,
            paymentElement: paymentElement,
            clientSecret: paymentData.client_secret,
            orderReference: paymentData.order_reference || '',
            paymentReference: paymentData.payment_reference || ''
        };

        if (submit) {
            submit.textContent = 'Confirmar pago';
        }

        return checkout._stripePayment;
    }

    function confirmMountedStripePayment(checkout) {
        const payment = checkout._stripePayment;

        if (!payment) {
            return Promise.reject(new Error('El formulario de Stripe todavia no esta preparado.'));
        }

        setPaymentError(checkout, '');

        return payment.stripe.confirmPayment({
            elements: payment.elements,
            confirmParams: {
                return_url: stripeReturnUrl(payment.orderReference)
            },
            redirect: 'if_required'
        }).then(function (result) {
            if (result.error) {
                throw new Error(result.error.message || 'No se pudo confirmar el pago.');
            }

            const intent = result.paymentIntent || {};
            const paidStatuses = ['succeeded', 'processing', 'requires_capture'];

            if (intent.status && paidStatuses.indexOf(intent.status) === -1) {
                throw new Error('El pago no se ha completado. Estado: ' + intent.status + '.');
            }
            
            // console.log(intent);
            checkout._stripePaymentConfirmed = true;
            const submit = document.querySelector('.musement-checkout__submit');
            if (submit) {
                submit.disabled = true;
                submit.style.display = 'none';
            }


            return intent;
        });
    }

// Función para monitorear vouchers y tickets
let attempts = 0;
let monitoringInterval = null;

function startVoucherMonitoring(orderUuid, checkout, statusElement, bookingRef, originalOrderData, octoPayload) {
    console.log('🚀 Iniciando monitoreo de vouchers y tickets para orden:', orderUuid);

    console.log("____________________ORDER DATA octoPayload: __________________________");
    console.log(JSON.stringify(octoPayload, null, 2));
    console.log("_______________________________________________________________");

    attempts = 0;  // ✅ Usar la variable global, no crear una nueva
    function showPayloadWithUrls(vouchers, tickets, orderData) {

        console.log("____________________ORDER DATA WITH URLS: __________________________");
        console.log(JSON.stringify(orderData, null, 2));
        console.log("_______________________________________________________________");

        const payload = {
            webhook: {
                id: "5ec5abc7-6a49-4aa5-930b-3dfb9cbb9123",
                event: "order_update",
                url: "",
                retryOnError: true,
                useContactLanguage: true,
                capabilities: ["octo/cart", "octo/questions", "octo/extras", "ventrata/dashboard"]
            },
            bookings: octoPayload.order.bookings || " esto es  una prueba de rendimiento. ",
            order: orderData || {
                id: orderUuid,
                supplierReference: bookingRef,
                status: "CONFIRMED",
                utcCreatedAt: new Date().toISOString(),
                utcUpdatedAt: new Date().toISOString()
            },
            documents: {
                vouchers: vouchers.map(v => ({
                    activity_name: v.activity_name,
                    url: v.url,
                    type: "voucher"
                })),
                tickets: tickets.map(t => ({
                    activity_name: t.activity_name,
                    ticket_id: t.ticket_id,
                    url: t.url,
                    type: "ticket"
                }))
            },
            timestamp: new Date().toISOString()
        };
        
        console.log('═══════════════════════════════════════════════════════════');
        console.log('📦 PAYLOAD COMPLETO CON URLs DE VOUCHERS Y TICKETS');
        console.log('═══════════════════════════════════════════════════════════');
        console.log(JSON.stringify(payload, null, 2));
        console.log('═══════════════════════════════════════════════════════════');
        
        return payload;
    }
    
    function checkDocuments() {
        attempts++;
        statusElement.textContent = `🔍 Verificando disponibilidad de documentos (intento ${attempts}/20)...`;
        
        fetch(MUSEMENT.ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'musement_check_order_status',
                nonce: MUSEMENT.nonce,
                order_uuid: orderUuid
            })
        })
        .then(r => r.json())
        .then(result => {
            if (!result.success) {
                console.warn('Error en consulta de vouchers:', result.data);
                return;
            }
            
            const vouchers = result.data.vouchers || [];
            
            if (vouchers.length > 0) {
                fetch(MUSEMENT.ajax, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'musement_get_order_tickets',
                        nonce: MUSEMENT.nonce,
                        order_uuid: orderUuid
                    })
                })
                .then(r => r.json())
                .then(ticketResult => {
                    const tickets = ticketResult.success ? ticketResult.data.tickets : [];
                    
                    showPayloadWithUrls(vouchers, tickets, originalOrderData);
                    
                    displayDocuments(vouchers, tickets, statusElement, checkout, bookingRef);
                    
                    if (monitoringInterval) {
                        clearInterval(monitoringInterval);
                        monitoringInterval = null;
                    }
                })
                .catch(err => console.error('Error obteniendo tickets:', err));
            } else if (attempts >= 20) {
                statusElement.innerHTML = '✅ Pago completado. Los documentos estarán disponibles en breve. Recibirás un email de confirmación.';
                if (monitoringInterval) {
                    clearInterval(monitoringInterval);
                    monitoringInterval = null;
                }
                setTimeout(function() {
                    // window.location.href = MUSEMENT.checkoutUrl + '?reserva=' + bookingRef + '&status=success';
                }, 5000);
            } else {
                console.log(`⏳ Sin documentos aún (intento ${attempts}/20)`);
            }
        })
        .catch(err => console.error('Error:', err));
    }
    
    function displayDocuments(vouchers, tickets, statusElement, checkout, bookingRef) {
        let html = '<div class="musement-documents" style="margin-top: 16px; padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">';
        
        if (vouchers.length > 0) {
            html += '<h4 style="margin: 0 0 8px 0; color: #166534;">📄 Vouchers:</h4>';
            html += '<ul style="margin: 0; padding-left: 20px;">';
            vouchers.forEach(v => {
                html += `<li style="margin: 8px 0;">
                    <a href="${v.url}" target="_blank" rel="noopener noreferrer" style="color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        📎 ${v.activity_name} - Descargar voucher (PDF)
                    </a>
                </li>`;
            });
            html += '</ul>';
        }
        
        if (tickets.length > 0) {
            html += '<h4 style="margin: 8px 0 8px 0; color: #166534;">🎫 Entradas individuales:</h4>';
            html += '<ul style="margin: 0; padding-left: 20px;">';
            tickets.forEach(t => {
                if (t.url) {
                    html += `<li style="margin: 8px 0;">
                        <a href="${t.url}" target="_blank" rel="noopener noreferrer" style="color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            🎟️ ${t.activity_name} - Ticket ${t.ticket_id}
                        </a>
                    </li>`;
                }
            });
            html += '</ul>';
        }
        
        html += '</div>';
        
        let container = checkout.querySelector('.musement-checkout__documents');
        if (!container) {
            container = document.createElement('div');
            container.className = 'musement-checkout__documents';
            statusElement.insertAdjacentElement('afterend', container);
        }
        container.innerHTML = html;
        statusElement.innerHTML = '✅ ¡Reserva confirmada! Tus documentos están disponibles para descargar.';
        
        setTimeout(function() {
            window.location.href = MUSEMENT.checkoutUrl + '?reserva=' + bookingRef + '&status=success';
        }, 10000);
    }
    
    checkDocuments();
    monitoringInterval = setInterval(checkDocuments, 10000);
}


    function initCheckout(checkout) {
        initPaymentAccordion(checkout);

        post('musement_cart_get').then(function (json) {
            if (json && json.success) {
                renderCheckout(checkout, json.data);
            }
        });

        checkout.querySelector('.musement-checkout__form').addEventListener('submit', function (event) {
            event.preventDefault();

            const form = event.currentTarget;
            const status = checkout.querySelector('.musement-checkout__status');
            const submit = form.querySelector('button[type="submit"]');
            const payload = {};

            if (checkout._stripePayment) {
                status.textContent = 'Confirmando pago...';
                submit.disabled = true;

                function createMusementOrderAfterPayment(checkout, cartUuid) {
                    const form = checkout.querySelector('.musement-checkout__form');
                    const formData = new FormData(form);
                    const firstName = formData.get('first_name') || '';
                    const lastName = formData.get('last_name') || '';
                    const email = formData.get('email') || '';
                    const phone = formData.get('phone') || '';
                    
                    return post('musement_create_order', {
                        cart_uuid: cartUuid,
                        first_name: firstName,
                        last_name: lastName,
                        email: email,
                        phone: phone
                    }).then(function (result) {
                        if (!result || !result.success) {
                            throw new Error(result?.data?.message || 'Error al crear la reserva');
                        }
                        return result.data;
                    });
                }

                confirmMountedStripePayment(checkout).then(function (intent) {
                    status.textContent = '✅ Pago recibido. Creando tu reserva...';
                    
                    const cartUuid = MUSEMENT.cartUuid;
                    if (!cartUuid) {
                        throw new Error('No se encontró el carrito');
                    }
                    
                    return createMusementOrderAfterPayment(checkout, cartUuid);
                    
                }).then(function (orderData){
                    const order = orderData.order || orderData;
                    const vouchers = orderData.vouchers || [];
                    const bookingRef = order.uuid || order.identifier || orderData.order_uuid || '';
                    const orderUuid = orderData.order_uuid || order.uuid;
                    const octoPayload = orderData.octo_payload;

                    const customerData = {
                        first_name: orderData.customer_data?.first_name || '',
                        last_name: orderData.customer_data?.last_name || '',
                        email: orderData.customer_data?.email || '',
                        phone: orderData.customer_data?.phone || ''
                    };

                    console.log('=== OCTO PAYLOAD ===');
                    console.log(JSON.stringify(octoPayload, null, 2));
                    console.log('===================');
                    
                    console.log('✅ Reserva creada:', { bookingRef, vouchers, orderUuid });
                    
                    function displayVouchers(voucherList) {
                        let vouchersHtml = '<div class="musement-vouchers" style="margin-top: 16px; padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">';
                        vouchersHtml += '<h4 style="margin: 0 0 8px 0; color: #166534;">📄 Tus entradas/vouchers:</h4>';
                        vouchersHtml += '<ul style="margin: 0; padding-left: 20px;">';
                        
                        voucherList.forEach(function(voucher) {
                            if (voucher.url) {
                                const activityName = voucher.activity_name || 'Actividad';
                                vouchersHtml += `<li style="margin: 8px 0;">
                                    <a href="${voucher.url}" target="_blank" rel="noopener noreferrer" style="color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                        📎 ${activityName} - Descargar voucher (PDF)
                                    </a>
                                </li>`;
                            }
                        });
                        
                        vouchersHtml += '</ul></div>';
                        
                        let vouchersContainer = checkout.querySelector('.musement-checkout__vouchers');
                        if (!vouchersContainer) {
                            vouchersContainer = document.createElement('div');
                            vouchersContainer.className = 'musement-checkout__vouchers';
                            status.insertAdjacentElement('afterend', vouchersContainer);
                        }
                        vouchersContainer.innerHTML = vouchersHtml;
                        fetch(MUSEMENT.ajax, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'musement_send_octo_with_vouchers',
                                nonce: MUSEMENT.nonce,
                                order_uuid: orderUuid,
                                first_name: customerData?.first_name || '',
                                last_name: customerData?.last_name || '',
                                email: customerData?.email || '',
                                phone: customerData?.phone || ''
                            })
                        }).then(r => r.json())
                        .then(result => {
                            if (result.success) {
                                console.log('📤 OCTO enviado correctamente');
                            } else {
                                console.warn('⚠️ OCTO no enviado:', result.data?.message);
                            }
                        });
                    }
                    
                    if (vouchers && vouchers.length > 0) {
                        status.textContent = `✅ ¡Reserva confirmada! Nº: ${bookingRef.substring(0, 8)}... Puedes descargar tus vouchers:`;
                        displayVouchers(vouchers);
                        
                        setTimeout(function() {
                        // window.location.href = MUSEMENT.checkoutUrl + '?reserva=' + bookingRef + '&status=success';
                        }, 10000);
                    } else {

                        const orderDataCompleto = {
                            id: orderUuid,
                            testMode: false,
                            supplierReference: bookingRef,
                            status: "CONFIRMED",
                            utcCreatedAt: orderData.utcCreatedAt || new Date().toISOString(),
                            utcUpdatedAt: orderData.utcUpdatedAt || new Date().toISOString(),
                            bookings: orderData.order?.bookings || orderData.bookings || [],
                            contact: orderData.order?.contact || orderData.contact || customerData,
                            pricing: orderData.order?.pricing || orderData.pricing || { original: 0, retail: 0, currency: "EUR" }
                        };
                        status.textContent = `✅ Pago confirmado. Nº reserva: ${bookingRef.substring(0, 8)}... Esperando generación de vouchers...`;
                        startVoucherMonitoring(orderUuid, checkout, status, bookingRef, orderDataCompleto, octoPayload);
                    }
                }).catch(function (error) {
                    setPaymentError(checkout, error.message);
                    status.textContent = '❌ ' + error.message;
                    submit.disabled = false;
                    checkout._stripePaymentConfirmed = false;
                });

                return;
            }

            new FormData(form).forEach(function (value, key) {
                payload[key] = value;
            });

            status.textContent = 'Preparando pago...';
            submit.disabled = true;

            post('musement_checkout_start', payload).then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data ? json.data.message : '');
                }

                if (json.data && json.data.debug_payloads && Object.keys(json.data.debug_payloads).length) {
                    console.log('Musement debug payloads', json.data.debug_payloads);

                    if (json.data.debug_payloads.salesforce) {
                        console.log('Salesforce payload preview', json.data.debug_payloads.salesforce);
                    }
                }

                if (json.data.payment_url) {
                    status.textContent = 'Redirigiendo a la pasarela de pago...';
                    window.location.href = json.data.payment_url;
                    return;
                }

                if (json.data.client_secret) {
                    mountStripePaymentElement(checkout, json.data);
                    status.textContent = 'Introduce los datos de la tarjeta para completar el pago.';
                    return;
                }

                if (json.data.requires_payment_gateway) {
                    console.warn('Musement payment gateway unavailable', {
                        gateway: json.data.payment_gateway || '',
                        error: json.data.payment_error_code || '',
                        message: json.data.message || ''
                    });
                    status.textContent = 'Pedido creado: ' + json.data.order_reference + '. ' + (json.data.message || 'Falta configurar la pasarela de pago.');
                    return;
                }

                console.warn('Musement checkout finished without payment instructions', json.data);
                status.textContent = 'Pedido creado: ' + json.data.order_reference + '. Pago pendiente.';
            }).catch(function (error) {
                status.textContent = error.message || 'No se pudo iniciar el pago.';
            }).finally(function () {
                submit.disabled = false;
            });
        });
    }
})();
