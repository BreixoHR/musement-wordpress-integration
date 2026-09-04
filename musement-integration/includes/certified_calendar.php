<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode para calendario de disponibilidad con participantes
 *
 * Uso recomendado, para cualquier producto real (no solo los de demo) -- pega
 * el UUID de la actividad en Musement:
 *       [musement_certified_calendar uuid="165fcd0d-2046-11e7-9cc9-06a7e332783f"]
 *
 * También admite, por compatibilidad, los dos nombres de demo/certificación
 * definidos en musement_certified_products() ("pompidou", "knossos"):
 *       [musement_certified_calendar product="pompidou"]
 *
 * Y si en "product" se pone directamente un UUID (que es lo que la mayoría de
 * gente probablemente intente, al ser el atributo "obvio" del shortcode), se
 * trata igual que si se hubiera puesto en "uuid" -- antes esto daba siempre
 * "producto no encontrado", porque "product" solo reconocía esos dos nombres.
 */
function musement_certified_calendar_shortcode($atts)
{
    // Obtener atributos
    $atts = shortcode_atts([
        'product' => '',
        'uuid' => '',
        'title' => '',
        'description' => '',
        'price' => 0,
        'currency' => 'EUR',
        'image' => '',
        'show_details' => 'true',
        'requires_participants' => 'true',
        'has_extra_data' => 'false',
    ], $atts, 'musement_certified_calendar');

    $demo_products = musement_certified_products();
    $demo_product = null;

    if (empty($atts['uuid']) && !empty($atts['product'])) {
        if (isset($demo_products[$atts['product']])) {
            // "product" es uno de los nombres de demo conocidos.
            $demo_product = $demo_products[$atts['product']];
            $atts['uuid'] = $demo_product['uuid'];
        } else {
            // "product" no es un nombre de demo -- se asume que es un UUID.
            $atts['uuid'] = $atts['product'];
        }
    }

    if ($demo_product) {
        $atts['title'] = $demo_product['title'] ?? $atts['title'];
        $atts['description'] = $demo_product['description'] ?? $atts['description'];
        $atts['price'] = $demo_product['price'] ?? $atts['price'];
        $atts['currency'] = $demo_product['currency'] ?? $atts['currency'];
        $atts['image'] = $demo_product['image'] ?? $atts['image'];
        // Antes estos dos campos del producto de demo nunca se copiaban a $atts
        // (ni "requires_participants" ni "has_extra_data" estaban registrados en
        // shortcode_atts(), así que se perdían siempre y quedaba el valor por
        // defecto pasara lo que pasara).
        $atts['requires_participants'] = !empty($demo_product['requires_participants']) ? 'true' : 'false';
        $atts['has_extra_data'] = !empty($demo_product['has_extra_data']) ? 'true' : 'false';
    } elseif (!empty($atts['uuid'])) {
        $product_data = musement_get_product_by_uuid($atts['uuid']);
        if ($product_data) {
            $atts['title'] = $product_data['title'] ?? $atts['title'];
            $atts['description'] = $product_data['description'] ?? $atts['description'];
            $atts['price'] = $product_data['price'] ?? $atts['price'];
            $atts['currency'] = $product_data['currency'] ?? $atts['currency'];
            $atts['image'] = $product_data['image'] ?? $atts['image'];
        }
    }

    if (empty($atts['uuid'])) {
        return '<p style="color: #d63638;">Error: No se encontró el producto especificado.</p>';
    }

    // Encolar scripts necesarios
    wp_enqueue_style('flatpickr');
    wp_enqueue_script('flatpickr');
    wp_enqueue_script('flatpickr-es');
    wp_enqueue_style('musement-frontend');
    // Roboto: misma tipografía que el widget de reservas tomado como referencia
    wp_enqueue_style('musement-roboto-font', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap', [], null);

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
         data-requires-participants="<?php echo esc_attr($atts['requires_participants']); ?>"
         data-has-extra-data="<?php echo esc_attr($atts['has_extra_data']); ?>">
        <style>

            .calendar-product-info, .musement-checkout__title{
                display: none !important;
            }

            .musement-certified-calendar-wrapper {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            /* El tema (Gilroy/Helvetica) aplica su propia fuente a párrafos,
               inputs, botones, etc. con selectores que pueden pesar más o
               cargarse después que esta regla. El "*" + !important asegura
               que absolutamente todo dentro del widget -- incluido el texto
               que se inyecta luego por JS (participant-status, labels...) --
               use Roboto sin depender de la especificidad del tema. */
            .musement-certified-calendar-wrapper,
            .musement-certified-calendar-wrapper * {
                font-family: "Roboto", "Helvetica Neue", Arial, sans-serif !important;
            }

            .musement-certified-calendar-wrapper .calendar-header {
                margin-bottom: 14px;
            }

            .musement-certified-calendar-wrapper .calendar-header h3 {
                font-size: 15px;
                font-weight: 400;
                color: #1a1a1a;
                margin: 0;
            }

            .musement-certified-calendar-wrapper .calendar-header p {
                color: #666;
                font-size: 14px;
                margin: 4px 0 0;
            }

            /* Fila inferior con nombre del producto y precio, estilo Demo Tours */
            .musement-certified-calendar-wrapper .calendar-product-info {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                justify-content: space-between;
                padding: 16px 2px 4px;
                margin-top: 4px;
                border-top: 1px solid #e3e3e3;
            }

            .musement-certified-calendar-wrapper .calendar-product-info img,
            .musement-certified-calendar-wrapper .calendar-product-info .info-content p {
                display: none;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-content {
                flex: 1;
                min-width: 0;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-content h4 {
                font-size: 15px;
                font-weight: 700;
                color: #1a1a1a;
                margin: 0;
                line-height: 1.35;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .info-price {
                flex-shrink: 0;
                text-align: right;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .price-amount {
                display: block;
                font-weight: 700;
                color: #1a1a1a;
                font-size: 16px;
                white-space: nowrap;
            }

            .musement-certified-calendar-wrapper .calendar-product-info .price-tax {
                display: block;
                color: #767676;
                font-size: 12px;
                margin-top: 2px;
            }

            .musement-certified-calendar-wrapper .calendar-container {
                background: transparent;
                padding: 0;
                border: none;
                width: 100%;
            }

            /* El input solo existe para que flatpickr se enganche a él; el
               calendario en sí se pinta aparte (inline:true), así que el
               input de texto no debe verse (mostraba el valor "2026-08-20"
               en crudo debajo de "Fecha"). */
            .musement-certified-calendar-wrapper .calendar-container .certified-calendar-datepicker {
                display: none !important;
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

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-days,
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-rContainer {
                width: 100% !important;
            }

            /* Flatpickr limita cada día a max-width:39px por defecto; con el
               contenedor forzado a 100% ese límite fijo (no el 14.2857% pensado
               para 7 columnas) es lo que decide cuántos días caben por fila, así
               que si el contenedor es más ancho que 7*39px caben 8-9 días en vez
               de 7. Con una cuadrícula CSS de 7 columnas fijas, el número de días
               por fila ya no depende del tamaño de cada día. */
            .musement-certified-calendar-wrapper .calendar-container .dayContainer {
                display: grid !important;
                grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
                justify-items: center;
                align-items: center;
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day {
                width: 32px !important;
                height: 32px !important;
                max-width: 32px !important;
                line-height: 32px !important;
            }

            /* Cabecera mes/año: flatpickr la renderiza como <select>/<input>,
               los desnudamos de apariencia de formulario para que se vean como
               texto centrado en negrita, igual que en la web de referencia. */
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-months {
                margin-bottom: 6px;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-current-month {
                display: flex !important;
                align-items: center;
                justify-content: center;
                gap: 6px;
                font-size: 19px;
                font-weight: 700;
                color: #1a1a1a;
                padding: 0;
            }

            /* El tema/plugins de WordPress suelen forzar width:100% en todo
               input/select de formulario; sin anularlo aquí, el <select> del
               mes y el <input> del año se estiran y "Agosto" y "2026" quedan
               separados por un hueco enorme en vez de ir juntos. */
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-current-month .numInputWrapper {
                width: 4.5ch !important;
                flex: 0 0 auto;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-current-month .flatpickr-monthDropdown-months,
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-current-month input.cur-year {
                width: auto !important;
                max-width: none;
                flex: 0 0 auto;
                font-size: 19px;
                font-weight: 700;
                color: #1a1a1a;
                background: transparent;
                border: none;
                padding: 0;
                margin: 0;
                text-align: center;
                -webkit-appearance: none;
                appearance: none;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-prev-month,
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-next-month {
                padding: 0;
                background: transparent !important;
                fill: #1a1a1a;
                color: #1a1a1a;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-prev-month:hover svg,
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-next-month:hover svg {
                fill: #1a1a1a;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-weekdays {
                margin-bottom: 4px;
            }

            .musement-certified-calendar-wrapper .calendar-container span.flatpickr-weekday {
                font-weight: 700;
                font-size: 13px;
                color: #1a1a1a;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day {
                border-radius: 8px !important;
                font-weight: 500;
                color: #1a1a1a;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.flatpickr-disabled,
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.prevMonthDay,
            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.nextMonthDay {
                color: rgba(26, 26, 26, 0.3) !important;
                background: transparent !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.available {
                background: #78D28C !important;
                color: #1a1a1a !important;
                font-weight: 700;
                border-radius: 8px !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.available:hover {
                background: #1D5963 !important;
                color: #fff !important;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.today {
                border-color: #1a1a1a;
            }

            .musement-certified-calendar-wrapper .calendar-container .flatpickr-day.selected {
                background: #1b4b4b !important;
                border-color: #1b4b4b !important;
                color: #fff !important;
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
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 8px;
            }

            /* La cabecera "Horarios disponibles:" y el filtro de idioma son
               hijos directos del mismo contenedor que las tarjetas de horario;
               al convertirlo en grid, deben ocupar todas las columnas en vez
               de comportarse como una tarjeta más. */
            .musement-certified-calendar-wrapper .slots-container > div:not(.slot-item),
            .musement-certified-calendar-wrapper .slots-container > p {
                grid-column: 1 / -1;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                padding: 10px 12px;
                margin: 0;
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
                order: 1;
                font-weight: 500;
            }

            /* El precio va debajo del todo: al ser el único con flex-basis:100%
               fuerza el salto de línea justo después de él (hora + estado
               quedan juntos arriba, precio abajo), sin tocar el orden real
               del HTML que genera el JS. */
            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-price {
                order: 3;
                flex-basis: 100%;
                color: #666;
                font-size: 13px;
            }

            .musement-certified-calendar-wrapper .slots-container .slot-item .slot-status {
                order: 2;
                margin-left: auto;
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
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                padding: 0;
                border-radius: 50%;
                border: 1px solid #ddd;
                background: #fff;
                cursor: pointer;
                font-size: 20px;
                line-height: 1;
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
                flex-direction: column;
                gap: 10px;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary {
                width: 100%;
                padding: 14px;
                background: #1b4b4b;
                color: #fff;
                border: none;
                border-radius: 0px;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: background 0.3s;
                box-sizing: border-box;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary:hover:not(:disabled) {
                background: #123838;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary:disabled {
                background: #ccc;
                cursor: not-allowed;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-primary.added {
                background: #00a32a;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-secondary {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 3px;
                background: #fff;
                color: #1b4b4b;
                border: 1px solid #1b4b4b;
                border-radius: 0px;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                transition: background 0.2s;
                box-sizing: border-box;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-secondary:hover {
                background: #f2f7f6;
            }

            .musement-certified-calendar-wrapper .calendar-actions .btn-secondary svg {
                width: 18px;
                height: 18px;
                flex-shrink: 0;
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

        </style>

        <!-- Product Info -->
        <div class="calendar-header">
            <h3>Elige una fecha y reserva.</h3>
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
            <div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">Información de participantes</div>
            <div id="questions-content-<?php echo esc_attr($widget_id); ?>"></div>
        </div>

        <!-- Datos extra del cliente -->
        <div class="musement-certified-extra-data" id="extra-data-<?php echo esc_attr($widget_id); ?>" style="display:none;">
            <div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">Información adicional de reserva</div>
            <div id="extra-data-content-<?php echo esc_attr($widget_id); ?>"></div>
        </div>

        <!-- Summary -->
        <div class="selection-summary" id="summary-<?php echo esc_attr($widget_id); ?>">
            <div style="font-weight: 600; margin-bottom: 6px;">Resumen de selección</div>
            <div id="summary-content-<?php echo esc_attr($widget_id); ?>"></div>
        </div>

        <!-- Nombre del producto y precio, justo encima de los botones -->
        <div class="calendar-product-info" id="product-info-<?php echo esc_attr($widget_id); ?>">
            <?php if ($atts['image']): ?>
            <img src="<?php echo esc_url($atts['image']); ?>" alt="<?php echo esc_attr($atts['title']); ?>">
            <?php endif; ?>
            <div class="info-content">
                <h4><?php echo esc_html($atts['title']); ?></h4>
                <p><?php echo esc_html($atts['description']); ?></p>
            </div>
            <div class="info-price">
                <?php $currency_symbol = strtoupper($atts['currency']) === 'EUR' ? '€' : (' ' . esc_html($atts['currency'])); ?>
                <span class="price-amount"><?php echo number_format($atts['price'], 2, ',', '.'); ?> <?php echo $currency_symbol; ?></span>
                <span class="price-tax">imp. incl.</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="calendar-actions">
            <button class="btn-primary" id="add-cart-<?php echo esc_attr($widget_id); ?>" disabled>
                Añadir al carrito
            </button>
            <a class="btn-secondary" href="<?php echo esc_url(musement_checkout_url()); ?>">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M3 3h2l2.4 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 8H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="9" cy="20" r="1.4" fill="currentColor"/>
                    <circle cx="17" cy="20" r="1.4" fill="currentColor"/>
                </svg>
                Ir al carrito
            </a>
        </div>

        <div class="calendar-message" id="message-<?php echo esc_attr($widget_id); ?>"></div>

        <!-- Barra de carrito flotante -->
        <div class="musement-cart-bar" id="demo-cart-bar" >
            <span>
                <strong class="musement-cart-bar__count" id="cart-count-<?php echo esc_attr($widget_id); ?>">0</strong> producto(s)
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

        // Si el shortcode se procesa más de una vez en la misma carga de página
        // (habitual con plugins de SEO/algunos temas que pasan el contenido por
        // "the_content" más de una vez), este <script> se ejecuta dos veces y
        // crea dos "state" independientes con dos listeners sobre el mismo botón.
        // Uno de los dos nunca se entera de la fecha/horario ya elegidos, así que
        // al pulsar "Añadir al carrito" muestra "Selecciona fecha y horario" un
        // instante -- mientras el otro listener, con el estado correcto, sí añade
        // el producto, por eso el aviso desaparece sin que la compra falle de
        // verdad. Marcamos el elemento para que solo se inicialice una vez.
        if (widget.dataset.musementInitialized === 'true') {
            return;
        }
        widget.dataset.musementInitialized = 'true';

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
            // Mes ('YYYY-MM') al que corresponde "availability". Solo se pintan/bloquean
            // días cuando su mes es justo este: si la petición aún no ha llegado o ha
            // fallado, el calendario se deja tal cual en vez de teñirlo entero de gris.
            availabilityMonth: null,
            questions: [],
            participantCount: 1,
            // Ya no se confirma a mano: updateProgress lo pone a true en cuanto todos
            // los campos obligatorios de todos los participantes están rellenos.
            allConfirmed: false,
            addingToCart: false,
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
                        state.slotMinBuy = 1;
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

                    // Días de los meses vecinos que flatpickr pinta para cuadrar la
                    // rejilla: su disponibilidad no está cargada, así que se dejan
                    // intactos para poder seguir usándolos como salto de mes.
                    if (dayElem.classList.contains('prevMonthDay') ||
                        dayElem.classList.contains('nextMonthDay')) {
                        return;
                    }

                    // Días pasados: flatpickr ya los ha bloqueado por minDate y los pinta
                    // a su manera, no hay que volver a atenuarlos.
                    if (dayElem.classList.contains('flatpickr-disabled')) {
                        return;
                    }

                    var status = state.availability ? state.availability[dateStr] : null;

                    // Un día marcado 'full' se bloquea siempre, aunque el resto del mes
                    // no se haya podido cargar: o está agotado, o ya se comprobó que no
                    // tiene ningún horario.
                    if (status !== 'full') {
                        // Sin datos de este mes no se sabe nada todavía: no se toca nada.
                        if (state.availabilityMonth !== dateStr.substring(0, 7)) {
                            return;
                        }

                        if (status) {
                            dayElem.classList.add('available');
                            dayElem.style.background = '#edfaef';
                            dayElem.style.borderRadius = '4px';
                            return;
                        }
                    }

                    // Aquí caen tanto los días agotados ('full') como los que la API ni
                    // siquiera devuelve, que son los que no tienen ningún horario. Antes
                    // solo se bloqueaban los primeros y los segundos quedaban clicables
                    // con aspecto normal, así que el cliente elegía un día para acabar
                    // leyendo "No hay horarios disponibles". Los dos casos significan lo
                    // mismo -- no hay nada que reservar -- y se marcan igual.
                    //
                    // "flatpickr-disabled" por sí sola es solo estética: flatpickr no la
                    // usa para bloquear el click (eso solo pasa con la opción "disable" al
                    // construir el calendario, no con clases añadidas después). Sin
                    // pointer-events el día seguiría siendo clicable.
                    dayElem.classList.add('flatpickr-disabled');
                    dayElem.style.pointerEvents = 'none';
                    dayElem.style.opacity = '0.4';
                    dayElem.style.cursor = 'not-allowed';
                    dayElem.setAttribute('aria-disabled', 'true');
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
                // Si el usuario ya ha cambiado de mes, esta respuesta llega tarde y
                // pintaría el mes equivocado (o lo bloquearía entero).
                if (currentMonthStr() !== monthStr) {
                    return;
                }

                if (response && response.success) {
                    // Un mes sin ningún día devuelto es información válida ("no hay
                    // nada este mes"), no un fallo: se acepta el mapa vacío para que
                    // esos días salgan en gris.
                    state.availability = (response.data && response.data.days) || {};
                    state.availabilityMonth = monthStr;
                } else {
                    // Fallo del endpoint: mejor dejar el calendario abierto que
                    // bloquear días que quizá sí se pueden reservar.
                    state.availabilityMonth = null;
                }

                console.log('musement: disponibilidad de ' + monthStr, state.availability);

                if (fpInstance) {
                    fpInstance.redraw();
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                if (currentMonthStr() === monthStr) {
                    state.availabilityMonth = null;
                    if (fpInstance) {
                        fpInstance.redraw();
                    }
                }
            });
        }

        function currentMonthStr() {
            if (!fpInstance) return null;
            return fpInstance.currentYear + '-' + String(fpInstance.currentMonth + 1).padStart(2, '0');
        }

        // Un día que el calendario daba por bueno pero cuyo endpoint de horarios
        // devuelve cero horarios se marca como no disponible y se repinta, para que
        // quede en gris igual que el resto y nadie vuelva a pincharlo.
        function markDateUnavailable(dateStr) {
            if (!dateStr) return;
            state.availability = state.availability || {};
            if (state.availability[dateStr] === 'full') return;
            state.availability[dateStr] = 'full';
            if (fpInstance) {
                fpInstance.redraw();
            }
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

            // Los horarios de inventario en tiempo real pueden tardar varios segundos en
            // responder. Si el usuario cambia de fecha antes de que llegue la respuesta,
            // una petición anterior más lenta podía llegar después y repintar los horarios
            // con datos de la fecha equivocada, dejando "state.selectedSlot" desincronizado
            // con lo que el usuario veía en pantalla. Con este token solo se aplica la
            // respuesta de la petición más reciente.
            var requestDate = state.selectedDate;
            var requestToken = {};
            state._slotsRequestToken = requestToken;

            var data = new FormData();
            data.append('action', 'musement_day_availability');
            data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
            data.append('uuid', state.uuid);
            data.append('date', requestDate);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (state._slotsRequestToken !== requestToken || state.selectedDate !== requestDate) {
                    return; // respuesta obsoleta: el usuario ya cambió de fecha
                }

                if (response.success) {
                    state.lastSlotsData = response.data;
                    renderSlots(widgetId, response.data);
                } else {
                    container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles</p>';
                }
            })
            .catch(function() {
                if (state._slotsRequestToken !== requestToken || state.selectedDate !== requestDate) {
                    return;
                }
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
                    type: p.type || 'musement',
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
                // La respuesta ha sido correcta y no trae ni un horario: el día no es
                // reservable aunque el calendario lo diera por bueno. Se marca para que
                // pase a gris y no se pueda volver a elegir.
                markDateUnavailable(state.selectedDate);
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

            // Algunos productos (p.ej. entradas sin colas de uso libre) no tienen
            // horario real: Musement los representa igualmente como un grupo
            // TIME-BASED con un único slot a las "23:59", que no es una hora de
            // verdad -- es como marca "sin horario fijo, válido todo el día". Sin
            // este caso especial se mostraba "23:59" al cliente como si fuera un
            // horario real que tuviera que elegir, cuando el producto se puede usar
            // en cualquier momento del día seleccionado.
            var isFlexibleAllDay = groups.length === 1 &&
                (groups[0].slots || []).length === 1 &&
                groups[0].slots[0] &&
                groups[0].slots[0].time === '23:59';

            var html = '';

            // Filtro de idioma (solo si hay más de uno disponible)
            if (languageCodes.length > 1) {
                html += '<div class="language-filter" id="language-filter-' + widgetId + '">';
                html += '<span class="language-filter-label">Idioma:</span>';
                html += '<button type="button" class="language-filter-btn' + (!state.selectedLanguage ? ' active' : '') + '" data-lang="" data-product="' + widgetId + '">Todos</button>';
                languageCodes.forEach(function(code) {
                    var active = state.selectedLanguage === code ? ' active' : '';
                    html += '<button type="button" class="language-filter-btn' + active + '" data-lang="' + code + '" data-product="' + widgetId + '">' + languagesMap[code] + '</button>';
                });
                html += '</div>';
            }

            if (!isFlexibleAllDay) {
                html += '<div style="margin-top: 10px;"><strong>Horarios disponibles:</strong></div>';
            }
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
                        '" data-min-buy="' + (slot.min_buy || 1) +
                        '" data-products-b64="' + productsB64 + '">';
                    var timeLabel = isFlexibleAllDay ? 'Entrada válida todo el día' : (slot.time || 'Horario flexible');
                    html += '<span class="slot-time">' + timeLabel + '</span>';
                    html += '<span class="slot-price">' + priceLabel + '</span>';
                    if (language.code || language.name) {
                        html += '<span class="slot-status available">' + (language.name || language.code) + '</span>';
                    }
                    html += '<span class="slot-status ' + (isAvailable ? 'available' : 'unavailable') + '">' +
                        (isAvailable ? 'Disponible' : 'Agotado') + '</span>';
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
                    state.slotMinBuy = 1;
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

                    // Algunos horarios (sobre todo inventario en tiempo real) exigen un
                    // mínimo de participantes por reserva; si no se respeta, Musement
                    // rechaza el carrito con PARTY_SIZE_NOT_RESPECTED.
                    state.slotMinBuy = parseInt(this.dataset.minBuy, 10) || 1;
                    if ((state.participantCount || 1) < state.slotMinBuy) {
                        state.participantCount = state.slotMinBuy;
                    }

                    // Precio de referencia (el precio real se calcula por participante según el tipo elegido)
                    if (products.length > 0) {
                        var minP = products.reduce(function(min, p) { return (min === null || p.price < min.price) ? p : min; }, null);
                        state._price = minP.price;
                        state._productIdentifier = minP.id;
                        state._productType = minP.type;
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

            // Entrada de horario libre ("23:59" = todo el día, ver isFlexibleAllDay
            // arriba): no hay nada que elegir, así que se selecciona sola en cuanto
            // se pinta -- el cliente nunca ve un selector para un único horario falso.
            if (isFlexibleAllDay) {
                var onlyFlexibleSlot = container.querySelector('.slot-item:not(.unavailable)');
                if (onlyFlexibleSlot) {
                    onlyFlexibleSlot.click();
                }
            }
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


        function mapSchemaToQuestions(rawSchema) {
            if (!rawSchema) {
                return [];
            }

            // Algunas respuestas de Musement envuelven el JSON Schema en "data"
            // (como la mayoría de endpoints de este plugin), otras lo devuelven
            // directamente en la raíz. Se aceptan ambas formas en vez de asumir
            // solo una, para no depender de que todos los productos respondan
            // exactamente igual.
            var schema = rawSchema.properties
                ? rawSchema
                : (rawSchema.data && rawSchema.data.properties ? rawSchema.data : null);

            if (!schema || typeof schema.properties !== 'object' || schema.properties === null) {
                // No es un error -- simplemente no se reconoce la forma como un
                // JSON Schema con preguntas. Se avisa por consola para poder ver
                // qué mandó Musement realmente si algún producto falla, en vez de
                // fallar en silencio o reventar más abajo.
                console.warn('musement: esquema de participantes vacío o con forma inesperada', rawSchema);
                return [];
            }

            return Object.entries(schema.properties).map(function(entry) {
                var id = entry[0];
                var field = entry[1] || {};
                return {
                    id: id,
                    label: field.title || field.label || id,
                    type: getInputType(field),
                    required: Array.isArray(schema.required) && schema.required.indexOf(id) !== -1,
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
            var minBuy = state.slotMinBuy || 1;

            var html = '';

            // Selector de participantes
            html += '<div class="participant-selector">';
            html += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 15px;">Número de participantes</label>';
            html += '<div style="display: flex; align-items: center; gap: 12px;">';
            html += '<button class="participant-btn" data-action="minus" data-product="' + widgetId + '">−</button>';
            html += '<span id="participant-count-' + widgetId + '" style="font-size: 20px; font-weight: 600; min-width: 40px; text-align: center;">' + currentCount + '</span>';
            html += '<button class="participant-btn" data-action="plus" data-product="' + widgetId + '">+</button>';
            html += '</div>';
            if (minBuy > 1) {
                html += '<div style="font-size: 12px; color: #666; margin-top: 6px;">Este horario exige un mínimo de ' + minBuy + ' participantes</div>';
            }
            html += '<div style="font-size: 12px; color: #666; margin-top: 6px;">Cada participante debe completar su propia información</div>';
            html += '</div>';
            
            // Formularios por participante
            html += '<div id="participants-container-' + widgetId + '">';
            html += renderParticipantForms(widgetId, questions, currentCount);
            html += '</div>';
            
            // Progress
            html += '<div class="certified-progress" id="progress-' + widgetId + '">Progreso: 0/0 campos completados</div>';

            content.innerHTML = html;

            // Eventos de participantes
            document.querySelectorAll('.participant-btn[data-product="' + widgetId + '"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var action = this.dataset.action;
                    var currentCount = state.participantCount || 1;
                    var minBuy = state.slotMinBuy || 1;
                    var newCount = action === 'plus' ? currentCount + 1 : Math.max(minBuy, currentCount - 1);
                    
                    state.participantCount = newCount;
                    document.getElementById('participant-count-' + widgetId).textContent = newCount;
                    
                    var container = document.getElementById('participants-container-' + widgetId);
                    container.innerHTML = renderParticipantForms(widgetId, state.questions, newCount);

                    // Al cambiar el número de participantes hay formularios nuevos sin
                    // rellenar: updateProgress vuelve a calcular si la selección está
                    // completa y bloquea "Añadir al carrito" si ya no lo está.
                    updateProgress(widgetId);
                    attachQuestionEvents(widgetId);
                });
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
            html += '<div style="font-weight: 600; margin-bottom: 8px; font-size: 15px;">Información extra del cliente</div>';
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
                html += '<span>Participante ' + p + '</span>';
                html += '<span class="participant-status" id="participant-status-' + widgetId + '-' + p + '">Pendiente</span>';
                html += '</div>';

                // Tipo de entrada (adulto/niño/etc.), si el producto tiene varios tipos disponibles
                if (products.length > 0) {
                    var ticketInputId = 'ticket_' + widgetId + '_p' + p;
                    html += '<div class="question-item">';
                    html += '<label for="' + ticketInputId + '">Tipo de entrada';
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
                        statusEl.textContent = 'Completado';
                        statusEl.className = 'participant-status completed';
                    } else {
                        statusEl.textContent = 'Pendiente';
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
                var msg = 'Progreso: ' + filledFields + '/' + totalFields + ' campos completados (' + progress + '%)';
                if (requiredFields > 0 && filledRequired < requiredFields) {
                    msg += ' - Faltan ' + (requiredFields - filledRequired) + ' obligatorios';
                    progressEl.className = 'certified-progress';
                } else if (filledFields === totalFields) {
                    progressEl.className = 'certified-progress completed';
                } else {
                    progressEl.className = 'certified-progress';
                }
                progressEl.textContent = msg;
            }
            
            // Ya no hay paso manual de "Confirmar participantes": en cuanto todos los
            // campos obligatorios (incluido el tipo de entrada) están rellenos, la
            // selección se da por válida y "Añadir al carrito" se desbloquea solo.
            state.allConfirmed = requiredFields === 0 || filledRequired === requiredFields;
            updateSummary(widgetId);
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
                        type: state._productType || 'musement',
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
                            type: product.type || 'musement',
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
            html += '<div class="summary-row"><span>Fecha</span><span>' + state.selectedDate + '</span></div>';
            html += '<div class="summary-row"><span>Horario</span><span>' + state.selectedSlot.time + '</span></div>';
            if (state.selectedSlot.language || state.selectedSlot.languageName) {
                html += '<div class="summary-row"><span>Idioma</span><span>' + (state.selectedSlot.languageName || state.selectedSlot.language) + '</span></div>';
            }
            html += '<div class="summary-row"><span>Participantes</span><span>' + participantCount + '</span></div>';

            pricing.items.forEach(function(item) {
                html += '<div class="summary-row"><span>' + item.productName + ' x' + item.quantity + '</span><span>' + (item.unitPrice * item.quantity).toFixed(2) + ' ' + item.currency + '</span></div>';
            });

            html += '<div class="summary-row summary-total"><span>Total</span><span>' + pricing.total.toFixed(2) + ' ' + pricing.currency + '</span></div>';

            content.innerHTML = html;
            summary.classList.add('visible');

            // Mientras se está enviando el carrito el botón sigue bloqueado: ahora
            // updateSummary se dispara al teclear en cualquier campo, y sin esto una
            // edición durante la petición reabriría la puerta al doble envío.
            if (!state.addingToCart) {
                addBtn.disabled = false;
            }
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
                showMessage(widgetId, 'error', 'Selecciona fecha y horario');
                return;
            }

            var minBuyCheck = state.slotMinBuy || 1;
            if ((state.participantCount || 1) < minBuyCheck) {
                showMessage(widgetId, 'error', 'Este horario exige un mínimo de ' + minBuyCheck + ' participantes');
                return;
            }

            // Red de seguridad: el botón ya solo se habilita con todo relleno, pero si
            // algo se ha quedado a medias marcamos los campos que faltan.
            if (!validateAllParticipants(widgetId)) {
                showMessage(widgetId, 'error', 'Completa todos los campos obligatorios para cada participante');
                return;
            }

            var pricing = getParticipantsPricing(widgetId);

            if (pricing.items.length === 0) {
                showMessage(widgetId, 'error', 'Selecciona el tipo de entrada para cada participante');
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
                type: mainItem.type || 'musement',
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
            state.addingToCart = true;

            console.log('Musement cart add payload', payload);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(function(response) { return response.text(); })
            .then(function(text) {
                try {
                    var response = JSON.parse(text);
                    console.log('Respuesta carrito:', response);

                    if (response && response.success) {
                        showMessage(widgetId, 'success', '' + participantCount + ' participantes añadidos al carrito');
                        updateCartBadge(widgetId, response.data);

                        addBtn.textContent = 'Añadido (' + participantCount + ' participantes)';
                        addBtn.classList.add('added');
                        setTimeout(function() {
                            addBtn.textContent = 'Añadir al carrito';
                            addBtn.classList.remove('added');
                            state.addingToCart = false;
                            addBtn.disabled = false;
                        }, 3000);
                    } else {
                        var errorMsg = response && response.data ? response.data.message : 'Error desconocido';
                        showMessage(widgetId, 'error', 'Error: ' + errorMsg);
                        console.error('Error detallado:', response);
                        if (response && response.data && response.data.remote_error_data) {
                            console.error('Musement remote_error_data:', JSON.stringify(response.data.remote_error_data, null, 2));
                        }
                        state.addingToCart = false;
                        addBtn.disabled = false;
                    }
                } catch(e) {
                    console.error('Error parsing JSON:', e);
                    showMessage(widgetId, 'error', 'Error de respuesta: ' + text.substring(0, 200));
                    state.addingToCart = false;
                    addBtn.disabled = false;
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showMessage(widgetId, 'error', 'Error de conexión: ' + error.message);
                state.addingToCart = false;
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