<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Productos certificados para pruebas
 */
function musement_certified_products(): array
{
    return [
        'pompidou' => [
            'uuid' => '165fcd0d-2046-11e7-9cc9-06a7e332783f',
            'title' => 'Centre Pompidou Paris',
            'description' => 'Tour privado por el Centro Pompidou con guía experto.',
            'image' => 'https://images-sandbox.musement.com/cover/0046/40/thumb_4539815_cover_header.jpeg?w=540',
            'requires_participants' => true,
            'has_extra_data' => true,
            'price' => 225.00,
            'currency' => 'EUR',
            'type' => 'supplier_questions'
        ],
        'knossos' => [
            'uuid' => '0420c908-981e-4e27-a40f-767e8b66574a',
            'title' => 'Palacio de Knossos',
            'description' => 'Entrada al recinto arqueológico de Knossos con audioguía.',
            'image' => 'https://images-sandbox.musement.com/cover/0004/59/thumb_358136_cover_header.jpeg?w=540',
            'requires_participants' => true,
            'has_extra_data' => false,
            'price' => 28.00,
            'currency' => 'EUR',
            'type' => 'supplier_questions'
        ]
    ];
}

/**
 * Shortcode para productos certificados
 * Uso: [musement_certified_products]
 */
function musement_certified_products_shortcode()
{
    wp_enqueue_style('flatpickr');
    wp_enqueue_script('flatpickr');
    wp_enqueue_script('flatpickr-es');
    wp_enqueue_style('musement-frontend');

    $products = musement_certified_products();

    ob_start();
    ?>
    <style>
        .musement-certified-products {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 15px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .musement-certified-products .cert-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .musement-certified-products .cert-header h2 {
            font-size: 28px;
            color: #1d2327;
            margin: 0 0 8px;
        }

        .musement-certified-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .musement-certified-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .musement-certified-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .musement-certified-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .musement-certified-card__body {
            padding: 18px 20px 20px;
        }

        .musement-certified-card__body h3 {
            font-size: 18px;
            color: #1d2327;
            margin: 0 0 6px;
        }

        .musement-certified-card__body p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin: 0 0 12px;
        }

        .musement-certified-card__body small {
            display: block;
            color: #7b2fbe;
            font-size: 12px;
            margin-bottom: 10px;
            padding: 4px 8px;
            background: #f3e8ff;
            border-radius: 4px;
        }

        .musement-certified-card__button {
            width: 100%;
            padding: 10px;
            background: #2271b1;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
        }

        .musement-certified-card__button:hover {
            background: #135e96;
            transform: translateY(-1px);
        }

        .musement-certified-card__button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Contenido dinámico */
        .musement-certified-dynamic {
            margin-top: 15px;
            display: none;
        }

        .musement-certified-dynamic.visible {
            display: block;
        }

        .musement-certified-dynamic .flatpickr-wrapper {
            width: 100%;
        }

        .musement-certified-dynamic .flatpickr-calendar {
            width: 100% !important;
            max-width: 100% !important;
            box-shadow: none !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 6px !important;
        }

        .musement-certified-dynamic .flatpickr-calendar.open {
            position: relative !important;
            top: 0 !important;
            left: 0 !important;
        }

        .musement-certified-dynamic .flatpickr-days {
            width: 100% !important;
        }

        .musement-certified-dynamic .dayContainer {
            display: grid !important;
            grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
        }

        .musement-certified-dynamic .flatpickr-day {
            max-width: none !important;
            width: 100% !important;
        }

        .musement-certified-slot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            margin: 4px 0;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .musement-certified-slot:hover:not(.unavailable) {
            border-color: #2271b1;
            background: #f8f9fa;
        }

        .musement-certified-slot.selected {
            border-color: #2271b1;
            background: #e8f0fe;
        }

        .musement-certified-slot.unavailable {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .musement-certified-slot .slot-time {
            font-weight: 500;
        }

        .musement-certified-slot .slot-price {
            color: #666;
            font-size: 13px;
        }

        .musement-certified-slot .slot-status {
            font-size: 12px;
            padding: 2px 10px;
            border-radius: 12px;
        }

        .musement-certified-slot .slot-status.available {
            background: #edfaef;
            color: #008a20;
        }

        .musement-certified-slot .slot-status.unavailable {
            background: #fce8e8;
            color: #d63638;
        }

        /* 🔥 ESTILOS PARA MÚLTIPLES PARTICIPANTES */
        .participant-selector {
            background: #f0f7ff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #d4e4f7;
        }

        .participant-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .participant-btn:hover {
            background: #e8f0fe;
            border-color: #2271b1;
        }

        .participant-form {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 12px;
            background: #fff;
            transition: border-color 0.3s;
        }

        .participant-form.completed {
            border-color: #00a32a;
            background: #fafffa;
        }

        .participant-form .participant-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 15px;
            color: #1d2327;
        }

        .participant-form .participant-status {
            font-size: 12px;
            color: #999;
        }

        .participant-form .participant-status.completed {
            color: #00a32a;
        }

        /* Preguntas */
        .musement-certified-questions {
            margin-top: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .musement-certified-questions .question-item {
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f1;
        }

        .musement-certified-questions .question-item:last-child {
            border-bottom: none;
        }

        .musement-certified-questions .question-item label {
            display: block;
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .musement-certified-questions .question-item label .required {
            color: #d63638;
            margin-left: 4px;
        }

        .musement-certified-questions .question-item input,
        .musement-certified-questions .question-item select {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .musement-certified-questions .question-item input:focus,
        .musement-certified-questions .question-item select:focus {
            border-color: #2271b1;
            outline: none;
        }

        .musement-certified-questions .question-item input.error,
        .musement-certified-questions .question-item select.error {
            border-color: #d63638;
        }

        /* Resumen */
        .musement-certified-summary {
            margin-top: 10px;
            padding: 12px;
            background: #f0f7ff;
            border-radius: 6px;
            border: 1px solid #d4e4f7;
            display: none;
        }

        .musement-certified-summary.visible {
            display: block;
        }

        .musement-certified-summary .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 14px;
        }

        .musement-certified-summary .summary-total {
            border-top: 1px solid #d4e4f7;
            margin-top: 4px;
            padding-top: 4px;
            font-weight: 700;
            font-size: 16px;
        }

        /* Confirmar preguntas */
        .musement-certified-confirm-btn {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            background: #7b2fbe;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .musement-certified-confirm-btn:hover:not(:disabled) {
            background: #5a1d8a;
        }

        .musement-certified-confirm-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .musement-certified-confirm-btn.confirmed {
            background: #00a32a;
        }

        /* Añadir al carrito */
        .musement-certified-add-cart {
            width: 100%;
            margin-top: 10px;
            padding: 12px;
            background: #2271b1;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
        }

        .musement-certified-add-cart:hover:not(:disabled) {
            background: #135e96;
            transform: translateY(-1px);
        }

        .musement-certified-add-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .musement-certified-add-cart.added {
            background: #00a32a;
        }

        /* Mensajes */
        .musement-certified-message {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            display: none;
        }

        .musement-certified-message.success {
            display: block;
            background: #edfaef;
            border: 1px solid #b8e6c0;
            color: #008a20;
        }

        .musement-certified-message.error {
            display: block;
            background: #fce8e8;
            border: 1px solid #f5c8c8;
            color: #d63638;
        }

        /* Progress */
        .musement-certified-progress {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
            text-align: center;
        }

        .musement-certified-progress.completed {
            color: #00a32a;
            font-weight: 600;
        }

        /* Loading */
        .musement-certified-loading {
            text-align: center;
            color: #666;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .musement-certified-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="musement-certified-products">
        <div class="cert-header">
            <h2>🧪 Productos Certificados</h2>
            <p>Selecciona un producto para probar el flujo completo de compra</p>
            <small style="color: #999;">Cada participante debe completar su propia información</small>
        </div>

        <div class="musement-certified-grid" id="certified-grid">
            <?php foreach ($products as $key => $product): ?>
            <article class="musement-certified-card" 
                     data-product="<?php echo esc_attr($key); ?>"
                     data-uuid="<?php echo esc_attr($product['uuid']); ?>"
                     data-type="<?php echo esc_attr($product['type'] ?? 'supplier_questions'); ?>"
                     data-price="<?php echo esc_attr($product['price'] ?? 0); ?>"
                     data-currency="<?php echo esc_attr($product['currency'] ?? 'EUR'); ?>">
                
                <?php if ($product['image']): ?>
                <img src="<?php echo esc_url($product['image']); ?>" 
                     alt="<?php echo esc_attr($product['title']); ?>" 
                     loading="lazy">
                <?php endif; ?>

                <div class="musement-certified-card__body">
                    <h3><?php echo esc_html($product['title']); ?></h3>
                    <p><?php echo esc_html($product['description']); ?></p>

                    <?php if ($product['requires_participants']): ?>
                    <small>📋 Requiere información de participantes</small>
                    <?php endif; ?>

                    <button class="musement-certified-card__button" data-product="<?php echo esc_attr($key); ?>">
                        📅 Ver Disponibilidad
                    </button>

                    <!-- Contenido dinámico -->
                    <div class="musement-certified-dynamic" id="dynamic-<?php echo esc_attr($key); ?>">
                        <div class="musement-certified-calendar" id="calendar-<?php echo esc_attr($key); ?>">
                            <input type="text" class="certified-datepicker" 
                                   id="datepicker-<?php echo esc_attr($key); ?>"
                                   placeholder="Selecciona una fecha"
                                   data-product="<?php echo esc_attr($key); ?>"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        </div>

                        <div class="musement-certified-slots" id="slots-<?php echo esc_attr($key); ?>">
                            <p style="color: #999; text-align: center; padding: 10px; margin: 0;">
                                Selecciona una fecha para ver horarios
                            </p>
                        </div>

                        <div class="musement-certified-questions" id="questions-<?php echo esc_attr($key); ?>" style="display:none;">
                            <div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">📋 Información de participantes</div>
                            <div id="questions-content-<?php echo esc_attr($key); ?>"></div>
                        </div>

                        <div class="musement-certified-summary" id="summary-<?php echo esc_attr($key); ?>">
                            <div style="font-weight: 600; margin-bottom: 6px;">📋 Resumen de selección</div>
                            <div id="summary-content-<?php echo esc_attr($key); ?>"></div>
                        </div>

                        <button class="musement-certified-add-cart" id="add-cart-<?php echo esc_attr($key); ?>" disabled>
                            🛒 Añadir al carrito
                        </button>
                        <div class="musement-certified-message" id="message-<?php echo esc_attr($key); ?>"></div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ============================================================
        // ESTADO
        // ============================================================
        var state = {};
        var fpInstances = {};
        var cartItems = [];

        <?php foreach ($products as $key => $product): ?>
        state['<?php echo esc_js($key); ?>'] = {
            uuid: '<?php echo esc_js($product['uuid']); ?>',
            name: '<?php echo esc_js($product['title']); ?>',
            type: '<?php echo esc_js($product['type'] ?? 'supplier_questions'); ?>',
            price: <?php echo (float) ($product['price'] ?? 0); ?>,
            currency: '<?php echo esc_js($product['currency'] ?? 'EUR'); ?>',
            selectedDate: null,
            selectedSlot: null,
            availability: {},
            questions: [],
            participantCount: 1,
            participants: [],
            allConfirmed: false,
            _price: null,
            _currency: 'EUR',
            _productIdentifier: '',
            _productName: ''
        };
        <?php endforeach; ?>

        // ============================================================
        // BOTONES VER DISPONIBILIDAD
        // ============================================================
        document.querySelectorAll('.musement-certified-card__button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var productKey = this.dataset.product;
                var dynamic = document.getElementById('dynamic-' + productKey);
                var isVisible = dynamic.classList.contains('visible');

                document.querySelectorAll('.musement-certified-dynamic').forEach(function(el) {
                    el.classList.remove('visible');
                });

                if (!isVisible) {
                    dynamic.classList.add('visible');
                    initCalendar(productKey);
                }
            });
        });

        // ============================================================
        // FUNCIONES DEL CALENDARIO
        // ============================================================
        function initCalendar(productKey) {
            var stateData = state[productKey];
            var input = document.getElementById('datepicker-' + productKey);

            if (!input) return;

            if (fpInstances[productKey]) {
                fpInstances[productKey].destroy();
                delete fpInstances[productKey];
            }

            var fp = flatpickr(input, {
                locale: 'es',
                dateFormat: 'Y-m-d',
                minDate: 'today',
                inline: true,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        stateData.selectedDate = dateStr;
                        stateData.selectedSlot = null;
                        stateData.allConfirmed = false;
                        updateStatus(productKey, 'loading', '⏳ Cargando...');
                        loadSlots(productKey);
                    }
                },
                onMonthChange: function(selectedDates, dateStr, instance) {
                    loadMonthAvailability(productKey, instance.currentYear, instance.currentMonth);
                },
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    var date = dayElem.dateObj;
                    var dateStr = date.getFullYear() + '-' + 
                        String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(date.getDate()).padStart(2, '0');

                    if (stateData.availability && stateData.availability[dateStr]) {
                        if (stateData.availability[dateStr] !== 'full') {
                            dayElem.classList.add('available');
                            dayElem.style.background = '#edfaef';
                            dayElem.style.borderRadius = '4px';
                        } else {
                            dayElem.classList.add('flatpickr-disabled');
                        }
                    }
                }
            });

            fpInstances[productKey] = fp;
            loadMonthAvailability(productKey, fp.currentYear, fp.currentMonth);
        }

        function loadMonthAvailability(productKey, year, month) {
            var stateData = state[productKey];
            var monthStr = year + '-' + String(month + 1).padStart(2, '0');

            var data = new FormData();
            data.append('action', 'musement_month_availability');
            data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
            data.append('uuid', stateData.uuid);
            data.append('month', monthStr);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success && response.data.days) {
                    stateData.availability = response.data.days;
                    if (fpInstances[productKey]) {
                        fpInstances[productKey].redraw();
                    }
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
            });
        }

        // ============================================================
        // CARGAR SLOTS
        // ============================================================
        function loadSlots(productKey) {
            var stateData = state[productKey];
            var container = document.getElementById('slots-' + productKey);

            if (!stateData.selectedDate) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">Selecciona una fecha</p>';
                return;
            }

            container.innerHTML = '<p class="musement-certified-loading">Cargando horarios...</p>';

            var data = new FormData();
            data.append('action', 'musement_day_availability');
            data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
            data.append('uuid', stateData.uuid);
            data.append('date', stateData.selectedDate);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) {
                    renderSlots(productKey, response.data);
                } else {
                    container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles</p>';
                    updateStatus(productKey, 'unavailable', '❌ Sin horarios');
                }
            })
            .catch(function() {
                container.innerHTML = '<p style="color: #d63638; text-align: center; padding: 10px;">Error al cargar horarios</p>';
                updateStatus(productKey, 'unavailable', '❌ Error');
            });
        }

        function renderSlots(productKey, data) {
            var stateData = state[productKey];
            var container = document.getElementById('slots-' + productKey);

            var groups = [];
            if (Array.isArray(data) && data.length > 0) {
                for (var i = 0; i < data.length; i++) {
                    if (data[i] && data[i].groups && Array.isArray(data[i].groups)) {
                        groups = data[i].groups;
                        break;
                    }
                }
                if (groups.length === 0 && data[0] && data[0].groups) {
                    groups = data[0].groups;
                }
            }
            if (groups.length === 0 && data.groups && Array.isArray(data.groups)) {
                groups = data.groups;
            }

            if (!groups || groups.length === 0) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles</p>';
                updateStatus(productKey, 'unavailable', '❌ Sin horarios');
                return;
            }

            var html = '<div style="margin-top: 10px;"><strong>🕐 Horarios disponibles:</strong></div>';
            var hasAvailable = false;

            groups.forEach(function(group) {
                var slots = group.slots || [];
                slots.forEach(function(slot) {
                    var isAvailable = slot.availability === -1 || slot.availability > 0;
                    if (isAvailable) hasAvailable = true;

                    var isSelected = stateData.selectedSlot && stateData.selectedSlot.time === slot.time;

                    // 🔥 OBTENER PRODUCT_ID DEL SLOT
                    var productIdentifier = '';
                    var productName = 'Entrada';
                    var price = 'Precio variable';
                    var priceValue = 0;
                    var holderCode = '';
                    var holderCodeNormalized = '';

                    if (slot.products && slot.products.length > 0) {
                        var selectedProduct = slot.products[0];
                        // Buscar producto por defecto
                        for (var p = 0; p < slot.products.length; p++) {
                            if (slot.products[p].default) {
                                selectedProduct = slot.products[p];
                                break;
                            }
                        }
                        productIdentifier = selectedProduct.product_id || selectedProduct.product_identifier || '';
                        productName = selectedProduct.name || 'Entrada';
                        holderCode = selectedProduct.holder_code || '';
                        holderCodeNormalized = selectedProduct.holder_code_normalized || '';
                        
                        if (selectedProduct.retail_price) {
                            price = selectedProduct.retail_price.formatted_value || 
                                    selectedProduct.retail_price.formatted_iso_value || 
                                    selectedProduct.retail_price.value + ' ' + selectedProduct.retail_price.currency;
                            priceValue = parseFloat(selectedProduct.retail_price.value) || 0;
                        }
                    }

                    html += '<div class="musement-certified-slot' + (isSelected ? ' selected' : '') + (isAvailable ? '' : ' unavailable') + '" data-time="' + (slot.time || 'flexible') + '" data-price="' + priceValue + '" data-product-identifier="' + productIdentifier + '" data-product-name="' + productName + '" data-holder-code="' + holderCode + '" data-holder-code-normalized="' + holderCodeNormalized + '">';
                    html += '<span class="slot-time">🕐 ' + (slot.time || 'Horario flexible') + '</span>';
                    html += '<span class="slot-price">' + price + '</span>';
                    html += '<span class="slot-status ' + (isAvailable ? 'available' : 'unavailable') + '">' + 
                        (isAvailable ? '✅ Disponible' : '❌ Agotado') + '</span>';
                    html += '</div>';
                });
            });

            container.innerHTML = html;

            if (hasAvailable) {
                updateStatus(productKey, 'available', '✅ Disponible');
            } else {
                updateStatus(productKey, 'unavailable', '❌ Agotado');
            }

            container.querySelectorAll('.musement-certified-slot:not(.unavailable)').forEach(function(el) {
                el.addEventListener('click', function() {
                    var time = this.dataset.time;
                    var price = parseFloat(this.dataset.price) || 0;
                    var productIdentifier = this.dataset.productIdentifier || '';
                    var productName = this.dataset.productName || 'Entrada';
                    var holderCode = this.dataset.holderCode || '';
                    var holderCodeNormalized = this.dataset.holderCodeNormalized || '';

                    stateData.selectedSlot = { 
                        time: time,
                        productIdentifier: productIdentifier,
                        productName: productName,
                        holderCode: holderCode,
                        holderCodeNormalized: holderCodeNormalized
                    };
                    stateData._price = price;
                    stateData._productIdentifier = productIdentifier;
                    stateData._productName = productName;

                    container.querySelectorAll('.musement-certified-slot').forEach(function(s) {
                        s.classList.remove('selected');
                    });
                    this.classList.add('selected');

                    // Cargar preguntas
                    loadSupplierQuestions(productKey);
                });
            });
        }

        // ============================================================
        // CARGAR PREGUNTAS
        // ============================================================
        async function loadSupplierQuestions(widgetId) {
            
            var stateData = state[widgetId];
            
            const form = new FormData();

            form.append('action', 'musement_participant_schema');
            form.append('nonce', MUSEMENT.nonce);
            form.append('uuid', stateData.uuid);

            const response = await fetch(MUSEMENT.ajax, {
                method: 'POST',
                credentials: 'same-origin',
                body: form
            });

            const json = await response.json();

            if (!json.success) {
                throw new Error(json.data?.message || 'No se pudo obtener el esquema.');
            }

            const schema = json.data;
            console.log('Esquema de preguntas 2:', schema);
            mapSchemaToQuestions(stateData, schema);
        }


        function mapSchemaToQuestions(stateData, schema) {
            console.log('Esquema de preguntas 3:', schema);
            var questions =  Object.entries(schema.properties).map(([id, field]) => ({
                id: id,
                label: field.title || id,
                type: getInputType(field),
                required: (schema.required || []).includes(id),
                options: field.enum || [],
                placeholder: ''
            }));


            console.log('Preguntas mapeadas:', questions);

            console.log('stateData antes de asignar preguntas:', state);

            renderQuestions(stateData, questions);
        }
        

        function getInputType(field) {
            if (field.format === 'date') {
                return 'date';
            }

            if (field.enum) {
                return 'select';
            }

            return 'text';
        }

        // ============================================================
        // RENDERIZAR PREGUNTAS CON MÚLTIPLES PARTICIPANTES
        // ============================================================
        function renderQuestions(stateData, questions) { 
            var productKey = stateData.productKey || 'knossos';
            
            console.log('🎨 Renderizando preguntas para:', productKey);
            console.log('📋 Preguntas:', questions);
            console.log('📦 stateData:', stateData);

            // Guardar preguntas en el estado
            stateData.questions = questions;
            
            var content = document.getElementById('questions-content-' + productKey);
            if (!content) {
                console.error('❌ No se encontró el contenedor para:', productKey);
                return;
            }

            document.getElementById('questions-' + productKey).style.display = 'block';
            var currentCount = stateData.participantCount || 1;

            var html = '';
            
            // Selector de participantes
            html += '<div class="participant-selector">';
            html += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 15px;">👥 Número de participantes</label>';
            html += '<div style="display: flex; align-items: center; gap: 12px;">';
            html += '<button class="participant-btn" data-action="minus" data-product="' + productKey + '">−</button>';
            html += '<span id="participant-count-' + productKey + '" style="font-size: 20px; font-weight: 600; min-width: 40px; text-align: center;">' + currentCount + '</span>';
            html += '<button class="participant-btn" data-action="plus" data-product="' + productKey + '">+</button>';
            html += '</div>';
            html += '<div style="font-size: 12px; color: #666; margin-top: 6px;">Cada participante deberá completar su propia información</div>';
            html += '</div>';
            
            // Formularios de participantes
            html += '<div id="participants-container-' + productKey + '">';
            html += renderParticipantForms(productKey, questions, currentCount);
            html += '</div>';
            
            // Botón confirmar
            html += '<button class="musement-certified-confirm-btn" id="confirm-all-' + productKey + '" disabled>';
            html += '✅ Confirmar todos los participantes';
            html += '</button>';
            
            // Progreso
            html += '<div class="musement-certified-progress" id="progress-' + productKey + '">📊 Progreso: 0/0 campos completados</div>';

            content.innerHTML = html;

            document.querySelectorAll('.participant-btn[data-product="' + productKey + '"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var action = this.dataset.action;
                    var currentCount = stateData.participantCount || 1;
                    var newCount = action === 'plus' ? currentCount + 1 : Math.max(1, currentCount - 1);
                    stateData.participantCount = newCount;

                    document.getElementById('participant-count-' + productKey).textContent = newCount;

                    var container = document.getElementById('participants-container-' + productKey);
                    container.innerHTML = renderParticipantForms(productKey, stateData.questions, newCount);
                    
                    stateData.allConfirmed = false;
                    var confirmBtn = document.getElementById('confirm-all-' + productKey);
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.textContent = '✅ Confirmar todos los participantes';
                        confirmBtn.style.background = '#7b2fbe';
                    }
                    

                    attachQuestionEvents(productKey);
                    updateProgress(productKey);
                });
            });

            document.getElementById('confirm-all-' + productKey).addEventListener('click', function() {
                var allValid = validateAllParticipants(productKey);
                if (allValid) {
                    stateData.allConfirmed = true;
                    this.textContent = '✅ Todos confirmados';
                    this.disabled = true;
                    this.style.background = '#00a32a';
                    updateSummary(productKey);
                    showMessage(productKey, 'success', '✅ Todos los participantes han sido confirmados');
                } else {
                    showMessage(productKey, 'error', '❌ Completa todos los campos obligatorios para cada participante');
                }
            });

            attachQuestionEvents(productKey);
            updateProgress(productKey);
        }

        // ============================================================
        // RENDERIZAR FORMULARIOS DE PARTICIPANTES
        // ============================================================
        function renderParticipantForms(productKey, questions, count) {
            var html = '';
            
            for (var p = 1; p <= count; p++) {
                html += '<div class="participant-form" id="participant-form-' + productKey + '-' + p + '">';
                html += '<div class="participant-header">';
                html += '<span>👤 Participante ' + p + '</span>';
                html += '<span class="participant-status" id="participant-status-' + productKey + '-' + p + '">⏳ Pendiente</span>';
                html += '</div>';
                
                questions.forEach(function(question) {
                    var isRequired = question.required || false;
                    var inputId = 'q_' + productKey + '_p' + p + '_' + question.id;
                    
                    html += '<div class="question-item">';
                    html += '<label for="' + inputId + '">';
                    html += question.label;
                    if (isRequired) {
                        html += ' <span class="required">*</span>';
                    }
                    html += '</label>';

                    switch(question.type) {
                        case 'date':
                            html += '<input type="date" id="' + inputId + '" class="certified-question-input" data-product="' + productKey + '" data-participant="' + p + '" data-qid="' + question.id + '" data-required="' + isRequired + '">';
                            break;
                        case 'select':
                            html += '<select id="' + inputId + '" class="certified-question-input" data-product="' + productKey + '" data-participant="' + p + '" data-qid="' + question.id + '" data-required="' + isRequired + '">';
                            html += '<option value="">-- Selecciona --</option>';
                            if (question.options) {
                                question.options.forEach(function(option) {
                                    html += '<option value="' + option + '">' + option + '</option>';
                                });
                            }
                            html += '</select>';
                            break;
                        default:
                            html += '<input type="' + (question.type || 'text') + '" id="' + inputId + '" class="certified-question-input" data-product="' + productKey + '" data-participant="' + p + '" data-qid="' + question.id + '" data-required="' + isRequired + '" placeholder="' + (question.placeholder || '') + '">';
                            break;
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            return html;
        }

        // ============================================================
        // ASIGNAR EVENTOS A LOS INPUTS
        // ============================================================
        function attachQuestionEvents(productKey) {
            console.log(' Asignando eventos a inputs para:', productKey);
            
            document.querySelectorAll('.certified-question-input[data-product="' + productKey + '"]').forEach(function(input) {
                // Remover eventos anteriores
                var newInput = input.cloneNode(true);
                input.parentNode.replaceChild(newInput, input);
                
                // Agregar nuevos eventos
                newInput.addEventListener('change', function() {
                    updateProgress(productKey);
                });
                newInput.addEventListener('input', function() {
                    updateProgress(productKey);
                });
            });
        }

        // ============================================================
        // ACTUALIZAR PROGRESO
        // ============================================================
        function updateProgress(productKey) {
            console.log('📊 Actualizando progreso para:', productKey);
            
            var stateData = state[productKey];
            if (!stateData) {
                console.error('❌ stateData no encontrado para:', productKey);
                return;
            }
            
            var totalFields = 0;
            var filledFields = 0;
            var requiredFields = 0;
            var filledRequired = 0;
            var participantCount = stateData.participantCount || 1;
            
            var inputs = document.querySelectorAll('.certified-question-input[data-product="' + productKey + '"]');
            
            if (inputs.length === 0) {
                console.warn('⚠️ No se encontraron inputs para:', productKey);
                return;
            }
            
            inputs.forEach(function(input) {
                totalFields++;
                var isRequired = input.dataset.required === 'true';
                if (isRequired) {
                    requiredFields++;
                    if (input.value && input.value.trim() !== '') {
                        filledRequired++;
                    }
                }
                if (input.value && input.value.trim() !== '') {
                    filledFields++;
                }
            });
            
            // Actualizar progreso de cada participante
            for (var p = 1; p <= participantCount; p++) {
                var allFilled = true;
                var allRequiredFilled = true;
                document.querySelectorAll('.certified-question-input[data-product="' + productKey + '"][data-participant="' + p + '"]').forEach(function(input) {
                    var isRequired = input.dataset.required === 'true';
                    if (isRequired && (!input.value || input.value.trim() === '')) {
                        allRequiredFilled = false;
                        allFilled = false;
                    } else if (!input.value || input.value.trim() === '') {
                        allFilled = false;
                    }
                });
                
                var statusEl = document.getElementById('participant-status-' + productKey + '-' + p);
                var formEl = document.getElementById('participant-form-' + productKey + '-' + p);
                if (statusEl) {
                    if (allFilled) {
                        statusEl.textContent = '✅ Completado';
                        statusEl.className = 'participant-status completed';
                    } else if (allRequiredFilled) {
                        statusEl.textContent = '⚠️ Opcionales pendientes';
                        statusEl.className = 'participant-status';
                        statusEl.style.color = '#f0b400';
                    } else {
                        statusEl.textContent = '⏳ Pendiente';
                        statusEl.className = 'participant-status';
                        statusEl.style.color = '#999';
                    }
                }
                if (formEl) {
                    formEl.className = 'participant-form' + (allRequiredFilled ? ' completed' : '');
                }
            }
            
            var progress = totalFields > 0 ? Math.round((filledFields / totalFields) * 100) : 0;
            var progressEl = document.getElementById('progress-' + productKey);
            if (progressEl) {
                var msg = '📊 Progreso: ' + filledFields + '/' + totalFields + ' campos completados (' + progress + '%)';
                if (requiredFields > 0 && filledRequired < requiredFields) {
                    msg += ' - ⚠️ Faltan ' + (requiredFields - filledRequired) + ' obligatorios';
                    progressEl.className = 'musement-certified-progress';
                } else if (filledFields === totalFields) {
                    progressEl.className = 'musement-certified-progress completed';
                } else {
                    progressEl.className = 'musement-certified-progress';
                }
                progressEl.textContent = msg;
            }
            
            // Habilitar/deshabilitar botón de confirmar
            var confirmBtn = document.getElementById('confirm-all-' + productKey);
            if (confirmBtn) {
                var allRequiredFilledGlobal = requiredFields === 0 || filledRequired === requiredFields;
                confirmBtn.disabled = !allRequiredFilledGlobal;
                if (allRequiredFilledGlobal && filledFields === totalFields) {
                    confirmBtn.style.background = '#00a32a';
                    confirmBtn.textContent = '✅ Confirmar todos los participantes';
                } else if (allRequiredFilledGlobal) {
                    confirmBtn.style.background = '#f0b400';
                    confirmBtn.textContent = '⚠️ Faltan campos opcionales (puedes confirmar)';
                } else {
                    confirmBtn.style.background = '#7b2fbe';
                    confirmBtn.textContent = '✅ Confirmar todos los participantes';
                }
            }
        }

        // ============================================================
        // VALIDAR TODOS LOS PARTICIPANTES
        // ============================================================
        function validateAllParticipants(productKey) {
            var allValid = true;
            
            document.querySelectorAll('.certified-question-input[data-product="' + productKey + '"]').forEach(function(input) {
                var isRequired = input.dataset.required === 'true';
                if (isRequired && (!input.value || input.value.trim() === '')) {
                    allValid = false;
                    input.classList.add('error');
                } else {
                    input.classList.remove('error');
                }
            });
            
            return allValid;
        }

        // ============================================================
        // RECOGER TODAS LAS RESPUESTAS
        // ============================================================
        function getAllAnswers(productKey) {
            var stateData = state[productKey];
            var answers = [];
            var participantCount = stateData.participantCount || 1;
            
            for (var p = 1; p <= participantCount; p++) {
                var participantAnswers = {};
                
                document.querySelectorAll('.certified-question-input[data-product="' + productKey + '"][data-participant="' + p + '"]').forEach(function(input) {
                    var qid = input.dataset.qid;
                    participantAnswers[qid] = input.value || '';
                });
                
                answers.push({
                    participant: p,
                    answers: participantAnswers
                });
            }
            
            return answers;
        }

        // ============================================================
        // ACTUALIZAR RESUMEN
        // ============================================================
        function updateSummary(productKey) {
            var stateData = state[productKey];
            var summary = document.getElementById('summary-' + productKey);
            var content = document.getElementById('summary-content-' + productKey);
            var addBtn = document.getElementById('add-cart-' + productKey);

            if (!stateData.selectedDate || !stateData.selectedSlot || !stateData.allConfirmed) {
                summary.classList.remove('visible');
                addBtn.disabled = true;
                return;
            }

            var price = stateData._price || stateData.price || 0;
            var currency = stateData.currency || 'EUR';
            var participantCount = stateData.participantCount || 1;
            var total = price * participantCount;

            var html = '';
            html += '<div class="summary-row"><span>📅 Fecha</span><span>' + stateData.selectedDate + '</span></div>';
            html += '<div class="summary-row"><span>🕐 Horario</span><span>' + stateData.selectedSlot.time + '</span></div>';
            html += '<div class="summary-row"><span>👥 Participantes</span><span>' + participantCount + '</span></div>';
            html += '<div class="summary-row"><span>💰 Precio por persona</span><span>' + price.toFixed(2) + ' ' + currency + '</span></div>';
            html += '<div class="summary-row summary-total"><span>💰 Total</span><span>' + total.toFixed(2) + ' ' + currency + '</span></div>';

            content.innerHTML = html;
            summary.classList.add('visible');
            addBtn.disabled = false;
        }

        // ============================================================
        // AÑADIR AL CARRITO
        // ============================================================
        document.querySelectorAll('.musement-certified-add-cart').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var productKey = this.dataset.product || this.id.replace('add-cart-', '');
                var stateData = state[productKey];
                var message = document.getElementById('message-' + productKey);

                if (!stateData.selectedDate || !stateData.selectedSlot) {
                    showMessage(productKey, 'error', '❌ Selecciona fecha y horario');
                    return;
                }

                if (!stateData.allConfirmed) {
                    showMessage(productKey, 'error', '❌ Confirma todos los participantes');
                    return;
                }

                var price = stateData._price || stateData.price || 0;
                var currency = stateData.currency || 'EUR';
                var participantCount = stateData.participantCount || 1;
                var allAnswers = getAllAnswers(productKey);

                var payload = {
                    action: 'musement_cart_add',
                    nonce: '<?php echo wp_create_nonce('musement_ajax'); ?>',
                    activity_uuid: stateData.uuid,
                    title: stateData.name,
                    date: stateData.selectedDate,
                    time: stateData.selectedSlot.time || '00:00',
                    product_identifier: stateData._productIdentifier || '',
                    product_name: stateData._productName || stateData.name,
                    quantity: participantCount,
                    unit_price: price,
                    currency: currency,
                    language: 'es',
                    holder_code: stateData.selectedSlot.holderCode || '',
                    holder_code_normalized: stateData.selectedSlot.holderCodeNormalized || '',
                    questions: JSON.stringify(allAnswers),
                    participant_info: JSON.stringify(allAnswers),
                    participants: participantCount
                };

                console.log('🛒 Enviando al carrito con ' + participantCount + ' participantes:', payload);

                var formData = new FormData();
                Object.keys(payload).forEach(function(key) {
                    if (payload[key] !== undefined && payload[key] !== null && payload[key] !== '') {
                        formData.append(key, payload[key]);
                    }
                });

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(function(response) { return response.text(); })
                .then(function(text) {
                    try {
                        var response = JSON.parse(text);
                        console.log('📦 Respuesta carrito:', response);

                        if (response && response.success) {
                            showMessage(productKey, 'success', '✅ ' + participantCount + ' participantes añadidos al carrito');
                            updateCartBadge(response.data);

                            btn.textContent = '✅ Añadido (' + participantCount + ' participantes)';
                            btn.classList.add('added');
                            setTimeout(function() {
                                btn.textContent = '🛒 Añadir al carrito';
                                btn.classList.remove('added');
                            }, 3000);
                        } else {
                            var errorMsg = response && response.data ? response.data.message : 'Error desconocido';
                            showMessage(productKey, 'error', '❌ Error: ' + errorMsg);
                            console.error('❌ Error detallado:', response);
                            if (response && response.data && response.data.remote_error_data) {
                                console.error('❌ Musement remote_error_data:', JSON.stringify(response.data.remote_error_data, null, 2));
                            }
                        }
                    } catch(e) {
                        console.error('❌ Error parsing JSON:', e);
                        showMessage(productKey, 'error', '❌ Error de respuesta: ' + text.substring(0, 200));
                    }
                })
                .catch(function(error) {
                    console.error('❌ Error:', error);
                    showMessage(productKey, 'error', '❌ Error de conexión: ' + error.message);
                });
            });
        });

        // ============================================================
        // UTILIDADES
        // ============================================================
        function updateStatus(productKey, status, message) {
            // No hay status en la tarjeta por ahora
        }

        function showMessage(productKey, type, text) {
            var el = document.getElementById('message-' + productKey);
            if (!el) return;
            el.className = 'musement-certified-message ' + type;
            el.textContent = text;

            setTimeout(function() {
                el.className = 'musement-certified-message';
            }, 5000);
        }

        function updateCartBadge(cart) {
            var badge = document.querySelector('.musement-cart-bar__count');
            var total = document.querySelector('.musement-cart-bar__total');

            if (badge) {
                var count = cart && cart.items ? cart.items.length : 0;
                badge.textContent = count;
            }

            if (total) {
                var summary = cart && cart.summary ? cart.summary : { total: 0, currency: 'EUR' };
                total.textContent = summary.total.toFixed(2) + ' ' + summary.currency;
            }
        }
    });
    </script>

    <?php
    return ob_get_clean();
}

add_shortcode('musement_certified_products', 'musement_certified_products_shortcode');
