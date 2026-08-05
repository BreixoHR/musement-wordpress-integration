<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode para calendario de disponibilidad con participantes
 * Uso: [musement_certified_calendar product="pompidou"]
 *       [musement_certified_calendar uuid="165fcd0d-2046-11e7-9cc9-06a7e332783f"]
 */
function musement_certified_calendar_shortcode($atts)
{
    // Obtener atributos
    $atts = shortcode_atts([
        'product' => 'pompidou',
        'uuid' => '',
        'title' => '',
        'description' => '',
        'price' => 0,
        'currency' => 'EUR',
        'image' => '',
        'show_details' => 'true'
    ], $atts, 'musement_certified_calendar');

    // Si se proporciona UUID, buscar el producto
    if (!empty($atts['uuid'])) {
        $product_data = musement_get_product_by_uuid($atts['uuid']);
        if ($product_data) {
            $atts['title'] = $product_data['title'] ?? $atts['title'];
            $atts['description'] = $product_data['description'] ?? $atts['description'];
            $atts['price'] = $product_data['price'] ?? $atts['price'];
            $atts['currency'] = $product_data['currency'] ?? $atts['currency'];
            $atts['image'] = $product_data['image'] ?? $atts['image'];
        }
    } else {
        // Obtener producto por nombre
        $products = musement_certified_products();
        if (isset($products[$atts['product']])) {
            $product = $products[$atts['product']];
            $atts['uuid'] = $product['uuid'];
            $atts['title'] = $product['title'] ?? $atts['title'];
            $atts['description'] = $product['description'] ?? $atts['description'];
            $atts['price'] = $product['price'] ?? $atts['price'];
            $atts['currency'] = $product['currency'] ?? $atts['currency'];
            $atts['image'] = $product['image'] ?? $atts['image'];
        }
    }

    if (empty($atts['uuid'])) {
        return '<p style="color: #d63638;">❌ Error: No se encontró el producto especificado.</p>';
    }

    // Encolar scripts necesarios
    wp_enqueue_style('flatpickr');
    wp_enqueue_script('flatpickr');
    wp_enqueue_script('flatpickr-es');
    wp_enqueue_style('musement-frontend');

    // Generar ID único para el widget
    $widget_id = 'musement-calendar-' . uniqid();

    ob_start();
    ?>
    <div class="musement-certified-calendar-wrapper" id="<?php echo esc_attr($widget_id); ?>"
         data-uuid="<?php echo esc_attr($atts['uuid']); ?>"
         data-product="<?php echo esc_attr($atts['product']); ?>"
         data-title="<?php echo esc_attr($atts['title']); ?>"
         data-description="<?php echo esc_attr($atts['description']); ?>"
         data-price="<?php echo esc_attr($atts['price']); ?>"
         data-currency="<?php echo esc_attr($atts['currency']); ?>"
         data-image="<?php echo esc_url($atts['image']); ?>"
         data-show-details="<?php echo esc_attr($atts['show_details']); ?>"
         data-requires-participants="<?php echo esc_attr($atts['requires_participants'] ?? 'true'); ?>"
         data-has-extra-data="<?php echo esc_attr($atts['has_extra_data'] ?? 'false'); ?>">
        <style>
            .musement-certified-calendar-wrapper .calendar-header {
                text-align: center;
                margin-bottom: 20px;
            }

            .musement-certified-calendar-wrapper .calendar-header h3 {
                font-size: 22px;
                color: #1d2327;
                margin: 0 0 4px;
            }

            .musement-certified-calendar-wrapper .calendar-header p {
                color: #666;
                font-size: 14px;
                margin: 0 0 8px;
            }

            .musement-certified-calendar-wrapper .calendar-product-info {
                display: flex;
                gap: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px 16px;
                margin-bottom: 15px;
                border: 1px solid #e5e7eb;
                align-items: center;
            }

            .musement-certified-calendar-wrapper .calendar-product-info img {
                width: 80px;
                height: 80px;
                object-fit: cover;
                border-radius: 6px;
                flex-shrink: 0;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-content {
                flex: 1;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-content h4 {
                font-size: 16px;
                color: #1d2327;
                margin: 0 0 4px;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-content p {
                font-size: 13px;
                color: #666;
                margin: 0 0 4px;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-content .price {
                font-weight: 700;
                color: #1d2327;
                font-size: 16px;
            }

            .musement-certified-calendar-wrapper .calendar-container {
                background: #fff;
                border-radius: 8px;
                padding: 12px;
                border: 1px solid #e5e7eb;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-calendar {
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-calendar.open {
                position: relative !important;
                top: 0 !important;
                left: 0 !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-days {
                width: 100% !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .dayContainer {
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.available {
                background: #edfaef !important;
                border-radius: 4px !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.available:hover {
                background: #d4edda !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.selected {
                background: #2271b1 !important;
                border-color: #2271b1 !important;
            }

            /* Slots */
            .musement-certified-calendar-wrapper .slots-container {
                margin-top: 12px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
                display: none;
            }

            .musement-certified-calendar-wrapper .slots-container.visible {
                display: block;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 12px;
                margin: 4px 0;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item:hover:not(.unavailable) {
                border-color: #2271b1;
                background: #f8f9fa;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item.selected {
                border-color: #2271b1;
                background: #e8f0fe;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item.unavailable {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-time {
                font-weight: 500;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-price {
                color: #666;
                font-size: 13px;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-status {
                font-size: 12px;
                padding: 2px 10px;
                border-radius: 12px;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-status.available {
                background: #edfaef;
                color: #008a20;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-status.unavailable {
                background: #fce8e8;
                color: #d63638;
            }

            /* Filtro de idioma */
            .musement-certified-calendar-wrapper .language-filter {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 6px;
                margin-bottom: 10px;
            }

            .musement-certified-calendar-wrapper .language-filter .language-filter-label {
                font-size: 13px;
                font-weight: 600;
                color: #1d2327;
                margin-right: 4px;
            }

            .musement-certified-calendar-wrapper .language-filter-btn {
                padding: 4px 12px;
                border-radius: 14px;
                border: 1px solid #ddd;
                background: #fff;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .musement-certified-calendar-wrapper .language-filter-btn:hover {
                border-color: #2271b1;
                background: #f8f9fa;
            }

            .musement-certified-calendar-wrapper .language-filter-btn.active {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
            }

            /* Participantes */
            .musement-certified-calendar-wrapper .participant-selector {
                background: #f0f7ff;
                padding: 12px;
                border-radius: 6px;
                margin: 12px 0 15px;
                border: 1px solid #d4e4f7;
            }

            .musement-certified-calendar-wrapper .participant-btn {
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

            .musement-certified-calendar-wrapper .participant-btn:hover {
                background: #e8f0fe;
                border-color: #2271b1;
            }

            .musement-certified-calendar-wrapper .participant-form {
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                padding: 14px;
                margin-bottom: 12px;
                background: #fff;
                transition: border-color 0.3s;
            }

            .musement-certified-calendar-wrapper .participant-form.completed {
                border-color: #00a32a;
                background: #fafffa;
            }

            .musement-certified-calendar-wrapper .participant-form .participant-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                font-weight: 600;
                font-size: 15px;
                color: #1d2327;
            }

            .musement-certified-calendar-wrapper .participant-form .participant-status {
                font-size: 12px;
                color: #999;
            }

            .musement-certified-calendar-wrapper .participant-form .participant-status.completed {
                color: #00a32a;
            }

            .musement-certified-calendar-wrapper .participant-form .question-item {
                margin-bottom: 10px;
                padding: 6px 0;
                border-bottom: 1px solid #f0f0f1;
            }

            .musement-certified-calendar-wrapper .participant-form .question-item:last-child {
                border-bottom: none;
            }

            .musement-certified-calendar-wrapper .participant-form .question-item label {
                display: block;
                font-weight: 500;
                margin-bottom: 3px;
                font-size: 13px;
            }

            .musement-certified-calendar-wrapper .participant-form .question-item label .required {
                color: #d63638;
                margin-left: 4px;
            }

            .musement-certified-calendar-wrapper .participant-form .question-item input,
            .musement-certified-calendar-wrapper .participant-form .question-item select {
                width: 100%;
                padding: 6px 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
            }

            .musement-certified-calendar-wrapper .participant-form .question-item input.error,
            .musement-certified-calendar-wrapper .participant-form .question-item select.error {
                border-color: #d63638;
            }

            .musement-certified-calendar-wrapper .musement-certified-extra-data {
                border: 1px solid #e5e7eb;
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px;
                margin-bottom: 16px;
            }

            .musement-certified-calendar-wrapper .extra-data-block {
                display: grid;
                gap: 12px;
            }

            /* Confirmar */
            .musement-certified-calendar-wrapper .certified-confirm-btn {
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

            .musement-certified-calendar-wrapper .certified-confirm-btn:hover:not(:disabled) {
                background: #5a1d8a;
            }

            .musement-certified-calendar-wrapper .certified-confirm-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
            }

            .musement-certified-calendar-wrapper .certified-confirm-btn.confirmed {
                background: #00a32a;
            }

            /* Progress */
            .musement-certified-calendar-wrapper .certified-progress {
                margin-top: 8px;
                font-size: 13px;
                color: #666;
                text-align: center;
            }

            .musement-certified-calendar-wrapper .certified-progress.completed {
                color: #00a32a;
                font-weight: 600;
            }

            /* Resumen */
            .musement-certified-calendar-wrapper .selection-summary {
                margin-top: 12px;
                padding: 12px;
                background: #f0f7ff;
                border-radius: 6px;
                border: 1px solid #d4e4f7;
                display: none;
            }

            .musement-certified-calendar-wrapper .selection-summary.visible {
                display: block;
            }

            .musement-certified-calendar-wrapper .selection-summary .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
                font-size: 14px;
            }

            .musement-certified-calendar-wrapper .selection-summary .summary-total {
                border-top: 1px solid #d4e4f7;
                margin-top: 4px;
                padding-top: 4px;
                font-weight: 700;
                font-size: 16px;
            }

            .musement-certified-calendar-wrapper .calendar-actions {
                margin-top: 12px;
                display: flex;
                gap: 10px;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary {
                flex: 1;
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

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary:hover:not(:disabled) {
                background: #135e96;
                transform: translateY(-1px);
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary:disabled {
                background: #ccc;
                cursor: not-allowed;
                transform: none;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary.added {
                background: #00a32a;
            }

            .musement-certified-calendar-wrapper .calendar-message {
                margin-top: 8px;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 13px;
                display: none;
            }

            .musement-certified-calendar-wrapper .calendar-message.success {
                display: block;
                background: #edfaef;
                border: 1px solid #b8e6c0;
                color: #008a20;
            }

            .musement-certified-calendar-wrapper .calendar-message.error {
                display: block;
                background: #fce8e8;
                border: 1px solid #f5c8c8;
                color: #d63638;
            }

            .musement-certified-calendar-wrapper .calendar-loading {
                text-align: center;
                color: #666;
                padding: 20px;
            }

            @media (max-width: 768px) {
                .musement-certified-calendar-wrapper .calendar-product-info {
                    flex-direction: column;
                    text-align: center;
                }

                .musement-certified-calendar-wrapper .calendar-product-info img {
                    width: 120px;
                    height: 120px;
                }

                .musement-certified-calendar-wrapper .calendar-actions {
                    flex-direction: column;
                }
            }
        </style>

        <!-- Product Info -->
        <div class="calendar-header">
            <h3>📅 Disponibilidad</h3>
            <p>Selecciona una fecha para ver los horarios disponibles</p>
        </div>

        <div class="calendar-product-info" id="product-info-<?php echo esc_attr($widget_id); ?>">
            <?php if ($atts['image']): ?>
            <img src="<?php echo esc_url($atts['image']); ?>" alt="<?php echo esc_attr($atts['title']); ?>">
            <?php endif; ?>
            <div class="info-content">
                <h4><?php echo esc_html($atts['title']); ?></h4>
                <p><?php echo esc_html($atts['description']); ?></p>
                <span class="price">Desde <?php echo number_format($atts['price'], 2); ?> <?php echo esc_html($atts['currency']); ?></span>
            </div>
        </div>

        <!-- Calendar -->
        <div class="calendar-container">
            <input type="text" class="certified-calendar-datepicker" 
                id="datepicker-<?php echo esc_attr($widget_id); ?>"
                placeholder="Selecciona una fecha"
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
        </div>

        <!-- Slots -->
        <div class="slots-container" id="slots-<?php echo esc_attr($widget_id); ?>">
            <p style="color: #999; text-align: center; padding: 10px; margin: 0;">
                Selecciona una fecha para ver horarios
            </p>
        </div>

        <!-- Participantes y preguntas -->
        <div class="musement-certified-questions" id="questions-<?php echo esc_attr($widget_id); ?>" style="display:none;">
            <div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">📋 Información de participantes</div>
            <div id="questions-content-<?php echo esc_attr($widget_id); ?>"></div>
        </div>

        <!-- Datos extra del cliente -->
        <div class="musement-certified-extra-data" id="extra-data-<?php echo esc_attr($widget_id); ?>" style="display:none;">
            <div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">📌 Información adicional de reserva</div>
            <div id="extra-data-content-<?php echo esc_attr($widget_id); ?>"></div>
        </div>

        <!-- Summary -->
        <div class="selection-summary" id="summary-<?php echo esc_attr($widget_id); ?>">
            <div style="font-weight: 600; margin-bottom: 6px;">📋 Resumen de selección</div>
            <div id="summary-content-<?php echo esc_attr($widget_id); ?>"></div>
        </div>

        <!-- Actions -->
        <div class="calendar-actions">
            <button class="btn-primary" id="add-cart-<?php echo esc_attr($widget_id); ?>" disabled>
                🛒 Añadir al carrito
            </button>
        </div>

        <div class="calendar-message" id="message-<?php echo esc_attr($widget_id); ?>"></div>

        <!-- Barra de carrito flotante -->
        <div class="musement-cart-bar" id="demo-cart-bar" >
            <span>
                🛒 <strong class="musement-cart-bar__count" id="cart-count-<?php echo esc_attr($widget_id); ?>">0</strong> producto(s)
            </span>
            <span class="musement-cart-bar__total" id="cart-total-<?php echo esc_attr($widget_id); ?>">0.00 EUR</span>
            <a class="musement-cart-bar__checkout" href="<?php echo esc_url(musement_checkout_url()); ?>" style="color:white">
                Checkout
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ============================================================
        // INICIALIZAR
        // ============================================================
        var widgetId = '<?php echo esc_js($widget_id); ?>';
        var widget = document.getElementById(widgetId);
        if (!widget) return;
        console.log('musement: certified calendar init for', widgetId);
        var uuid = widget.dataset.uuid;
        var productKey = widget.dataset.product;
        var productTitle = widget.dataset.title;
        var productPrice = parseFloat(widget.dataset.price) || 0;
        var productCurrency = widget.dataset.currency || 'EUR';

        var state = {
            uuid: uuid,
            name: productTitle,
            price: productPrice,
            currency: productCurrency,
            selectedDate: null,
            selectedSlot: null,
            // Idioma elegido para toda la reserva (ligado al horario/slot seleccionado)
            selectedLanguage: null,
            availability: {},
            questions: [],
            participantCount: 1,
            allConfirmed: false,
            hasExtraData: widget.dataset.hasExtraData === 'true',
            requiresParticipants: widget.dataset.requiresParticipants === 'true',
            // Tipos de entrada (adulto/niño/etc.) disponibles para el horario seleccionado
            currentSlotProducts: [],
            // Última respuesta cruda de horarios del día, para poder refiltrar por idioma sin volver a pedir al servidor
            lastSlotsData: null,
            _price: null,
            _currency: 'EUR',
            _productIdentifier: '',
            _productName: ''
        };

        var fpInstance = null;

        // ============================================================
        // INICIALIZAR FLATPICKR
        // ============================================================
        var input = document.getElementById('datepicker-' + widgetId);
        // Inicializar flatpickr cuando esté disponible. Reintentar si no se ha cargado aún.
        function ensureFlatpickrInit() {
            var inputEl = document.getElementById('datepicker-' + widgetId);
            if (!inputEl) return;
            if (typeof flatpickr === 'undefined') {
                // esperar un poco y reintentar
                setTimeout(ensureFlatpickrInit, 80);
                return;
            }

            fpInstance = flatpickr(inputEl, {
                locale: 'es',
                dateFormat: 'Y-m-d',
                minDate: 'today',
                inline: true,
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        state.selectedDate = dateStr;
                        state.selectedSlot = null;
                        state.selectedLanguage = null;
                        state.currentSlotProducts = [];
                        state.allConfirmed = false;
                        document.getElementById('questions-' + widgetId).style.display = 'none';
                        loadSlots(widgetId);
                        updateSummary(widgetId);
                    }
                },
                onMonthChange: function(selectedDates, dateStr, instance) {
                    loadMonthAvailability(widgetId, instance.currentYear, instance.currentMonth);
                },
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    var date = dayElem.dateObj;
                    var dateStr = date.getFullYear() + '-' + 
                        String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(date.getDate()).padStart(2, '0');

                    if (state.availability && state.availability[dateStr]) {
                        if (state.availability[dateStr] !== 'full') {
                            dayElem.classList.add('available');
                            dayElem.style.background = '#edfaef';
                            dayElem.style.borderRadius = '4px';
                        } else {
                            dayElem.classList.add('flatpickr-disabled');
                        }
                    }
                }
            });

            loadMonthAvailability(widgetId, fpInstance.currentYear, fpInstance.currentMonth);
        }

        ensureFlatpickrInit();

        // ============================================================
        // DISPONIBILIDAD
        // ============================================================
        function loadMonthAvailability(widgetId, year, month) {
            var monthStr = year + '-' + String(month + 1).padStart(2, '0');

            var data = new FormData();
            data.append('action', 'musement_month_availability');
            data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
            data.append('uuid', state.uuid);
            data.append('month', monthStr);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success && response.data.days) {
                    state.availability = response.data.days;
                    if (fpInstance) {
                        fpInstance.redraw();
                    }
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
            });
        }

        // ============================================================
        // SLOTS
        // ============================================================
        function loadSlots(widgetId) {
            var container = document.getElementById('slots-' + widgetId);

            if (!state.selectedDate) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">Selecciona una fecha</p>';
                container.classList.remove('visible');
                return;
            }

            container.innerHTML = '<p class="calendar-loading">Cargando horarios...</p>';
            container.classList.add('visible');

            var data = new FormData();
            data.append('action', 'musement_day_availability');
            data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
            data.append('uuid', state.uuid);
            data.append('date', state.selectedDate);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response.success) {
                    state.lastSlotsData = response.data;
                    renderSlots(widgetId, response.data);
                } else {
                    container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles</p>';
                }
            })
            .catch(function() {
                container.innerHTML = '<p style="color: #d63638; text-align: center; padding: 10px;">Error al cargar horarios</p>';
            });
        }

        function normalizeProducts(rawProducts) {
            if (!rawProducts || !rawProducts.length) return [];

            return rawProducts.map(function(p) {
                var priceValue = 0;
                var priceFormatted = 'Precio variable';
                var currency = productCurrency;

                if (p.retail_price) {
                    priceValue = parseFloat(p.retail_price.value) || 0;
                    currency = p.retail_price.currency || currency;
                    priceFormatted = p.retail_price.formatted_value ||
                        p.retail_price.formatted_iso_value ||
                        (priceValue.toFixed(2) + ' ' + currency);
                }

                return {
                    id: p.product_id || p.product_identifier || p.id || p.uuid || '',
                    name: p.name || p.title || 'Entrada',
                    price: priceValue,
                    priceFormatted: priceFormatted,
                    currency: currency,
                    holderCode: p.holder_code || '',
                    holderCodeNormalized: p.holder_code_normalized || ''
                };
            });
        }

        function renderSlots(widgetId, data) {
            var container = document.getElementById('slots-' + widgetId);

            function slotLanguage(slot, group) {
                if (slot.language) {
                    if (typeof slot.language === 'string') {
                        return { code: slot.language, name: slot.language };
                    }

                    return {
                        code: slot.language.code || slot.language.id || '',
                        name: slot.language.name || slot.language.code || slot.language.id || ''
                    };
                }

                if (slot.languages && slot.languages.length > 0) {
                    var firstLanguage = slot.languages[0] || {};

                    if (typeof firstLanguage === 'string') {
                        return { code: firstLanguage, name: firstLanguage };
                    }

                    return {
                        code: firstLanguage.code || firstLanguage.id || '',
                        name: firstLanguage.name || firstLanguage.code || firstLanguage.id || ''
                    };
                }

                if (group && group.language) {
                    if (typeof group.language === 'string') {
                        return { code: group.language, name: group.language };
                    }

                    return {
                        code: group.language.code || group.language.id || '',
                        name: group.language.name || group.language.code || group.language.id || ''
                    };
                }

                return { code: '', name: '' };
            }

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
                return;
            }

            // Idiomas disponibles entre todos los grupos/horarios del día
            var languagesMap = {};
            groups.forEach(function(group) {
                var slots = group.slots || [];
                slots.forEach(function(slot) {
                    var lang = slotLanguage(slot, group);
                    var code = lang.code || lang.name;
                    if (code) {
                        languagesMap[code] = lang.name || lang.code;
                    }
                });
            });
            var languageCodes = Object.keys(languagesMap);

            // Si el idioma seleccionado ya no está disponible para este día, resetear
            if (state.selectedLanguage && languageCodes.indexOf(state.selectedLanguage) === -1) {
                state.selectedLanguage = null;
            }

            var html = '';

            // Filtro de idioma (solo si hay más de uno disponible)
            if (languageCodes.length > 1) {
                html += '<div class="language-filter" id="language-filter-' + widgetId + '">';
                html += '<span class="language-filter-label">🌐 Idioma:</span>';
                html += '<button type="button" class="language-filter-btn' + (!state.selectedLanguage ? ' active' : '') + '" data-lang="" data-product="' + widgetId + '">Todos</button>';
                languageCodes.forEach(function(code) {
                    var active = state.selectedLanguage === code ? ' active' : '';
                    html += '<button type="button" class="language-filter-btn' + active + '" data-lang="' + code + '" data-product="' + widgetId + '">' + languagesMap[code] + '</button>';
                });
                html += '</div>';
            }

            html += '<div style="margin-top: 10px;"><strong>🕐 Horarios disponibles:</strong></div>';
            var hasAnySlotRendered = false;

            groups.forEach(function(group) {
                var slots = group.slots || [];
                slots.forEach(function(slot) {
                    var language = slotLanguage(slot, group);
                    var langCode = language.code || language.name;

                    if (state.selectedLanguage && langCode !== state.selectedLanguage) {
                        return; // filtrado por idioma
                    }

                    hasAnySlotRendered = true;

                    var isAvailable = slot.availability === -1 || slot.availability > 0;

                    var isSelected = state.selectedSlot &&
                        state.selectedSlot.time === slot.time &&
                        state.selectedSlot.language === langCode;

                    var normalizedProducts = normalizeProducts(slot.products || []);
                    var priceLabel = 'Precio variable';
                    if (normalizedProducts.length > 0) {
                        var minProduct = normalizedProducts.reduce(function(min, p) {
                            return (min === null || p.price < min.price) ? p : min;
                        }, null);
                        priceLabel = normalizedProducts.length > 1
                            ? ('Desde ' + minProduct.priceFormatted)
                            : minProduct.priceFormatted;
                    }

                    var productsB64 = '';
                    try {
                        productsB64 = btoa(unescape(encodeURIComponent(JSON.stringify(normalizedProducts))));
                    } catch (e) {
                        productsB64 = '';
                    }

                    html += '<div class="slot-item' + (isSelected ? ' selected' : '') + (isAvailable ? '' : ' unavailable') +
                        '" data-time="' + (slot.time || 'flexible') +
                        '" data-language="' + (language.code || '') +
                        '" data-language-name="' + (language.name || '') +
                        '" data-products-b64="' + productsB64 + '">';
                    html += '<span class="slot-time">🕐 ' + (slot.time || 'Horario flexible') + '</span>';
                    html += '<span class="slot-price">' + priceLabel + '</span>';
                    if (language.code || language.name) {
                        html += '<span class="slot-status available">' + (language.name || language.code) + '</span>';
                    }
                    html += '<span class="slot-status ' + (isAvailable ? 'available' : 'unavailable') + '">' +
                        (isAvailable ? '✅ Disponible' : '❌ Agotado') + '</span>';
                    html += '</div>';
                });
            });

            if (!hasAnySlotRendered) {
                html += '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles para el idioma seleccionado</p>';
            }

            container.innerHTML = html;

            // Filtro de idioma: reaplica el render local sin volver a pedir al servidor
            container.querySelectorAll('.language-filter-btn[data-product="' + widgetId + '"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    state.selectedLanguage = this.dataset.lang || null;
                    state.selectedSlot = null;
                    state.currentSlotProducts = [];
                    state.allConfirmed = false;
                    document.getElementById('questions-' + widgetId).style.display = 'none';
                    renderSlots(widgetId, state.lastSlotsData);
                    updateSummary(widgetId);
                });
            });

            // Selección de horario
            container.querySelectorAll('.slot-item:not(.unavailable)').forEach(function(el) {
                el.addEventListener('click', function() {
                    var time = this.dataset.time;
                    var language = this.dataset.language || '';
                    var languageName = this.dataset.languageName || language;
                    var products = [];
                    try {
                        products = this.dataset.productsB64 ? JSON.parse(decodeURIComponent(escape(atob(this.dataset.productsB64)))) : [];
                    } catch (e) {
                        products = [];
                    }

                    state.selectedSlot = {
                        time: time,
                        language: language,
                        languageName: languageName
                    };
                    // El idioma del horario elegido queda fijado para toda la reserva
                    if (!state.selectedLanguage) {
                        state.selectedLanguage = language || null;
                    }
                    state.currentSlotProducts = products;
                    state.allConfirmed = false;

                    // Precio de referencia (el precio real se calcula por participante según el tipo elegido)
                    if (products.length > 0) {
                        var minP = products.reduce(function(min, p) { return (min === null || p.price < min.price) ? p : min; }, null);
                        state._price = minP.price;
                        state._productIdentifier = minP.id;
                        state._productName = minP.name;
                    }

                    container.querySelectorAll('.slot-item').forEach(function(s) {
                        s.classList.remove('selected');
                    });
                    this.classList.add('selected');

                    // Cargar preguntas
                    loadSupplierQuestions(widgetId);
                });
            });
        }

        // ============================================================
        // PREGUNTAS Y PARTICIPANTES
        // ============================================================
        async function loadSupplierQuestions(widgetId) {
            if (!state.selectedDate || !state.selectedSlot) {
                document.getElementById('questions-' + widgetId).style.display = 'none';
                return;
            }

            const container = document.getElementById('questions-' + widgetId);
            const content = document.getElementById('questions-content-' + widgetId);

            container.style.display = 'block';
            content.innerHTML = '<p class="calendar-loading">Cargando preguntas...</p>';

            try {
                const formData = new FormData();
                formData.append('action', 'musement_participant_schema');
                formData.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
                formData.append('uuid', state.uuid);

                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                const json = await response.json();

                if (!json.success) {
                    throw new Error(json.data?.message || 'No se pudo obtener el esquema de preguntas.');
                }

                const schema = json.data;
                const questions = mapSchemaToQuestions(schema);

                state.questions = questions;
                renderQuestions(widgetId, questions);

            } catch (err) {
                console.error(err);
                content.innerHTML = '<p>Error cargando las preguntas.</p>';
            }
        }


        function mapSchemaToQuestions(schema) {
            if (!schema || !schema.properties) {
                return [];
            }

            return Object.entries(schema.properties).map(function(entry) {
                var id = entry[0];
                var field = entry[1];
                return {
                    id: id,
                    label: field.title || field.label || id,
                    type: getInputType(field),
                    required: Array.isArray(schema.required) && schema.required.includes(id),
                    options: field.enum || [],
                    placeholder: field.placeholder || ''
                };
            });
        }

        function getInputType(field) {
            if (field.format === 'date' || field.type === 'date') {
                return 'date';
            }

            if (field.enum) {
                return 'select';
            }

            if (field.type === 'number' || field.type === 'integer') {
                return 'number';
            }

            if (field.format === 'email' || field.type === 'email') {
                return 'email';
            }

            return 'text';
        }

        function renderQuestions(widgetId, questions) {
            var content = document.getElementById('questions-content-' + widgetId);
            var currentCount = state.participantCount || 1;

            var html = '';

            // Selector de participantes
            html += '<div class="participant-selector">';
            html += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 15px;">👥 Número de participantes</label>';
            html += '<div style="display: flex; align-items: center; gap: 12px;">';
            html += '<button class="participant-btn" data-action="minus" data-product="' + widgetId + '">−</button>';
            html += '<span id="participant-count-' + widgetId + '" style="font-size: 20px; font-weight: 600; min-width: 40px; text-align: center;">' + currentCount + '</span>';
            html += '<button class="participant-btn" data-action="plus" data-product="' + widgetId + '">+</button>';
            html += '</div>';
            html += '<div style="font-size: 12px; color: #666; margin-top: 6px;">Cada participante debe completar su propia información</div>';
            html += '</div>';
            
            // Formularios por participante
            html += '<div id="participants-container-' + widgetId + '">';
            html += renderParticipantForms(widgetId, questions, currentCount);
            html += '</div>';
            
            // Botón confirmar
            html += '<button class="certified-confirm-btn" id="confirm-all-' + widgetId + '" disabled>';
            html += '✅ Confirmar todos los participantes';
            html += '</button>';
            
            // Progress
            html += '<div class="certified-progress" id="progress-' + widgetId + '">📊 Progreso: 0/0 campos completados</div>';

            content.innerHTML = html;

            // Eventos de participantes
            document.querySelectorAll('.participant-btn[data-product="' + widgetId + '"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var action = this.dataset.action;
                    var currentCount = state.participantCount || 1;
                    var newCount = action === 'plus' ? currentCount + 1 : Math.max(1, currentCount - 1);
                    
                    state.participantCount = newCount;
                    document.getElementById('participant-count-' + widgetId).textContent = newCount;
                    
                    var container = document.getElementById('participants-container-' + widgetId);
                    container.innerHTML = renderParticipantForms(widgetId, state.questions, newCount);
                    
                    state.allConfirmed = false;
                    var confirmBtn = document.getElementById('confirm-all-' + widgetId);
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.textContent = '✅ Confirmar todos los participantes';
                        confirmBtn.style.background = '#7b2fbe';
                    }
                    updateProgress(widgetId);
                    attachQuestionEvents(widgetId);
                });
            });

            // Evento confirmar
            document.getElementById('confirm-all-' + widgetId).addEventListener('click', function() {
                var allValid = validateAllParticipants(widgetId);
                if (allValid) {
                    state.allConfirmed = true;
                    this.textContent = '✅ Todos confirmados';
                    this.disabled = true;
                    this.style.background = '#00a32a';
                    updateSummary(widgetId);
                    showMessage(widgetId, 'success', '✅ Todos los participantes han sido confirmados');
                    document.getElementById('add-cart-' + widgetId).disabled = false;
                } else {
                    showMessage(widgetId, 'error', '❌ Completa todos los campos obligatorios para cada participante');
                }
            });

            attachQuestionEvents(widgetId);
            renderExtraDataSection(widgetId);
            attachExtraDataEvents(widgetId);
            updateProgress(widgetId);
        }

        function renderExtraDataSection(widgetId) {
            var wrapper = document.getElementById('extra-data-' + widgetId);
            var container = document.getElementById('extra-data-content-' + widgetId);
            if (!wrapper || !container) {
                return;
            }

            if (!state.hasExtraData) {
                wrapper.style.display = 'none';
                container.innerHTML = '';
                return;
            }

            wrapper.style.display = 'block';
            container.innerHTML = renderExtraCustomerData(widgetId);
        }

        function renderExtraCustomerData(widgetId) {
            var html = '';
            html += '<div class="extra-data-block">';
            html += '<div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">📝 Información extra del cliente</div>';
            html += '<div class="question-item">';
            html += '<label for="extra_phone_' + widgetId + '">Teléfono de contacto</label>';
            html += '<input type="tel" id="extra_phone_' + widgetId + '" class="certified-extra-input" data-product="' + widgetId + '" data-key="phone" placeholder="+34 600 000 000">';
            html += '</div>';
            html += '<div class="question-item">';
            html += '<label for="extra_pickup_' + widgetId + '">Dirección de recogida</label>';
            html += '<input type="text" id="extra_pickup_' + widgetId + '" class="certified-extra-input" data-product="' + widgetId + '" data-key="pickup_address" placeholder="Calle, hotel o punto de encuentro">';
            html += '</div>';
            html += '<div class="question-item">';
            html += '<label for="extra_diet_' + widgetId + '">Restricciones alimentarias</label>';
            html += '<input type="text" id="extra_diet_' + widgetId + '" class="certified-extra-input" data-product="' + widgetId + '" data-key="dietary_restrictions" placeholder="Ej. sin gluten, vegetariano">';
            html += '</div>';
            html += '</div>';
            return html;
        }

        function attachExtraDataEvents(widgetId) {
            document.querySelectorAll('.certified-extra-input[data-product="' + widgetId + '"]').forEach(function(input) {
                input.removeEventListener('change', function() {});
                input.removeEventListener('input', function() {});
                input.addEventListener('change', function() {
                    updateProgress(widgetId);
                });
                input.addEventListener('input', function() {
                    updateProgress(widgetId);
                });
            });
        }

        function renderParticipantForms(widgetId, questions, count) {
            var html = '';
            var products = state.currentSlotProducts || [];
            var showTicketTypeSelect = products.length > 1;

            for (var p = 1; p <= count; p++) {
                html += '<div class="participant-form" id="participant-form-' + widgetId + '-' + p + '">';
                html += '<div class="participant-header">';
                html += '<span>👤 Participante ' + p + '</span>';
                html += '<span class="participant-status" id="participant-status-' + widgetId + '-' + p + '">⏳ Pendiente</span>';
                html += '</div>';

                // Tipo de entrada (adulto/niño/etc.), si el producto tiene varios tipos disponibles
                if (products.length > 0) {
                    var ticketInputId = 'ticket_' + widgetId + '_p' + p;
                    html += '<div class="question-item">';
                    html += '<label for="' + ticketInputId + '">🎫 Tipo de entrada';
                    if (showTicketTypeSelect) {
                        html += ' <span class="required">*</span>';
                    }
                    html += '</label>';

                    if (showTicketTypeSelect) {
                        html += '<select id="' + ticketInputId + '" class="certified-question-input certified-tickettype-input" data-product="' + widgetId + '" data-participant="' + p + '" data-qid="ticket_type" data-required="true">';
                        html += '<option value="">-- Selecciona --</option>';
                        products.forEach(function(prod) {
                            html += '<option value="' + prod.id + '">' + prod.name + ' — ' + prod.priceFormatted + '</option>';
                        });
                        html += '</select>';
                    } else {
                        var only = products[0];
                        html += '<input type="hidden" id="' + ticketInputId + '" class="certified-question-input certified-tickettype-input" data-product="' + widgetId + '" data-participant="' + p + '" data-qid="ticket_type" data-required="false" value="' + only.id + '">';
                        html += '<div style="font-size: 13px; color: #666; padding: 6px 0;">' + only.name + ' — ' + only.priceFormatted + '</div>';
                    }
                    html += '</div>';
                }

                questions.forEach(function(question) {
                    var isRequired = question.required || false;
                    var inputId = 'q_' + widgetId + '_p' + p + '_' + question.id;
                    
                    html += '<div class="question-item">';
                    html += '<label for="' + inputId + '">';
                    html += question.label;
                    if (isRequired) {
                        html += ' <span class="required">*</span>';
                    }
                    html += '</label>';

                    switch(question.type) {
                        case 'date':
                            html += '<input type="date" id="' + inputId + '" class="certified-question-input" data-product="' + widgetId + '" data-participant="' + p + '" data-qid="' + question.id + '" data-required="' + isRequired + '">';
                            break;
                        case 'select':
                            html += '<select id="' + inputId + '" class="certified-question-input" data-product="' + widgetId + '" data-participant="' + p + '" data-qid="' + question.id + '" data-required="' + isRequired + '">';
                            html += '<option value="">-- Selecciona --</option>';
                            if (question.options) {
                                question.options.forEach(function(option) {
                                    html += '<option value="' + option + '">' + option + '</option>';
                                });
                            }
                            html += '</select>';
                            break;
                        default:
                            html += '<input type="' + (question.type || 'text') + '" id="' + inputId + '" class="certified-question-input" data-product="' + widgetId + '" data-participant="' + p + '" data-qid="' + question.id + '" data-required="' + isRequired + '" placeholder="' + (question.placeholder || '') + '">';
                            break;
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            return html;
        }

        function attachQuestionEvents(widgetId) {
            document.querySelectorAll('.certified-question-input[data-product="' + widgetId + '"]').forEach(function(input) {
                input.removeEventListener('change', function() {});
                input.removeEventListener('input', function() {});
                input.addEventListener('change', function() {
                    updateProgress(widgetId);
                });
                input.addEventListener('input', function() {
                    updateProgress(widgetId);
                });
            });
        }

        function getExtraCustomerData(widgetId) {
            var data = {};
            document.querySelectorAll('.certified-extra-input[data-product="' + widgetId + '"]').forEach(function(input) {
                var key = input.dataset.key;
                if (!key) {
                    return;
                }
                var value = input.value || '';
                if (value.trim() !== '') {
                    data[key] = value.trim();
                }
            });
            return data;
        }

        function updateProgress(widgetId) {
            var totalFields = 0;
            var filledFields = 0;
            var requiredFields = 0;
            var filledRequired = 0;
            var participantCount = state.participantCount || 1;
            
            document.querySelectorAll('.certified-question-input[data-product="' + widgetId + '"]').forEach(function(input) {
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
            
            // Actualizar estado de cada participante
            for (var p = 1; p <= participantCount; p++) {
                var allRequiredFilled = true;
                document.querySelectorAll('.certified-question-input[data-product="' + widgetId + '"][data-participant="' + p + '"]').forEach(function(input) {
                    var isRequired = input.dataset.required === 'true';
                    if (isRequired && (!input.value || input.value.trim() === '')) {
                        allRequiredFilled = false;
                    }
                });
                
                var statusEl = document.getElementById('participant-status-' + widgetId + '-' + p);
                var formEl = document.getElementById('participant-form-' + widgetId + '-' + p);
                if (statusEl) {
                    if (allRequiredFilled) {
                        statusEl.textContent = '✅ Completado';
                        statusEl.className = 'participant-status completed';
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
            var progressEl = document.getElementById('progress-' + widgetId);
            if (progressEl) {
                var msg = '📊 Progreso: ' + filledFields + '/' + totalFields + ' campos completados (' + progress + '%)';
                if (requiredFields > 0 && filledRequired < requiredFields) {
                    msg += ' - ⚠️ Faltan ' + (requiredFields - filledRequired) + ' obligatorios';
                    progressEl.className = 'certified-progress';
                } else if (filledFields === totalFields) {
                    progressEl.className = 'certified-progress completed';
                } else {
                    progressEl.className = 'certified-progress';
                }
                progressEl.textContent = msg;
            }
            
            // Habilitar botón
            var confirmBtn = document.getElementById('confirm-all-' + widgetId);
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

        function validateAllParticipants(widgetId) {
            var allValid = true;
            
            document.querySelectorAll('.certified-question-input[data-product="' + widgetId + '"]').forEach(function(input) {
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

        function getAllAnswers(widgetId) {
            var answers = [];
            var participantCount = state.participantCount || 1;
            
            for (var p = 1; p <= participantCount; p++) {
                var participantAnswers = {};
                
                document.querySelectorAll('.certified-question-input[data-product="' + widgetId + '"][data-participant="' + p + '"]').forEach(function(input) {
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
        // PRECIOS POR TIPO DE ENTRADA (adulto/niño/etc.)
        // ============================================================
        function getParticipantsPricing(widgetId) {
            var participantCount = state.participantCount || 1;
            var products = state.currentSlotProducts || [];

            // Producto sin desglose de tipos de entrada: se usa el precio base del shortcode
            if (products.length === 0) {
                var price = state._price || state.price || 0;
                var currency = state.currency || productCurrency;
                return {
                    items: [{
                        productIdentifier: state._productIdentifier || '',
                        productName: state._productName || state.name,
                        holderCode: '',
                        holderCodeNormalized: '',
                        unitPrice: price,
                        currency: currency,
                        quantity: participantCount
                    }],
                    total: price * participantCount,
                    currency: currency
                };
            }

            var items = {};
            var total = 0;
            var currency = state.currency || productCurrency;

            for (var p = 1; p <= participantCount; p++) {
                var select = document.querySelector('.certified-tickettype-input[data-product="' + widgetId + '"][data-participant="' + p + '"]');
                var productId = select ? select.value : '';
                var product = products.filter(function(pr) { return pr.id === productId; })[0];

                if (!product && products.length === 1) {
                    product = products[0];
                }

                if (product) {
                    currency = product.currency || currency;
                    total += product.price;
                    if (!items[product.id]) {
                        items[product.id] = {
                            productIdentifier: product.id,
                            productName: product.name,
                            holderCode: product.holderCode,
                            holderCodeNormalized: product.holderCodeNormalized,
                            unitPrice: product.price,
                            currency: product.currency || currency,
                            quantity: 0
                        };
                    }
                    items[product.id].quantity += 1;
                }
            }

            return {
                items: Object.keys(items).map(function(k) { return items[k]; }),
                total: total,
                currency: currency
            };
        }

        // ============================================================
        // ACTUALIZAR RESUMEN
        // ============================================================
        function updateSummary(widgetId) {
            var summary = document.getElementById('summary-' + widgetId);
            var content = document.getElementById('summary-content-' + widgetId);
            var addBtn = document.getElementById('add-cart-' + widgetId);

            if (!state.selectedDate || !state.selectedSlot || !state.allConfirmed) {
                summary.classList.remove('visible');
                addBtn.disabled = true;
                return;
            }

            var pricing = getParticipantsPricing(widgetId);
            var participantCount = state.participantCount || 1;

            var html = '';
            html += '<div class="summary-row"><span>📅 Fecha</span><span>' + state.selectedDate + '</span></div>';
            html += '<div class="summary-row"><span>🕐 Horario</span><span>' + state.selectedSlot.time + '</span></div>';
            if (state.selectedSlot.language || state.selectedSlot.languageName) {
                html += '<div class="summary-row"><span>🌐 Idioma</span><span>' + (state.selectedSlot.languageName || state.selectedSlot.language) + '</span></div>';
            }
            html += '<div class="summary-row"><span>👥 Participantes</span><span>' + participantCount + '</span></div>';

            pricing.items.forEach(function(item) {
                html += '<div class="summary-row"><span>🎫 ' + item.productName + ' x' + item.quantity + '</span><span>' + (item.unitPrice * item.quantity).toFixed(2) + ' ' + item.currency + '</span></div>';
            });

            html += '<div class="summary-row summary-total"><span>💰 Total</span><span>' + pricing.total.toFixed(2) + ' ' + pricing.currency + '</span></div>';

            content.innerHTML = html;
            summary.classList.add('visible');
            addBtn.disabled = false;
        }

        // ============================================================
        // AÑADIR AL CARRITO
        // ============================================================
        document.getElementById('add-cart-' + widgetId).addEventListener('click', function() {
            var message = document.getElementById('message-' + widgetId);
            var addBtn = this;

            // Evita doble/triple clic: sin esto, cada clic mandaba otra petición
            // musement_cart_add y duplicaba el producto en el carrito local y remoto.
            if (addBtn.disabled) {
                return;
            }

            if (!state.selectedDate || !state.selectedSlot) {
                showMessage(widgetId, 'error', '❌ Selecciona fecha y horario');
                return;
            }

            if (!state.allConfirmed) {
                showMessage(widgetId, 'error', '❌ Confirma todos los participantes');
                return;
            }

            var pricing = getParticipantsPricing(widgetId);

            if (pricing.items.length === 0) {
                showMessage(widgetId, 'error', '❌ Selecciona el tipo de entrada para cada participante');
                return;
            }

            var participantCount = state.participantCount || 1;
            var allAnswers = getAllAnswers(widgetId);
            var extraCustomerData = getExtraCustomerData(widgetId);

            // Item principal, para compatibilidad con integraciones que esperan un único producto/precio.
            // Si hay varios tipos de entrada distintos, revisa el campo "items" para el desglose completo.
            var mainItem = pricing.items[0];

            var payload = {
                action: 'musement_cart_add',
                nonce: '<?php echo wp_create_nonce('musement_ajax'); ?>',
                activity_uuid: state.uuid,
                title: state.name,
                date: state.selectedDate,
                time: state.selectedSlot.time || '00:00',
                product_identifier: mainItem.productIdentifier || '',
                product_name: mainItem.productName || state.name,
                quantity: participantCount,
                unit_price: mainItem.unitPrice,
                currency: pricing.currency,
                language: state.selectedSlot.language || '',
                holder_code: mainItem.holderCode || '',
                holder_code_normalized: mainItem.holderCodeNormalized || '',
                questions: JSON.stringify(allAnswers),
                participant_info: JSON.stringify(allAnswers),
                customer_extra_data: JSON.stringify(extraCustomerData),
                participants: participantCount,
                // Desglose por tipo de entrada (adulto/niño/etc.): necesario cuando hay más de un tipo seleccionado,
                // ya que los campos "legacy" de arriba solo representan el primer tipo.
                items: JSON.stringify(pricing.items),
                total_price: pricing.total
            };

            var formData = new FormData();
            Object.keys(payload).forEach(function(key) {
                if (payload[key] !== undefined && payload[key] !== null && payload[key] !== '') {
                    formData.append(key, payload[key]);
                }
            });

            addBtn.disabled = true;

            console.log('📤 Musement cart add payload', payload);
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
                        showMessage(widgetId, 'success', '✅ ' + participantCount + ' participantes añadidos al carrito');
                        updateCartBadge(widgetId, response.data);

                        addBtn.textContent = '✅ Añadido (' + participantCount + ' participantes)';
                        addBtn.classList.add('added');
                        setTimeout(function() {
                            addBtn.textContent = '🛒 Añadir al carrito';
                            addBtn.classList.remove('added');
                            addBtn.disabled = false;
                        }, 3000);
                    } else {
                        var errorMsg = response && response.data ? response.data.message : 'Error desconocido';
                        showMessage(widgetId, 'error', '❌ Error: ' + errorMsg);
                        console.error('❌ Error detallado:', response);
                        if (response && response.data && response.data.remote_error_data) {
                            console.error('❌ Musement remote_error_data:', JSON.stringify(response.data.remote_error_data, null, 2));
                        }
                        addBtn.disabled = false;
                    }
                } catch(e) {
                    console.error('❌ Error parsing JSON:', e);
                    showMessage(widgetId, 'error', '❌ Error de respuesta: ' + text.substring(0, 200));
                    addBtn.disabled = false;
                }
            })
            .catch(function(error) {
                console.error('❌ Error:', error);
                showMessage(widgetId, 'error', '❌ Error de conexión: ' + error.message);
                addBtn.disabled = false;
            });
        });

        // ============================================================
        // FUNCIONES UTILITARIAS
        // ============================================================
        function showMessage(widgetId, type, text) {
            var el = document.getElementById('message-' + widgetId);
            if (!el) return;
            el.className = 'calendar-message ' + type;
            el.textContent = text;

            setTimeout(function() {
                el.className = 'calendar-message';
            }, 5000);
        }

        function updateCartBadge(widgetId, cart) {
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

add_shortcode('musement_certified_calendar', 'musement_certified_calendar_shortcode');

/**
 * Obtener producto por UUID desde la API
 */
function musement_get_product_by_uuid($uuid) {
    $product = musement_remote_get_product($uuid);
    
    if (is_wp_error($product) || empty($product)) {
        return null;
    }
    
    return [
        'uuid' => $uuid,
        'title' => $product['title'] ?? '',
        'description' => $product['description'] ?? $product['about'] ?? '',
        'price' => $product['merchant_price']['value'] ?? $product['retail_price']['value'] ?? 0,
        'currency' => $product['merchant_price']['currency'] ?? $product['retail_price']['currency'] ?? 'EUR',
        'image' => $product['cover_image_url'] ?? $product['cover_image']['url'] ?? ($product['images'][0]['url'] ?? '')
    ];
}