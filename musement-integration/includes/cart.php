<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'musement_cart_start_session', 1);

function musement_cart_start_session(): void
{
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    if (!isset($_SESSION['musement_cart']) || !is_array($_SESSION['musement_cart'])) {
        $_SESSION['musement_cart'] = [];
    }

    $remote = $_SESSION['musement_remote_cart'] ?? null;
    $valid_uuid = $remote['uuid'] ?? null;

    if (!$valid_uuid) {

        $new = musement_create_remote_cart();

        if (is_array($new) && !empty($new['uuid'])) {
            $_SESSION['musement_remote_cart'] = $new;
        }
    }
}

function musement_create_remote_cart(): ?array
{
    $token = musement_get_access_token();

    // Use admin-configurable operator credentials for the Musement cart.
    // Set these in: WP Admin → Musement → Configuración de ciudad, or override in wp-config.php.
    $operator_email    = defined('MUSEMENT_OPERATOR_EMAIL')    ? MUSEMENT_OPERATOR_EMAIL    : get_option('musement_operator_email',    get_bloginfo('admin_email'));
    $operator_firstname= defined('MUSEMENT_OPERATOR_FIRSTNAME') ? MUSEMENT_OPERATOR_FIRSTNAME : get_option('musement_operator_firstname', get_bloginfo('name'));
    $operator_lastname = defined('MUSEMENT_OPERATOR_LASTNAME')  ? MUSEMENT_OPERATOR_LASTNAME  : get_option('musement_operator_lastname',  'Operator');
    $operator_mobile   = defined('MUSEMENT_OPERATOR_MOBILE')    ? MUSEMENT_OPERATOR_MOBILE    : get_option('musement_operator_mobile',   '');
    $operator_country  = defined('MUSEMENT_OPERATOR_COUNTRY_ID')? (int) MUSEMENT_OPERATOR_COUNTRY_ID : (int) get_option('musement_operator_country_id', 1);

    $customer_body = [
        'email'     => sanitize_email($operator_email),
        'firstname' => sanitize_text_field($operator_firstname),
        'lastname'  => sanitize_text_field($operator_lastname),
        'country'   => ['id' => $operator_country],
    ];
    if (!empty($operator_mobile)) {
        $customer_body['mobile'] = sanitize_text_field($operator_mobile);
    }

    $res = wp_remote_post('https://sandbox.musement.com/api/v3/carts', [
        'timeout' => 15,
        'headers' => [
            'Authorization'          => 'Bearer ' . $token,
            'Accept'                 => 'application/json',
            'Content-Type'           => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency'    => 'EUR',
            'X-Musement-Market'      => 'es',
            'X-Musement-Version'     => '3.4.0',
        ],
        'body' => wp_json_encode(['customer' => $customer_body]),
    ]);

    if (is_wp_error($res)) {
        error_log($res->get_error_message());
        return null;
    }

    $status = wp_remote_retrieve_response_code($res);

    if ($status < 200 || $status >= 300) {
        error_log('Create remote cart failed: ' . wp_remote_retrieve_body($res));
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);

    return is_array($body) ? $body : null;
}

function musement_remote_cart_get_id(): ?string
{
    musement_cart_start_session();

    $remote = $_SESSION['musement_remote_cart'] ?? null;

    if (!is_array($remote)) {
        return null;
    }

    return $remote['uuid'] ?? null;
}

function musement_remote_cart_refresh(): ?array
{
    $cart_id = musement_remote_cart_get_id();
    $token = musement_get_access_token();

    if (!$cart_id || !$token) {
        return null;
    }

    $res = wp_remote_get(
        sprintf(
            'https://sandbox.musement.com/api/v3/carts/%s',
            $cart_id
        ),
        [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Musement-Application' => MUSEMENT_APPLICATION,
                'X-Musement-Currency' => 'EUR',
                'X-Musement-Market' => 'es',
                'X-Musement-Version' => '3.4.0',
            ],
        ]
    );

    if (is_wp_error($res)) {
        error_log($res->get_error_message());
        return null;
    }

    $status = wp_remote_retrieve_response_code($res);

    if ($status < 200 || $status >= 300) {
        error_log('Remote cart refresh failed: ' . wp_remote_retrieve_body($res));
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);

    if (!is_array($body)) {
        return null;
    }

    $_SESSION['musement_remote_cart'] = $body;

    return $body;
}

function musement_remote_cart_add_item(array &$item): bool
{
    $cart_id = musement_remote_cart_get_id();
    $token = musement_get_access_token();

    if (!$cart_id || !$token) {
        return false;
    }

    $endpoint = sprintf(
        'https://sandbox.musement.com/api/v3/carts/%s/items',
        $cart_id
    );

    // snapshot ANTES
    $before = musement_remote_cart_refresh();
    $before_items = $before['items'] ?? [];

    $res = wp_remote_post($endpoint, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ],
        'body' => wp_json_encode([[
            'type' => 'musement',
            'product_identifier' => (string) $item['product_identifier'],
            'quantity' => (int) $item['quantity'],
            'language' => !empty($item['language']) ? $item['language'] : null,
            'pickup' => !empty($item['pickup']) ? $item['pickup'] : null,
        ]]),
    ]);

    if (is_wp_error($res)) {
        error_log($res->get_error_message());
        return false;
    }

    $status = wp_remote_retrieve_response_code($res);

    if ($status < 200 || $status >= 300) {
        error_log('Remote add failed: ' . wp_remote_retrieve_body($res));
        return false;
    }

    // snapshot DESPUÉS
    $after = musement_remote_cart_refresh();
    $after_items = $after['items'] ?? [];

    if (!$after_items) {
        return false;
    }

    /**
     * 🔥 DIFERENCIA REAL (NO ORDEN)
     * buscamos item nuevo por product_identifier + quantity
     */
    foreach ($after_items as $new_item) {

        $already_existed = false;

        foreach ($before_items as $old_item) {
            if (($old_item['uuid'] ?? '') === ($new_item['uuid'] ?? '')) {
                $already_existed = true;
                break;
            }
        }

        if ($already_existed) {
            continue;
        }

        // match adicional de seguridad
        if (
            ($new_item['quantity'] ?? 0) == (int) $item['quantity']
        ) {
            $item['remote_item_uuid'] = $new_item['uuid'] ?? '';
            break;
        }
    }

    return true;
}

function musement_remote_cart_remove_item(string $remote_item_uuid): bool
{
    if (!$remote_item_uuid) {
        return false;
    }

    $cart_id = musement_remote_cart_get_id();
    $token = musement_get_access_token();

    if (!$cart_id || !$token) {
        return false;
    }

    $endpoint = sprintf(
        'https://sandbox.musement.com/api/v3/carts/%s/items/%s',
        $cart_id,
        $remote_item_uuid
    );

    $res = wp_remote_request($endpoint, [
        'method' => 'DELETE',
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
        ],
    ]);

    if (is_wp_error($res)) {
        error_log($res->get_error_message());
        return false;
    }

    $status = wp_remote_retrieve_response_code($res);

    // Accept 200 (OK) or 204 (No Content) — different API versions may return either.
    if ($status !== 204 && $status !== 200) {
        error_log('Remote remove failed (status ' . $status . '): ' . wp_remote_retrieve_body($res));
        return false;
    }

    musement_remote_cart_refresh();

    return true;
}

function musement_remote_create_order(string $cart_uuid, string $email_notification = 'NONE')
{
    $token = musement_get_access_token();

    if (!$token || !$cart_uuid) {
        return new WP_Error('musement_missing_data', 'Missing token or cart uuid.');
    }

    $endpoint = 'https://sandbox.musement.com/api/v3/orders';

    $body = [
        'cart_uuid' => $cart_uuid,
        'email_notification' => $email_notification,
    ];

    $res = wp_remote_post($endpoint, [
        'timeout' => 25,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $data = json_decode(wp_remote_retrieve_body($res), true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error(
            'musement_order_failed',
            'Order creation failed',
            [
                'status' => $status,
                'body' => $data,
            ]
        );
    }

    return is_array($data)
        ? $data
        : new WP_Error('musement_invalid_response', 'Invalid response from musement');
}

function musement_cart_items(): array
{
    musement_cart_start_session();

    $_SESSION['musement_cart'] = array_values(
        array_map(
            'musement_cart_normalize_item',
            $_SESSION['musement_cart']
        )
    );

    return $_SESSION['musement_cart'];
}

function musement_cart_total(array $items = null): array
{
    $items = $items ?? musement_cart_items();

    $total = 0.0;
    $currency = 'EUR';

    foreach ($items as $item) {

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unit_price = (float) ($item['unit_price'] ?? 0);

        $total += $quantity * $unit_price;

        if (!empty($item['currency'])) {
            $currency = sanitize_text_field($item['currency']);
        }
    }

    return [
        'total' => round($total, 2),
        'currency' => $currency,
    ];
}

function musement_cart_normalize_item(array $raw): array
{
    $quantity = max(1, (int) ($raw['quantity'] ?? 1));

    $raw_unit_price = $raw['unit_price'] ?? ($raw['musement_price'] ?? 0);

    $unit_price = $raw_unit_price !== ''
        ? (float) $raw_unit_price
        : 0.0;

    return [

        'key' => sanitize_key($raw['key'] ?? wp_generate_uuid4()),

        'remote_item_uuid' => sanitize_text_field($raw['remote_item_uuid'] ?? ''),

        'activity_uuid' => sanitize_text_field($raw['activity_uuid'] ?? ''),
        'title' => sanitize_text_field($raw['title'] ?? ''),

        'date' => sanitize_text_field($raw['date'] ?? ''),
        'time' => sanitize_text_field($raw['time'] ?? ''),

        'product_identifier' => sanitize_text_field($raw['product_identifier'] ?? ''),
        'product_name' => sanitize_text_field($raw['product_name'] ?? ''),

        'option_name' => sanitize_text_field($raw['option_name'] ?? ''),
        'option_feature_code' => sanitize_text_field($raw['option_feature_code'] ?? ''),
        'option_type' => sanitize_text_field($raw['option_type'] ?? ''),

        'holder_code' => sanitize_text_field($raw['holder_code'] ?? ''),
        'holder_code_normalized' => sanitize_text_field($raw['holder_code_normalized'] ?? ''),

        'availability' => isset($raw['availability'])
            ? (int) ($raw['availability'])
            : 0,

        'language' => sanitize_text_field($raw['language'] ?? ''),
        'pickup' => sanitize_text_field($raw['pickup'] ?? ''),

        'quantity' => $quantity,

        'unit_price_formatted' => sanitize_text_field($raw['unit_price_formatted'] ?? ''),
        'unit_price' => $unit_price,
        'musement_price' => (float) ($raw['musement_price'] ?? $unit_price),

        'currency' => sanitize_text_field($raw['currency'] ?? 'EUR'),
    ];
}

function musement_cart_payload(): array
{
    $items = musement_cart_items();

    return [
        'items' => $items,
        'summary' => musement_cart_total($items),
    ];
}

function musement_cart_add_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    $item = musement_cart_normalize_item([
        'activity_uuid' => isset($_POST['activity_uuid']) ? wp_unslash($_POST['activity_uuid']) : '',
        'title' => isset($_POST['title']) ? wp_unslash($_POST['title']) : '',
        'date' => isset($_POST['date']) ? wp_unslash($_POST['date']) : '',
        'time' => isset($_POST['time']) ? wp_unslash($_POST['time']) : '',
        'product_identifier' => isset($_POST['product_identifier']) ? wp_unslash($_POST['product_identifier']) : '',
        'product_name' => isset($_POST['product_name']) ? wp_unslash($_POST['product_name']) : '',
        'option_name' => isset($_POST['option_name']) ? wp_unslash($_POST['option_name']) : '',
        'option_feature_code' => isset($_POST['option_feature_code']) ? wp_unslash($_POST['option_feature_code']) : '',
        'option_type' => isset($_POST['option_type']) ? wp_unslash($_POST['option_type']) : '',
        'holder_code' => isset($_POST['holder_code']) ? wp_unslash($_POST['holder_code']) : '',
        'holder_code_normalized' => isset($_POST['holder_code_normalized']) ? wp_unslash($_POST['holder_code_normalized']) : '',
        'availability' => isset($_POST['availability']) ? wp_unslash($_POST['availability']) : 0,
        'language' => isset($_POST['language']) ? wp_unslash($_POST['language']) : '',
        'pickup' => isset($_POST['pickup']) ? wp_unslash($_POST['pickup']) : '',
        'quantity' => isset($_POST['quantity']) ? wp_unslash($_POST['quantity']) : 1,
        'unit_price_formatted' => isset($_POST['unit_price_formatted']) ? wp_unslash($_POST['unit_price_formatted']) : '',
        'unit_price' => isset($_POST['unit_price']) ? wp_unslash($_POST['unit_price']) : 0,
        'musement_price' => isset($_POST['musement_price']) ? wp_unslash($_POST['musement_price']) : 0,
        'currency' => isset($_POST['currency']) ? wp_unslash($_POST['currency']) : 'EUR',
    ]);

    if (
        !$item['activity_uuid']
        || !$item['date']
        || !$item['time']
        || !$item['product_identifier']
    ) {
        wp_send_json_error([
            'message' => __('Faltan datos para añadir el producto.', 'musement-integration'),
        ], 400);
    }

    musement_cart_start_session();

    $added = musement_remote_cart_add_item($item);

    if(!$added) {
        wp_send_json_error([
            'message' => __('No se pudo añadir el producto al carrito remoto.', 'musement-integration'),
        ], 500);
    }

    $_SESSION['musement_cart'][] = $item;

    wp_send_json_success(musement_cart_payload());
}

function musement_ensure_cart(): void
{
    musement_cart_start_session();
}

function musement_remote_cart_get_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    musement_cart_start_session();

    wp_send_json_success(
        $_SESSION['musement_remote_cart'] ?? []
    );
}

function musement_cart_get_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    wp_send_json_success(musement_cart_payload());
}

function musement_cart_remove_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    $key = isset($_POST['key'])
        ? sanitize_key(wp_unslash($_POST['key']))
        : '';

    musement_cart_start_session();

    foreach ($_SESSION['musement_cart'] as $existing_item) {

        if (($existing_item['key'] ?? '') === $key) {

            if (!empty($existing_item['remote_item_uuid'])) {

                musement_remote_cart_remove_item(
                    $existing_item['remote_item_uuid']
                );
            }

            break;
        }
    }

    $_SESSION['musement_cart'] = array_values(
        array_filter(
            $_SESSION['musement_cart'],
            function ($item) use ($key) {
                return ($item['key'] ?? '') !== $key;
            }
        )
    );

    wp_send_json_success(musement_cart_payload());
}

function musement_cart_clear(): void
{
    musement_cart_start_session();

    $_SESSION['musement_cart'] = [];

    $remote_uuid = musement_remote_cart_get_id();

    if ($remote_uuid) {

        $token = musement_get_access_token();

        wp_remote_request(
            sprintf(
                'https://sandbox.musement.com/api/v3/carts/%s',
                $remote_uuid
            ),
            [
                'method' => 'DELETE',
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Musement-Application' => MUSEMENT_APPLICATION,
                    'X-Musement-Version' => '3.4.0',
                ],
            ]
        );
    }

    unset($_SESSION['musement_remote_cart']);

    musement_cart_start_session();
}


/**
 * Actualiza los datos del cliente en el carrito remoto de Musement
 * 
 * @param array $customer_data Datos del cliente (email, firstname, lastname, phone, country_id)
 * @return bool
 */
function musement_remote_cart_update_customer(array $customer_data): bool
{
    $cart_id = musement_remote_cart_get_id();
    $token = musement_get_access_token();

    if (!$cart_id || !$token) {
        error_log('[Musement] Cannot update cart customer: missing cart_id or token');
        return false;
    }

    // ✅ Endpoint correcto: PUT /carts/{cartUuid}/customer
    $endpoint = sprintf(
        'https://sandbox.musement.com/api/v3/carts/%s/customer',
        $cart_id
    );

    // Construir el body según la documentación
    $body = [
        'email' => $customer_data['email'] ?? '',
        'firstname' => $customer_data['first_name'] ?? '',
        'lastname' => $customer_data['last_name'] ?? '',
    ];

    // Añadir teléfono si existe (según la estructura que muestra la documentación)
    if (!empty($customer_data['phone'])) {
        $body['extra_customer_data'] = [
            'phone_number' => $customer_data['phone']
        ];
    }

    // Añadir country si existe
    if (!empty($customer_data['country_id'])) {
        $body['country'] = ['id' => (int) $customer_data['country_id']];
    }

    error_log('[Musement] Updating cart customer data: ' . wp_json_encode($body));
    error_log('[Musement] Endpoint: ' . $endpoint);

    $res = wp_remote_request($endpoint, [
        'method' => 'PUT', // ✅ IMPORTANTE: Usar PUT en lugar de PATCH
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($res)) {
        error_log('[Musement] Cart customer update error: ' . $res->get_error_message());
        return false;
    }

    $status = wp_remote_retrieve_response_code($res);
    $response_body = wp_remote_retrieve_body($res);

    if ($status < 200 || $status >= 300) {
        error_log('[Musement] Cart customer update failed. Status: ' . $status);
        error_log('[Musement] Response: ' . $response_body);
        return false;
    }

    error_log('[Musement] Cart customer updated successfully');
    
    // Refrescar el carrito en sesión para tener los datos actualizados
    musement_remote_cart_refresh();

    return true;
}



add_action('wp_ajax_musement_remote_cart_get', 'musement_remote_cart_get_ajax');
add_action('wp_ajax_nopriv_musement_remote_cart_get', 'musement_remote_cart_get_ajax');

add_action('wp_ajax_musement_cart_add', 'musement_cart_add_ajax');
add_action('wp_ajax_nopriv_musement_cart_add', 'musement_cart_add_ajax');

add_action('wp_ajax_musement_cart_get', 'musement_cart_get_ajax');
add_action('wp_ajax_nopriv_musement_cart_get', 'musement_cart_get_ajax');

add_action('wp_ajax_musement_cart_remove', 'musement_cart_remove_ajax');
add_action('wp_ajax_nopriv_musement_cart_remove', 'musement_cart_remove_ajax');
