<?php
/**
 * Plugin Name: Musement Integration
 * Description: Integracion basica con Musement para mostrar tours y disponibilidad.
 * Version: 0.13.306
 * Author: Jose Pablo Ibañez
 * Text Domain: musement-integration
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MUSEMENT_INTEGRATION_VERSION', '0.13.306'); 
define('MUSEMENT_INTEGRATION_DIR', plugin_dir_path(__FILE__));
define('MUSEMENT_INTEGRATION_URL', plugin_dir_url(__FILE__));
define('MUSEMENT_APPLICATION', '***');
define('MUSEMENT_CLIENT_ID', '***');
define('MUSEMENT_CLIENT_SECRET', '***');
define('MUSEMENT_STRIPE_PUBLISHABLE_KEY', '***');
define('MUSEMENT_STRIPE_SECRET_KEY', '***');
define('MUSEMENT_STRIPE_WEBHOOK_SECRET', '***');
define('MUSEMENT_VENTRATA_ENDPOINT', 'https://tu-endpoint-salesforce.com/webhook');
define('MUSEMENT_VENTRATA_API_KEY', '***');

require_once MUSEMENT_INTEGRATION_DIR . 'includes/api.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/ajax.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/cart.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/orders.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/salesforce.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/musement-sales.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/payments.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/payment-webhooks.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/payment-hosted.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/payment-stripe.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/checkout.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/admin/settings.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/octo.php';


register_activation_hook(__FILE__, 'musement_orders_install');
register_activation_hook(__FILE__, 'musement_checkout_page_install');

function musement_integration_shortcode($atts): string
{
    // Obtener ciudad seleccionada en el admin
    $city_id = get_option('musement_city_id', '');
    $city_name = get_option('musement_city_name', '');
    
    $atts = shortcode_atts([
        'city_id' => $city_id,
        'limit' => '12',
        'title' => $city_name ? sprintf(__('Tours en %s', 'musement-integration'), $city_name) : __('Tours disponibles', 'musement-integration'),
    ], $atts, 'musement_tours');

    $city_id = (int) $atts['city_id'];
    $limit = max(1, min(12, (int) $atts['limit']));
    $title = sanitize_text_field($atts['title']);

    ob_start();
    ?>
    <section class="musement-widget" 
            data-city-id="<?php echo esc_attr($city_id); ?>" 
            data-limit="<?php echo esc_attr($limit); ?>">
        <div class="musement-widget__header">
            <h2 class="musement-widget__title"><?php echo esc_html($title); ?></h2>
        </div>
        <div class="musement-widget__status" role="status"><?php esc_html_e('Cargando tours...', 'musement-integration'); ?></div>
        <div class="musement-tours" aria-live="polite"></div>
    </section>
    <?php

    return ob_get_clean();
}
add_shortcode('musement_tours', 'musement_integration_shortcode');

function musement_checkout_shortcode(): string
{
    ob_start();
    ?>
    <section class="musement-checkout" data-musement-checkout>
        <h2 class="musement-checkout__title"><?php esc_html_e('Resumen de compra', 'musement-integration'); ?></h2>
        
        <div class="musement-checkout__items"></div>
        <form class="musement-checkout__form">
            <div class="musement-checkout__fields">
                <label>
                    <?php esc_html_e('Nombre', 'musement-integration'); ?>
                    <input type="text" name="first_name" required>
                </label>
                <label>
                    <?php esc_html_e('Apellidos', 'musement-integration'); ?>
                    <input type="text" name="last_name" required>
                </label>
                <label>
                    <?php esc_html_e('Email', 'musement-integration'); ?>
                    <input type="email" name="email" required>
                </label>
                <label>
                    <?php esc_html_e('Telefono', 'musement-integration'); ?>
                    <input type="tel" name="phone" required>
                </label>
            </div>
            <div class="musement-payment" data-musement-payment>
                <h3 class="musement-payment__title"><?php esc_html_e('Selecciona tu metodo de pago', 'musement-integration'); ?></h3>
                <input type="hidden" name="payment_method" value="stripe">
                <div class="musement-payment__item is-active">
                    <button class="musement-payment__header" type="button" data-payment-method="stripe" aria-expanded="true" aria-controls="musement-payment-stripe">
                        <span><?php esc_html_e('Pagar con tarjeta', 'musement-integration'); ?></span>
                        <img src="https://www.example.com/logo.png" alt="Stripe" loading="lazy">
                    </button>
                    <div class="musement-payment__body">
                        <div id="musement-payment-stripe"></div>
                        <div class="musement-payment-errors"></div>
                    </div>
                </div>
                <p class="musement-payment__terms">
                    <?php esc_html_e('Para este pedido se aplican los terminos y condiciones generales, incluido el derecho de desistimiento, asi como el aviso de privacidad.', 'musement-integration'); ?>
                </p>
            </div>
            <div class="musement-checkout__status" role="status"></div>
            <button class="musement-checkout__submit" type="submit"><?php esc_html_e('Pagar', 'musement-integration'); ?></button>
        </form>
    </section>
    <?php

    return ob_get_clean();
}
add_shortcode('musement_checkout', 'musement_checkout_shortcode');

function musement_checkout_page_id(): int
{
    $page_id = (int) get_option('musement_checkout_page_id', 0);

    if ($page_id && get_post_status($page_id) === 'publish') {
        return $page_id;
    }

    $page = get_page_by_path('checkout-musement');

    if ($page && $page->post_status === 'publish') {
        update_option('musement_checkout_page_id', (int) $page->ID);
        return (int) $page->ID;
    }
    return 0;
}

function musement_checkout_url(): string
{
    $page_id = musement_checkout_page_id();

    if ($page_id) {
        return get_permalink($page_id);
    }

    return home_url('/checkout-musement/');
}

function musement_checkout_page_install(): void
{
    if (musement_checkout_page_id()) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Resumen de compra', 'musement-integration'),
        'post_name' => 'checkout-musement',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '[musement_checkout]',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ], true);

    if (!is_wp_error($page_id) && $page_id) {
        update_option('musement_checkout_page_id', (int) $page_id);
    }
}
add_action('plugins_loaded', 'musement_checkout_page_install');

function musement_checkout_page_maybe_update_title(): void
{
    $page_id = musement_checkout_page_id();

    if (!$page_id || get_option('musement_checkout_page_title_version') === '0.13.2') {
        return;
    }

    $title = get_the_title($page_id);

    if (in_array($title, ['Checkout', 'Checkout Musement'], true)) {
        wp_update_post([
            'ID' => $page_id,
            'post_title' => __('Resumen de compra', 'musement-integration'),
        ]);
    }

    update_option('musement_checkout_page_title_version', '0.13.2');
}


add_action('wp_ajax_musement_create_order', 'musement_create_order');
add_action('wp_ajax_nopriv_musement_create_order', 'musement_create_order');

function musement_create_order() {
    check_ajax_referer('musement_ajax', 'nonce');
    $cart_uuid = sanitize_text_field($_POST['cart_uuid'] ?? '');

    // Recogemos del formulario los datos del cliente
    $customer_data = [
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
    ];

    // 1. Crear la orden en Musement (estado PENDING)
    //    Esto es exactamente lo que ya haces.
    $order = musement_remote_create_order($cart_uuid);

    if (is_wp_error($order)) {
        wp_send_json_error(['message' => $order->get_error_message()]);
    }

    // 2. PASO NUEVO: ¡Ya tenemos el pago! Avisamos a Musement.
    $order_uuid = $order['uuid'] ?? '';
    $vouchers = [];
    $voucher_urls = [];

    if ($order_uuid) {
        // Esta es la función que debe llamar al endpoint de "no-payment flow"
        $payment_confirmation = musement_remote_mark_order_paid($order_uuid);

        

        if (is_wp_error($payment_confirmation)) {
            // Es crucial loguear este error para investigarlo.
            error_log('[MUSEMENT] Error al confirmar pago de orden ' . $order_uuid . ': ' . $payment_confirmation->get_error_message());
            // Podrías devolver un error aquí, o manejarlo internamente.
        } else {
            // ¡Perfecto! La orden ahora está confirmada.
            $updated_order = musement_remote_get_order($order_uuid);

            if(is_wp_error($updated_order)) {
                error_log('[MUSEMENT] Error al obtener orden actualizada ' . $order_uuid . ': ' . $updated_order->get_error_message());
            } else {
                $vouchers = musement_extract_vouchers_from_order($updated_order);

                foreach ($vouchers as $voucher) {
                    if (!empty($voucher['url'])) {
                        $voucher_urls[] = $voucher['url'];
                    }
                }

                $order = $updated_order; // Actualizamos la orden con los datos confirmados.
            }

            error_log('[MUSEMENT] Pago confirmado para orden ' . $order_uuid);
            // Actualiza el estado en la respuesta para que el frontend sepa que fue exitoso.
            $order['status'] = 'CONFIRMED'; 

            //$octo_payload = musement_build_octo_payload($order, $customer_data);
            $octo_payload = musement_build_octo_payload($order, $customer_data, $voucher_urls);
            $_SESSION['musement_octo_payload'] = $octo_payload; // Guardamos el payload en sesión para usarlo después en el webhook de Ventrata.
        }
    }

    // Limpiar carrito y devolver respuesta.
    musement_cart_clear();
    wp_send_json_success([
        'order' => $order,
        'octo_payload' => $octo_payload ?? null,
        'vouchers' => $vouchers,
        'order_uuid' => $order_uuid,
        'customer_data' => $customer_data 
    ]);
}

// Endpoint para consultar estado de orden desde el frontend
add_action('wp_ajax_musement_check_order_status', 'musement_check_order_status');
add_action('wp_ajax_nopriv_musement_check_order_status', 'musement_check_order_status');

function musement_check_order_status() {
    // Verificar nonce por seguridad
    check_ajax_referer('musement_ajax', 'nonce');
    
    $order_uuid = sanitize_text_field($_POST['order_uuid'] ?? '');
    
    if (!$order_uuid) {
        wp_send_json_error(['message' => 'Falta el UUID de la orden']);
        return;
    }
    
    error_log('[MUSEMENT] Consultando orden: ' . $order_uuid);
    
    // Obtener orden de Musement usando tu función existente
    $order = musement_remote_get_order($order_uuid);
    
    if (is_wp_error($order)) {
        error_log('[MUSEMENT] Error: ' . $order->get_error_message());
        wp_send_json_error(['message' => $order->get_error_message()]);
        return;
    }
    
    // Extraer información relevante
    $vouchers = [];
    $items_status = [];
    
    if (!empty($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            // Estado del item
            $items_status[] = [
                'name' => $item['product']['name'] ?? $item['name'] ?? 'Actividad',
                'status' => $item['status'] ?? 'unknown',
                'max_confirmation_time' => $item['max_confirmation_time'] ?? null
            ];
            
            // Buscar vouchers
            if (!empty($item['vouchers']) && is_array($item['vouchers'])) {
                foreach ($item['vouchers'] as $voucher) {
                    if (!empty($voucher['url'])) {
                        $vouchers[] = [
                            'activity_name' => $item['product']['name'] ?? 'Actividad',
                            'url' => $voucher['url'],
                            'status' => $item['status'] ?? 'unknown'
                        ];
                    }
                }
            }
        }
    }
    
    wp_send_json_success([
        'order_status' => $order['status'] ?? 'unknown',
        'items_status' => $items_status,
        'vouchers' => $vouchers,
        'order_uuid' => $order_uuid
    ]);
}



add_action('plugins_loaded', 'musement_checkout_page_maybe_update_title', 20);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'musement-frontend',
        MUSEMENT_INTEGRATION_URL . 'assets/frontend.css',
        [],
        MUSEMENT_INTEGRATION_VERSION
    );

    wp_enqueue_style(
        'flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        '4.6.13'
    );

    wp_enqueue_script(
        'flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
        [],
        '4.6.13',
        true
    );

    wp_enqueue_script(
        'stripe-js',
        'https://js.stripe.com/v3/',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'flatpickr-es',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js',
        ['flatpickr'],
        '4.6.13',
        true
    );

    wp_enqueue_script(
        'musement-frontend',
        MUSEMENT_INTEGRATION_URL . 'assets/frontend.js',
        ['wp-element', 'flatpickr-es', 'stripe-js'],
        MUSEMENT_INTEGRATION_VERSION,
        true
    );

    wp_localize_script('musement-frontend', 'MUSEMENT', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('musement_ajax'),
        'defaultDate' => current_time('Y-m-d'),
        'checkoutUrl' => apply_filters('musement_checkout_url', musement_checkout_url()),
        'price' => get_option('musement_product_price', 0),
    ]);
});
