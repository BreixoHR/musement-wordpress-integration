<?php
/**
 * Plugin Name: Musement Integration
 * Description: Integracion basica con Musement para mostrar tours y disponibilidad.
 * Version: 2.12.020
 * Author: Breixo A. Herrera
 * Text Domain: musement-integration
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MUSEMENT_INTEGRATION_VERSION', '2.12.020');
define('MUSEMENT_INTEGRATION_DIR', plugin_dir_path(__FILE__));
define('MUSEMENT_INTEGRATION_URL', plugin_dir_url(__FILE__));
define('MUSEMENT_APPLICATION', '***');
define('MUSEMENT_CLIENT_ID', '***');
define('MUSEMENT_CLIENT_SECRET', '***');

// Dominio base de la API de Musement. Antes estaba quemado como
// "sandbox.musement.com" en 26 sitios distintos repartidos en 4 archivos --
// si alguna vez hay que volver a sandbox (o alternar entre entornos), este es
// el UNICO sitio que hay que tocar.
define('MUSEMENT_API_BASE_URL', 'https://api.musement.com');

/**  CONTRASEÑAS DE SANDBOX ----------- MUSEMENT
* define('MUSEMENT_APPLICATION', '***');
* define('MUSEMENT_CLIENT_ID', '***');
* define('MUSEMENT_CLIENT_SECRET', '***');
 */
// Las claves de Stripe YA NO se hardcodean aquí -- antes tenían prioridad
// sobre lo configurado en wp-admin (musement_stripe_publishable_key() /
// musement_stripe_secret_key() / musement_stripe_webhook_secret() en
// includes/payment-stripe.php comprueban primero si estas constantes están
// definidas), así que aunque se metieran las claves reales en el panel,
// el plugin seguía usando estas de prueba sin más.
//
// Configura las claves de Stripe (test o produccion) desde:
// wp-admin -> Ajustes -> Musement Integration -> Stripe.
//
// Si en algun momento hace falta forzarlas por codigo otra vez (por ejemplo
// en un entorno de staging), se pueden volver a definir aqui:
// define('MUSEMENT_STRIPE_PUBLISHABLE_KEY', '***');
// define('MUSEMENT_STRIPE_SECRET_KEY', '***');
// define('MUSEMENT_STRIPE_WEBHOOK_SECRET', '***');
define('MUSEMENT_VENTRATA_ENDPOINT', 'https://tu-endpoint-salesforce.com/webhook');
define('MUSEMENT_VENTRATA_API_KEY', '***');

/**
 * Fuerza que los error_log() de depuración queden guardados en un fichero
 * propio del plugin, sin depender de que WP_DEBUG_LOG esté bien configurado
 * en wp-config.php (que muchas veces no surte efecto si se define después de
 * que WordPress ya arrancó). El fichero vive en wp-content/ para que
 * sobreviva a actualizaciones del plugin.
 */
if (!defined('MUSEMENT_DEBUG_LOG_PATH')) {
    define('MUSEMENT_DEBUG_LOG_PATH', WP_CONTENT_DIR . '/musement-debug.log');
}
ini_set('log_errors', '1');
ini_set('error_log', MUSEMENT_DEBUG_LOG_PATH);

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
require_once MUSEMENT_INTEGRATION_DIR . 'includes/admin/block-product.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/octo.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/pdf-merger.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/certification.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/certified_products.php';
require_once MUSEMENT_INTEGRATION_DIR . 'includes/certified_calendar.php';





register_activation_hook(__FILE__, 'musement_orders_install');
register_activation_hook(__FILE__, 'musement_checkout_page_install');


// Registrar shortcodes de certificación
add_shortcode('musement_certification_cards', function() {
    if (class_exists('Musement_Certification_Unified')) {
        return Musement_Certification_Unified::render_cards_shortcode();
    }
    return '<p>Error: La clase de certificación no está disponible.</p>';
});

// También mantener el shortcode original de demo si existe
add_shortcode('musement_certification_demo', function() {
    if (class_exists('Musement_Certification_Unified')) {
        return Musement_Certification_Unified::render_frontend_shortcode();
    }
    return '<p>Error: La clase de certificación no está disponible.</p>';
});

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
    $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');

    if (!$cart_uuid) {
        wp_send_json_error([
            'message' => 'No se encontró el carrito',
            'debug' => 'cart_uuid is empty'
        ]);
        return;
    }

    // Idempotencia: si esta misma compra (mismo cart_uuid) ya se está procesando
    // o ya se procesó (doble clic, reintento de red, dos peticiones simultáneas,
    // etc.), no volvemos a crear la reserva en Musement -- eso generaba
    // bookings/vouchers duplicados para un único carrito.
    $order_lock_key = 'musement_order_lock_' . md5($cart_uuid);
    $mysql_lock_name = 'musement_order_' . md5($cart_uuid);

    if (!musement_order_lock_acquire($mysql_lock_name)) {
        wp_send_json_error([
            'message' => 'Tu reserva ya se está procesando, espera unos segundos.',
        ], 409);
        return;
    }

    // Si otra petición para este mismo carrito ya terminó de crear la orden,
    // devolvemos ese mismo resultado en vez de crear otra reserva duplicada.
    $existing_result = get_transient($order_lock_key);

    if (is_array($existing_result)) {
        musement_order_lock_release($mysql_lock_name);
        wp_send_json_success($existing_result);
        return;
    }

    // Recogemos del formulario los datos del cliente
    $customer_data = [
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
    ];
    
    // 🔥 OBTENER PREGUNTAS DEL CARRITO
    $cart = musement_cart_payload();
    $items = $cart['items'] ?? [];
    $summary = $cart['summary'] ?? ['total' => 0, 'currency' => 'EUR'];
    
    // 🔥 VERIFICAR QUE EL CARRITO NO ESTÁ VACÍO
    if (empty($items)) {
        delete_transient($order_lock_key);
        musement_order_lock_release($mysql_lock_name);
        wp_send_json_error([
            'message' => 'El carrito está vacío',
            'debug' => 'No hay items en el carrito'
        ]);
        return;
    }

    if (empty($customer_data['email'])) {
        delete_transient($order_lock_key);
        musement_order_lock_release($mysql_lock_name);
        wp_send_json_error([
            'message' => 'El email es obligatorio',
            'debug' => 'Email is empty'
        ]);
        return;
    }

    // OJO: aquí NO se vuelve a llamar a musement_remote_cart_sync_from_local().
    // Ese sync completo (añadir lo que falte + podar huérfanos) ya se ejecutó
    // en musement_checkout_start_ajax(), justo antes de crear el PaymentIntent
    // de Stripe -- el carrito remoto que se acaba de cobrar es exactamente el
    // que dejó esa sincronización. Repetirla aquí no aporta nada (el cliente no
    // edita el carrito mientras rellena la tarjeta) y sí tiene un coste real:
    // cada sync es una nueva ronda de "intentar emparejar cada item otra vez",
    // y si un item no se reconocía como ya presente, se volvía a añadir --
    // dejando dos items vivos para un único producto justo antes de crear el
    // pedido en Musement. Esta era la causa de las reservas duplicadas.
    $cart_uuid = musement_remote_cart_get_id() ?: $cart_uuid;


    // Si musement_checkout_start_ajax() tuvo que pedirle al cliente algún dato
    // extra (ver musement_extract_missing_customer_fields()), el frontend lo
    // reenvía aquí para que el carrito llegue a musement_remote_create_order()
    // con esos datos ya guardados -- no basta con que se guardaran en la
    // llamada anterior, porque este PUT vuelve a mandar el objeto completo.
    $extra_customer_data = musement_checkout_extra_customer_data_from_post();
    $customer_update = musement_remote_cart_update_customer($customer_data, $extra_customer_data);
    if (is_wp_error($customer_update)) {
        // A estas alturas ya se ha cobrado la tarjeta -- no bloqueamos la
        // creación del pedido por esto (mismo criterio que ya había), pero si
        // es justo el motivo por el que Musement rechace el pedido más abajo,
        // el mensaje de error de musement_remote_create_order() dejará claro
        // por qué, en vez de fallar aquí con un mensaje genérico.
        error_log('[MUSEMENT] ⚠️ Could not update customer data in remote cart: ' . $customer_update->get_error_message());
    }

    // Recogemos del formulario los datos del cliente
    $customer_data = [
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
    ];

    // El importe a verificar contra Stripe tiene que ser el TOTAL REAL del
    // carrito remoto en este momento (no el del carrito local calculado antes
    // de sincronizar, $summary), calculado con musement_remote_cart_total_with_margin()
    // -- la MISMA función que usa musement_checkout_start_ajax() para fijar el
    // importe del PaymentIntent, así que ambos números no pueden divergir.
    $remote_total_data = musement_remote_cart_total_with_margin(
        (float) ($summary['total'] ?? 0),
        (string) ($summary['currency'] ?? 'EUR')
    );
    $remote_total = $remote_total_data['total'];
    $remote_currency = $remote_total_data['currency'];

    // Verificamos el pago DIRECTAMENTE contra Stripe antes de crear u homologar
    // ninguna reserva en Musement. Antes de esto, cualquiera podía llamar a este
    // endpoint (es wp_ajax_nopriv) con un cart_uuid válido y conseguir una reserva
    // confirmada con vouchers sin haber pagado nunca -- este check es lo que lo impide.
    $payment_check = musement_stripe_verify_payment_for_cart(
        $payment_intent_id,
        $cart_uuid,
        $remote_total,
        $remote_currency
    );

    if (is_wp_error($payment_check)) {
        delete_transient($order_lock_key);
        musement_order_lock_release($mysql_lock_name);
        error_log('[MUSEMENT] ❌ Pago no verificado para carrito ' . $cart_uuid . ': ' . $payment_check->get_error_message());
        wp_send_json_error([
            'message' => $payment_check->get_error_message(),
        ], 402);
        return;
    }

    // 1. Crear la orden en Musement (estado PENDING) -- o recuperar la que ya se
    //    creó en un intento anterior para este mismo carrito. Si no hiciéramos esto,
    //    un fallo DESPUÉS de crear la orden (p.ej. al pedir su detalle o construir el
    //    payload) haría que un reintento volviera a llamar a musement_remote_create_order(),
    //    y Musement no rechaza la repetición: le añade más bookings a la misma orden.
    $order_created_key = 'musement_order_created_' . md5($cart_uuid);
    $existing_order_uuid = get_transient($order_created_key);

    $order = $existing_order_uuid
        ? musement_remote_get_order($existing_order_uuid)
        : musement_remote_create_order($cart_uuid);

    if (is_wp_error($order)) {
        delete_transient($order_lock_key);
        musement_order_lock_release($mysql_lock_name);
        wp_send_json_error(['message' => $order->get_error_message()]);
        return;
    }

    // 2. PASO NUEVO: ¡Ya tenemos el pago! Avisamos a Musement.
    $order_uuid = $order['uuid'] ?? '';

    // Guardamos el order_uuid en cuanto lo tenemos -- antes de marcar el pago, pedir
    // el detalle o construir el payload -- para que si algo falla a partir de aquí,
    // el siguiente intento reutilice esta orden en vez de crear otra.
    if ($order_uuid) {
        set_transient($order_created_key, $order_uuid, 12 * HOUR_IN_SECONDS);
    }

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

    $response_data = [
        'order' => $order,
        'octo_payload' => $octo_payload ?? null,
        'vouchers' => $vouchers,
        'order_uuid' => $order_uuid,
        'customer_data' => $customer_data
    ];

    // Guardamos el resultado para esta compra: si llega otra petición con el
    // mismo cart_uuid (doble clic, reintento, etc.) se le devuelve esta misma
    // respuesta en vez de crear otra reserva/booking duplicado en Musement.
    set_transient($order_lock_key, $response_data, HOUR_IN_SECONDS);
    musement_order_lock_release($mysql_lock_name);

    // Limpiar carrito y devolver respuesta.
    musement_cart_clear();
    wp_send_json_success($response_data);
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
            // Musement devuelve el nombre de la actividad en "title", no en
            // "name" -- por eso activity_name salía siempre como el genérico
            // "Actividad" en el payload que llega a Salesforce.
            $activity_name = $item['product']['title'] ?? $item['product']['name'] ?? $item['name'] ?? 'Actividad';

            // Estado del item
            $items_status[] = [
                'name' => $activity_name,
                'status' => $item['status'] ?? 'unknown',
                'max_confirmation_time' => $item['max_confirmation_time'] ?? null
            ];

            // Buscar vouchers
            if (!empty($item['vouchers']) && is_array($item['vouchers'])) {
                foreach ($item['vouchers'] as $voucher) {
                    if (!empty($voucher['url'])) {
                        $vouchers[] = [
                            'activity_name' => $activity_name,
                            'url' => $voucher['url'],
                            'status' => $item['status'] ?? 'unknown',
                            // uuid de la reserva (booking) a la que pertenece este
                            // voucher -- sin esto, Salesforce no puede saber a cuál
                            // de varias reservas del mismo pedido corresponde cada
                            // voucher, y menos aún rellenar deliveryOptions por reserva.
                            'booking_id' => $item['uuid'] ?? '',
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

/**
 * Inyectar el calendario dentro del div #secundary
 */
add_action('wp_footer', 'musement_inject_calendar_in_secundary', 100);

function musement_inject_calendar_in_secundary() {
    // Solo en páginas individuales
    if (!is_singular()) {
        return;
    }
    
    // Determinar qué producto mostrar
    $product = 'pompidou'; // Por defecto
    
    // Detectar automáticamente por el título o slug
    $post = get_post();
    $title = strtolower($post->post_title);
    $slug = $post->post_name;
    
    if (strpos($title, 'knossos') !== false || strpos($slug, 'knossos') !== false) {
        $product = 'knossos';
    } elseif (strpos($title, 'pompidou') !== false || strpos($slug, 'pompidou') !== false) {
        $product = 'pompidou';
    }
    
    // Generar el HTML del calendario
    $calendar_html = do_shortcode('[musement_certified_calendar product="knosos"]');
    
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var secundary = document.getElementById('secundary');
        if (secundary) {
            // Buscar si ya hay un div para el calendario
            var calendarDiv = secundary.querySelector('.musement-sidebar-calendar');
            
            if (!calendarDiv) {
                // Crear el contenedor para el calendario
                var wrapper = document.createElement('div');
                wrapper.className = 'musement-calendar-wrapper';
                wrapper.style.margin = '10px 0';
                
                // Insertar después del span .custom_precio
                var precioSpan = secundary.querySelector('.custom_precio');
                if (precioSpan && precioSpan.nextSibling) {
                    secundary.insertBefore(wrapper, precioSpan.nextSibling);
                } else {
                    secundary.insertBefore(wrapper, secundary.firstChild);
                }
                
                // Insertar el HTML del calendario
                
                // Re-inicializar Flatpickr si es necesario
                if (typeof flatpickr !== 'undefined') {
                    document.dispatchEvent(new Event('DOMContentLoaded'));
                }
            }
        }
    });
    </script>
    <?php
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
