<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_money_payload($money): array {
    if (!is_array($money)) {
        return [
            'currency' => '',
            'value' => null,
            'formatted' => '',
        ];
    }

    return [
        'currency' => sanitize_text_field($money['currency'] ?? ''),
        'value' => isset($money['value']) ? (float) $money['value'] : null,
        'formatted' => sanitize_text_field($money['formatted_value'] ?? ''),
    ];
}

function musement_language_payload($language): array {
    return [
        'code' => sanitize_text_field($language['code'] ?? ''),
        'name' => sanitize_text_field($language['name'] ?? ''),
    ];
}

function musement_product_payload($product): array {
    $retail_price = $product['retail_price'] ?? $product['price'] ?? [];

    return [
        'type' => sanitize_text_field($product['type'] ?? 'musement'),
        'product_identifier' => sanitize_text_field($product['product_id'] ?? $product['product_identifier'] ?? $product['id'] ?? ''),
        'name' => sanitize_text_field($product['name'] ?? ''),
        'holder_code' => sanitize_text_field($product['holder_code'] ?? ''),
        'holder_code_normalized' => sanitize_text_field($product['holder_code_normalized'] ?? ''),
        'default' => !empty($product['default']),
        'min_buy' => isset($product['min_buy']) ? (int) $product['min_buy'] : 1,
        'max_buy' => isset($product['max_buy']) ? (int) $product['max_buy'] : 0,
        'availability' => isset($product['availability']) ? (int) $product['availability'] : 0,
        'price' => [
            'currency' => '',
            'value' => null,
            'formatted' => ''
        ],
    ];
}

function musement_resolve_bookable_product(array $products): ?array {
    if (empty($products)) {
        return null;
    }

    foreach ($products as $product) {
        if (!empty($product['default'])) {
            return $product;
        }
    }

    return $products[0];
}

function musement_slot_payload($slot, array $group = []): array {
    $products = [];

    if (!empty($slot['products']) && is_array($slot['products'])) {
        foreach ($slot['products'] as $product) {
            if (is_array($product)) {
                $products[] = musement_product_payload($product);
            }
        }
    }

    $languages = [];

    if (!empty($slot['languages']) && is_array($slot['languages'])) {
        foreach ($slot['languages'] as $language) {
            if (is_array($language)) {
                $languages[] = musement_language_payload($language);
            }
        }
    }

    // Use the first product's price for the slot-level price, or an empty money object.
    $first_product = !empty($products) ? $slot['products'][0] : [];

    return [
        'price'                => musement_money_payload($first_product['price'] ?? []),
        'resolved_product'     => musement_resolve_bookable_product($products),
        'time'                 => sanitize_text_field($slot['time'] ?? $slot['start_time'] ?? ''),
        'option_name'          => sanitize_text_field($group['name'] ?? ''),
        'option_feature_code'  => sanitize_text_field(
            $group['feature_code']
            ?? $group['featureCode']
            ?? ''
        ),
        'option_type'          => sanitize_text_field(
            $group['type']
            ?? $group['option_type']
            ?? ''
        ),
        'min_buy'     => isset($slot['min_buy'])      ? (int) $slot['min_buy']      : 1,
        'max_buy'     => isset($slot['max_buy'])      ? (int) $slot['max_buy']      : 0,
        'availability'=> isset($slot['availability']) ? (int) $slot['availability'] : 0,
        'languages'   => $languages,
        'products'    => $products,
    ];
}

function musement_day_availability() {
    check_ajax_referer('musement_ajax', 'nonce');

    $uuid = sanitize_text_field($_POST['uuid'] ?? '');
    $date = sanitize_text_field($_POST['date'] ?? '');

    if (!$uuid || !$date) {
        wp_send_json_error(['message' => 'Missing uuid or date'], 400);
    }

    $url = sprintf(
        'https://sandbox.musement.com/api/v3/activities/%s/dates/%s',
        rawurlencode($uuid),
        rawurlencode($date)
    );

    $token = musement_get_access_token();
    
    if (!$token) {
        wp_send_json_error(['message' => 'Could not get access token'], 500);
    }

    $res = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept-Language' => 'es-ES',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ]
    ]);

    if (is_wp_error($res)) {
        wp_send_json_error(['message' => $res->get_error_message()], 500);
    }

    $data = json_decode(wp_remote_retrieve_body($res), true);
    
    // NORMALIZACIÓN
    if (is_array($data) && isset($data[0]['groups'])) {
        $data = ['groups' => $data[0]['groups']];
    }
    
    wp_send_json_success($data);
}

function musement_month_availability() {
    check_ajax_referer('musement_ajax', 'nonce');

    $uuid  = sanitize_text_field($_POST['uuid']  ?? '');
    $month = sanitize_text_field($_POST['month'] ?? '');

    if (!$uuid || !$month) {
        wp_send_json_error(['message' => 'Missing uuid or month'], 400);
    }

    $url = sprintf(
        'https://sandbox.musement.com/api/v3/activities/%s/dates',
        rawurlencode($uuid)
    );

    // Include auth token so authenticated endpoints return correct data.
    $token   = musement_get_access_token();
    $headers = [
        'Accept-Language'       => 'es-ES',
        'X-Musement-Application'=> MUSEMENT_APPLICATION,
        'X-Musement-Currency'   => 'EUR',
        'X-Musement-Market'     => 'es',
        'X-Musement-Version'    => '3.4.0',
    ];
    if ($token) {
        $headers['Authorization'] = 'Bearer ' . $token;
    }

    $res = wp_remote_get($url, [
        'headers' => $headers,
    ]);

    if (is_wp_error($res)) {
        wp_send_json_error(['message' => $res->get_error_message()], 500);
    }

    $data = json_decode(wp_remote_retrieve_body($res), true);
    $map  = [];

    if (is_array($data)) {
        foreach ($data as $day) {
            $date = $day['day'] ?? null;
            if (!$date) continue;
            if (strpos($date, $month) !== 0) continue;

            $status = 'available';
            if (!empty($day['sold_out'])) {
                $status = 'full';
            } elseif (($day['availability'] ?? -1) <= 5) {
                $status = 'low';
            }
            $map[$date] = $status;
        }
    }

    wp_send_json_success(['days' => $map]);
}

function musement_availability() {
    check_ajax_referer('musement_ajax', 'nonce');

    $uuid = isset($_POST['uuid']) ? sanitize_text_field(wp_unslash($_POST['uuid'])) : '';
    $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
    $pickup = isset($_POST['pickup']) ? sanitize_text_field(wp_unslash($_POST['pickup'])) : '';

    if (!$uuid || !$date) {
        wp_send_json_error(['message' => __('Missing uuid or date.', 'musement-integration')], 400);
    }

    $url = add_query_arg(
        array_filter([
            'available_from' => $date,
            'available_to' => $date,
            'city_in' => 2,
            'pickup' => $pickup ?: null
        ]),
        sprintf(
            'https://sandbox.musement.com/api/v3/activities/%s/dates',
            rawurlencode($uuid)
        )
    );

    $res = wp_remote_get($url, [
        'headers' => [
            'Accept-Language' => 'es-ES',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ]
    ]);

    if (is_wp_error($res)) {
        wp_send_json_error(['message' => $res->get_error_message()], 500);
    }

    $data = json_decode(wp_remote_retrieve_body($res), true);
    $slots = [];

    if (is_array($data)) {
        foreach ($data as $item) {
            if (!is_array($item)) continue;

            $slots[] = [
                'date' => sanitize_text_field($item['day'] ?? ''),
                'time' => null,
                'option_name' => !empty($item['sold_out']) ? 'Agotado' : 'Disponible',
                'option_feature_code' => '',
                'option_type' => '',
                'min_buy' => 1,
                'max_buy' => 10,
                'availability' => !empty($item['sold_out']) ? 0 : 1,
                'languages' => [],
                'products' => [
                    [
                        'product_identifier' => sanitize_text_field($uuid),
                        'name' => 'Entrada',
                        'min_buy' => 1,
                        'max_buy' => 10,
                        'availability' => !empty($item['sold_out']) ? 0 : 1,
                        'price' => [
                            'currency' => sanitize_text_field($item['retail_price']['currency'] ?? 'EUR'),
                            'value' => (float) ($item['retail_price']['value'] ?? 0),
                            'formatted' => sanitize_text_field($item['retail_price']['formatted_iso_value'] ?? ''),
                        ]
                    ]
                ]
            ];
        }
    }

    wp_send_json_success(['slots' => $slots]);
}

function musement_activities() {
    check_ajax_referer('musement_ajax', 'nonce');

    $city_id = isset($_POST['city_id']) ? (int) sanitize_text_field(wp_unslash($_POST['city_id'])) : 0;
    $lat     = isset($_POST['lat'])     ? (float) sanitize_text_field(wp_unslash($_POST['lat']))     : 37.1773;
    $lng     = isset($_POST['lng'])     ? (float) sanitize_text_field(wp_unslash($_POST['lng']))     : -3.5986;
    $limit   = isset($_POST['limit'])   ? max(1, min(12, (int) sanitize_text_field(wp_unslash($_POST['limit'])))) : 12;

    $margins = get_option('musement_product_margins', []);

    // Hardcoded test activity is only shown when WP_DEBUG is active.
    $test_activities = [];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $test_uuid         = '4e451a8a-2ee5-493f-aa12-a39f13d7253e';
        $test_margin       = isset($margins[$test_uuid]) ? (float) $margins[$test_uuid] : 0;
        $test_original     = 40;
        $test_final        = $test_original * (1 + ($test_margin / 100));
        $test_activities[] = [
            'uuid'        => $test_uuid,
            'title'       => '[TEST] Tour con confirmación instantánea',
            'description' => 'Actividad de prueba para verificar el flujo completo de reserva.',
            'image'       => 'https://images.musement.com/cover/0002/39/milan-vittorio-emanuele-ii-gallery-italy-jpg_header-138313.jpeg',
            'price'       => [
                'value'     => $test_final,
                'currency'  => 'EUR',
                'formatted' => number_format($test_final, 2) . ' €',
            ],
        ];
    }

    $activities = [];
    if ($city_id) {
        $activities = musement_get_activities_by_city($city_id, $limit);
    } else {
        $activities = musement_get_activities($lat, $lng, $limit);
    }

    $city_activities = array_map(function ($activity) use ($margins) {
        $uuid   = $activity['uuid'] ?? '';
        $margin = isset($margins[$uuid]) ? (float) $margins[$uuid] : 0;

        if ($margin <= 0) {
            return null;
        }

        $original_price = $activity['retail_price']['value'] ?? 0;
        $final_price    = $original_price * (1 + ($margin / 100));

        $cover_image = '';
        if (!empty($activity['cover_image_url'])) {
            $cover_image = $activity['cover_image_url'];
        } elseif (!empty($activity['cover_image']['url'])) {
            $cover_image = $activity['cover_image']['url'];
        } elseif (!empty($activity['images'][0]['url'])) {
            $cover_image = $activity['images'][0]['url'];
        }

        return [
            'uuid'        => sanitize_text_field($uuid),
            'title'       => sanitize_text_field($activity['title'] ?? $activity['name'] ?? __('Tour sin titulo', 'musement-integration')),
            'description' => wp_strip_all_tags($activity['description'] ?? $activity['about'] ?? ''),
            'image'       => esc_url_raw($cover_image),
            'price'       => [
                'value'     => $final_price,
                'currency'  => 'EUR',
                'formatted' => number_format($final_price, 2) . ' €',
            ],
        ];
    }, $activities);

    $city_activities = array_filter($city_activities);

    $items = array_merge($test_activities, array_values($city_activities));

    wp_send_json_success([
        'activities' => $items,
        'city_id'    => $city_id,
    ]);
}

/**
 * Obtiene actividades por ID de ciudad
 */
function musement_get_activities_by_city($city_id, $limit = 12) {
    $token = musement_get_access_token();
    
    if (!$token || !$city_id) {
        return [];
    }
    
    $url = add_query_arg([
        'city_in' => $city_id,
        'limit' => $limit,
    ], 'https://sandbox.musement.com/api/v3/activities');
    
    $res = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept-Language' => 'es-ES',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ],
    ]);

    if (is_wp_error($res)) {
        error_log('[MUSEMENT] Error fetching activities by city: ' . $res->get_error_message());
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);
    return is_array($body['data'] ?? null) ? $body['data'] : [];
}

/**
 * Endpoint para obtener todos los tickets de una orden
 */
add_action('wp_ajax_musement_get_order_tickets', 'musement_ajax_get_order_tickets');
add_action('wp_ajax_nopriv_musement_get_order_tickets', 'musement_ajax_get_order_tickets');

function musement_ajax_get_order_tickets() {
    check_ajax_referer('musement_ajax', 'nonce');
    
    $order_uuid = sanitize_text_field($_POST['order_uuid'] ?? '');
    
    if (!$order_uuid) {
        wp_send_json_error(['message' => 'Falta el UUID de la orden']);
        return;
    }
    
    // Obtener la orden completa
    $order = musement_remote_get_order($order_uuid);
    
    if (is_wp_error($order)) {
        wp_send_json_error(['message' => $order->get_error_message()]);
        return;
    }
    
    $all_tickets = [];
    
    if (!empty($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $activity_uuid = $item['product']['activity_uuid'] ?? $item['uuid'] ?? '';
            $activity_name = $item['product']['name'] ?? $item['name'] ?? 'Actividad';
            $item_status = $item['status'] ?? 'unknown';
            
            // Obtener tickets de esta actividad
            $tickets = musement_remote_get_activity_tickets($order_uuid, $activity_uuid);
            
            if (!is_wp_error($tickets) && !empty($tickets)) {
                foreach ($tickets as $ticket) {
                    $all_tickets[] = [
                        'ticket_id' => $ticket['id'] ?? '',
                        'activity_uuid' => $activity_uuid,
                        'activity_name' => $activity_name,
                        'status' => $item_status,
                        'url' => $ticket['url'] ?? null
                    ];
                }
            }
        }
    }
    
    wp_send_json_success([
        'tickets' => $all_tickets,
        'order_uuid' => $order_uuid
    ]);
}

/**
 * Endpoint para descargar un ticket específico
 */
add_action('wp_ajax_musement_download_ticket', 'musement_ajax_download_ticket');
add_action('wp_ajax_nopriv_musement_download_ticket', 'musement_ajax_download_ticket');

function musement_ajax_download_ticket() {
    check_ajax_referer('musement_ajax', 'nonce');
    
    $order_uuid = sanitize_text_field($_POST['order_uuid'] ?? '');
    $activity_uuid = sanitize_text_field($_POST['activity_uuid'] ?? '');
    $ticket_id = sanitize_text_field($_POST['ticket_id'] ?? '');
    
    if (!$order_uuid || !$activity_uuid || !$ticket_id) {
        wp_send_json_error(['message' => 'Faltan parámetros requeridos']);
        return;
    }
    
    $result = musement_remote_download_ticket_pdf($order_uuid, $activity_uuid, $ticket_id);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
        return;
    }
    
    wp_send_json_success([
        'url' => $result['url'],
        'filename' => $result['filename']
    ]);
}

add_action('wp_ajax_musement_activities', 'musement_activities');
add_action('wp_ajax_nopriv_musement_activities', 'musement_activities');
add_action('wp_ajax_musement_availability', 'musement_availability');
add_action('wp_ajax_nopriv_musement_availability', 'musement_availability');
add_action('wp_ajax_musement_month_availability', 'musement_month_availability');
add_action('wp_ajax_nopriv_musement_month_availability', 'musement_month_availability');
add_action('wp_ajax_musement_day_availability', 'musement_day_availability');
add_action('wp_ajax_nopriv_musement_day_availability', 'musement_day_availability');