<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_checkout_customer_from_post(): array
{
    return [
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
        'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
        'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
    ];
}

function musement_checkout_payment_method_from_post(): string
{
    $payment_method = isset($_POST['payment_method']) ? sanitize_key(wp_unslash($_POST['payment_method'])) : 'stripe';
    $allowed = ['stripe', 'card'];

    return in_array($payment_method, $allowed, true) ? $payment_method : 'stripe';
}

/**
 * Lee del POST el mapa plano "ruta.con.puntos" => valor con los campos de
 * cliente adicionales que el frontend haya recogido (descubiertos en un
 * intento anterior de musement_checkout_start, ver
 * musement_extract_missing_customer_fields()), y lo reconstruye a la forma
 * anidada que espera Musement.
 */
function musement_checkout_extra_customer_data_from_post(): array
{
    $raw = isset($_POST['extra_customer_data']) ? wp_unslash($_POST['extra_customer_data']) : '{}';
    $flat = json_decode($raw, true);

    if (!is_array($flat)) {
        return [];
    }

    return musement_build_extra_customer_data_payload($flat);
}

/**
 * Formato ligero (solo lo que el frontend necesita para pintar el campo) de
 * la lista de campos de cliente que Musement acaba de decir que faltan.
 */
function musement_missing_customer_fields_for_response(array $missing_fields): array
{
    return array_map(
        function (array $field): array {
            return [
                'key' => implode('.', $field['path']),
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => $field['required'],
            ];
        },
        $missing_fields
    );
}

function musement_debug_payloads_enabled(): bool
{
    if (defined('MUSEMENT_DEBUG_PAYLOADS')) {
        return (bool) MUSEMENT_DEBUG_PAYLOADS;
    }

    return (bool) get_option('musement_debug_payloads', false);
}

function musement_checkout_start_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    // musement_checkout_start_ajax() era el único endpoint de carrito sin
    // ningún candado -- si "pagar" se solapaba con una eliminación de
    // producto todavía en curso (lenta: llama al DELETE de Musement), las dos
    // peticiones sincronizaban el carrito remoto en paralelo y el pedido
    // final podía terminar con un producto duplicado. Ver
    // musement_cart_session_lock_acquire().
    if (!musement_cart_session_lock_acquire()) {
        wp_send_json_error([
            'message' => __('Tu carrito se esta actualizando, espera un momento y vuelve a intentarlo.', 'musement-integration'),
        ], 409);
        return;
    }

    $items = musement_cart_items();
    $summary = musement_cart_total($items);
    $customer = musement_checkout_customer_from_post();
    $payment_method = musement_checkout_payment_method_from_post();

    if (!$items) {
        wp_send_json_error([
            'message' => __('El carrito esta vacio.', 'musement-integration'),
        ], 400);
    }

    if (!$customer['first_name'] || !$customer['last_name'] || !$customer['email'] || !$customer['phone']) {
        wp_send_json_error([
            'message' => __('Faltan datos del cliente.', 'musement-integration'),
        ], 400);
    }

    $remote_sync = musement_remote_cart_sync_from_local();
    if (is_wp_error($remote_sync)) {
        wp_send_json_error([
            'message' => $remote_sync->get_error_message(),
            'remote_error' => $remote_sync->get_error_data(),
        ], 502);
    }

    $extra_customer_data = musement_checkout_extra_customer_data_from_post();
    $customer_update = musement_remote_cart_update_customer($customer, $extra_customer_data);

    if (is_wp_error($customer_update)) {
        if ($customer_update->get_error_code() === 'musement_customer_extra_data_required') {
            // Este proveedor/actividad concreto exige algún dato de cliente que
            // este checkout todavía no pide (fecha de nacimiento, DNI...). En vez
            // de mantener una lista de campos fija por producto -- inviable con
            // 3000+ actividades en el catálogo -- Musement nos acaba de decir EN
            // SU RESPUESTA qué campo falta, de qué tipo es y cómo se llama. Se
            // manda esa lista al frontend para que pinte el campo que haga falta
            // y reintente, sin necesidad de tocar código por cada producto nuevo.
            // No se crea el PaymentIntent todavía -- no tiene sentido cobrar antes
            // de saber si Musement va a aceptar la reserva.
            $missing_fields = is_array($customer_update->get_error_data())
                ? ($customer_update->get_error_data()['missing_fields'] ?? [])
                : [];

            wp_send_json_success([
                'requires_extra_customer_data' => true,
                'missing_fields' => musement_missing_customer_fields_for_response($missing_fields),
            ]);
            return;
        }

        // Cualquier otro fallo al actualizar el cliente no bloquea el checkout:
        // si de verdad hace falta para crear la reserva, fallará más adelante en
        // musement_create_order() con un error más claro.
        error_log('[MUSEMENT] ⚠️ Could not update customer data in remote cart (checkout_start): ' . $customer_update->get_error_message());
    }

    $items = musement_cart_items();
    $local_summary = musement_cart_total($items);

    // El importe que se cobra en Stripe (musement_payment_create_session() lee
    // $order['total']) tiene que salir del carrito remoto YA sincronizado y
    // podado arriba, no del local: es la misma fuente que usa después
    // musement_create_order() para verificar el pago, así que ambos números no
    // pueden divergir -- ver musement_remote_cart_total_with_margin().
    $summary = musement_remote_cart_total_with_margin(
        (float) ($local_summary['total'] ?? 0),
        (string) ($local_summary['currency'] ?? 'EUR')
    );

    $order_id = musement_orders_create($customer, $items, $summary);
    $order = musement_orders_get($order_id);

    if (!$order) {
        wp_send_json_error([
            'message' => __('No se pudo crear el pedido local.', 'musement-integration'),
        ], 500);
    }

    $cart_uuid = musement_remote_cart_get_id();
    if ($cart_uuid) {
        // reusar integration_errors_json para no tocar esquema DB
        $existing = json_decode($order['integration_errors_json'] ?? '[]', true) ?: [];
        $existing['musement_cart_uuid'] = $cart_uuid;
        musement_orders_update($order_id, [
            'integration_errors_json' => $existing,
        ]);
    }

    $order['selected_payment_method'] = $payment_method;
    $order['cart_uuid'] = $cart_uuid;
    $payment = musement_payment_create_session($order);
    $debug_payloads = [];

    if (musement_debug_payloads_enabled()) {
        $debug_payloads['salesforce'] = musement_salesforce_order_payload($order);
    }

    if (is_wp_error($payment)) {
        $payment_error_data = is_array($payment->get_error_data()) ? $payment->get_error_data() : [];

        wp_send_json_success([
            'message' => $payment->get_error_message(),
            'requires_payment_gateway' => true,
            'order_id' => $order_id,
            'order_reference' => $order['order_reference'],
            'payment_gateway' => sanitize_key($payment_error_data['gateway'] ?? musement_payment_gateway()),
            'payment_error_code' => sanitize_key($payment->get_error_code()),
            'status' => 'pending_payment',
            'debug_payloads' => $debug_payloads,
        ]);
    }

    if (!empty($payment['payment_reference'])) {
        musement_orders_update($order_id, [
            'payment_reference' => $payment['payment_reference'],
        ]);
    }

    $message = !empty($payment['payment_url'])
    ? __('Pedido creado. Redirigiendo a la pasarela de pago.', 'musement-integration')
    : __('Pedido creado. Pago pendiente.', 'musement-integration');


    wp_send_json_success([
        'message' => $message,
        'order_id' => $order_id,
        'order_reference' => $order['order_reference'],
        'payment_url' => esc_url_raw($payment['payment_url'] ?? ''),
        'payment_reference' => sanitize_text_field($payment['payment_reference'] ?? ''),
        'payment_method' => $payment_method,
        'payment_gateway' => sanitize_key($payment['gateway'] ?? musement_payment_gateway()),
        'status' => sanitize_text_field($payment['status'] ?? 'pending_payment'),
        'debug_payloads' => $debug_payloads,
        'client_secret' => sanitize_text_field($payment['client_secret'] ?? ''),
        'stripe_publishable_key' => ($payment['gateway'] ?? '') === 'stripe' ? musement_stripe_publishable_key() : '',

    ]);
}

function musement_checkout_test_paid_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => __('No tienes permisos para confirmar pagos de prueba.', 'musement-integration'),
        ], 403);
    }

    $order_reference = isset($_POST['order_reference']) ? sanitize_text_field(wp_unslash($_POST['order_reference'])) : '';
    $payment_reference = isset($_POST['payment_reference']) ? sanitize_text_field(wp_unslash($_POST['payment_reference'])) : '';

    if (!$order_reference || !$payment_reference) {
        wp_send_json_error([
            'message' => __('Falta la referencia del pedido o del pago.', 'musement-integration'),
        ], 400);
    }

    $order = musement_orders_get_by_reference($order_reference);

    if (!$order) {
        wp_send_json_error([
            'message' => __('No se encontro el pedido local.', 'musement-integration'),
        ], 404);
    }

    $result = musement_payment_complete_order((int) $order['id'], $payment_reference);

    if (is_wp_error($result)) {
        wp_send_json_error([
            'message' => $result->get_error_message(),
            'order_reference' => $order_reference,
        ], 502);
    }

    wp_send_json_success([
        'message' => __('Pago confirmado y reserva enviada a Musement.', 'musement-integration'),
        'order_reference' => $result['order_reference'],
        'status' => $result['status'],
        'musement_booking_reference' => $result['musement_booking_reference'] ?? '',
    ]);
}

add_action('wp_ajax_musement_checkout_start', 'musement_checkout_start_ajax');
add_action('wp_ajax_nopriv_musement_checkout_start', 'musement_checkout_start_ajax');
add_action('wp_ajax_musement_checkout_test_paid', 'musement_checkout_test_paid_ajax');

function musement_fetch_tickets_ajax(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No permisos'], 403);
    }

    // recibe order_reference o order_uuid y activity_uuid
    $order_ref = isset($_POST['order_reference']) ? sanitize_text_field(wp_unslash($_POST['order_reference'])) : '';
    $order_uuid = isset($_POST['order_uuid']) ? sanitize_text_field(wp_unslash($_POST['order_uuid'])) : '';
    $activity_uuid = isset($_POST['activity_uuid']) ? sanitize_text_field(wp_unslash($_POST['activity_uuid'])) : '';

    if (!$order_uuid && !$order_ref) {
        wp_send_json_error(['message' => 'Falta order_uuid o order_reference'], 400);
    }

    if (!$order_uuid) {
        $order = musement_orders_get_by_reference($order_ref);
        if (!$order || empty($order['musement_response_json'])) {
            wp_send_json_error(['message' => 'Pedido remoto no encontrado en DB'], 404);
        }
        $mresponse = is_array($order['musement_response_json']) ? $order['musement_response_json'] : json_decode($order['musement_response_json'], true);
        $order_uuid = $mresponse['uuid'] ?? $mresponse['identifier'] ?? $order_uuid;
    }

    if (!$activity_uuid) {
        // intenta sacar una activity_uuid desde items guardados
        $order = musement_orders_get_by_reference($order_ref);
        $items = $order['items'] ?? [];
        $activity_uuid = $items[0]['activity_uuid'] ?? '';
        if (!$activity_uuid) {
            wp_send_json_error(['message' => 'Falta activity_uuid y no se ha podido inferir'], 400);
        }
    }

    $tickets = musement_remote_get_activity_tickets($order_uuid, $activity_uuid);
    if (is_wp_error($tickets)) {
        wp_send_json_error(['message' => $tickets->get_error_message(), 'data' => $tickets->get_error_data() ?? []], 502);
    }

    wp_send_json_success(['tickets' => $tickets]);
}
add_action('wp_ajax_musement_fetch_tickets', 'musement_fetch_tickets_ajax');

function musement_create_prebooking(array $order)
{
    $endpoint = defined('MUSEMENT_PREBOOKING_ENDPOINT')
        ? MUSEMENT_PREBOOKING_ENDPOINT
        : '';

    if (!$endpoint) {
        return new WP_Error('no_endpoint', 'Musement endpoint no configurado');
    }

    $payload = [
        'order_reference' => $order['order_reference'],
        'items' => $order['items'],
        'customer' => $order['customer'],
        'total' => $order['total'] ?? 0,
        'currency' => $order['currency'] ?? 'EUR',
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($payload),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body)) {
        return new WP_Error('empty_response', 'Musement devolvió respuesta vacía');
    }

    return $body;
}
