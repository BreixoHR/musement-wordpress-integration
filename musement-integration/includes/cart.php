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

    if (!$token) {
        error_log('[MUSEMENT] ❌ Cannot create cart: no token');
        return null;
    }
        error_log('[MUSEMENT] 📤 Creating remote cart...');


    $res = wp_remote_post(MUSEMENT_API_BASE_URL . '/api/v3/carts', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ],
        'body' => wp_json_encode([
            'customer' => [
                'email' => 'ops@example.com',
                'firstname' => 'MNPQ SL',
                'lastname' => 'OPERADORES TURISTICOS',
                'country' => [
                    'id' => 1
                ],
            ]
        ]),
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
        error_log('[MUSEMENT] ❌ Refresh failed: missing cart_id or token');
        return null;
    }

    $res = wp_remote_get(
        sprintf(
            MUSEMENT_API_BASE_URL . '/api/v3/carts/%s',
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
        error_log('[MUSEMENT] ❌ Refresh WP Error: ' . $res->get_error_message());
        return null;
    }

    $status = wp_remote_retrieve_response_code($res);

    if ($status < 200 || $status >= 300) {
        error_log('[MUSEMENT] ❌ Refresh failed. Status: ' . $status . ' Response: ' . wp_remote_retrieve_body($res));
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);

    if (!is_array($body)) {
        error_log('[MUSEMENT] ❌ Refresh invalid response');
        return null;
    }

    $_SESSION['musement_remote_cart'] = $body;
    return $body;
}

function musement_remote_cart_items_from_response(array $cart): array
{
    foreach (['items', 'cart_items', 'order_items'] as $key) {
        if (isset($cart[$key]) && is_array($cart[$key])) {
            return $cart[$key];
        }
    }

    return [];
}

function musement_remote_cart_item_uuid(array $remote_item): string
{
    return sanitize_text_field(
        $remote_item['uuid']
        ?? $remote_item['id']
        ?? $remote_item['item_uuid']
        ?? ''
    );
}

function musement_remote_cart_item_value(array $remote_item, string $key): string
{
    // "date"/"time" se comprueban primero: el carrito remoto de Musement solo trae
    // product.date como "AAAA-MM-DD HH:MM" combinado, nunca por separado. Si esto
    // se dejara caer al fallback genérico de más abajo, "date" devolvería la fecha
    // Y la hora juntas, que nunca coincidiría con local_item['date'] ("AAAA-MM-DD"
    // solo) -- eso hacía que musement_remote_cart_find_matching_item() nunca
    // encontrara el ítem ya existente y volviera a añadirlo duplicado en cada
    // sincronización del carrito.
    if (in_array($key, ['date', 'time'], true) && isset($remote_item['product']['date'])) {
        $product_date = (string) $remote_item['product']['date'];

        if ($key === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}/', $product_date, $matches)) {
            return $matches[0];
        }

        if ($key === 'time' && preg_match('/\b(\d{2}:\d{2})(?::\d{2})?\b/', $product_date, $matches)) {
            return $matches[1];
        }
    }

    $value = $remote_item[$key] ?? null;

    if ($value === null && isset($remote_item['product']) && is_array($remote_item['product'])) {
        $value = $remote_item['product'][$key] ?? null;
    }

    if ($value === null && $key === 'product_identifier' && isset($remote_item['product']) && is_array($remote_item['product'])) {
        $value = $remote_item['product']['id'] ?? $remote_item['product']['identifier'] ?? null;
    }

    return is_scalar($value) ? (string) $value : '';
}

function musement_remote_cart_response_is_cart(array $response_data): bool
{
    return !empty(musement_remote_cart_items_from_response($response_data));
}

function musement_remote_cart_response_is_item(array $response_data): bool
{
    if (musement_remote_cart_response_is_cart($response_data)) {
        return false;
    }

    return isset($response_data['product'])
        || isset($response_data['product_identifier'])
        || isset($response_data['quantity']);
}

function musement_remote_cart_find_matching_item(array $cart, array $local_item): ?array
{
    $remote_items = musement_remote_cart_items_from_response($cart);
    $remote_uuid = sanitize_text_field($local_item['remote_item_uuid'] ?? '');

    foreach ($remote_items as $remote_item) {
        if (!is_array($remote_item)) {
            continue;
        }

        if ($remote_uuid && musement_remote_cart_item_uuid($remote_item) === $remote_uuid) {
            return $remote_item;
        }
    }

    foreach ($remote_items as $remote_item) {
        if (!is_array($remote_item)) {
            continue;
        }

        $remote_date = musement_remote_cart_item_value($remote_item, 'date');
        $remote_time = musement_remote_cart_item_value($remote_item, 'time');
        $same_product = musement_remote_cart_item_value($remote_item, 'product_identifier') === (string) ($local_item['product_identifier'] ?? '');
        $same_date = $remote_date === '' || $remote_date === (string) ($local_item['date'] ?? '');
        $same_time = $remote_time === '' || $remote_time === (string) ($local_item['time'] ?? '');

        if ($same_product && $same_date && $same_time) {
            return $remote_item;
        }
    }

    return null;
}

function musement_remote_cart_attach_item_uuid(array &$item, array $response_data = null): void
{
    if ($response_data) {
        $direct_uuid = musement_remote_cart_response_is_item($response_data)
            ? musement_remote_cart_item_uuid($response_data)
            : '';

        if ($direct_uuid) {
            $item['remote_item_uuid'] = $direct_uuid;
            return;
        }

        $matched = musement_remote_cart_find_matching_item($response_data, $item);
        if ($matched) {
            $matched_uuid = musement_remote_cart_item_uuid($matched);
            if ($matched_uuid) {
                $item['remote_item_uuid'] = $matched_uuid;
                return;
            }
        }
    }

    $cart = musement_remote_cart_refresh();
    if (!$cart) {
        return;
    }

    $matched = musement_remote_cart_find_matching_item($cart, $item);
    if ($matched) {
        $matched_uuid = musement_remote_cart_item_uuid($matched);
        if ($matched_uuid) {
            $item['remote_item_uuid'] = $matched_uuid;
        }
    }
}

function musement_remote_cart_finalize_add(array &$item, string $response_body): bool
{
    $response_data = json_decode($response_body, true);
    musement_remote_cart_attach_item_uuid($item, is_array($response_data) ? $response_data : null);

    $cart = musement_remote_cart_refresh();
    if (!$cart) {
        return false;
    }

    musement_remote_cart_attach_item_uuid($item, $cart);

    return true;
}

function musement_remote_cart_add_error(string $code, string $message, array $data = []): WP_Error
{
    error_log('[MUSEMENT] ❌ ' . $message . ' ' . wp_json_encode($data));

    return new WP_Error($code, $message, $data);
}

function musement_remote_cart_minimal_item_payload(array $item): array
{
    $payload = [
        'type' => 'musement',
        'product_identifier' => (string) $item['product_identifier'],
        'quantity' => (int) $item['quantity'],
    ];

    if (!empty($item['language'])) {
        $payload['language'] = $item['language'];
    }

    if (!empty($item['pickup'])) {
        $payload['pickup'] = $item['pickup'];
    }

    return $payload;
}

function musement_remote_cart_post_item(string $endpoint, string $token, array $payload)
{
    return wp_remote_post($endpoint, [
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
        'body' => wp_json_encode($payload),
    ]);
}

function musement_remote_cart_participants_decode($participant_info): array
{
    if (is_string($participant_info)) {
        $decoded = json_decode($participant_info, true);
        $participant_info = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : [];
    }

    if (!is_array($participant_info) || empty($participant_info)) {
        return [];
    }

    return $participant_info;
}

function musement_remote_cart_normalize_participants_list(array $participant_info): array
{
    $participants = isset($participant_info['participants']) && is_array($participant_info['participants'])
        ? $participant_info['participants']
        : $participant_info;

    $normalized = [];

    foreach ($participants as $participant) {
        if (!is_array($participant)) {
            continue;
        }

        if (isset($participant['answers']) && is_array($participant['answers'])) {
            $answers = $participant['answers'];
            unset($answers['ticket_type']);
            $normalized[] = $answers;
            continue;
        }

        unset($participant['participant']);
        $normalized[] = $participant;
    }

    return array_values(array_filter($normalized));
}

function musement_remote_cart_participants_payload($participant_info): array
{
    $participant_info = musement_remote_cart_participants_decode($participant_info);

    if (!$participant_info) {
        return [];
    }

    return musement_remote_cart_normalize_participants_list($participant_info);
}

function musement_remote_cart_update_item_participants(string $remote_item_uuid, $participant_info)
{
    $payload = musement_remote_cart_participants_payload($participant_info);

    if (!$payload) {
        return true;
    }

    $cart_id = musement_remote_cart_get_id();
    $token = musement_get_access_token();

    if (!$cart_id || !$token || !$remote_item_uuid) {
        return new WP_Error(
            'musement_remote_cart_participants_missing_data',
            __('Falta el carrito remoto, el producto remoto o el token de Musement.', 'musement-integration')
        );
    }

    $endpoint = sprintf(
        MUSEMENT_API_BASE_URL . '/api/v3/carts/%s/items/%s/participants',
        rawurlencode($cart_id),
        rawurlencode($remote_item_uuid)
    );

    $headers = [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'X-Musement-Application' => MUSEMENT_APPLICATION,
        'X-Musement-Version' => '3.4.0',
    ];

    error_log('[MUSEMENT] 📤 Updating participants endpoint: ' . $endpoint);
    error_log('[MUSEMENT] 📤 Updating participants for cart item ' . $remote_item_uuid . ': ' . wp_json_encode($payload));

    $res = wp_remote_request($endpoint, [
        'method' => 'PUT',
        'timeout' => 20,
        'headers' => $headers,
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $response_body = wp_remote_retrieve_body($res);
    $response_data = json_decode($response_body, true);

    if ($status >= 200 && $status < 300) {
        error_log('[MUSEMENT] ✅ Participants updated for cart item ' . $remote_item_uuid . '. Response: ' . $response_body);

        return [
            'status' => $status,
            'body' => is_array($response_data) ? $response_data : $response_body,
            'payload' => $payload,
            'cart_uuid' => $cart_id,
            'endpoint' => $endpoint,
            'remote_item_uuid' => $remote_item_uuid,
        ];
    }

    return new WP_Error(
        'musement_remote_cart_participants_failed',
        __('Musement rechazo los datos de participantes del carrito remoto.', 'musement-integration'),
        [
            'status' => $status,
            'body' => is_array($response_data) ? $response_data : $response_body,
            'payload' => $payload,
            'cart_uuid' => $cart_id,
            'endpoint' => $endpoint,
            'remote_item_uuid' => $remote_item_uuid,
        ]
    );
}

function musement_remote_cart_participants_item_not_found(WP_Error $error): bool
{
    $data = is_array($error->get_error_data()) ? $error->get_error_data() : [];
    $body = is_array($data['body'] ?? null) ? $data['body'] : [];
    $message = is_string($body['message'] ?? null) ? $body['message'] : '';

    return (int) ($data['status'] ?? 0) === 404
        && (
            (string) ($body['code'] ?? '') === '1400'
            || stripos($message, 'Cart item') !== false
        );
}

function musement_remote_cart_local_item_error_data(array $item, array $extra = []): array
{
    return array_merge($extra, [
        'local_item' => [
            'activity_uuid' => $item['activity_uuid'] ?? '',
            'product_identifier' => $item['product_identifier'] ?? '',
            'date' => $item['date'] ?? '',
            'time' => $item['time'] ?? '',
        ],
    ]);
}

function musement_remote_cart_ensure_item_for_participants(array &$item)
{
    $lookup_item = $item;
    $lookup_item['remote_item_uuid'] = '';

    $cart = musement_remote_cart_refresh();
    $cart_uuid = is_array($cart) ? sanitize_text_field($cart['uuid'] ?? '') : '';

    if ($cart_uuid && sanitize_text_field($item['remote_item_uuid'] ?? '') === $cart_uuid) {
        $item['remote_item_uuid'] = '';
    }

    if (is_array($cart)) {
        $matched = musement_remote_cart_find_matching_item($cart, $lookup_item);

        if ($matched) {
            $remote_uuid = musement_remote_cart_item_uuid($matched);

            if ($remote_uuid) {
                $item['remote_item_uuid'] = $remote_uuid;
                return true;
            }
        }
    }

    $item['remote_item_uuid'] = '';
    $added = musement_remote_cart_add_item($item);

    if (is_wp_error($added)) {
        return $added;
    }

    if (!$added || empty($item['remote_item_uuid'])) {
        return new WP_Error(
            'musement_remote_cart_item_missing',
            __('No se pudo recuperar el producto del carrito remoto para actualizar participantes.', 'musement-integration'),
            musement_remote_cart_local_item_error_data($item)
        );
    }

    return true;
}

function musement_remote_cart_sync_participants_from_local()
{
    musement_cart_start_session();

    foreach ($_SESSION['musement_cart'] as $index => $raw_item) {
        $item = musement_cart_normalize_item($raw_item);

        if (empty($item['participant_info'])) {
            continue;
        }

        $ensured = musement_remote_cart_ensure_item_for_participants($item);
        $_SESSION['musement_cart'][$index] = $item;

        if (is_wp_error($ensured)) {
            $error_data = is_array($ensured->get_error_data()) ? $ensured->get_error_data() : [];

            return new WP_Error(
                $ensured->get_error_code(),
                $ensured->get_error_message(),
                musement_remote_cart_local_item_error_data($item, $error_data)
            );
        }

        $remote_item_uuid = sanitize_text_field($item['remote_item_uuid'] ?? '');

        if (!$remote_item_uuid) {
            continue;
        }

        $updated = musement_remote_cart_update_item_participants($remote_item_uuid, $item['participant_info']);

        if (is_wp_error($updated) && musement_remote_cart_participants_item_not_found($updated)) {
            error_log('[MUSEMENT] 🔁 Remote cart item not found for participants. Recreating item and retrying.');

            $item['remote_item_uuid'] = '';
            $_SESSION['musement_cart'][$index] = $item;

            $ensured = musement_remote_cart_ensure_item_for_participants($item);
            $_SESSION['musement_cart'][$index] = $item;

            if (is_wp_error($ensured)) {
                $updated = $ensured;
            } elseif (!empty($item['remote_item_uuid'])) {
                $updated = musement_remote_cart_update_item_participants($item['remote_item_uuid'], $item['participant_info']);
            }
        }

        if (is_wp_error($updated)) {
            $error_data = is_array($updated->get_error_data()) ? $updated->get_error_data() : [];

            return new WP_Error(
                $updated->get_error_code(),
                $updated->get_error_message(),
                musement_remote_cart_local_item_error_data($item, $error_data)
            );
        }

        if (is_array($updated)) {
            $item['remote_participants_update'] = $updated;
            $_SESSION['musement_cart'][$index] = $item;
        }
    }

    musement_remote_cart_refresh();

    return true;
}

function musement_remote_cart_add_item(array &$item)
{
    $cart_id = musement_remote_cart_get_id();
    $token = musement_get_access_token();

    if (!$cart_id || !$token) {
        return musement_remote_cart_add_error(
            'musement_remote_cart_missing_auth',
            __('Falta el carrito remoto o el token de Musement.', 'musement-integration')
        );
    }

    $cart_check = musement_remote_cart_refresh();
    if (!$cart_check) {
        error_log('[MUSEMENT] ❌ Could not refresh cart, recreating...');
        $new_cart = musement_create_remote_cart();
        if ($new_cart && isset($new_cart['uuid'])) {
            $_SESSION['musement_remote_cart'] = $new_cart;
            $cart_id = $new_cart['uuid'];
        } else {
            return musement_remote_cart_add_error(
                'musement_remote_cart_recreate_failed',
                __('No se pudo recrear el carrito remoto de Musement.', 'musement-integration')
            );
        }
    }

    $endpoint = sprintf(
        MUSEMENT_API_BASE_URL . '/api/v3/carts/%s/items',
        $cart_id
    );

    $minimal_body_data = musement_remote_cart_minimal_item_payload($item);
    $body_data = $minimal_body_data;

    if (!empty($item['activity_uuid'])) {
        $body_data['activity_uuid'] = $item['activity_uuid'];
    }

    if (!empty($item['language'])) {
        $body_data['language'] = $item['language'];
    }

    if (!empty($item['pickup'])) {
        $body_data['pickup'] = $item['pickup'];
    }

    if (!empty($item['date'])) {
        $body_data['date'] = $item['date'];
    }

    if (!empty($item['time'])) {
        $body_data['time'] = $item['time'];
    }

    if (!empty($item['product_name'])) {
        $body_data['product_name'] = $item['product_name'];
    }

    if (!empty($item['unit_price'])) {
        $body_data['unit_price'] = (float) $item['unit_price'];
    }

    if (!empty($item['currency'])) {
        $body_data['currency'] = $item['currency'];
    }

    if (!empty($item['holder_code'])) {
        $body_data['holder_code'] = $item['holder_code'];
    }

    if (!empty($item['holder_code_normalized'])) {
        $body_data['holder_code_normalized'] = $item['holder_code_normalized'];
    }

    // LOG para depuración
    error_log('[MUSEMENT] 📤 Enviando a carrito remoto: ' . wp_json_encode($body_data));

    $request_payload = $body_data;
    $res = musement_remote_cart_post_item($endpoint, $token, $request_payload);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $response_body = wp_remote_retrieve_body($res);

    if ($status < 200 || $status >= 300) {
        error_log('[MUSEMENT] ❌ Remote add failed. Status: ' . $status . ' Response: ' . $response_body);

        if ($body_data !== $minimal_body_data) {
            error_log('[MUSEMENT] 🔁 Remote add fallback: intentando payload mínimo.');
            $minimal_res = musement_remote_cart_post_item($endpoint, $token, $minimal_body_data);

            if (is_wp_error($minimal_res)) {
                return $minimal_res;
            }

            $minimal_status = wp_remote_retrieve_response_code($minimal_res);
            $minimal_response_body = wp_remote_retrieve_body($minimal_res);

            if ($minimal_status >= 200 && $minimal_status < 300) {
                error_log('[MUSEMENT] ✅ Remote add minimal fallback succeeded. Status: ' . $minimal_status . ' Response: ' . $minimal_response_body);
                return musement_remote_cart_finalize_add($item, $minimal_response_body);
            }

            error_log('[MUSEMENT] ❌ Remote add minimal fallback failed. Status: ' . $minimal_status . ' Response: ' . $minimal_response_body);
        }

        if (in_array($status, [400, 422], true)) {
            error_log('[MUSEMENT] 🔁 Remote add fallback: intentando enviar payload como {"items": [...]}.');
            $request_payload = ['items' => [$minimal_body_data]];
            error_log('[MUSEMENT] 🔁 Fallback payload: ' . wp_json_encode($request_payload));

            $res_fallback = musement_remote_cart_post_item($endpoint, $token, $request_payload);

            if (is_wp_error($res_fallback)) {
                return $res_fallback;
            }

            $status_fallback = wp_remote_retrieve_response_code($res_fallback);
            $response_body_fallback = wp_remote_retrieve_body($res_fallback);

            if ($status_fallback < 200 || $status_fallback >= 300) {
                return musement_remote_cart_add_error(
                    'musement_remote_cart_add_failed',
                    __('Musement rechazo el producto al copiarlo al carrito remoto.', 'musement-integration'),
                    [
                        'status' => $status_fallback,
                        'body' => json_decode($response_body_fallback, true) ?: $response_body_fallback,
                        'payload' => $request_payload,
                        'minimal_attempt' => isset($minimal_status) ? [
                            'status' => $minimal_status,
                            'body' => json_decode($minimal_response_body, true) ?: $minimal_response_body,
                            'payload' => $minimal_body_data,
                        ] : null,
                        'first_attempt' => [
                            'status' => $status,
                            'body' => json_decode($response_body, true) ?: $response_body,
                            'payload' => $body_data,
                        ],
                    ]
                );
            }

            error_log('[MUSEMENT] ✅ Remote add fallback succeeded. Status: ' . $status_fallback . ' Response: ' . $response_body_fallback);
            return musement_remote_cart_finalize_add($item, $response_body_fallback);
        }

        return musement_remote_cart_add_error(
            'musement_remote_cart_add_failed',
            __('Musement rechazo el producto al copiarlo al carrito remoto.', 'musement-integration'),
            [
                'status' => $status,
                'body' => json_decode($response_body, true) ?: $response_body,
                'payload' => $request_payload,
                'minimal_attempt' => isset($minimal_status) ? [
                    'status' => $minimal_status,
                    'body' => json_decode($minimal_response_body, true) ?: $minimal_response_body,
                    'payload' => $minimal_body_data,
                ] : null,
            ]
        );
    }

    error_log('[MUSEMENT] ✅ Remote add succeeded. Status: ' . $status . ' Response: ' . $response_body);

    return musement_remote_cart_finalize_add($item, $response_body);
}

function musement_remote_cart_sync_from_local()
{
    musement_cart_start_session();

    $cart = musement_remote_cart_refresh();
    if (!$cart) {
        $new_cart = musement_create_remote_cart();
        if (!is_array($new_cart) || empty($new_cart['uuid'])) {
            return new WP_Error(
                'musement_remote_cart_unavailable',
                __('No se pudo crear o recuperar el carrito remoto de Musement.', 'musement-integration')
            );
        }

        $_SESSION['musement_remote_cart'] = $new_cart;
        $cart = $new_cart;
    }

    foreach ($_SESSION['musement_cart'] as $index => $raw_item) {
        $item = musement_cart_normalize_item($raw_item);
        $matched = musement_remote_cart_find_matching_item($cart, $item);

        if ($matched) {
            $remote_uuid = musement_remote_cart_item_uuid($matched);
            if ($remote_uuid) {
                $item['remote_item_uuid'] = $remote_uuid;
            }

            $_SESSION['musement_cart'][$index] = $item;
            continue;
        }

        $added = musement_remote_cart_add_item($item);
        if (is_wp_error($added)) {
            $error_data = is_array($added->get_error_data()) ? $added->get_error_data() : [];
            $error_data['local_item'] = [
                'activity_uuid' => $item['activity_uuid'] ?? '',
                'product_identifier' => $item['product_identifier'] ?? '',
                'date' => $item['date'] ?? '',
                'time' => $item['time'] ?? '',
            ];

            return new WP_Error(
                $added->get_error_code(),
                $added->get_error_message(),
                $error_data
            );
        }

        if (!$added) {
            return new WP_Error(
                'musement_remote_cart_sync_failed',
                __('No se pudo copiar un producto del carrito local al carrito remoto de Musement.', 'musement-integration'),
                [
                    'activity_uuid' => $item['activity_uuid'] ?? '',
                    'product_identifier' => $item['product_identifier'] ?? '',
                    'date' => $item['date'] ?? '',
                    'time' => $item['time'] ?? '',
                ]
            );
        }

        $_SESSION['musement_cart'][$index] = $item;
        $cart = $_SESSION['musement_remote_cart'] ?? musement_remote_cart_refresh();
    }

    $participants_sync = musement_remote_cart_sync_participants_from_local();
    if (is_wp_error($participants_sync)) {
        return $participants_sync;
    }

    $refreshed_cart = musement_remote_cart_refresh();
    if (!$refreshed_cart) {
        return new WP_Error(
            'musement_remote_cart_refresh_failed',
            __('El carrito remoto se sincronizo, pero no se pudo verificar antes del checkout.', 'musement-integration')
        );
    }

    return $refreshed_cart;
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
        MUSEMENT_API_BASE_URL . '/api/v3/carts/%s/items/%s',
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

    if ($status !== 204) {
        error_log('Remote remove failed: ' . wp_remote_retrieve_body($res));
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

    $endpoint = MUSEMENT_API_BASE_URL . '/api/v3/orders';

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
        'questions' => $raw['questions'] ?? [],
        'participants' => isset($raw['participants']) ? (int) $raw['participants'] : 1,
        'participant_info' => $raw['participant_info'] ?? [],
        'remote_participants_update' => $raw['remote_participants_update'] ?? [],
        'customer_extra_data' => $raw['customer_extra_data'] ?? [],
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

/**
 * Reemplazo de musement_cart_add_ajax() para soportar varios tipos de entrada
 * (adulto/niño/senior/estudiante/etc.) dentro de la misma reserva.
 *
 * Cambios respecto a la versión original:
 * - Lee el nuevo campo POST "items" (JSON) con el desglose por tipo de entrada
 *   que manda el widget del calendario: [{productIdentifier, productName,
 *   holderCode, holderCodeNormalized, unitPrice, currency, quantity}, ...]
 * - Si "items" no llega o viene vacío, hace fallback al comportamiento legacy
 *   (un único producto/tipo para todos los participantes), usando los campos
 *   sueltos product_identifier/holder_code/unit_price/quantity de siempre.
 * - Llama a musement_remote_cart_add_item() una vez por cada tipo de entrada
 *   distinto, no una sola vez para todos los participantes.
 * - Reparte participant_info entre los items según la respuesta "ticket_type"
 *   de cada participante, para que musement_remote_cart_update_item_participants()
 *   reciba solo los participantes que corresponden a cada tipo.
 * - Si algún tipo falla al añadirse pero otros sí se añaden, se informa como
 *   éxito parcial (con "partial_errors") en vez de perder todo el carrito.
 */
function musement_cart_add_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    // Idempotencia: si el mismo "añadir al carrito" (mismo producto/fecha/horario/
    // participantes/tipos de entrada) llega dos veces casi al mismo tiempo -- doble o
    // triple clic en el botón, reintento de red, etc. -- ignoramos el duplicado en vez
    // de meter el mismo producto varias veces al carrito local y remoto. Usamos un
    // candado de MySQL (no un transient) porque es atómico entre procesos PHP distintos,
    // incluso si las peticiones llegan literalmente en paralelo.
    $add_lock_name = 'musement_cart_add_' . md5(wp_json_encode($_POST));
    if (!musement_order_lock_acquire($add_lock_name)) {
        wp_send_json_error([
            'message' => __('Este producto ya se está añadiendo al carrito, espera un momento.', 'musement-integration'),
            'items' => musement_cart_items(),
            'summary' => musement_cart_total(),
        ], 409);
        return;
    }

    $is_open_date = isset($_POST['open_date']) ? filter_var($_POST['open_date'], FILTER_VALIDATE_BOOLEAN) : false;

    $questions_raw = isset($_POST['questions']) ? wp_unslash($_POST['questions']) : '{}';
    $questions = json_decode($questions_raw, true);
    if (!is_array($questions)) {
        $questions = [];
    }

    $customer_extra_data_raw = isset($_POST['customer_extra_data']) ? wp_unslash($_POST['customer_extra_data']) : '{}';
    $customer_extra_data = json_decode($customer_extra_data_raw, true);
    if (!is_array($customer_extra_data)) {
        $customer_extra_data = [];
    }

    $participant_info_raw = isset($_POST['participant_info']) ? wp_unslash($_POST['participant_info']) : '[]';
    $participant_info = json_decode($participant_info_raw, true);
    if (!is_array($participant_info)) {
        $participant_info = [];
    }

    $participants = isset($_POST['participants']) ? (int) $_POST['participants'] : 1;

    // Desglose por tipo de entrada (adulto/niño/senior/estudiante/etc.) que manda el widget.
    // Si no llega o viene vacío, se usa el comportamiento legacy: un único tipo para todos.
    $items_raw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
    $items_breakdown = json_decode($items_raw, true);

    if (!is_array($items_breakdown) || empty($items_breakdown)) {
        $items_breakdown = [[
            'productIdentifier' => isset($_POST['product_identifier']) ? wp_unslash($_POST['product_identifier']) : '',
            'productName' => isset($_POST['product_name']) ? wp_unslash($_POST['product_name']) : '',
            'holderCode' => isset($_POST['holder_code']) ? wp_unslash($_POST['holder_code']) : '',
            'holderCodeNormalized' => isset($_POST['holder_code_normalized']) ? wp_unslash($_POST['holder_code_normalized']) : '',
            'unitPrice' => isset($_POST['unit_price']) ? wp_unslash($_POST['unit_price']) : 0,
            'currency' => isset($_POST['currency']) ? wp_unslash($_POST['currency']) : 'EUR',
            'quantity' => $participants,
        ]];
    }

    error_log('[MUSEMENT] Cart add AJAX request payload: ' . wp_json_encode($_POST));

    // Datos comunes a toda la reserva (fecha, horario, idioma...), independientes del tipo de entrada
    $base = [
        'activity_uuid' => isset($_POST['activity_uuid']) ? wp_unslash($_POST['activity_uuid']) : '',
        'title' => isset($_POST['title']) ? wp_unslash($_POST['title']) : '',
        'date' => isset($_POST['date']) ? wp_unslash($_POST['date']) : '',
        'time' => isset($_POST['time']) ? wp_unslash($_POST['time']) : '',
        'option_name' => isset($_POST['option_name']) ? wp_unslash($_POST['option_name']) : '',
        'option_feature_code' => isset($_POST['option_feature_code']) ? wp_unslash($_POST['option_feature_code']) : '',
        'option_type' => isset($_POST['option_type']) ? wp_unslash($_POST['option_type']) : '',
        'availability' => isset($_POST['availability']) ? wp_unslash($_POST['availability']) : 0,
        'language' => isset($_POST['language']) ? wp_unslash($_POST['language']) : '',
        'pickup' => isset($_POST['pickup']) ? wp_unslash($_POST['pickup']) : '',
    ];

    if ($is_open_date) {
        $base['date'] = '';
        $base['time'] = '';
    }

    // 🔥 CORRECCIÓN: time ya no es obligatorio, y si está vacío, usar '00:00'
    if (empty($base['time'])) {
        $base['time'] = '00:00';
    }

    if (!$base['activity_uuid'] || !$base['date']) {
        musement_order_lock_release($add_lock_name);
        wp_send_json_error([
            'message' => __('Faltan datos para añadir el producto.', 'musement-integration'),
        ], 400);
        return;
    }

    musement_cart_start_session();

    $added_items = [];
    $errors = [];

    foreach ($items_breakdown as $breakdown) {
        $product_identifier = sanitize_text_field((string) ($breakdown['productIdentifier'] ?? ''));
        $quantity = max(1, (int) ($breakdown['quantity'] ?? 1));

        if (!$product_identifier) {
            error_log('[MUSEMENT] Cart add AJAX missing product_identifier for one of the items; activity_uuid=' . $base['activity_uuid']);
            $errors[] = __('No se pudo identificar la variante vendible de uno de los tipos de entrada. Selecciona de nuevo fecha y horario.', 'musement-integration');
            continue;
        }

        // Participantes que corresponden a este tipo de entrada concreto.
        // Si un participante no mandó "ticket_type" (producto sin desglose de tipos), se incluye igualmente.
        $item_participant_info = array_values(array_filter($participant_info, function ($participant) use ($product_identifier) {
            $ticket_type = $participant['answers']['ticket_type'] ?? null;
            return $ticket_type === null || $ticket_type === '' || $ticket_type === $product_identifier;
        }));

        $item = musement_cart_normalize_item([
            'activity_uuid' => $base['activity_uuid'],
            'title' => $base['title'],
            'date' => $base['date'],
            'time' => $base['time'],
            'product_identifier' => $product_identifier,
            'product_name' => sanitize_text_field((string) ($breakdown['productName'] ?? '')),
            'option_name' => $base['option_name'],
            'option_feature_code' => $base['option_feature_code'],
            'option_type' => $base['option_type'],
            'holder_code' => sanitize_text_field((string) ($breakdown['holderCode'] ?? '')),
            'holder_code_normalized' => sanitize_text_field((string) ($breakdown['holderCodeNormalized'] ?? '')),
            'availability' => $base['availability'],
            'language' => $base['language'],
            'pickup' => $base['pickup'],
            'quantity' => $quantity,
            'unit_price_formatted' => '',
            'unit_price' => $breakdown['unitPrice'] ?? 0,
            'musement_price' => $breakdown['unitPrice'] ?? 0,
            'currency' => sanitize_text_field((string) ($breakdown['currency'] ?? 'EUR')),
            'questions' => $questions,
            'participant_info' => $item_participant_info,
            'customer_extra_data' => $customer_extra_data,
        ]);

        error_log('[MUSEMENT] Cart add AJAX normalized item (' . $product_identifier . '): ' . wp_json_encode($item));

        $added = musement_remote_cart_add_item($item);

        if (is_wp_error($added)) {
            error_log('[MUSEMENT] ⚠️ Carrito remoto falló para el tipo "' . $product_identifier . '": ' . $added->get_error_message());
            $errors[] = $added->get_error_message();
            continue;
        }

        if (!$added) {
            $errors[] = sprintf(
                /* translators: %s: nombre del tipo de entrada */
                __('No se pudo copiar el tipo de entrada "%s" al carrito remoto de Musement.', 'musement-integration'),
                $item['product_name'] ?? $product_identifier
            );
            continue;
        }

        if (!empty($item['remote_item_uuid']) && !empty($item_participant_info)) {
            $participants_update = musement_remote_cart_update_item_participants($item['remote_item_uuid'], $item_participant_info);

            if (is_wp_error($participants_update)) {
                $errors[] = $participants_update->get_error_message();
                continue;
            }

            if (is_array($participants_update)) {
                $item['remote_participants_update'] = $participants_update;
            }
        }

        $_SESSION['musement_cart'][] = $item;
        $added_items[] = $item;
    }

    // Si ningún tipo de entrada se pudo añadir, se informa como error total (comportamiento igual al original)
    if (empty($added_items)) {
        musement_order_lock_release($add_lock_name);
        wp_send_json_error([
            'message' => !empty($errors) ? implode(' ', $errors) : __('No se pudo añadir ningún tipo de entrada al carrito.', 'musement-integration'),
            'items' => musement_cart_items(),
            'summary' => musement_cart_total(),
            'remote_error' => $errors,
        ], 502);
        return;
    }

    $cart_after = musement_remote_cart_refresh();
    error_log('[MUSEMENT] 📊 Carrito después de añadir: ' . wp_json_encode($cart_after));

    $response = musement_cart_payload();

    // Éxito parcial: se añadieron algunos tipos de entrada pero no todos
    if (!empty($errors)) {
        $response['partial_errors'] = $errors;
    }

    musement_order_lock_release($add_lock_name);
    wp_send_json_success($response);
}

function musement_ensure_cart(): void
{
    musement_cart_start_session();
}

function musement_remote_cart_get_ajax(): void
{
    check_ajax_referer('musement_ajax', 'nonce');

    musement_cart_start_session();

    $remote_cart = $_SESSION['musement_remote_cart'] ?? [];

    if (is_array($remote_cart)) {
        $remote_cart['local_participants_updates'] = [];

        foreach (musement_cart_items() as $item) {
            if (!empty($item['remote_item_uuid']) && !empty($item['remote_participants_update'])) {
                $remote_cart['local_participants_updates'][$item['remote_item_uuid']] = $item['remote_participants_update'];
            }
        }
    }

    wp_send_json_success($remote_cart);
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
                MUSEMENT_API_BASE_URL . '/api/v3/carts/%s',
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
        MUSEMENT_API_BASE_URL . '/api/v3/carts/%s/customer',
        $cart_id
    );

    // Construir el body según la documentación
    $body = [
        'email' => $customer_data['email'] ?? '',
        'firstname' => $customer_data['first_name'] ?? '',
        'lastname' => $customer_data['last_name'] ?? '',
    ];



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
