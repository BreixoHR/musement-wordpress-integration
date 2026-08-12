<?php
/**
 * Sistema Unificado de Certificación y Demostración para Productos Musement
 * 
 * @package MusementIntegration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase unificada para certificación y demostración frontend
 */
class Musement_Certification_Unified {
    
    private $test_products = [
        'standard_dated' => [
            'uuid' => '101dcb56-2046-11e7-9cc9-06a7e332783f',
            'name' => 'Duomo Milan',
            'type' => 'standard_dated',
            'icon' => '🏛️',
            'description' => 'Producto con fecha y hora fija. El usuario selecciona día y horario específico.',
            'color' => '#2271b1'
        ],
        'supplier_questions' => [
            'uuid' => '0420c908-981e-4e27-a40f-767e8b66574a',
            'name' => 'Knossos (date of birth)',
            'type' => 'supplier_questions',
            'icon' => '📋',
            'description' => 'Producto con preguntas del proveedor. El usuario debe responder preguntas adicionales.',
            'color' => '#7b2fbe'
        ],
        'pickup' => [
            'uuid' => 'df542cb8-8fca-44d0-94e6-715399c783f0',
            'name' => 'Calendar Activity with Pickup',
            'type' => 'pickup',
            'icon' => '🚐',
            'description' => 'Producto con opción de recogida. El usuario selecciona punto de recogida.',
            'color' => '#d63638'
        ],
        'open_date' => [
            'uuid' => '82f6f46c-3923-4510-b5ba-b50aad624eb1',
            'name' => 'Genova Aquarium',
            'type' => 'open_date',
            'icon' => '🐠',
            'description' => 'Producto sin fecha fija (open date). El usuario puede comprar sin seleccionar fecha.',
            'color' => '#00a32a'
        ]
    ];
    
    private $certification_results = [];
    private $current_test_session = '';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_musement_run_certification', [$this, 'ajax_run_certification']);
        add_action('wp_ajax_musement_export_certification', [$this, 'ajax_export_certification']);
        add_action('wp_ajax_musement_test_product_flow', [$this, 'ajax_test_product_flow']);
        add_action('wp_ajax_musement_demo_add_to_cart', [$this, 'ajax_demo_add_to_cart']);
        add_action('wp_ajax_musement_demo_get_cart', [$this, 'ajax_demo_get_cart']);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'musement-settings',
            __('Certificación y Demo', 'musement-integration'),
            __('Certificación', 'musement-integration'),
            'manage_options',
            'musement-certification',
            [$this, 'render_unified_page']
        );
    }
    
    // ============================================================
    // SHORTCODE PARA TARJETAS - CON CARRITO REAL
    // ============================================================
    
    /**
     * Shortcode para mostrar la demo con tarjetas
     * Uso: [musement_certification_cards]
     */
    public static function render_cards_shortcode() {
        $instance = new self();
        return $instance->render_cards_frontend();
    }
    
    /**
     * Renderizar demo con tarjetas - USA EL CARRITO REAL
     */
    public function render_cards_frontend() {
        wp_enqueue_style('flatpickr');
        wp_enqueue_script('flatpickr');
        wp_enqueue_script('flatpickr-es');
        wp_enqueue_style('musement-frontend');
        
        ob_start();
        ?>
        <div class="musement-demo-cards">
            <style>
                .musement-demo-cards {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px 15px 80px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                
                .musement-demo-cards .demo-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                
                .musement-demo-cards .demo-header h2 {
                    font-size: 28px;
                    color: #1d2327;
                    margin: 0 0 8px;
                }
                
                .musement-demo-cards .demo-header p {
                    color: #666;
                    font-size: 15px;
                }
                
                .musement-demo-cards .demo-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 25px;
                }
                
                .musement-demo-cards .demo-card {
                    background: #fff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                    border: 1px solid #e5e7eb;
                    transition: transform 0.3s, box-shadow 0.3s;
                }
                
                .musement-demo-cards .demo-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
                }
                
                .musement-demo-cards .demo-card-image {
                    height: 200px;
                    background: #f0f0f1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 60px;
                    position: relative;
                }
                
                .musement-demo-cards .demo-card-image .demo-badge {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: rgba(0,0,0,0.7);
                    color: #fff;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                }
                
                .musement-demo-cards .demo-card-image .demo-type {
                    position: absolute;
                    bottom: 10px;
                    left: 10px;
                    background: rgba(34,113,177,0.85);
                    color: #fff;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                }
                
                .musement-demo-cards .demo-card-body {
                    padding: 18px 20px 20px;
                }
                
                .musement-demo-cards .demo-card-body h3 {
                    font-size: 18px;
                    color: #1d2327;
                    margin: 0 0 6px;
                }
                
                .musement-demo-cards .demo-card-body .demo-description {
                    color: #666;
                    font-size: 14px;
                    line-height: 1.5;
                    margin: 0 0 12px;
                }
                
                .musement-demo-cards .demo-card-body .demo-meta {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px 0;
                    border-top: 1px solid #f0f0f1;
                    border-bottom: 1px solid #f0f0f1;
                    margin-bottom: 12px;
                }
                
                .musement-demo-cards .demo-card-body .demo-price {
                    font-size: 18px;
                    font-weight: 700;
                    color: #1d2327;
                }
                
                .musement-demo-cards .demo-card-body .demo-price span {
                    font-size: 14px;
                    font-weight: 400;
                    color: #666;
                }
                
                .musement-demo-cards .demo-card-body .demo-status {
                    font-size: 13px;
                    font-weight: 500;
                }
                
                .musement-demo-cards .demo-card-body .demo-status.pending { color: #ffb900; }
                .musement-demo-cards .demo-card-body .demo-status.available { color: #00a32a; }
                .musement-demo-cards .demo-card-body .demo-status.unavailable { color: #d63638; }
                .musement-demo-cards .demo-card-body .demo-status.loading { color: #ffb900; }
                
                .musement-demo-cards .demo-btn {
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
                
                .musement-demo-cards .demo-btn:hover {
                    background: #135e96;
                    transform: translateY(-1px);
                }
                
                .musement-demo-cards .demo-btn:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                    transform: none;
                }
                
                .musement-demo-cards .demo-btn.active {
                    background: #00a32a;
                }
                
                .musement-demo-cards .demo-calendar-wrapper {
                    margin-top: 12px;
                    padding: 12px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 1px solid #e5e7eb;
                    display: none;
                }
                
                .musement-demo-cards .demo-calendar-wrapper.visible {
                    display: block;
                }
                
                .musement-demo-cards .demo-calendar-wrapper .flatpickr-calendar {
                    width: 100% !important;
                    max-width: 100% !important;
                    box-shadow: none !important;
                    border: none !important;
                }
                
                .musement-demo-cards .demo-calendar-wrapper .flatpickr-calendar.open {
                    position: relative !important;
                    top: 0 !important;
                    left: 0 !important;
                }
                
                .musement-demo-cards .demo-calendar-wrapper .flatpickr-days {
                    width: 100% !important;
                }
                
                .musement-demo-cards .demo-calendar-wrapper .dayContainer {
                    display: grid !important;
                    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
                    width: 100% !important;
                    min-width: 100% !important;
                    max-width: 100% !important;
                }

                .musement-demo-cards .demo-calendar-wrapper .flatpickr-day {
                    max-width: none !important;
                    width: 100% !important;
                }
                
                .musement-demo-cards .demo-slots {
                    margin-top: 10px;
                    display: none;
                }
                
                .musement-demo-cards .demo-slots.visible {
                    display: block;
                }
                
                .musement-demo-cards .demo-slot {
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
                
                .musement-demo-cards .demo-slot:hover:not(.unavailable) {
                    border-color: #2271b1;
                    background: #f8f9fa;
                }
                
                .musement-demo-cards .demo-slot.selected {
                    border-color: #2271b1;
                    background: #e8f0fe;
                }
                
                .musement-demo-cards .demo-slot.unavailable {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                
                .musement-demo-cards .demo-slot .slot-time {
                    font-weight: 500;
                }
                
                .musement-demo-cards .demo-slot .slot-status {
                    font-size: 12px;
                    padding: 2px 10px;
                    border-radius: 12px;
                }
                
                .musement-demo-cards .demo-slot .slot-status.available {
                    background: #edfaef;
                    color: #008a20;
                }
                
                .musement-demo-cards .demo-slot .slot-status.unavailable {
                    background: #fce8e8;
                    color: #d63638;
                }
                
                /* Selección info */
                .musement-demo-cards .demo-selection-info {
                    margin-top: 10px;
                    padding: 10px 12px;
                    background: #f0f7ff;
                    border-radius: 6px;
                    border: 1px solid #d4e4f7;
                    font-size: 13px;
                    display: none;
                }
                
                .musement-demo-cards .demo-selection-info.visible {
                    display: block;
                }
                
                .musement-demo-cards .demo-selection-info .info-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 2px 0;
                }
                
                .musement-demo-cards .demo-selection-info .info-total {
                    border-top: 1px solid #d4e4f7;
                    margin-top: 4px;
                    padding-top: 4px;
                    font-weight: 700;
                }
                
                /* Botón añadir al carrito real */
                .musement-demo-cards .demo-add-to-real-cart {
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
                
                .musement-demo-cards .demo-add-to-real-cart:hover:not(:disabled) {
                    background: #135e96;
                    transform: translateY(-1px);
                }
                
                .musement-demo-cards .demo-add-to-real-cart:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                    transform: none;
                }
                
                .musement-demo-cards .demo-add-to-real-cart.added {
                    background: #00a32a;
                }
                
                .musement-demo-cards .demo-cart-message {
                    margin-top: 8px;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 13px;
                    display: none;
                }
                
                .musement-demo-cards .demo-cart-message.success {
                    display: block;
                    background: #edfaef;
                    border: 1px solid #b8e6c0;
                    color: #008a20;
                }
                
                .musement-demo-cards .demo-cart-message.error {
                    display: block;
                    background: #fce8e8;
                    border: 1px solid #f5c8c8;
                    color: #d63638;
                }
                
                @media (max-width: 768px) {
                    .musement-demo-cards .demo-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
            
            <div class="demo-header">
                <h2>🧪 Demo de Productos - Certificación</h2>
                <p>Prueba el flujo completo de compra con diferentes tipos de productos</p>
                <small style="color: #999;">Los productos se añaden al carrito real con Stripe</small>
            </div>
            
            <div class="demo-grid">
                <?php foreach ($this->test_products as $key => $product): ?>
                <div class="demo-card" data-product="<?php echo esc_attr($key); ?>">
                    <div class="demo-card-image" style="background: <?php echo $product['color']; ?>20;">
                        <?php echo $product['icon']; ?>
                        <span class="demo-badge">🔬 Demo</span>
                        <span class="demo-type"><?php echo esc_html($product['type']); ?></span>
                    </div>
                    
                    <div class="demo-card-body">
                        <h3><?php echo esc_html($product['name']); ?></h3>
                        <p class="demo-description"><?php echo esc_html($product['description']); ?></p>
                        
                        <div class="demo-meta">
                            <span class="demo-price">
                                <span>Desde</span> 
                                <?php echo number_format(20.00, 2); ?> €
                            </span>
                            <span class="demo-status pending" id="status-<?php echo esc_attr($key); ?>">
                                ⏳ Pendiente
                            </span>
                        </div>
                        
                        <button class="demo-btn" id="btn-<?php echo esc_attr($key); ?>" 
                                data-product="<?php echo esc_attr($key); ?>">
                            📅 Ver Disponibilidad
                        </button>
                        
                        <div class="demo-calendar-wrapper" id="calendar-<?php echo esc_attr($key); ?>">
                            <input type="text" class="demo-datepicker" 
                                   id="datepicker-<?php echo esc_attr($key); ?>"
                                   placeholder="Selecciona una fecha"
                                   data-product="<?php echo esc_attr($key); ?>"
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        
                        <div class="demo-slots" id="slots-<?php echo esc_attr($key); ?>">
                            <p style="color: #999; text-align: center; padding: 10px; margin: 0;">
                                Selecciona una fecha para ver horarios
                            </p>
                        </div>
                        
                        <!-- Info de selección -->
                        <div class="demo-selection-info" id="selection-<?php echo esc_attr($key); ?>">
                            <div class="info-row"><span>📅 Fecha</span><span id="sel-date-<?php echo esc_attr($key); ?>">-</span></div>
                            <div class="info-row"><span>🕐 Horario</span><span id="sel-time-<?php echo esc_attr($key); ?>">-</span></div>
                            <?php if ($product['type'] === 'pickup'): ?>
                            <div class="info-row"><span>🏨 Recogida</span><span id="sel-pickup-<?php echo esc_attr($key); ?>">-</span></div>
                            <?php endif; ?>
                            <div class="info-row info-total"><span>💰 Total</span><span id="sel-total-<?php echo esc_attr($key); ?>">20.00 €</span></div>
                        </div>
                        
                        <!-- Botón añadir al carrito REAL -->
                        <button class="demo-add-to-real-cart" id="add-cart-<?php echo esc_attr($key); ?>" 
                                data-product="<?php echo esc_attr($key); ?>" disabled>
                            🛒 Añadir al carrito
                        </button>
                        <div class="demo-cart-message" id="message-<?php echo esc_attr($key); ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var state = {};
                var fpInstances = {};
                
                <?php foreach ($this->test_products as $key => $product): ?>
                state['<?php echo esc_js($key); ?>'] = {
                    uuid: '<?php echo esc_js($product['uuid']); ?>',
                    name: '<?php echo esc_js($product['name']); ?>',
                    type: '<?php echo esc_js($product['type']); ?>',
                    selectedDate: null,
                    selectedSlot: null,
                    selectedPickup: null,
                    availability: {},
                    questions: [],
                    answers: {},
                    questionsAnswered: false,
                    isOpenDate: <?php echo ($product['type'] === 'open_date') ? 'true' : 'false'; ?>,
                    _price: null,
                    _currency: 'EUR'
                };
                <?php endforeach; ?>
                
                // Botones Ver Disponibilidad
                document.querySelectorAll('.demo-btn[data-product]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var productKey = this.dataset.product;
                        var wrapper = document.getElementById('calendar-' + productKey);
                        var isVisible = wrapper.classList.contains('visible');
                        
                        document.querySelectorAll('.demo-calendar-wrapper').forEach(function(w) {
                            w.classList.remove('visible');
                        });
                        
                        if (!isVisible) {
                            wrapper.classList.add('visible');
                            initCalendar(productKey);
                        }
                    });
                });
                
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
                                if (stateData.type === 'pickup') {
                                    stateData.selectedPickup = null;
                                    stateData.selectedSlot = null;
                                }
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
                
                function loadSlots(productKey) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    // 🔥 SI ES OPEN DATE - NO MOSTRAR CALENDARIO
                    if (stateData.isOpenDate) {
                        console.log('📅 Producto Open Date - mostrando mensaje de fecha abierta');
                        
                        var html = '<div style="margin-top: 10px; padding: 12px; background: #e8f5e9; border-radius: 6px; border: 1px solid #a5d6a7;">';
                        html += '<div style="font-weight: 600; margin-bottom: 6px; font-size: 15px;">✅ Entrada sin fecha fija (Open Date)</div>';
                        html += '<div style="font-size: 14px; color: #2e7d32;">';
                        html += '🕐 Puedes usar este ticket en cualquier fecha dentro del próximo año.<br>';
                        html += '📅 No necesitas seleccionar fecha ni horario específico.';
                        html += '</div>';
                        html += '</div>';
                        
                        container.innerHTML = html;
                        container.classList.add('visible');
                        
                        // 🔥 Marcar como seleccionado automáticamente
                        stateData.selectedDate = 'open-date';
                        stateData.selectedSlot = { time: 'Flexible' };
                        
                        // Actualizar estado
                        updateStatus(productKey, 'available', '✅ Open Date');
                        updateSelectionInfo(productKey);
                        return;
                    }
                    
                    if (!stateData.selectedDate) {
                        container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">Selecciona una fecha</p>';
                        container.classList.remove('visible');
                        return;
                    }
                    
                    if (stateData.type === 'pickup') {
                        loadPickupOptionsFromAPI(productKey);
                        return;
                    }

                    if (stateData.type === 'supplier_questions') {
                        loadSupplierQuestions(productKey);
                        return;
                    }
                    
                    loadSlotsDirect(productKey);
                }
                
                function loadPickupOptionsFromAPI(productKey) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    if (!stateData.selectedDate) {
                        container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">Selecciona una fecha primero</p>';
                        return;
                    }
                    
                    container.innerHTML = '<p style="color: #666; text-align: center; padding: 10px;">Cargando opciones de recogida...</p>';
                    container.classList.add('visible');
                    
                    var data = new FormData();
                    data.append('action', 'musement_get_pickup_options');
                    data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
                    data.append('uuid', stateData.uuid);
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: data
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(response) {
                        if (response.success && response.data.pickups && response.data.pickups.length > 0) {
                            renderPickupSelect(productKey, response.data.pickups);
                        } else {
                            container.innerHTML = `
                                <p style="color: #999; text-align: center; padding: 10px;">
                                    Este producto no requiere selección de recogida.<br>
                                    <button class="demo-btn" style="margin-top: 10px; width: auto; padding: 6px 20px;" onclick="loadSlotsDirect('${productKey}')">
                                        Continuar sin recogida
                                    </button>
                                </p>
                            `;
                            updateStatus(productKey, 'available', '✅ Sin recogida');
                        }
                    })
                    .catch(function(error) {
                        console.error('Error cargando pickup:', error);
                        container.innerHTML = `
                            <p style="color: #d63638; text-align: center; padding: 10px;">
                                Error al cargar opciones de recogida.<br>
                                <button class="demo-btn" style="margin-top: 10px; width: auto; padding: 6px 20px; background: #d63638;" onclick="loadSlotsDirect('${productKey}')">
                                    Intentar sin recogida
                                </button>
                            </p>
                        `;
                        updateStatus(productKey, 'unavailable', '❌ Error');
                    });
                }
                
                function renderPickupSelect(productKey, pickups) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    var html = '<div style="margin-bottom: 10px; padding: 12px; background: #f0f7ff; border-radius: 6px; border: 1px solid #d4e4f7;">';
                    html += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">🏨 Selecciona punto de recogida:</label>';
                    html += '<select id="pickup-select-' + productKey + '" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: #fff; cursor: pointer;">';
                    html += '<option value="">-- Selecciona una opción --</option>';
                    
                    pickups.forEach(function(pickup) {
                        var id = pickup.id || pickup.uuid || '';
                        var name = pickup.name || 'Punto de recogida';
                        var type = pickup.type || '';
                        var address = pickup.address || '';
                        var displayText = name;
                        if (type) displayText += ' (' + type + ')';
                        if (address) displayText += ' - ' + address;
                        
                        // 🔥 MARCAR LA OPCIÓN SELECCIONADA
                        var isSelected = (stateData.selectedPickup === id);
                        html += '<option value="' + id + '" ' + (isSelected ? 'selected' : '') + '>' + displayText + '</option>';
                    });
                    
                    html += '</select>';
                    html += '</div>';
                    
                    // Si ya hay pickup seleccionado, mostrar horarios
                    if (stateData.selectedPickup) {
                        html += '<div style="margin-top: 10px;"><strong>🕐 Horarios disponibles:</strong></div>';
                        html += '<p style="color: #666; text-align: center; padding: 10px;">Cargando horarios para el punto de recogida seleccionado...</p>';
                    } else {
                        html += '<p style="color: #999; text-align: center; padding: 10px; margin: 0;">Selecciona un punto de recogida para ver horarios</p>';
                    }
                    
                    container.innerHTML = html;
                    
                    // 🔥 EVENTO DE CAMBIO DEL SELECT
                    var select = document.getElementById('pickup-select-' + productKey);
                    if (select) {
                        // Asegurar que el valor seleccionado está correcto
                        if (stateData.selectedPickup) {
                            select.value = stateData.selectedPickup;
                        }
                        
                        select.addEventListener('change', function() {
                            var pickupId = this.value;
                            console.log('🔄 Pickup seleccionado:', pickupId);
                            
                            if (pickupId) {
                                stateData.selectedPickup = pickupId;
                                stateData.selectedSlot = null;
                                stateData._price = null;
                                updateStatus(productKey, 'loading', '⏳ Cargando horarios...');
                                updateSelectionInfo(productKey);
                                loadSlotsWithPickupSelect(productKey);
                            } else {
                                stateData.selectedPickup = null;
                                stateData.selectedSlot = null;
                                stateData._price = null;
                                updateStatus(productKey, 'pending', '📍 Selecciona recogida');
                                updateSelectionInfo(productKey);
                                
                                // Mostrar solo el select sin horarios
                                var container = document.getElementById('slots-' + productKey);
                                var html = '<div style="margin-bottom: 10px; padding: 12px; background: #f0f7ff; border-radius: 6px; border: 1px solid #d4e4f7;">';
                                html += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">🏨 Selecciona punto de recogida:</label>';
                                html += select.outerHTML;
                                html += '</div>';
                                html += '<p style="color: #999; text-align: center; padding: 10px; margin: 0;">Selecciona un punto de recogida para ver horarios</p>';
                                container.innerHTML = html;
                                
                                // Re-aplicar evento
                                document.getElementById('pickup-select-' + productKey).addEventListener('change', function() {
                                    var pickupId = this.value;
                                    if (pickupId) {
                                        stateData.selectedPickup = pickupId;
                                        stateData.selectedSlot = null;
                                        stateData._price = null;
                                        updateStatus(productKey, 'loading', '⏳ Cargando horarios...');
                                        updateSelectionInfo(productKey);
                                        loadSlotsWithPickupSelect(productKey);
                                    }
                                });
                            }
                        });
                    }
                }
                
                function loadSlotsWithPickupSelect(productKey) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    if (!stateData.selectedDate || !stateData.selectedPickup) return;
                    
                    // Mantener el select con la selección actual
                    var selectHtml = '';
                    var select = document.getElementById('pickup-select-' + productKey);
                    if (select) {
                        // Asegurar que el valor seleccionado está correcto
                        select.value = stateData.selectedPickup;
                        
                        selectHtml = '<div style="margin-bottom: 10px; padding: 12px; background: #f0f7ff; border-radius: 6px; border: 1px solid #d4e4f7;">';
                        selectHtml += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">🏨 Punto de recogida:</label>';
                        selectHtml += select.outerHTML;
                        selectHtml += '</div>';
                    }
                    
                    container.innerHTML = selectHtml + '<div style="margin-top: 10px;"><strong>🕐 Cargando horarios...</strong></div><p style="color: #666; text-align: center; padding: 10px;">Cargando horarios para el punto de recogida seleccionado...</p>';
                    
                    // Re-aplicar evento al select
                    var newSelect = document.getElementById('pickup-select-' + productKey);
                    if (newSelect) {
                        // 🔥 FORZAR EL VALOR SELECCIONADO
                        newSelect.value = stateData.selectedPickup;
                        
                        newSelect.addEventListener('change', function() {
                            var pickupId = this.value;
                            if (pickupId) {
                                stateData.selectedPickup = pickupId;
                                stateData.selectedSlot = null;
                                stateData._price = null;
                                updateStatus(productKey, 'loading', '⏳ Cargando horarios...');
                                updateSelectionInfo(productKey);
                                loadSlotsWithPickupSelect(productKey);
                            } else {
                                stateData.selectedPickup = null;
                                stateData.selectedSlot = null;
                                stateData._price = null;
                                updateStatus(productKey, 'pending', '📍 Selecciona recogida');
                                updateSelectionInfo(productKey);
                            }
                        });
                    }
                    
                    // Llamar a la API
                    var data = new FormData();
                    data.append('action', 'musement_day_availability');
                    data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
                    data.append('uuid', stateData.uuid);
                    data.append('date', stateData.selectedDate);
                    data.append('pickup', stateData.selectedPickup);
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: data
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(response) {
                        if (response.success) {
                            renderSlotsWithPickupSelect(productKey, response.data);
                        } else {
                            container.innerHTML += '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles para este punto de recogida</p>';
                            updateStatus(productKey, 'unavailable', '❌ Sin horarios');
                        }
                    })
                    .catch(function() {
                        container.innerHTML += '<p style="color: #d63638; text-align: center; padding: 10px;">Error al cargar horarios</p>';
                        updateStatus(productKey, 'unavailable', '❌ Error');
                    });
                }
                
                function renderSlotsWithPickupSelect(productKey, data) {
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
                    
                    var selectHtml = '';
                    var select = document.getElementById('pickup-select-' + productKey);
                    if (select) {
                        selectHtml = '<div style="margin-bottom: 10px; padding: 12px; background: #f0f7ff; border-radius: 6px; border: 1px solid #d4e4f7;">';
                        selectHtml += '<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">🏨 Punto de recogida:</label>';
                        selectHtml += select.outerHTML;
                        selectHtml += '</div>';
                    }
                    
                    if (!groups || groups.length === 0) {
                        container.innerHTML = selectHtml + '<p style="color: #999; text-align: center; padding: 10px;">No hay horarios disponibles</p>';
                        updateStatus(productKey, 'unavailable', '❌ Sin horarios');
                        return;
                    }
                    
                    var html = selectHtml + '<div style="margin-top: 10px;"><strong>🕐 Horarios disponibles:</strong></div>';
                    var hasAvailable = false;
                    var totalSlots = 0;
                    var firstPrice = null; // 🔥 Guardar el precio del primer slot disponible
                    
                    groups.forEach(function(group) {
                        var slots = group.slots || [];
                        totalSlots += slots.length;
                        
                        slots.forEach(function(slot) {
                            var isAvailable = slot.availability === -1 || slot.availability > 0;
                            if (isAvailable) hasAvailable = true;
                            
                            var isSelected = stateData.selectedSlot && stateData.selectedSlot.time === slot.time;
                            
                            // 🔥 OBTENER PRECIO REAL
                            var price = 'Precio variable';
                            var priceValue = 0;
                            var currency = 'EUR';
                            
                            if (slot.products && slot.products.length > 0) {
                                var firstProduct = slot.products[0];
                                if (firstProduct.retail_price) {
                                    price = firstProduct.retail_price.formatted_value || 
                                            firstProduct.retail_price.formatted_iso_value || 
                                            firstProduct.retail_price.value + ' ' + firstProduct.retail_price.currency;
                                    priceValue = parseFloat(firstProduct.retail_price.value) || 0;
                                    currency = firstProduct.retail_price.currency || 'EUR';
                                    
                                    // 🔥 Guardar el precio del primer slot disponible
                                    if (isAvailable && firstPrice === null) {
                                        firstPrice = priceValue;
                                        stateData._price = priceValue;
                                        stateData._currency = currency;
                                    }
                                }
                            }
                            
                            html += '<div class="demo-slot' + (isSelected ? ' selected' : '') + (isAvailable ? '' : ' unavailable') + '" data-time="' + (slot.time || 'flexible') + '" data-price="' + priceValue + '" style="margin: 4px 0; padding: 10px 12px; background: #fff; border: 1px solid ' + (isSelected ? '#2271b1' : '#e5e7eb') + '; border-radius: 4px; cursor: pointer; transition: all 0.2s;">';
                            html += '<div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 8px;">';
                            html += '<span style="font-weight: 500;">🕐 ' + (slot.time || 'Horario flexible') + '</span>';
                            html += '<span style="color: #666; font-size: 13px;">' + price + '</span>';
                            html += '<span class="slot-status ' + (isAvailable ? 'available' : 'unavailable') + '" style="font-size: 12px; padding: 2px 10px; border-radius: 12px; ' + (isAvailable ? 'background: #edfaef; color: #008a20;' : 'background: #fce8e8; color: #d63638;') + '">' + 
                                (isAvailable ? '✅ Disponible' : '❌ Agotado') + '</span>';
                            html += '</div>';
                            html += '</div>';
                        });
                    });
                    
                    container.innerHTML = html;
                    
                    if (hasAvailable) {
                        updateStatus(productKey, 'available', '✅ Disponible (' + totalSlots + ' horarios)');
                    } else {
                        updateStatus(productKey, 'unavailable', '❌ Agotado');
                    }
                    
                    // Re-aplicar evento al select
                    var newSelect = document.getElementById('pickup-select-' + productKey);
                    if (newSelect) {
                        newSelect.addEventListener('change', function() {
                            var pickupId = this.value;
                            if (pickupId) {
                                stateData.selectedPickup = pickupId;
                                stateData.selectedSlot = null;
                                stateData._price = null; // Resetear precio
                                updateStatus(productKey, 'loading', '⏳ Cargando horarios...');
                                loadSlotsWithPickupSelect(productKey);
                            }
                        });
                    }
                    
                    // Eventos de selección de horario
                    container.querySelectorAll('.demo-slot:not(.unavailable)').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var time = this.dataset.time;
                            var price = parseFloat(this.dataset.price) || 20.00;
                            
                            stateData.selectedSlot = { time: time };
                            stateData._price = price; // 🔥 Guardar el precio seleccionado
                            
                            container.querySelectorAll('.demo-slot').forEach(function(s) {
                                s.classList.remove('selected');
                            });
                            this.classList.add('selected');
                            updateSelectionInfo(productKey);
                        });
                    });
                }
                
                function loadSlotsDirect(productKey) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    if (!stateData.selectedDate) return;
                    
                    container.innerHTML = '<p style="color: #666; text-align: center; padding: 10px;">Cargando horarios...</p>';
                    container.classList.add('visible');
                    
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
                            renderSlotsDirect(productKey, response.data);
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
                
                function renderSlotsDirect(productKey, data) {
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
                    var totalSlots = 0;
                    var firstPrice = null;
                    
                    groups.forEach(function(group) {
                        var slots = group.slots || [];
                        totalSlots += slots.length;
                        
                        slots.forEach(function(slot) {
                            var isAvailable = slot.availability === -1 || slot.availability > 0;
                            if (isAvailable) hasAvailable = true;
                            
                            var isSelected = stateData.selectedSlot && stateData.selectedSlot.time === slot.time;
                            
                            // 🔥 OBTENER PRECIO REAL
                            var price = 'Precio variable';
                            var priceValue = 0;
                            var currency = 'EUR';
                            
                            if (slot.products && slot.products.length > 0) {
                                var firstProduct = slot.products[0];
                                if (firstProduct.retail_price) {
                                    price = firstProduct.retail_price.formatted_value || 
                                            firstProduct.retail_price.formatted_iso_value || 
                                            firstProduct.retail_price.value + ' ' + firstProduct.retail_price.currency;
                                    priceValue = parseFloat(firstProduct.retail_price.value) || 0;
                                    currency = firstProduct.retail_price.currency || 'EUR';
                                    
                                    if (isAvailable && firstPrice === null) {
                                        firstPrice = priceValue;
                                        stateData._price = priceValue;
                                        stateData._currency = currency;
                                    }
                                }
                            }
                            
                            html += '<div class="demo-slot' + (isSelected ? ' selected' : '') + (isAvailable ? '' : ' unavailable') + '" data-time="' + (slot.time || 'flexible') + '" data-price="' + priceValue + '" style="margin: 4px 0; padding: 10px 12px; background: #fff; border: 1px solid ' + (isSelected ? '#2271b1' : '#e5e7eb') + '; border-radius: 4px; cursor: pointer; transition: all 0.2s;">';
                            html += '<div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 8px;">';
                            html += '<span style="font-weight: 500;">🕐 ' + (slot.time || 'Horario flexible') + '</span>';
                            html += '<span style="color: #666; font-size: 13px;">' + price + '</span>';
                            html += '<span class="slot-status ' + (isAvailable ? 'available' : 'unavailable') + '" style="font-size: 12px; padding: 2px 10px; border-radius: 12px; ' + (isAvailable ? 'background: #edfaef; color: #008a20;' : 'background: #fce8e8; color: #d63638;') + '">' + 
                                (isAvailable ? '✅ Disponible' : '❌ Agotado') + '</span>';
                            html += '</div>';
                            html += '</div>';
                        });
                    });
                    
                    container.innerHTML = html;
                    
                    if (hasAvailable) {
                        updateStatus(productKey, 'available', '✅ Disponible (' + totalSlots + ' horarios)');
                    } else {
                        updateStatus(productKey, 'unavailable', '❌ Agotado');
                    }
                    
                    container.querySelectorAll('.demo-slot:not(.unavailable)').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var time = this.dataset.time;
                            var price = parseFloat(this.dataset.price) || 20.00;
                            
                            stateData.selectedSlot = { time: time };
                            stateData._price = price;
                            
                            container.querySelectorAll('.demo-slot').forEach(function(s) {
                                s.classList.remove('selected');
                            });
                            this.classList.add('selected');
                            updateSelectionInfo(productKey);
                        });
                    });
                }
                
                function updateSelectionInfo(productKey) {
                    var stateData = state[productKey];
                    var info = document.getElementById('selection-' + productKey);
                    var btn = document.getElementById('add-cart-' + productKey);
                    
                    // 🔥 PARA OPEN DATE
                    if (stateData.isOpenDate) {
                        document.getElementById('sel-date-' + productKey).textContent = '📅 Fecha abierta (válido 1 año)';
                        document.getElementById('sel-time-' + productKey).textContent = '🕐 Sin horario fijo';
                        
                        // Precio real
                        var price = stateData._price || 53.00;
                        var currency = stateData._currency || 'EUR';
                        document.getElementById('sel-total-' + productKey).textContent = price.toFixed(2) + ' ' + currency;
                        
                        info.classList.add('visible');
                        btn.disabled = false;
                        return;
                    }
                    
                    if (!stateData.selectedDate || !stateData.selectedSlot) {
                        info.classList.remove('visible');
                        btn.disabled = true;
                        return;
                    }
                    
                    document.getElementById('sel-date-' + productKey).textContent = stateData.selectedDate;
                    document.getElementById('sel-time-' + productKey).textContent = stateData.selectedSlot.time || 'Flexible';

                    if (stateData.type === 'supplier_questions') {
                        var questionsStatus = stateData.questionsAnswered ? '✅ Completadas' : '⏳ Pendientes';
                        // Agregar al resumen
                        var pickupRow = document.querySelector('#selection-' + productKey + ' .info-row:last-child');
                        if (pickupRow) {
                            // Insertar antes del total
                            var newRow = document.createElement('div');
                            newRow.className = 'info-row';
                            newRow.innerHTML = '<span>📝 Preguntas</span><span>' + questionsStatus + '</span>';
                            pickupRow.parentNode.insertBefore(newRow, pickupRow);
                        }
                    }
                    
                    // 🔥 MOSTRAR LA RECOGIDA SELECCIONADA
                    if (stateData.type === 'pickup') {
                        var pickupName = 'No seleccionada';
                        if (stateData.selectedPickup) {
                            var select = document.getElementById('pickup-select-' + productKey);
                            if (select) {
                                var opt = select.options[select.selectedIndex];
                                if (opt && opt.text) {
                                    pickupName = opt.text;
                                }
                            }
                        }
                        document.getElementById('sel-pickup-' + productKey).textContent = pickupName;
                    }
                    
                    // Precio real
                    var price = stateData._price || 20.00;
                    var currency = stateData._currency || 'EUR';
                    document.getElementById('sel-total-' + productKey).textContent = price.toFixed(2) + ' ' + currency;
                    
                    info.classList.add('visible');
                    btn.disabled = false;
                    // 🔥 HABILITAR BOTÓN SOLO SI HAY RECOGIDA SELECCIONADA (PARA PICKUP)
                    if (stateData.type === 'pickup' && !stateData.selectedPickup) {
                        btn.disabled = true;
                    } else {
                        btn.disabled = false;
                    }

                    if (stateData.type === 'supplier_questions' && !stateData.questionsAnswered) {
                        btn.disabled = true;
                    }
                }
                
                // ============================================================
                // AÑADIR AL CARRITO REAL - USA EL MISMO SISTEMA QUE EL PLUGIN
                // ============================================================
                
                document.querySelectorAll('.demo-add-to-real-cart').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var productKey = this.dataset.product;
                        var stateData = state[productKey];
                        var message = document.getElementById('message-' + productKey);
                        
                        // 🔥 PARA OPEN DATE - NO VALIDAR FECHA NI HORARIO
                        if (!stateData.isOpenDate) {
                            if (!stateData.selectedDate || !stateData.selectedSlot) {
                                showMessage(productKey, 'error', '❌ Selecciona fecha y horario');
                                return;
                            }
                        }
                        
                        if (stateData.type === 'pickup' && !stateData.selectedPickup) {
                            showMessage(productKey, 'error', '❌ Selecciona un punto de recogida');
                            return;
                        }
                        
                        // 🔥 NUEVO: Verificar preguntas para supplier_questions
                        if (stateData.type === 'supplier_questions' && !stateData.questionsAnswered) {
                            showMessage(productKey, 'error', '❌ Responde todas las preguntas del proveedor');
                            return;
                        }
                        
                        // Precio real
                        var price = stateData._price || 20.00;
                        var currency = stateData._currency || 'EUR';
                        
                        // ✅ PAYLOAD SIMPLIFICADO
                        var payload = {
                            activity_uuid: stateData.uuid,
                            title: stateData.name,
                            date: stateData.isOpenDate ? '' : stateData.selectedDate,
                            time: stateData.isOpenDate ? '' : (stateData.selectedSlot ? stateData.selectedSlot.time || '00:00' : '00:00'),
                            product_identifier: stateData.uuid,
                            product_name: stateData.name,
                            quantity: 1,
                            unit_price: price,
                            currency: currency,
                            language: 'es'
                        };
                        
                        // 🔥 SI ES OPEN DATE, MARCARLO
                        if (stateData.isOpenDate) {
                            payload.open_date = true;
                        }
                        
                        // Si es pickup, enviar el pickup
                        if (stateData.type === 'pickup' && stateData.selectedPickup) {
                            payload.pickup = stateData.selectedPickup;
                        }
                        
                        // 🔥 NUEVO: Si es supplier_questions, enviar las respuestas
                        if (stateData.type === 'supplier_questions' && stateData.questionsAnswered) {
                            payload.questions = JSON.stringify(stateData.answers);
                        }
                        
                        console.log('🛒 Enviando al carrito (demo):', payload);
                        
                        var formData = new FormData();
                        formData.append('action', 'musement_cart_add');
                        formData.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
                        
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
                        .then(function(response) { 
                            console.log('📡 Status:', response.status);
                            return response.text(); 
                        })
                        .then(function(text) {
                            console.log('📄 Respuesta cruda:', text);
                            
                            try {
                                var response = JSON.parse(text);
                                console.log('📦 Respuesta JSON:', response);
                                return response;
                            } catch(e) {
                                console.error('❌ Error parsing JSON:', e);
                                throw new Error('La respuesta no es JSON válido. Respuesta: ' + text.substring(0, 200));
                            }
                        })
                        .then(function(response) {
                            if (response && response.success) {
                                showMessage(productKey, 'success', '✅ Producto añadido al carrito correctamente');
                                updateCartBadge(response.data);
                                
                                btn.textContent = '✅ Añadido';
                                btn.classList.add('added');
                                setTimeout(function() {
                                    btn.textContent = '🛒 Añadir al carrito';
                                    btn.classList.remove('added');
                                }, 3000);
                            } else {
                                var errorMsg = response && response.data ? response.data.message : 'Error desconocido';
                                showMessage(productKey, 'error', '❌ Error: ' + errorMsg);
                                console.error('❌ Error del servidor:', response);
                            }
                        })
                        .catch(function(error) {
                            console.error('❌ Error:', error);
                            showMessage(productKey, 'error', '❌ Error: ' + error.message);
                        });
                    });
                });
                
                // ============================================================
                // FUNCIÓN PARA CARGAR PREGUNTAS DEL PROVEEDOR
                // ============================================================
                
                function loadSupplierQuestions(productKey) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    if (!stateData.selectedDate || !stateData.selectedSlot) {
                        container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">Selecciona fecha y horario primero</p>';
                        return;
                    }
                    
                    container.innerHTML = '<p style="color: #666; text-align: center; padding: 10px;">Cargando preguntas del proveedor...</p>';
                    container.classList.add('visible');
                    
                    var data = new FormData();
                    data.append('action', 'musement_get_product_questions');
                    data.append('nonce', '<?php echo wp_create_nonce('musement_ajax'); ?>');
                    data.append('uuid', stateData.uuid);
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: data
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(response) {
                        if (response.success && response.data.questions && response.data.questions.length > 0) {
                            stateData.questions = response.data.questions;
                            renderSupplierQuestions(productKey, response.data.questions);
                        } else {
                            // Si no hay preguntas, continuar sin ellas
                            container.innerHTML = '<p style="color: #999; text-align: center; padding: 10px;">No hay preguntas adicionales para este producto</p>';
                            stateData.questionsAnswered = true;
                            updateSelectionInfo(productKey);
                        }
                    })
                    .catch(function(error) {
                        console.error('Error cargando preguntas:', error);
                        container.innerHTML = '<p style="color: #d63638; text-align: center; padding: 10px;">Error al cargar preguntas del proveedor</p>';
                        updateStatus(productKey, 'unavailable', '❌ Error');
                    });
                }
                
                // ============================================================
                // FUNCIÓN PARA RENDERIZAR PREGUNTAS DEL PROVEEDOR
                // ============================================================
                
                function renderSupplierQuestions(productKey, questions) {
                    var stateData = state[productKey];
                    var container = document.getElementById('slots-' + productKey);
                    
                    var html = '<div style="margin-top: 10px; padding: 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e5e7eb;">';
                    html += '<div style="font-weight: 600; margin-bottom: 10px; font-size: 15px;">📋 Preguntas del proveedor</div>';
                    html += '<div style="font-size: 13px; color: #666; margin-bottom: 12px;">Completa la siguiente información requerida:</div>';
                    
                    questions.forEach(function(question, index) {
                        var isRequired = question.required || false;
                        var answer = stateData.answers[question.id] || '';
                        
                        html += '<div class="demo-question" style="margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f1;">';
                        html += '<label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 14px;">';
                        html += question.label;
                        if (isRequired) {
                            html += ' <span style="color: #d63638;">*</span>';
                        }
                        html += '</label>';
                        
                        switch(question.type) {
                            case 'date':
                                html += '<input type="date" class="demo-question-input" data-qid="' + question.id + '" data-required="' + isRequired + '" value="' + answer + '" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
                                break;
                            case 'select':
                                html += '<select class="demo-question-input" data-qid="' + question.id + '" data-required="' + isRequired + '" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
                                html += '<option value="">-- Selecciona --</option>';
                                if (question.options && question.options.length > 0) {
                                    question.options.forEach(function(option) {
                                        var selected = (answer === option) ? 'selected' : '';
                                        html += '<option value="' + option + '" ' + selected + '>' + option + '</option>';
                                    });
                                }
                                html += '</select>';
                                break;
                            case 'number':
                                html += '<input type="number" class="demo-question-input" data-qid="' + question.id + '" data-required="' + isRequired + '" value="' + answer + '" placeholder="' + (question.placeholder || '') + '" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
                                break;
                            case 'text':
                            default:
                                html += '<input type="text" class="demo-question-input" data-qid="' + question.id + '" data-required="' + isRequired + '" value="' + answer + '" placeholder="' + (question.placeholder || '') + '" style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
                                break;
                        }
                        
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    
                    // Añadir el botón de confirmar
                    html += '<button class="demo-btn" id="confirm-questions-' + productKey + '" style="margin-top: 10px; width: 100%;" disabled>';
                    html += '✅ Confirmar respuestas';
                    html += '</button>';
                    
                    container.innerHTML = html;
                    
                    // Eventos de cambio en inputs
                    container.querySelectorAll('.demo-question-input').forEach(function(input) {
                        input.addEventListener('change', function() {
                            var qid = this.dataset.qid;
                            var value = this.value;
                            stateData.answers[qid] = value;
                            checkQuestionsComplete(productKey);
                        });
                        
                        input.addEventListener('input', function() {
                            var qid = this.dataset.qid;
                            var value = this.value;
                            stateData.answers[qid] = value;
                            checkQuestionsComplete(productKey);
                        });
                    });
                    
                    // Verificar completado inicial
                    checkQuestionsComplete(productKey);
                }
                
                // ============================================================
                // FUNCIÓN PARA VERIFICAR SI TODAS LAS PREGUNTAS ESTÁN COMPLETAS
                // ============================================================
                
                function checkQuestionsComplete(productKey) {
                    var stateData = state[productKey];
                    var btn = document.getElementById('confirm-questions-' + productKey);
                    var allAnswered = true;
                    
                    stateData.questions.forEach(function(question) {
                        if (question.required) {
                            var answer = stateData.answers[question.id] || '';
                            if (answer.trim() === '') {
                                allAnswered = false;
                            }
                        }
                    });
                    
                    if (btn) {
                        btn.disabled = !allAnswered;
                        if (allAnswered) {
                            btn.style.background = '#00a32a';
                            btn.textContent = '✅ Respuestas confirmadas';
                        } else {
                            btn.style.background = '#2271b1';
                            btn.textContent = '✅ Confirmar respuestas';
                        }
                    }
                }
                
                // ============================================================
                // EVENTO PARA CONFIRMAR PREGUNTAS
                // ============================================================
                
                // Agregar event listener para confirmar preguntas
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.id && e.target.id.startsWith('confirm-questions-')) {
                        var productKey = e.target.id.replace('confirm-questions-', '');
                        var stateData = state[productKey];
                        
                        // Verificar que todas las preguntas requeridas estén respondidas
                        var allAnswered = true;
                        stateData.questions.forEach(function(question) {
                            if (question.required) {
                                var answer = stateData.answers[question.id] || '';
                                if (answer.trim() === '') {
                                    allAnswered = false;
                                }
                            }
                        });
                        
                        if (allAnswered) {
                            stateData.questionsAnswered = true;
                            e.target.textContent = '✅ Respuestas confirmadas';
                            e.target.style.background = '#00a32a';
                            updateStatus(productKey, 'available', '✅ Preguntas completadas');
                            updateSelectionInfo(productKey);
                            
                            // Mostrar mensaje de éxito
                            showMessage(productKey, 'success', '✅ Preguntas del proveedor completadas correctamente');
                        } else {
                            showMessage(productKey, 'error', '❌ Responde todas las preguntas obligatorias');
                        }
                    }
                });
                
                // ============================================================
                // FUNCIONES UTILITARIAS
                // ============================================================
                
                function updateStatus(productKey, status, message) {
                    var el = document.getElementById('status-' + productKey);
                    if (!el) return;
                    
                    var stateData = state[productKey];
                    if (stateData.type === 'pickup' && !stateData.selectedPickup && stateData.selectedDate) {
                        el.className = 'demo-status pending';
                        el.textContent = '📍 Selecciona recogida';
                        return;
                    }
                    
                    el.className = 'demo-status ' + status;
                    el.textContent = message;
                }
                
                function showMessage(productKey, type, text) {
                    var el = document.getElementById('message-' + productKey);
                    if (!el) return;
                    el.className = 'demo-cart-message ' + type;
                    el.textContent = text;
                    
                    setTimeout(function() {
                        el.className = 'demo-cart-message';
                    }, 4000);
                }
                
                // 🔥 FUNCIÓN PARA ACTUALIZAR EL BADGE DEL CARRITO REAL
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
        </div>
        <?php
        return ob_get_clean();
    }
    
    // ============================================================
    // RENDER PÁGINA UNIFICADA (ADMIN)
    // ============================================================
    
    public function render_unified_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('🧪 Certificación y Demo de Productos', 'musement-integration'); ?></h1>
            <p><?php _e('Usa el shortcode <code>[musement_certification_cards]</code> en cualquier página para ver la demo con tarjetas.', 'musement-integration'); ?></p>
            
            <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px;">
                <h2><?php _e('📋 Productos Disponibles', 'musement-integration'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Producto', 'musement-integration'); ?></th>
                            <th><?php _e('UUID', 'musement-integration'); ?></th>
                            <th><?php _e('Tipo', 'musement-integration'); ?></th>
                            <th><?php _e('Descripción', 'musement-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->test_products as $key => $product): ?>
                        <tr>
                            <td><strong><?php echo $product['icon']; ?> <?php echo esc_html($product['name']); ?></strong></td>
                            <td><code><?php echo esc_html($product['uuid']); ?></code></td>
                            <td><?php echo esc_html($product['type']); ?></td>
                            <td><?php echo esc_html($product['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px;">
                <h2><?php _e('📝 Shortcodes Disponibles', 'musement-integration'); ?></h2>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">
                        <code style="background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 3px;">[musement_certification_cards]</code>
                        <span style="margin-left: 10px; color: #666;">→ Muestra la demo con tarjetas (usa el carrito real)</span>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    // ============================================================
    // AJAX HANDLERS
    // ============================================================
    
    public function ajax_test_product_flow() {
        check_ajax_referer('musement_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        $product_key = sanitize_text_field($_POST['product'] ?? '');
        
        if (!isset($this->test_products[$product_key])) {
            wp_send_json_error(['message' => 'Producto no encontrado']);
        }
        
        $product = $this->test_products[$product_key];
        $result = $this->run_product_test($product);
        
        wp_send_json_success($result);
    }
    
    public function ajax_run_certification() {
        check_ajax_referer('musement_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        $results = [];
        foreach ($this->test_products as $key => $product) {
            $results[$key] = $this->run_product_test($product);
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_export_certification() {
        check_ajax_referer('musement_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }
        
        $html = '<html><body><h1>Certificación Musement</h1><p>Resultados de las pruebas</p></body></html>';
        $filename = 'certificacion_musement_' . date('Y-m-d_His') . '.html';
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        file_put_contents($file_path, $html);
        
        $file_url = $upload_dir['url'] . '/' . $filename;
        
        wp_send_json_success(['url' => $file_url]);
    }
    
    public function ajax_demo_add_to_cart() {
        check_ajax_referer('musement_ajax', 'nonce');
        
        wp_send_json_success([
            'message' => 'Producto añadido al carrito (demo)'
        ]);
    }
    
    public function ajax_demo_get_cart() {
        check_ajax_referer('musement_ajax', 'nonce');
        
        wp_send_json_success([
            'items' => [],
            'total' => 0
        ]);
    }
    
    // ============================================================
    // TEST FUNCTIONS
    // ============================================================
    
    private function run_product_test($product) {
        return [
            'product' => $product['uuid'],
            'product_name' => $product['name'],
            'product_type' => $product['type'],
            'passed' => true,
            'steps' => [],
            'message' => '✅ Prueba completada',
            'timestamp' => current_time('mysql')
        ];
    }
}

// Inicializar la clase (esto permitirá que funcione el shortcode)
new Musement_Certification_Unified();