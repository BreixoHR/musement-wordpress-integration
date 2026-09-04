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

    // ============================================================
    // NUEVAS FUNCIONES PARA SEPARAR BOOKINGS POR CARACTERÍSTICAS
    // ============================================================

    /**
     * Genera un ID único
     */
    function generateId(prefix) {
        return (prefix || 'id') + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Normaliza un contacto
     */
    function normalizeContact(contact) {
        if (!contact || typeof contact !== 'object') return null;
        
        return {
            firstName: contact.firstName || contact.first_name || '',
            lastName: contact.lastName || contact.last_name || '',
            fullName: contact.fullName || contact.full_name || '',
            emailAddress: contact.emailAddress || contact.email || contact.email_address || '',
            phoneNumber: contact.phoneNumber || contact.phone || contact.phone_number || '',
            country: contact.country || 'ES',
            language: contact.language || 'es'
        };
    }

    /**
     * Normaliza el pricing
     */
    function normalizePricing(pricing) {
        if (!pricing || typeof pricing !== 'object') {
            return { original: 0, retail: 0, currency: 'EUR' };
        }
        
        return {
            original: parseFloat(pricing.original || pricing.value || pricing.retail || 0),
            retail: parseFloat(pricing.retail || pricing.value || pricing.original || 0),
            currency: pricing.currency || 'EUR'
        };
    }

    /**
     * Normaliza un unit item
     */
    function normalizeUnitItem(unit) {
        if (!unit || typeof unit !== 'object') return null;
        
        return {
            id: unit.id || generateId('unit'),
            unitId: unit.unitId || unit.unit_id || unit.id,
            unitType: unit.unitType || unit.unit_type || 'ADULT',
            unitName: unit.unitName || unit.unit_name || unit.unit_type || 'Adulto',
            quantity: parseInt(unit.quantity || 1, 10),
            contact: normalizeContact(unit.contact),
            pricing: normalizePricing(unit.pricing || unit.price)
        };
    }

    /**
     * Normaliza un booking para asegurar estructura consistente
     */
    function normalizeBooking(booking) {
        if (!booking || typeof booking !== 'object') return null;
        
        return {
            id: booking.id || generateId('booking'),
            productId: booking.productId || booking.product_id || booking.product_identifier || '',
            productName: booking.productName || booking.product_name || booking.title || 'Actividad',
            optionId: booking.optionId || booking.option_id || 'tour',
            optionType: booking.optionType || booking.option_type || 'tour',
            status: booking.status || 'CONFIRMED',
            localDateTimeStart: booking.localDateTimeStart || booking.date || booking.local_date_time_start,
            localDateTimeEnd: booking.localDateTimeEnd || booking.end_date || booking.local_date_time_end,
            timezone: booking.timezone || 'Europe/Madrid',
            language: booking.language || booking.language_code || 'es',
            unitItems: Array.isArray(booking.unitItems) 
                ? booking.unitItems.map(normalizeUnitItem).filter(function(u) { return u !== null; })
                : [],
            contact: normalizeContact(booking.contact || booking.customer_data),
            pricing: normalizePricing(booking.pricing || booking.price),
            metadata: booking.metadata || {},
            extras: booking.extras || [],
            questions: booking.questions || []
        };
    }

    /**
     * 🔑 Genera una clave única para un booking basado en sus características
     * Cada combinación única = un booking separado
     */
    function generateBookingKey(booking) {
        var date = booking.localDateTimeStart ? booking.localDateTimeStart.split('T')[0] : 'unknown';
        var productId = booking.productId || 'unknown';
        var optionId = booking.optionId || 'tour';
        var optionType = booking.optionType || '';
        var language = booking.language || 'es';
        
        // Características que definen un booking único
        var components = [
            date,
            productId,
            optionId,
            optionType,
            language
        ];
        
        // Filtrar vacíos y unir
        return components.filter(function(c) { 
            return c && c.length > 0; 
        }).join('|');
    }

    /**
     * 🔥 SEPARA BOOKINGS POR COMBINACIONES ÚNICAS
     * Esta es la función principal que reemplaza a groupBookingsByDate
     */
    function separateBookingsByUniqueCombination(bookings) {
        // Validar entrada
        if (!bookings || !Array.isArray(bookings) || bookings.length === 0) {
            console.warn('⚠️ No hay bookings para separar');
            return [];
        }
        
        // Normalizar bookings
        var normalizedBookings = [];
        bookings.forEach(function(b) {
            var normalized = normalizeBooking(b);
            if (normalized !== null && normalized.localDateTimeStart) {
                normalizedBookings.push(normalized);
            }
        });
        
        if (normalizedBookings.length === 0) {
            console.warn('⚠️ No se encontraron bookings válidos');
            return [];
        }
        
        // Agrupar por combinación única
        var grouped = {};
        
        normalizedBookings.forEach(function(booking) {
            var key = generateBookingKey(booking);
            
            if (!grouped[key]) {
                grouped[key] = {
                    key: key,
                    date: booking.localDateTimeStart.split('T')[0],
                    productId: booking.productId,
                    productName: booking.productName,
                    optionId: booking.optionId,
                    optionType: booking.optionType,
                    language: booking.language || 'es',
                    bookings: [],
                    unitItems: [],
                    totalOriginal: 0,
                    totalRetail: 0,
                    currency: booking.pricing.currency || 'EUR',
                    contact: booking.contact
                };
            }
            
            // Agregar booking al grupo
            grouped[key].bookings.push(booking);
            grouped[key].totalOriginal += booking.pricing.original || 0;
            grouped[key].totalRetail += booking.pricing.retail || 0;
            
            // Fusionar unitItems (sumar cantidades del mismo tipo)
            booking.unitItems.forEach(function(unit) {
                var existingUnit = null;
                for (var i = 0; i < grouped[key].unitItems.length; i++) {
                    if (grouped[key].unitItems[i].unitType === unit.unitType) {
                        existingUnit = grouped[key].unitItems[i];
                        break;
                    }
                }
                
                if (existingUnit) {
                    existingUnit.quantity += unit.quantity;
                    existingUnit.pricing.original += unit.pricing.original;
                    existingUnit.pricing.retail += unit.pricing.retail;
                } else {
                    grouped[key].unitItems.push({
                        unitType: unit.unitType,
                        unitName: unit.unitName,
                        quantity: unit.quantity,
                        pricing: {
                            original: unit.pricing.original,
                            retail: unit.pricing.retail,
                            currency: unit.pricing.currency || 'EUR'
                        },
                        contact: unit.contact || booking.contact
                    });
                }
            });
        });
        
        // Convertir a array y ordenar
        var keys = Object.keys(grouped).sort();
        var result = [];
        
        keys.forEach(function(key) {
            var group = grouped[key];
            
            // Construir booking consolidado
            var firstBooking = group.bookings[0] || {};
            var consolidatedBooking = {
                id: generateId('booking'),
                productId: group.productId,
                productName: group.productName || 'Actividad',
                optionId: group.optionId || 'tour',
                optionType: group.optionType || 'tour',
                status: 'CONFIRMED',
                localDateTimeStart: firstBooking.localDateTimeStart || group.date + 'T00:00:00+00:00',
                localDateTimeEnd: firstBooking.localDateTimeEnd || group.date + 'T23:59:59+00:00',
                timezone: firstBooking.timezone || 'Europe/Madrid',
                language: group.language,
                unitItems: group.unitItems,
                contact: group.contact || firstBooking.contact,
                pricing: {
                    original: group.totalOriginal,
                    retail: group.totalRetail,
                    currency: group.currency
                },
                metadata: {
                    grouped: true,
                    original_bookings: group.bookings.length,
                    characteristics: {
                        date: group.date,
                        product: group.productId,
                        option: group.optionId,
                        language: group.language
                    }
                }
            };
            
            result.push({
                key: key,
                date: group.date,
                productId: group.productId,
                productName: group.productName,
                optionId: group.optionId,
                language: group.language,
                booking: consolidatedBooking,
                summary: {
                    total_original: group.totalOriginal,
                    total_retail: group.totalRetail,
                    currency: group.currency,
                    count: group.bookings.length,
                    unit_types: group.unitItems.length
                }
            });
        });
        
        // Log detallado
        console.log('📊 Bookings separados por combinación única:');
        result.forEach(function(item, index) {
            console.log('  ' + (index + 1) + '. 📆 ' + item.date + ' | ' + item.productName + ' | ' + item.optionId + ' | ' + item.language);
            console.log('     - ' + item.summary.count + ' booking(s) originales consolidados');
            console.log('     - ' + item.summary.unit_types + ' tipo(s) de unidad');
            console.log('     - Total: ' + item.summary.total_retail + ' ' + item.summary.currency);
        });
        
        return result;
    }

    /**
     * Construye la sección de documentos
     */
    function buildDocuments(vouchers, tickets, mergedPdfUrl) {
        var documents = {
            vouchers: (vouchers || []).map(function(v) {
                return {
                    activity_name: v.activity_name || 'Actividad',
                    url: v.url,
                    type: "voucher",
                    booking_id: v.booking_id || null
                };
            })
        };

        // Salesforce recibe solo la URL de descarga del PDF fusionado, no su
        // contenido -- no hace falta (ni conviene) mandar el PDF en base64 aquí.
        if (mergedPdfUrl) {
            documents.merged_voucher = {
                url: mergedPdfUrl,
                type: "merged_voucher",
                count: (vouchers || []).length,
                description: "Todos los vouchers fusionados en un solo PDF"
            };
        }
        
        if (tickets && tickets.length > 0) {
            documents.tickets = tickets.map(function(t) {
                return {
                    activity_name: t.activity_name || 'Actividad',
                    ticket_id: t.ticket_id,
                    url: t.url,
                    type: "ticket",
                    booking_id: t.booking_id || null
                };
            });
        }
        
        return documents;
    }

    /**
     * Normaliza la orden con bookings separados
     */
    function normalizeOrderWithSeparatedBookings(orderData, separatedBookings) {
        if (!orderData || typeof orderData !== 'object') {
            return {
                id: generateId('order'),
                supplierReference: generateId('ref'),
                status: "CONFIRMED",
                utcCreatedAt: new Date().toISOString(),
                utcUpdatedAt: new Date().toISOString(),
                bookings: [],
                pricing: { original: 0, retail: 0, currency: 'EUR' }
            };
        }
        
        var totalOriginal = 0;
        var totalRetail = 0;
        var currency = 'EUR';
        
        separatedBookings.forEach(function(item) {
            totalOriginal += (item.summary.total_original || 0);
            totalRetail += (item.summary.total_retail || 0);
            if (item.summary.currency) {
                currency = item.summary.currency;
            }
        });
        
        var firstBooking = separatedBookings.length > 0 ? separatedBookings[0].booking : null;
        var contact = firstBooking ? firstBooking.contact : normalizeContact(orderData.contact);
        
        return {
            id: orderData.id || orderData.order_uuid || generateId('order'),
            supplierReference: orderData.supplierReference || orderData.identifier || orderData.id || generateId('ref'),
            status: orderData.status || "CONFIRMED",
            utcCreatedAt: orderData.utcCreatedAt || orderData.created_at || new Date().toISOString(),
            utcUpdatedAt: orderData.utcUpdatedAt || orderData.updated_at || new Date().toISOString(),
            contact: contact,
            pricing: {
                original: totalOriginal,
                retail: totalRetail,
                currency: currency,
                breakdown: {
                    subtotal: totalRetail,
                    taxes: 0,
                    discount: 0,
                    total: totalRetail
                }
            },
            bookings: separatedBookings.map(function(item) { 
                return item.booking.id; 
            })
        };
    }

    // ============================================================
    // FIN DE LAS NUEVAS FUNCIONES
    // ============================================================
    

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
                '<div class="musement-tour__slots"></div>' +
                '<div class="musement-tour__calendar"></div>' +
            '</div>';

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
            type: product.type || 'musement',
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
                select.style.padding = '4px 6px';
                select.style.borderRadius = '4px';
                select.style.border = '1px solid #cbd5e1';
                select.style.fontSize = '12px';
                
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
                                type: product.type || 'musement',
                                name: product.name,
                                availability: product.availability,
                                holder_code: product.holder_code || '',
                                holder_code_normalized: product.holder_code_normalized || '',
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
                                    type: product.type || 'musement',
                                    name: product.name,
                                    availability: product.availability,
                                    holder_code: product.holder_code || '',
                                    holder_code_normalized: product.holder_code_normalized || '',
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

        // frontend.js se carga en TODAS las páginas (wp_enqueue_scripts no lo
        // limita a ninguna página en concreto), así que antes esto metía la barra
        // flotante de carrito en cualquier sitio, incluida la home. Solo debe
        // aparecer donde de verdad se puede añadir algo al carrito: la página del
        // calendario de un producto ([musement_certified_calendar]).
        if (document.querySelector('.musement-certified-calendar-wrapper')) {
            ensureCartBar();
        }

        // MUSEMENT.cartUuid hace falta en CUALQUIER página con checkout, no solo
        // en el calendario -- antes solo se rellenaba dentro de ensureCartBar(),
        // así que en la página de checkout siempre quedaba vacío y el botón
        // "Pagar" fallaba con "No se encontró el carrito" incluso con productos
        // ya añadidos. Se pide aparte (sin la barra flotante) si no se pidió ya
        // arriba.
        if (!document.querySelector('.musement-certified-calendar-wrapper')) {
            ensureCartUuid();
        }
    });

    function money(value, currency) {
        return Number(value || 0).toFixed(2) + ' ' + (currency || 'EUR');
    }

    // ============================================================
    // TARJETA DE CONFIRMACIÓN ("gracias por tu compra")
    // ============================================================
    // Colores/enlaces de marca usados en la tarjeta -- cambia aquí, no dentro
    // de buildConfirmationCardHtml, para no tener que tocar el HTML/CSS.
    const CONFIRMATION_CONFIG = {
        primaryColor: '#1b4b4b',
        darkColor: '#123838',
        appStoreUrl: 'https://apps.apple.com/es/app/my-top-tour/id6743216454',
        appStoreBadgeUrl: 'https://tools.applemediaservices.com/api/badges/download-on-the-app-store/black/en-us',
        googlePlayUrl: 'https://play.google.com/store/apps/details?id=com.orbitaonline.demotours&hl=es',
        googlePlayBadgeUrl: 'https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png',
        contactUrl: 'mailto:ops@example.com'
    };

    function mttEscapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function mttFormatBookingDateTime(isoString) {
        if (!isoString) return '';
        const d = new Date(isoString);
        if (isNaN(d.getTime())) return '';
        const dateLabel = d.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
        const timeLabel = d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        return dateLabel + ' · ' + timeLabel + 'h';
    }

    // El primer booking del pedido basta: en este checkout solo se compra un
    // producto/fecha por pedido, así que no hace falta listar varios.
    function mttGetPrimaryBookingSummary(octoPayload) {
        const bookings = (octoPayload && octoPayload.order && octoPayload.order.bookings) || [];
        const booking = bookings[0] || {};
        const productName = (booking.product && booking.product.internalName) || '';
        const optionName = (booking.option && booking.option.internalName) || '';
        const unitItems = booking.unitItems || [];
        const dateTimeIso = unitItems[0] ? unitItems[0].localDateTimeStart : '';

        return {
            productName: productName,
            optionHtml: (optionName && optionName !== 'DEFAULT')
                ? '<p class="mtt-product-option">' + mttEscapeHtml(optionName) + '</p>'
                : '',
            datetimeHtml: dateTimeIso
                ? '<p class="mtt-product-date-time">' + mttEscapeHtml(mttFormatBookingDateTime(dateTimeIso)) + '</p>'
                : ''
        };
    }

    function buildConfirmationCardHtml(data) {
        const c = CONFIRMATION_CONFIG;
        const ticketsUrl = data.ticketsUrl || '';

        return `
<div class="mtt-confirmation-card">
    <style>
        .mtt-confirmation-card { max-width: 600px; margin: 0 auto; background: #ffffff !important; border-radius: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.06); overflow: hidden; }
        .mtt-confirmation-header { text-align: center; padding: 30px 20px 15px; }
        .mtt-confirmation-check { width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 50%; border: 2px solid ${c.primaryColor}; color: ${c.primaryColor}; font-size: 30px; line-height: 52px; font-weight: 700; }
        .mtt-confirmation-title { font-size: 22px !important; line-height: 1.25 !important; margin: 0 !important; color: #141414 !important; font-weight: 800 !important; }
        .mtt-booking-number { font-size: 13px; color: #666666; margin: 6px 0 0; }
        .mtt-divider { border: none; border-top: 1px solid #eeeeee; margin: 0 20px 20px; }
        .mtt-product-wrap { text-align: center; padding: 0 20px; }
        .mtt-product-box { display: inline-block; text-align: left; background: #fafafa; border: 1px solid #eeeeee; border-radius: 10px; padding: 12px 16px; max-width: 100%; }
        .mtt-product-title { font-size: 18px !important; line-height: 1.3 !important; margin: 0 0 6px !important; color: #141414 !important; font-weight: 800 !important; }
        .mtt-product-option { font-size: 13px; color: #777777; margin: 0 0 10px; }
        .mtt-product-date-time { font-size: 14px; color: #333333; margin: 0; line-height: 1.7; }
        .mtt-steps { padding: 20px; }
        .mtt-email-box { padding: 0 24px; text-align: center; }
        .mtt-email-box p:first-child { font-size: 14px; line-height: 1.6; margin: 0; color: #333333; }
        .mtt-email-box strong { color: ${c.primaryColor}; }
        .mtt-spam { font-size: 12px !important; color: #777777 !important; margin: 6px 0 0 !important; }
        .mtt-app-wrap { padding: 0 20px 20px; }
        .mtt-app-highlight { position: relative; overflow: hidden; background: radial-gradient(circle at top left, rgba(255,255,255,0.18), transparent 32%), linear-gradient(135deg, ${c.primaryColor} 0%, ${c.darkColor} 42%, #141414 100%); border-radius: 16px; padding: 24px 20px; text-align: center; color: #ffffff; box-shadow: 0 12px 28px rgba(0,0,0,0.18); }
        .mtt-app-highlight::before { content: ""; position: absolute; top: -60px; right: -60px; width: 150px; height: 150px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .mtt-app-title { font-size: 19px; line-height: 1.3; font-weight: 800; margin: 0 0 8px; color: #ffffff; }
        .mtt-app-text { max-width: 430px; margin: 0 auto 18px; font-size: 13px; line-height: 1.55; color: rgba(255,255,255,0.9); }
        .mtt-app-links { position: relative; z-index: 1; text-align: center; }
        .mtt-app-link { display: inline-block; margin: 5px; vertical-align: middle; transition: transform 0.22s ease, filter 0.22s ease; }
        .mtt-app-link:hover { transform: translateY(-2px) scale(1.02); filter: brightness(1.08); }
        .mtt-app-link img { display: block; border: 0; }
        .mtt-ticket-access { padding: 0 20px 20px; text-align: center; }
        .mtt-ticket-text { font-size: 13px; line-height: 1.45; color: #555555; margin: 0 0 10px; }
        .mtt-ticket-link { display: inline-block; color: ${c.primaryColor} !important; background: #ffffff; border: 1px solid ${c.primaryColor}; padding: 13px 26px; border-radius: 8px; font-size: 14px; font-weight: 700; text-decoration: none; transition: all 0.22s ease; }
        .mtt-ticket-link:hover { background: ${c.darkColor} !important; color: #ffffff !important; border-color: ${c.darkColor} !important; transform: translateY(-1px); box-shadow: 0 5px 12px rgba(0,0,0,0.16); }
        .mtt-contact { text-align: center; font-size: 12px; color: #888888; margin: 0 0 20px; padding: 0 20px; }
        .mtt-contact a { color: ${c.primaryColor}; text-decoration: none; }
        .mtt-ticket-link:focus-visible, .mtt-app-link:focus-visible { outline: 3px solid rgba(153,0,0,0.28); outline-offset: 3px; }
        @media (max-width: 480px) {
            .mtt-confirmation-card { border-radius: 12px; }
            .mtt-product-box { display: block; }
            .mtt-email-box { padding: 0 4px; }
            .mtt-app-highlight { padding: 22px 14px; }
            .mtt-app-link img[alt*="App"] { width: 124px !important; }
            .mtt-app-link img[alt*="Google"] { width: 140px !important; }
        }
    </style>

    <div class="mtt-confirmation-header">
        <div class="mtt-confirmation-check" aria-hidden="true">✓</div>
        <h2 class="mtt-confirmation-title">${mttEscapeHtml(data.title)}</h2>
        <p class="mtt-booking-number">${mttEscapeHtml(data.bookingNumberLabel)} <strong>${mttEscapeHtml(data.bookingNumber)}</strong></p>
    </div>

    <hr class="mtt-divider">

    <div class="mtt-product-wrap">
        <div class="mtt-product-box">
            <h3 class="mtt-product-title">${mttEscapeHtml(data.productName)}</h3>
            ${data.productOptionHtml || ''}
            ${data.productDatetimeHtml || ''}
        </div>
    </div>

    <hr class="mtt-divider" style="margin-top:20px; margin-bottom:0;">

    <div class="mtt-steps">
        <div class="mtt-email-box">
            <p>Te hemos enviado la confirmación a<br><strong>${mttEscapeHtml(data.email)}</strong></p>
            <p class="mtt-spam">Si no la ves en unos minutos, revisa también la carpeta de spam.</p>
        </div>
    </div>

    <div class="mtt-app-wrap">
        <div class="mtt-app-highlight">
            <p class="mtt-app-title">Lleva tus entradas siempre contigo</p>
            <p class="mtt-app-text">Descarga nuestra app y accede a tus entradas (y a tu audioguía, si la compraste) el día de la visita, sin buscar el email.</p>
            <div class="mtt-app-links">
                <a href="${c.appStoreUrl}" target="_blank" rel="noopener" class="mtt-app-link" data-mtt-track="app_store">
                    <img src="${c.appStoreBadgeUrl}" alt="Descargar en App Store" style="width:132px; height:auto; border-radius:7px;">
                </a>
                <a href="${c.googlePlayUrl}" target="_blank" rel="noopener" class="mtt-app-link" data-mtt-track="google_play">
                    <img src="${c.googlePlayBadgeUrl}" alt="Descargar en Google Play" style="width:148px; height:auto;">
                </a>
            </div>
        </div>
    </div>

    <div class="mtt-ticket-access">
        <p class="mtt-ticket-text">También puedes ver y descargar tus entradas ahora mismo.</p>
        <a href="${mttEscapeHtml(ticketsUrl || '#')}" target="_blank" rel="noopener" class="mtt-ticket-link" data-mtt-track="view_tickets"${ticketsUrl ? '' : ' aria-disabled="true"'}>
            ${ticketsUrl ? 'Ver mis entradas' : 'Generando tus entradas…'}
        </a>
    </div>

    <p class="mtt-contact">
        ¿Tienes alguna duda?
        <a href="${c.contactUrl}" target="_blank" rel="noopener">Contacta con nosotros</a>
    </p>
</div>`;
    }

    // Actualiza el botón de "ver mis entradas" de la tarjeta una vez que el
    // voucher está listo. Los vouchers casi nunca están listos al instante
    // (por eso existe todo el sondeo de startVoucherMonitoring), así que esto
    // se llama tanto justo tras el pago (por si ya hubiera vouchers) como más
    // tarde, en cuanto el sondeo los encuentra.
    function renderThankYouDownloadButton(checkout, url) {
        if (!checkout || !url) {
            return;
        }

        const link = checkout.querySelector('.mtt-ticket-link');
        if (!link) {
            return;
        }

        link.href = url;
        link.textContent = 'Ver mis entradas';
        link.removeAttribute('aria-disabled');
    }

    // Pinta la misma tarjeta que se muestra tras un pago real (ver el .then()
    // de éxito en initCheckout), pero con datos de relleno y sin llamar a
    // ningún endpoint: solo para poder maquetar/ajustar el CSS del "gracias"
    // sin generar pedidos ni reservas reales. Ver activación en initCheckout.
    function renderThankYouPreview(checkout) {
        const itemsBox = checkout.querySelector('.musement-checkout__items');
        const checkoutForm = checkout.querySelector('.musement-checkout__form');
        const status = checkout.querySelector('.musement-checkout__status');

        if (itemsBox) {
            itemsBox.innerHTML = buildConfirmationCardHtml({
                title: '¡Reserva confirmada!',
                bookingNumberLabel: 'Nº de reserva',
                bookingNumber: 'MUS00000000',
                productName: 'Entrada al Palacio de Versalles con Audioguía',
                productOptionHtml: '<p class="mtt-product-option">Adulto</p>',
                productDatetimeHtml: '<p class="mtt-product-date-time">' + mttFormatBookingDateTime(new Date().toISOString()) + '</p>',
                email: 'cliente@ejemplo.com',
                ticketsUrl: '#'
            });
        }

        if (checkoutForm) {
            checkoutForm.hidden = true;
        }

        if (status) {
            status.textContent = '';
        }
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

        // Con el carrito vacio el enlace al checkout no lleva a ningun sitio util,
        // asi que se apaga hasta que haya algo que pagar.
        const checkoutLink = document.querySelector('.musement-cart-bar__checkout');

        if (checkoutLink) {
            const hasItems = items.length > 0;

            checkoutLink.classList.toggle('is-disabled', !hasItems);
            checkoutLink.setAttribute('aria-disabled', hasItems ? 'false' : 'true');
        }
    }

    function ensureCartUuid() {
        return post('musement_remote_cart_get').then(function (json) {
            console.log("MUSEMENT REMOTE CART 168 : ", json);
            MUSEMENT.cartUuid = json && json.data ? json.data.uuid : MUSEMENT.cartUuid;
        });
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
            // Arranca apagado y se enciende en updateCartBadge() al saber que hay
            // productos, para que no haya un instante en que se pueda pulsar.
            '<a class="musement-cart-bar__checkout is-disabled" aria-disabled="true" href="' + (MUSEMENT.checkoutUrl || '#') + '">Checkout</a>';
        document.body.appendChild(bar);

        ensureCartUuid();

        post('musement_cart_get').then(function (json) {

            console.log("MUSEMENT CART: ", json);
            if (json && json.success) {
                updateCartBadge(json.data);
            }
        });
    }

    /**
     * "2026-09-11" -> "11 sept 2026". Si la fecha no viene o no se puede
     * interpretar se devuelve tal cual, que siempre es mejor que un "Invalid Date".
     */
    function formatCheckoutDate(value) {
        if (!value) {
            return '';
        }

        const parts = String(value).split('-');

        if (parts.length !== 3) {
            return value;
        }

        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

        if (isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
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

            if (item.image) {
                const media = document.createElement('div');
                const img = document.createElement('img');

                media.className = 'musement-checkout__item-media';
                img.src = item.image;
                img.alt = item.title || 'Tour';
                img.loading = 'lazy';
                // Si la portada no carga, se quita el hueco en vez de dejar el
                // icono de imagen rota en mitad del resumen.
                img.addEventListener('error', function () {
                    media.remove();
                });

                media.appendChild(img);
                row.appendChild(media);
            }

            const info = document.createElement('div');
            const title = document.createElement('strong');
            const meta = document.createElement('span');

            info.className = 'musement-checkout__item-info';
            title.textContent = item.title || 'Tour';
            meta.textContent = [
                formatCheckoutDate(item.date),
                // "00:00" no es una hora real: es lo que se guarda cuando el
                // producto no tiene horario fijo (ver musement_cart_add_ajax),
                // así que enseñarla solo confunde.
                item.time && item.time !== '00:00' ? item.time : '',
                item.product_name
            ].filter(Boolean).join(' · ');

            info.appendChild(title);
            info.appendChild(meta);
            row.appendChild(info);

            const price = document.createElement('div');
            price.className = 'musement-checkout__item-price';
            price.innerHTML = '<strong></strong><span></span>';
            price.querySelector('strong').textContent = money(
                Number(item.unit_price || 0) * Number(item.quantity || 1),
                item.currency
            );
            price.querySelector('span').textContent = 'x' + item.quantity;
            row.appendChild(price);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Quitar';
            row.appendChild(remove);

            remove.addEventListener('click', function () {
                const removeBtn = this;
                const submitBtn = checkout.querySelector('.musement-checkout__submit');

                // Este botón no se desactivaba: si la eliminación tardaba (llama
                // al DELETE de Musement, puede ir lento) el usuario podía pulsar
                // "Pagar" mientras la eliminación seguía en curso -- dos
                // peticiones mutando el mismo carrito de sesión a la vez podían
                // dejar un producto duplicado en el pedido final. El servidor ya
                // lo bloquea (musement_cart_session_lock_acquire()), pero además
                // evitamos aquí que se pueda disparar esa carrera desde el propio
                // navegador.
                removeBtn.disabled = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                post('musement_cart_remove', { key: item.key }).then(function (json) {
                    if (json && json.success) {
                        renderCheckout(checkout, json.data);
                        updateCartBadge(json.data);
                        window.location.reload();
                        return;
                    }

                    removeBtn.disabled = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }).catch(function () {
                    removeBtn.disabled = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
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

    /**
     * Añade al formulario principal los campos de cliente que Musement exige
     * para esta actividad (musement_extract_missing_customer_fields() en el
     * servidor) -- distintos para cada proveedor, así que se construyen en
     * tiempo real a partir de lo que Musement devuelve, no de una lista fija.
     *
     * Antes esto era una caja aparte con su propio botón "Continuar", que
     * aparecía solo DESPUÉS de intentar pagar. Ahora los campos se pintan
     * junto a nombre/email/teléfono, así que el cliente los rellena de una
     * sola vez y "Pagar" hace el pago de verdad.
     *
     * @return {boolean} true si ha añadido algún campo nuevo.
     */
    function renderExtraCustomerFields(checkout, missingFields) {
        const fields = checkout.querySelector('.musement-checkout__fields');

        if (!fields || !missingFields || !missingFields.length) {
            return false;
        }

        let added = false;

        missingFields.forEach(function (field) {
            if (!field || !field.key || checkout.querySelector('[data-extra-customer-field="' + field.key + '"]')) {
                return; // ya estaba pintado
            }

            const label = document.createElement('label');
            const input = document.createElement('input');

            label.className = 'musement-checkout__extra-field';
            label.textContent = field.label + (field.required ? ' *' : '');

            // Sin atributo "name": el envío recoge el formulario con FormData y
            // estos campos no van sueltos en el payload, sino agrupados dentro de
            // "extra_customer_data" (ver collectExtraCustomerData).
            input.type = field.type === 'date' ? 'date' : (field.type === 'number' ? 'number' : 'text');
            input.required = !!field.required;
            input.dataset.extraCustomerField = field.key;

            label.appendChild(input);
            fields.appendChild(label);
            added = true;
        });

        return added;
    }

    function extraCustomerFieldInputs(checkout) {
        return Array.prototype.slice.call(checkout.querySelectorAll('[data-extra-customer-field]'));
    }

    function collectExtraCustomerData(checkout) {
        const values = {};

        extraCustomerFieldInputs(checkout).forEach(function (input) {
            values[input.dataset.extraCustomerField] = input.value.trim();
        });

        return values;
    }

    /**
     * Igual que extraCustomerFieldsComplete pero sin marcar nada en rojo: se usa
     * para decidir en silencio si ya se puede montar la tarjeta, y sería muy
     * desagradable teñir de rojo un campo que el cliente aún no ha tocado.
     */
    function extraCustomerFieldsFilled(checkout) {
        return extraCustomerFieldInputs(checkout).every(function (input) {
            return !input.required || input.value.trim();
        });
    }

    function extraCustomerFieldsComplete(checkout) {
        let complete = true;

        extraCustomerFieldInputs(checkout).forEach(function (input) {
            const missing = input.required && !input.value.trim();

            input.classList.toggle('has-error', missing);

            if (missing) {
                complete = false;
            }
        });

        return complete;
    }

    /**
     * Monta el formulario de tarjeta solo, sin que el cliente pulse Pagar, en
     * cuanto están todos los datos necesarios (los suyos y los que exija la
     * actividad, como la edad).
     *
     * No se puede hacer antes: los huecos de la tarjeta necesitan un
     * client_secret de Stripe, y ese sale de musement_checkout_start(), que crea
     * el pedido local con los datos del cliente. Sin cliente no hay pedido que
     * crear -- de ahí que se espere a tener el formulario completo en vez de
     * montarlo al cargar la página.
     */
    function maybeAutoStartPayment(checkout, form) {
        // _stripePayment: ya montado. _autoStarting: petición en vuelo -- sin este
        // candado un segundo "change" reenviaría el formulario y se crearían dos
        // pedidos para el mismo carrito.
        if (checkout._stripePayment || checkout._autoStarting || form.hidden) {
            return;
        }

        // Hasta que no se sabe si la actividad exige algún campo extra no se
        // arranca: si no, se enviaría el formulario "completo" justo antes de que
        // apareciera el campo de la edad, y el envío rebotaría para nada.
        if (!checkout._requirementsChecked) {
            return;
        }

        const data = {};

        new FormData(form).forEach(function (value, key) {
            data[key] = value;
        });

        if (!data.first_name || !data.last_name || !data.email || !data.phone) {
            return;
        }

        if (!extraCustomerFieldsFilled(checkout)) {
            return;
        }

        checkout._autoStarting = true;
        form.requestSubmit();
    }

    /**
     * Pregunta a Musement si esta actividad necesita algún dato extra del
     * cliente, en cuanto hay datos suficientes para preguntarlo. Es la misma
     * llamada que haría "Pagar", solo que adelantada, así que el campo aparece
     * antes de pagar en vez de rebotar el pago.
     *
     * Si falla se ignora en silencio: el descubrimiento al pagar sigue estando
     * como respaldo en el propio musement_checkout_start.
     */
    function probeCustomerRequirements(checkout, form, force) {
        const data = {};

        new FormData(form).forEach(function (value, key) {
            data[key] = value;
        });

        // Con "force" (el sondeo de la carga de página) se pregunta aunque no
        // haya ni un dato del cliente: es lo que permite enseñar el campo de
        // edad desde el principio. El resto de sondeos esperan a tener los
        // cuatro datos base para no preguntar a medio rellenar.
        if (!force && (!data.first_name || !data.last_name || !data.email || !data.phone)) {
            return Promise.resolve();
        }

        const extra = collectExtraCustomerData(checkout);
        // Rellenar un campo extra puede destapar otro (Musement valida por
        // niveles), así que su valor entra en la firma y vuelve a preguntarse.
        const signature = JSON.stringify([data.first_name, data.last_name, data.email, data.phone, extra]);

        if (checkout._requirementsSignature === signature) {
            return Promise.resolve();
        }

        checkout._requirementsSignature = signature;

        return post('musement_checkout_customer_requirements', {
            first_name: data.first_name,
            last_name: data.last_name,
            email: data.email,
            phone: data.phone,
            extra_customer_data: JSON.stringify(extra)
        }).then(function (json) {
            if (json && json.success && json.data) {
                renderExtraCustomerFields(checkout, json.data.missing_fields || []);
            }
        }).catch(function () {
            checkout._requirementsSignature = null;
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

        if (vouchers.length > 0 && orderUuid){
            console.log('═══════════════════════════════════════════════════════════');
            console.log('📦 INICIO PROCESO DE FUSIONADO DE LOS VOUCHERS. ');
            console.log('═══════════════════════════════════════════════════════════');
            fetch(MUSEMENT.ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'musement_merge_vouchers',
                    nonce: MUSEMENT.nonce,
                    order_uuid: orderUuid,
                    voucher_urls: JSON.stringify(vouchers.map(v => v.url)),
                    voucher_names: JSON.stringify(vouchers.map(v => v.activity_name))
                })
            })
            .then(r => r.json())
            .then(result => {
                console.log('═══════════════════════════════════════════════════════════');
                console.log('📦 RESULTADO DEL PROCESO DE FUSIONADO DE LOS VOUCHERS.    ');
                console.log('═══════════════════════════════════════════════════════════');

                let mergedPdfUrl = null;

                if (result.success && result.data.merged_pdf) {
                    // Solo nos interesa la URL publica de descarga; el payload que
                    // recibe Salesforce no lleva el PDF fusionado, solo su enlace.
                    mergedPdfUrl = result.data.merged_pdf;

                    console.log('✅ PDF fusionado correctamente:', result.data.merged_pdf);
                } else {
                    // La fusión falló (p.ej. FPDI no disponible, algún voucher no se pudo
                    // descargar). Antes esto se quedaba aquí sin hacer nada más -- la
                    // reserva jamás llegaba a Salesforce. Ahora se manda igualmente el
                    // payload con las URLs de cada voucher/ticket individual (sin PDF
                    // fusionado) para no perder el aviso.
                    console.warn('⚠️ No se pudo fusionar, se enviarán los vouchers/tickets individuales');
                }

                const finalPayload = buildFinalPayload(
                    vouchers,
                    tickets,
                    orderData,
                    mergedPdfUrl
                );
                sendPayloadToEndpoint(finalPayload);

                console.log('═══════════════════════════════════════════════════════════');
                console.log('📦 PAYLOAD FINAL ENVIADO A SALESFORCE');
                console.log('═══════════════════════════════════════════════════════════');
                console.log(JSON.stringify(finalPayload, null, 2));
                console.log('🆔 ORDER UUID (para el agente de certificación de Musement): ' + orderUuid);
                console.log('═══════════════════════════════════════════════════════════');
            })
        }
        
        console.log('═══════════════════════════════════════════════════════════');
        console.log('📦 PAYLOAD COMPLETO CON URLs DE VOUCHERS Y TICKETS 589');
        console.log('═══════════════════════════════════════════════════════════');
        console.log(JSON.stringify(payload, null, 2));
        console.log('═══════════════════════════════════════════════════════════');
        
        return payload;
    }

    function sendPayloadToEndpoint(payload) {
        console.log('📤 Enviando payload a endpoint...');

        // Se manda siempre, tenga o no PDF fusionado: aunque no haya merged_voucher,
        // el payload sigue llevando las URLs de cada voucher/ticket individual y eso
        // ya es suficiente para que Salesforce pueda descargar las entradas.
        sendPdfAsFormData(payload);
    }

    /**
     * Envía el payload (con las URLs de descarga) a Salesforce a través del backend
     */
    function sendPdfAsFormData(payload) {
        console.log('📤 Enviando peticion a salesforce desde el backend...');
    
        // 🔥 En lugar de enviar el PDF directamente, pedimos al backend que lo envíe
        fetch(MUSEMENT.ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'musement_send_pdf_to_endpoint',
                nonce: MUSEMENT.nonce,
                order_uuid: orderUuid,
                endpoint_url: 'https://example.my.salesforce-sites.com/services/apexrest/Booking/Musement',
                payload: JSON.stringify(payload)
            })
        })
        .then(r => r.json())
        .then(result => {
            console.log( " INTENTO DE ENVIO ", result );
            if (result.success) {
                console.log('✅ PDF enviado desde el backend:', result.data);
            } else {
                console.error('❌ Error enviando peticion a salesforce como formData:', result.data?.message);
                console.error('❌ Error resultado completo de la respueta:', result);
            }
        })
        .catch(error => {
            console.error('❌ Error:', error);
        });
    }

    /**
     * Construye el payload final con o sin PDF fusionado
     */
    /**
 * 🔥 CONSTRUYE EL PAYLOAD FINAL CON BOOKINGS SEPARADOS POR CARACTERÍSTICAS
 * Esta función reemplaza a la anterior y usa la lógica de separación
 */
function buildFinalPayload(vouchers, tickets, orderData, mergedPdfUrl) {
    console.log('🏗️ Construyendo payload final para Salesforce...');

    // octoPayload ya viene del servidor (musement_build_octo_payload() en
    // payments.php) en formato OCTO, con order.bookings como un booking
    // independiente por cada reserva real de Musement -- una fecha/producto/
    // opción distinta ya es un booking distinto, sin que el cliente tenga que
    // volver a agruparlos ni tocar su forma. Antes esta función reconstruía
    // el payload a mano (fusionando bookings, moviendo "bookings" fuera de
    // "order", sustituyendo cada booking por su solo ID); era un parche para
    // el bug de triplicado que ya está arreglado en el servidor, y de paso
    // generaba un formato no estándar que Salesforce no podía interpretar.
    var payload = (typeof octoPayload !== 'undefined' && octoPayload && octoPayload.order)
        ? JSON.parse(JSON.stringify(octoPayload))
        : { webhook: {}, order: { bookings: [] } };

    payload.documents = buildDocuments(vouchers, tickets, mergedPdfUrl);
    payload.timestamp = new Date().toISOString();

    console.log('📦 Bookings en el payload final:', (payload.order.bookings || []).length);

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
                // Se para el sondeo AQUI, antes de arrancar la cadena async
                // (pedir tickets -> fusionar PDF -> enviar a Salesforce), no
                // al final de ella. Esa cadena puede tardar mas de los 10s del
                // intervalo (varias llamadas de red seguidas), y si el
                // intervalo volvia a disparar mientras tanto, encontraba los
                // mismos vouchers otra vez y mandaba una SEGUNDA copia
                // completa de la reserva a Salesforce -- Musement y Stripe
                // solo se tocan una vez (estan protegidos en el servidor),
                // pero este envio a Salesforce no tenia ninguna proteccion.
                if (monitoringInterval) {
                    clearInterval(monitoringInterval);
                    monitoringInterval = null;
                }

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
                    renderThankYouDownloadButton(checkout, vouchers[0] && vouchers[0].url);
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
    
    // El link "Ver mis entradas" de la tarjeta de confirmación ya lo actualiza
    // renderThankYouDownloadButton() (llamado justo después de esto en
    // checkDocuments()); aquí solo hace falta refrescar el texto de estado.
    // Antes esto además navegaba a otra URL a los 10s, lo que borraba la
    // tarjeta de confirmación que se acababa de mostrar -- se ha quitado.
    function displayDocuments(vouchers, tickets, statusElement) {
        statusElement.textContent = 'Reserva confirmada. Tus entradas ya están disponibles.';
    }
    
    checkDocuments();
    monitoringInterval = setInterval(checkDocuments, 10000);
}


    function initCheckout(checkout) {
        // Si el contenido de la página se procesa más de una vez en la misma
        // carga (habitual con plugins de SEO/algunos temas que pasan el
        // contenido por "the_content" más de una vez), este bloque puede
        // aparecer duplicado en el HTML y querySelectorAll('[data-musement-checkout]')
        // encuentra ambos, inicializando el checkout dos veces -- dos listeners
        // de "submit" sobre el (o los) formulario(s), y un único clic en Pagar
        // dispara los dos: dos ejecuciones completas del cobro y la creación
        // del pedido para el mismo carrito, una detrás de otra (por el bloqueo
        // de sesión de PHP, no en paralelo). Esto es lo que provocaba que un
        // producto se añadiera varias veces al carrito remoto de Musement en
        // una sola compra. Marcamos el elemento para que solo se inicialice
        // una vez.
        if (checkout.dataset.musementInitialized === 'true') {
            return;
        }
        checkout.dataset.musementInitialized = 'true';

        // Previsualización de la pantalla de "gracias" sin pasar por el pago
        // real (ni crear pedido/reserva de verdad): útil solo para ajustar el
        // CSS. Se activa entrando a la página de checkout con
        // "#musement-preview-thankyou" al final de la URL. Se usa el hash (y
        // no un "?parametro") porque el hash nunca se envía al servidor, así
        // que ni una redirección canónica de WordPress ni ningún caché de
        // página pueden recortarlo antes de que este script lo lea.
        if (window.location.hash === '#musement-preview-thankyou') {
            renderThankYouPreview(checkout);
            return;
        }

        initPaymentAccordion(checkout);

        const checkoutForm = checkout.querySelector('.musement-checkout__form');

        post('musement_cart_get').then(function (json) {
            if (!json || !json.success) {
                return;
            }

            renderCheckout(checkout, json.data);

            if (!json.data || !json.data.items || !json.data.items.length) {
                return;
            }

            // Nada más pintar el carrito se pregunta si esta actividad exige algún
            // dato extra (edad, documento...), para que el campo esté ahí desde el
            // principio en vez de aparecer después de pulsar Pagar.
            probeCustomerRequirements(checkout, checkoutForm, true).then(function () {
                checkout._requirementsChecked = true;
                maybeAutoStartPayment(checkout, checkoutForm);
            });
        });

        // Y se vuelve a preguntar cuando el cliente termina de rellenar sus datos:
        // con el cliente ya identificado Musement puede exigir algo más. "change"
        // salta al salir de cada campo, así que no se pregunta en cada tecla.
        checkoutForm.addEventListener('change', function () {
            probeCustomerRequirements(checkout, checkoutForm).then(function () {
                maybeAutoStartPayment(checkout, checkoutForm);
            });
        });

        checkoutForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const form = event.currentTarget;
            const status = checkout.querySelector('.musement-checkout__status');
            const submit = form.querySelector('button[type="submit"]');
            const payload = {};

            if (checkout._stripePayment) {
                status.textContent = 'Confirmando pago...';
                submit.disabled = true;

                function createMusementOrderAfterPayment(checkout, cartUuid, paymentIntentId) {
                    const form = checkout.querySelector('.musement-checkout__form');
                    const formData = new FormData(form);
                    const firstName = formData.get('first_name') || '';
                    const lastName = formData.get('last_name') || '';
                    const email = formData.get('email') || '';
                    const phone = formData.get('phone') || '';

                    return post('musement_create_order', {
                        cart_uuid: cartUuid,
                        payment_intent_id: paymentIntentId,
                        first_name: firstName,
                        last_name: lastName,
                        email: email,
                        phone: phone,
                        extra_customer_data: JSON.stringify(
                            Object.assign({}, checkout._extraCustomerData || {}, collectExtraCustomerData(checkout))
                        )
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

                    return createMusementOrderAfterPayment(checkout, cartUuid, intent.id);
                    
                }).then(function (orderData){
                    const order = orderData.order || orderData;
                    const vouchers = orderData.vouchers || [];
                    // "identifier" es la referencia legible de Musement (p.ej. "MUS16469558"),
                    // el localizador de verdad -- antes se priorizaba "uuid" (un id interno
                    // largo tipo e6e7dfe3-6310-...) y encima se recortaba a 8 caracteres para
                    // que "cupiera", dejando un localizador que no servía ni para identificar
                    // la reserva ni para nada.
                    const bookingRef = order.identifier || order.uuid || orderData.order_uuid || '';
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
                    
                    // El envío a Salesforce NO pasa por aquí: eso lo hace siempre
                    // startVoucherMonitoring() más abajo, por el único camino que de
                    // verdad llega a Salesforce (fusión de vouchers + musement_send_pdf_to_endpoint).
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

                    // El backend ya vació el carrito (musement_cart_clear() dentro de
                    // musement_create_order()), pero la lista de productos del checkout
                    // seguía mostrando los mismos items en pantalla porque nada la
                    // volvía a pintar tras el pago -- daba la impresión de que "los
                    // productos se quedan en el carrito" aunque la compra ya estaba hecha.
                    // Ahora se muestra la tarjeta de confirmación en su lugar.
                    const itemsBox = checkout.querySelector('.musement-checkout__items');
                    const bookingSummary = mttGetPrimaryBookingSummary(octoPayload);
                    if (itemsBox) {
                        itemsBox.innerHTML = buildConfirmationCardHtml({
                            title: '¡Reserva confirmada!',
                            bookingNumberLabel: 'Nº de reserva',
                            bookingNumber: bookingRef,
                            productName: bookingSummary.productName,
                            productOptionHtml: bookingSummary.optionHtml,
                            productDatetimeHtml: bookingSummary.datetimeHtml,
                            email: customerData.email,
                            ticketsUrl: vouchers[0] ? vouchers[0].url : ''
                        });
                    }
                    const checkoutForm = checkout.querySelector('.musement-checkout__form');
                    if (checkoutForm) {
                        checkoutForm.hidden = true;
                    }
                    updateCartBadge({ items: [], summary: { total: 0, currency: 'EUR' } });

                    if (vouchers && vouchers.length > 0) {
                        status.textContent = `Reserva confirmada. Nº: ${bookingRef}.`;
                        renderThankYouDownloadButton(checkout, vouchers[0].url);
                    } else {
                        status.textContent = `Pago confirmado. Nº reserva: ${bookingRef}. Generando tus entradas...`;
                    }

                    // Independientemente de si Musement ha devuelto el voucher al instante o
                    // hay que esperarlo, el aviso a Salesforce con la URL de descarga siempre
                    // pasa por aquí: startVoucherMonitoring() comprueba el estado nada más
                    // entrar (si ya hay voucher, lo detecta en el primer intento) y ese es el
                    // único código que fusiona los vouchers y los envía al endpoint real.
                    startVoucherMonitoring(orderUuid, checkout, status, bookingRef, orderDataCompleto, octoPayload);
                }).catch(function (error) {
                    setPaymentError(checkout, error.message);
                    status.textContent = '❌ ' + error.message;
                    submit.disabled = false;
                    checkout._stripePaymentConfirmed = false;
                });

                return;
            }

            // Los campos extra que Musement pidió se rellenan ya en este mismo
            // formulario, así que se validan aquí y no en un paso aparte.
            if (!extraCustomerFieldsComplete(checkout)) {
                status.textContent = 'Completa los datos que faltan para la reserva.';
                return;
            }

            new FormData(form).forEach(function (value, key) {
                payload[key] = value;
            });
            payload.extra_customer_data = JSON.stringify(
                Object.assign({}, checkout._extraCustomerData || {}, collectExtraCustomerData(checkout))
            );

            status.textContent = 'Preparando pago...';
            submit.disabled = true;

            post('musement_checkout_start', payload).then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data ? json.data.message : '');
                }

                if (json.data && json.data.requires_extra_customer_data) {
                    // Respaldo: normalmente el sondeo de probeCustomerRequirements()
                    // ya ha pintado estos campos antes de llegar aquí. Si aun así
                    // Musement descubre uno nuevo al pagar, se añade al formulario
                    // y el cliente lo rellena sin salir de la misma pantalla.
                    renderExtraCustomerFields(checkout, json.data.missing_fields || []);
                    status.textContent = 'Falta un dato para completar la reserva.';
                    submit.disabled = false;

                    const pending = extraCustomerFieldInputs(checkout).filter(function (input) {
                        return !input.value.trim();
                    })[0];

                    if (pending) {
                        pending.focus();
                    }
                    return;
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
                // Se libera el candado del montaje automático pase lo que pase: si
                // esto falló, un cambio posterior en el formulario debe poder
                // volver a intentarlo.
                checkout._autoStarting = false;
            });
        });
    }
})();
