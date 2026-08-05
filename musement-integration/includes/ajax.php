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

    return [
        'price' => musement_money_payload($product['price'] ?? []),
        'resolved_product' => musement_resolve_bookable_product($products),
        'time' => sanitize_text_field($slot['time'] ?? $slot['start_time'] ?? ''),
        'option_name' => sanitize_text_field($group['name'] ?? ''),
        'option_feature_code' => sanitize_text_field(
            $group['feature_code']
            ?? $group['featureCode']
            ?? ''
        ),        
        'option_type' => sanitize_text_field(
            $group['type']
            ?? $group['option_type']
            ?? ''
        ),
        'min_buy' => isset($slot['min_buy']) ? (int) $slot['min_buy'] : 1,
        'max_buy' => isset($slot['max_buy']) ? (int) $slot['max_buy'] : 0,
        'availability' => isset($slot['availability']) ? (int) $slot['availability'] : 0,
        'languages' => $languages,
        'products' => $products,
    ];
}

function musement_day_availability() {
    check_ajax_referer('musement_ajax', 'nonce');

    $uuid = sanitize_text_field($_POST['uuid'] ?? '');
    $date = sanitize_text_field($_POST['date'] ?? '');
    $pickup = isset($_POST['pickup']) ? sanitize_text_field($_POST['pickup']) : '';

    // Log para depuración
    error_log('[MUSEMENT] === DAY AVAILABILITY ===');
    error_log('[MUSEMENT] UUID: ' . $uuid);
    error_log('[MUSEMENT] Date: ' . $date);
    error_log('[MUSEMENT] Pickup: ' . ($pickup ?: 'NINGUNO'));

    if (!$uuid || !$date) {
        error_log('[MUSEMENT] ❌ Missing uuid or date');
        wp_send_json_error(['message' => 'Missing uuid or date'], 400);
        return;
    }

    $token = musement_get_access_token();
    
    if (!$token) {
        error_log('[MUSEMENT] ❌ Could not get access token');
        wp_send_json_error(['message' => 'Could not get access token'], 500);
        return;
    }

    // 🔥 CONSTRUIR LA URL
    $url = sprintf(
        'https://sandbox.musement.com/api/v3/activities/%s/dates/%s',
        rawurlencode($uuid),
        rawurlencode($date)
    );

    // 🔥 SI HAY PICKUP, AGREGARLO COMO PARÁMETRO DE QUERY
    if (!empty($pickup)) {
        $url = add_query_arg('pickup', $pickup, $url);
        error_log('[MUSEMENT] URL con pickup: ' . $url);
    } else {
        error_log('[MUSEMENT] URL sin pickup: ' . $url);
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
        error_log('[MUSEMENT] ❌ Error WP: ' . $res->get_error_message());
        wp_send_json_error(['message' => $res->get_error_message()], 500);
        return;
    }

    $body = wp_remote_retrieve_body($res);
    $http_code = wp_remote_retrieve_response_code($res);
    
    error_log('[MUSEMENT] HTTP Code: ' . $http_code);
    error_log('[MUSEMENT] Body (primeros 500 chars): ' . substr($body, 0, 500));

    // 🔥 SI HAY ERROR HTTP, MOSTRAR EL MENSAJE
    if ($http_code !== 200) {
        $error_data = json_decode($body, true);
        $error_message = is_array($error_data) && isset($error_data['message']) 
            ? $error_data['message'] 
            : 'HTTP Error: ' . $http_code;
        error_log('[MUSEMENT] ❌ Error: ' . $error_message);
        wp_send_json_error(['message' => $error_message, 'code' => $http_code], $http_code);
        return;
    }

    // 🔥 DECODIFICAR LA RESPUESTA
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[MUSEMENT] ❌ Error JSON: ' . json_last_error_msg());
        wp_send_json_error(['message' => 'Error al parsear JSON: ' . json_last_error_msg()], 500);
        return;
    }

    error_log('[MUSEMENT] ✅ Datos recibidos correctamente. Tipo: ' . gettype($data));
    wp_send_json_success($data);
}

function musement_month_availability() {
    check_ajax_referer('musement_ajax', 'nonce');

    $uuid = sanitize_text_field($_POST['uuid'] ?? '');
    $month = sanitize_text_field($_POST['month'] ?? '');

    if (!$uuid || !$month) {
        wp_send_json_error(['message' => 'Missing uuid or month'], 400);
    }

    $url = sprintf(
        'https://sandbox.musement.com/api/v3/activities/%s/dates',
        rawurlencode($uuid)
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
    $map = [];

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

    // Obtener parámetros
    $city_id = isset($_POST['city_id']) ? (int) sanitize_text_field(wp_unslash($_POST['city_id'])) : 0;
    $lat = isset($_POST['lat']) ? (float) sanitize_text_field(wp_unslash($_POST['lat'])) : 37.1773;
    $lng = isset($_POST['lng']) ? (float) sanitize_text_field(wp_unslash($_POST['lng'])) : -3.5986;
    $limit = isset($_POST['limit']) ? max(1, min(12, (int) sanitize_text_field(wp_unslash($_POST['limit'])))) : 12;
    
    // 🔥 USAR MÁRGENES en lugar de precios fijos
    $margins = get_option('musement_product_margins', []);
    
    // 🔥 ACTIVIDAD DE PRUEBA - Siempre disponible (con margen por defecto 0)
    $test_activities = [];
    $test_uuid = '4e451a8a-2ee5-493f-aa12-a39f13d7253e';
    $test_margin = isset($margins[$test_uuid]) ? (float) $margins[$test_uuid] : 0;
    $test_original_price = 40; // Precio original de la actividad de prueba
    $test_final_price = $test_original_price * (1 + ($test_margin / 100));
    
    // Mostrar actividad de prueba aunque tenga margen 0 (para pruebas)
    $test_activities[] = [
        'uuid' => $test_uuid,
        'title' => '🧪 Galeria emanuelle.',
        'description' => 'Actividad de prueba para verificar el flujo completo de reserva. Tiene disponibilidad garantizada.',
        'image' => 'https://images.musement.com/cover/0002/39/milan-vittorio-emanuele-ii-gallery-italy-jpg_header-138313.jpeg',
        'price' => [
            'value' => $test_final_price,
            'currency' => 'EUR',
            'formatted' => number_format($test_final_price, 2) . ' €'
        ]
    ];
    
    // Obtener actividades según el método disponible
    $activities = [];
    
    if ($city_id) {
        $activities = musement_get_activities_by_city($city_id, $limit);
    } else {
        $activities = musement_get_activities($lat, $lng, $limit);
    }
    
    // Procesar actividades de la ciudad con MÁRGENES
    $city_activities = array_map(function ($activity) use ($margins) {
        $uuid = $activity['uuid'] ?? '';
        $margin = isset($margins[$uuid]) ? (float) $margins[$uuid] : 0;
        
        // Solo mostrar actividades con margen > 0
        if ($margin <= 0) {
            return null;
        }
        
        $original_price = $activity['retail_price']['value'] ?? 0;
        $final_price = $original_price * (1 + ($margin / 100));
        
        $cover_image = '';
        if (!empty($activity['cover_image_url'])) {
            $cover_image = $activity['cover_image_url'];
        } elseif (!empty($activity['cover_image']['url'])) {
            $cover_image = $activity['cover_image']['url'];
        } elseif (!empty($activity['images'][0]['url'])) {
            $cover_image = $activity['images'][0]['url'];
        }
        
        return [
            'uuid'  => sanitize_text_field($uuid),
            'title' => sanitize_text_field($activity['title'] ?? $activity['name'] ?? __('Tour sin titulo', 'musement-integration')),
            'description' => wp_strip_all_tags($activity['description'] ?? $activity['about'] ?? ''),
            'image' => esc_url_raw($cover_image),
            'price' => [
                'value' => $final_price,
                'currency' => 'EUR',
                'formatted' => number_format($final_price, 2) . ' €'
            ],
        ];
    }, $activities);
    
    // Filtrar nulos
    $city_activities = array_filter($city_activities);
    
    // COMBINAR: Actividad de prueba + actividades de la ciudad con margen
    $items = array_merge($test_activities, array_values($city_activities));
    
    wp_send_json_success([
        'activities' => $items,
        'test_mode' => true,
        'city_id' => $city_id
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

/**
 * Endpoint para fusionar vouchers desde el frontend
 */

add_action('wp_ajax_musement_merge_vouchers', 'musement_merge_vouchers_ajax');
add_action('wp_ajax_nopriv_musement_merge_vouchers', 'musement_merge_vouchers_ajax');

function musement_merge_vouchers_ajax() {
    // 🔥 Activar logs para depuración
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[MUSEMENT] Iniciando musement_merge_vouchers_ajax');
    }
    
    // Verificar nonce
    if (!check_ajax_referer('musement_ajax', 'nonce', false)) {
        error_log('[MUSEMENT] ❌ Nonce inválido');
        wp_send_json_error(['message' => 'Nonce inválido']);
        return;
    }
    
    $order_uuid = sanitize_text_field($_POST['order_uuid'] ?? '');
    $voucher_urls = json_decode(stripslashes($_POST['voucher_urls'] ?? '[]'), true);
    $voucher_names = json_decode(stripslashes($_POST['voucher_names'] ?? '[]'), true);
    
    error_log('[MUSEMENT] Order UUID: ' . $order_uuid);
    error_log('[MUSEMENT] Vouchers recibidos: ' . count($voucher_urls));
    
    if (empty($voucher_urls) || !$order_uuid) {
        error_log('[MUSEMENT] ❌ Faltan datos: order_uuid=' . $order_uuid . ', vouchers=' . count($voucher_urls));
        wp_send_json_error(['message' => 'Faltan datos para fusionar']);
        return;
    }
    
    // Construir array de vouchers
    $vouchers = [];
    foreach ($voucher_urls as $index => $url) {
        // Limpiar la URL por si tiene caracteres extraños
        $url = trim($url);
        
        // Verificar que la URL sea válida
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("[MUSEMENT] ❌ URL inválida: {$url}");
            continue;
        }
        
        $vouchers[] = [
            'url' => $url,
            'activity_name' => $voucher_names[$index] ?? 'Voucher ' . ($index + 1)
        ];
    }
    
    if (empty($vouchers)) {
        error_log('[MUSEMENT] ❌ No hay vouchers válidos para fusionar');
        wp_send_json_error(['message' => 'No hay URLs de vouchers válidas']);
        return;
    }
    
    error_log('[MUSEMENT] Vouchers válidos para fusionar: ' . count($vouchers));
    
    // 🔥 Verificar si la clase existe
    if (!class_exists('Musement_PDF_Merger')) {
        error_log('[MUSEMENT] ❌ Clase Musement_PDF_Merger no encontrada');
        require_once MUSEMENT_INTEGRATION_DIR . 'includes/pdf-merger.php';
        
        if (!class_exists('Musement_PDF_Merger')) {
            error_log('[MUSEMENT] ❌ No se pudo cargar Musement_PDF_Merger');
            wp_send_json_error(['message' => 'Clase de fusión de PDF no disponible']);
            return;
        }
    }
    
    // 🔥 Fusionar vouchers
    try {
        $merge_result = Musement_PDF_Merger::merge_vouchers($vouchers, $order_uuid);
        
        if (!$merge_result) {
            error_log('[MUSEMENT] ❌ No se pudieron fusionar los vouchers');
            wp_send_json_error(['message' => 'Error al fusionar los vouchers']);
            return;
        }
        
        // Obtener URL pública del PDF fusionado
        $upload_dir = wp_upload_dir();
        $merged_path = $merge_result['path'];
        $merged_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $merged_path);
        
        // Asegurarse de que la URL sea accesible
        if (!file_exists($merged_path)) {
            error_log("[MUSEMENT] ❌ El archivo fusionado no existe: {$merged_path}");
            wp_send_json_error(['message' => 'El archivo fusionado no existe']);
            return;
        }
        
        error_log('[MUSEMENT] ✅ Fusión completada exitosamente');
        error_log('[MUSEMENT] 📄 URL: ' . $merged_url);
        error_log('[MUSEMENT] 📊 Vouchers: ' . $merge_result['count'] . ', Páginas: ' . $merge_result['pages']);
        
        // Guardar en base de datos para referencia
        update_post_meta($order_uuid, '_musement_merged_voucher', $merged_path);
        update_post_meta($order_uuid, '_musement_merged_voucher_url', $merged_url);
        update_post_meta($order_uuid, '_musement_merged_voucher_count', $merge_result['count']);
        
        $base64 = base64_encode(file_get_contents($merged_path));

        wp_send_json_success([
            'merged_pdf' => $merged_url,
            'merged_pdf_base64' => $base64,
            'merged_pdf_path' => $merged_path,
            'count' => $merge_result['count'],
            'pages' => $merge_result['pages'],
            'size' => filesize($merged_path)
        ]);
        
    } catch (Exception $e) {
        error_log('[MUSEMENT] ❌ Excepción en fusión: ' . $e->getMessage());
        error_log('[MUSEMENT] Traza: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => 'Error al fusionar: ' . $e->getMessage()]);
    }
}

/**
 * Endpoint para obtener el PDF fusionado en base64
 */
add_action('wp_ajax_musement_get_pdf_base64', 'musement_get_pdf_base64');
add_action('wp_ajax_nopriv_musement_get_pdf_base64', 'musement_get_pdf_base64');

function musement_get_pdf_base64() {
    check_ajax_referer('musement_ajax', 'nonce');
    
    $order_uuid = sanitize_text_field($_POST['order_uuid'] ?? '');
    
    if (!$order_uuid) {
        wp_send_json_error(['message' => 'Falta el UUID de la orden']);
        return;
    }
    
    // Obtener la ruta del PDF fusionado
    $merged_path = get_post_meta($order_uuid, '_musement_merged_voucher', true);
    
    // Si no existe, buscar en la sesión
    if (!$merged_path || !file_exists($merged_path)) {
        $merged_path = $_SESSION['musement_merged_voucher'] ?? null;
    }
    
    if (!$merged_path || !file_exists($merged_path)) {
        wp_send_json_error(['message' => 'No se encontró el PDF fusionado']);
        return;
    }
    
    $file_content = file_get_contents($merged_path);
    $base64 = base64_encode($file_content);
    
    wp_send_json_success([
        'filename' => basename($merged_path),
        'base64' => $base64,
        'size' => filesize($merged_path)
    ]);
}


function musement_participant_schema() {
    check_ajax_referer('musement_ajax', 'nonce');

    $uuid = isset($_POST['uuid'])
        ? sanitize_text_field(wp_unslash($_POST['uuid']))
        : '';

    if (!$uuid) {
        wp_send_json_error([
            'message' => 'Missing activity uuid.',
        ], 400);
    }

    $response = wp_remote_get(
        "https://sandbox.musement.com/api/v3/activities/{$uuid}/participants-info/schema",
        [
            'timeout' => 20,
            'headers' => [
                'Accept'                  => 'application/json',
                'X-Musement-Application'  => MUSEMENT_APPLICATION,
                'X-Musement-Version'      => '3.4.0',
                'Accept-Language'         => 'es-ES',
            ],
        ]
    );

    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => $response->get_error_message(),
        ], 500);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code >= 400) {
        wp_send_json_error([
            'code' => $code,
            'body' => json_decode($body, true),
        ], $code);
    }

    wp_send_json_success(json_decode($body, true));
}

add_action('wp_ajax_musement_participant_schema', 'musement_participant_schema');
add_action('wp_ajax_nopriv_musement_participant_schema', 'musement_participant_schema');

/**
 * Endpoint para enviar el PDF a un endpoint externo desde el backend
 */
add_action('wp_ajax_musement_send_pdf_to_endpoint', 'musement_send_pdf_to_endpoint');
add_action('wp_ajax_nopriv_musement_send_pdf_to_endpoint', 'musement_send_pdf_to_endpoint');

function musement_send_pdf_to_endpoint() {
    // Verificar nonce
    if (!check_ajax_referer('musement_ajax', 'nonce', false)) {
        wp_send_json_error(['message' => 'Nonce inválido']);
        return;
    }
    
    $order_uuid = sanitize_text_field($_POST['order_uuid'] ?? '');
    $endpoint_url = sanitize_text_field($_POST['endpoint_url'] ?? '');
    $payload_json = stripslashes($_POST['payload'] ?? '{}');
    $payload = json_decode($payload_json, true);

    error_log('[MUSEMENT] 📤 Enviando payload al endpoint');
    error_log('[MUSEMENT] 📤 Order UUID: ' . $order_uuid);
    error_log('[MUSEMENT] 📤 Endpoint: ' . $endpoint_url);

    if (!$order_uuid || !$endpoint_url) {
        wp_send_json_error(['message' => 'Faltan parámetros']);
        return;
    }

    if (!is_array($payload)) {
        error_log('[MUSEMENT] ❌ Payload inválido recibido: ' . $payload_json);
        wp_send_json_error(['message' => 'El payload recibido no es un JSON válido']);
        return;
    }

    // El PDF fusionado ya viaja embebido en base64 dentro de payload.documents.merged_voucher.base64,
    // por lo que se envía todo el payload como JSON puro (no como multipart/form-data).
    $result = musement_send_pdf_via_curl($endpoint_url, $order_uuid, $payload);
    
    if (is_wp_error($result)) {
        error_log('[MUSEMENT] ❌ Error enviando peticion a salesforce: ' . $result->get_error_message());
        wp_send_json_error([
            'message' => $result->get_error_message(),
            'debug' => $result->get_error_data()
        ]);
        return;
    }
    
    error_log('[MUSEMENT] ✅ PDF enviado correctamente');
    wp_send_json_success([
        'message' => 'PDF enviado correctamente',
        'response' => $result
    ]);
}

/**
 * Envía el payload (con el PDF fusionado ya embebido en base64) al endpoint de Salesforce
 * como JSON puro, que es lo que espera MusementBookingService.doPost().
 */
function musement_send_pdf_via_curl($endpoint_url, $order_uuid, $payload = []) {
    // Verificar que curl está disponible
    if (!function_exists('curl_init')) {
        return new WP_Error('curl_not_available', 'cURL no está disponible en el servidor');
    }

    $body = wp_json_encode($payload);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
        ]
    ]);

    // Log para depuración
    error_log('[MUSEMENT] Enviando payload JSON a: ' . $endpoint_url);
    error_log('[MUSEMENT] Order UUID: ' . $order_uuid);
    error_log('[MUSEMENT] Tamaño del payload: ' . strlen($body) . ' bytes');

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    if ($error) {
        error_log('[MUSEMENT] Error cURL: ' . $error);
        return new WP_Error('curl_error', 'Error en cURL: ' . $error);
    }
    
    error_log('[MUSEMENT] Respuesta del endpoint: ' . $response);
    error_log('[MUSEMENT] HTTP Code: ' . $http_code);
    
    if ($http_code < 200 || $http_code >= 300) {
        return new WP_Error('http_error', 'HTTP error: ' . $http_code . ' - ' . $response);
    }
    
    // Intentar parsear JSON
    $decoded = json_decode($response, true);
    return $decoded ? $decoded : $response;
}

/**
 * Obtener opciones de pickup para un producto
 */
function musement_get_pickup_options($uuid) {
    $token = musement_get_access_token();
    
    if (!$token) {
        return new WP_Error('no_token', 'No se pudo obtener el token de acceso');
    }
    
    $url = sprintf(
        'https://sandbox.musement.com/api/v3/activities/%s/pickups',
        rawurlencode($uuid)
    );
    
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept-Language' => 'es-ES',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ]
    ]);
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($data) || !is_array($data)) {
        return [];
    }
    
    return $data;
}

/**
 * Endpoint AJAX para obtener opciones de pickup
 */
add_action('wp_ajax_musement_get_pickup_options', 'musement_ajax_get_pickup_options');
add_action('wp_ajax_nopriv_musement_get_pickup_options', 'musement_ajax_get_pickup_options');

function musement_ajax_get_pickup_options() {
    check_ajax_referer('musement_ajax', 'nonce');
    
    $uuid = sanitize_text_field($_POST['uuid'] ?? '');
    
    if (!$uuid) {
        wp_send_json_error(['message' => 'Falta el UUID del producto']);
        return;
    }
    
    error_log('[MUSEMENT] 📍 Buscando pickups para: ' . $uuid);
    
    $pickups = musement_get_pickup_options($uuid);
    
    if (is_wp_error($pickups)) {
        error_log('[MUSEMENT] ❌ Error: ' . $pickups->get_error_message());
        wp_send_json_error(['message' => $pickups->get_error_message()]);
        return;
    }
    
    // Si no hay pickups, devolver array vacío
    if (empty($pickups) || !is_array($pickups)) {
        error_log('[MUSEMENT] ⚠️ No hay pickups disponibles');
        wp_send_json_success([
            'pickups' => [],
            'count' => 0
        ]);
        return;
    }
    
    // Formatear opciones de pickup - AHORA CON EL FORMATO CORRECTO
    $formatted_pickups = array_map(function($pickup) {
        // Los pickups vienen con 'uuid' en lugar de 'id'
        $id = $pickup['uuid'] ?? $pickup['id'] ?? uniqid('pickup_');
        
        // El nombre puede estar en 'name'
        $name = $pickup['name'] ?? $pickup['location_name'] ?? 'Punto de recogida';
        
        // El tipo puede ser HOTEL, AIRPORT, etc.
        $type = $pickup['type'] ?? 'OTHER';
        
        // Para dirección, podríamos usar el nombre + tipo
        $address = $pickup['address'] ?? $pickup['location'] ?? $pickup['full_address'] ?? '';
        
        // Si no hay dirección, construir una basada en el nombre y tipo
        if (empty($address)) {
            $address = $name . ' (' . $type . ')';
        }
        
        return [
            'id' => $id,
            'uuid' => $id,
            'name' => $name,
            'type' => $type,
            'address' => $address,
            'description' => $pickup['description'] ?? $type . ' pickup',
            'latitude' => $pickup['latitude'] ?? null,
            'longitude' => $pickup['longitude'] ?? null,
            'time' => $pickup['time'] ?? $pickup['pickup_time'] ?? null,
            'tags' => $pickup['tags'] ?? [],
            'notes' => $pickup['notes'] ?? ''
        ];
    }, $pickups);
    
    error_log('[MUSEMENT] ✅ ' . count($formatted_pickups) . ' pickups formateados');
    
    wp_send_json_success([
        'pickups' => $formatted_pickups,
        'count' => count($formatted_pickups)
    ]);
}


// En ajax.php - Agregar este endpoint

/**
 * Endpoint para obtener preguntas de un producto
 */
add_action('wp_ajax_musement_get_product_questions', 'musement_ajax_get_product_questions');
add_action('wp_ajax_nopriv_musement_get_product_questions', 'musement_ajax_get_product_questions');

/**
 * Endpoint para obtener preguntas de un producto
 */
add_action('wp_ajax_musement_get_product_questions', 'musement_ajax_get_product_questions');
add_action('wp_ajax_nopriv_musement_get_product_questions', 'musement_ajax_get_product_questions');

function musement_ajax_get_product_questions() {
    check_ajax_referer('musement_ajax', 'nonce');
    
    $uuid = sanitize_text_field($_POST['uuid'] ?? '');
    
    if (!$uuid) {
        wp_send_json_error(['message' => 'Falta el UUID del producto']);
        return;
    }
    
    $product = musement_remote_get_product($uuid);
    
    if (is_wp_error($product)) {
        wp_send_json_error(['message' => $product->get_error_message()]);
        return;
    }
    
    $questions = [];
    
    // 🔥 Verificar si el producto tiene preguntas en la respuesta
    if (!empty($product['questions']) && is_array($product['questions'])) {
        foreach ($product['questions'] as $question) {
            $questions[] = [
                'id' => $question['id'] ?? uniqid('q_'),
                'code' => $question['code'] ?? '',
                'label' => $question['label'] ?? $question['question'] ?? 'Pregunta',
                'type' => $question['type'] ?? 'text',
                'required' => $question['required'] ?? false,
                'options' => $question['options'] ?? [],
                'placeholder' => $question['placeholder'] ?? ''
            ];
        }
    }
    
    // 🔥 Si no hay preguntas pero el producto requiere información del pasajero
    if (empty($questions) && !empty($product['has_passenger_info'])) {
        error_log('[MUSEMENT] Generando preguntas para producto con has_passenger_info: ' . $uuid);
        
        // Generar preguntas basadas en el tipo de producto
        $questions = [
            [
                'id' => 'full_name',
                'code' => 'full_name',
                'label' => 'Nombre completo del participante',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Ej: Juan Pérez García'
            ],
            [
                'id' => 'date_of_birth',
                'code' => 'date_of_birth',
                'label' => 'Fecha de nacimiento',
                'type' => 'date',
                'required' => true,
                'placeholder' => 'DD/MM/YYYY'
            ],
            [
                'id' => 'nationality',
                'code' => 'nationality',
                'label' => 'Nacionalidad',
                'type' => 'select',
                'required' => true,
                'options' => ['Española', 'Francesa', 'Italiana', 'Alemana', 'Británica', 'Estadounidense', 'Griega', 'Otra'],
                'placeholder' => 'Selecciona tu nacionalidad'
            ],
            [
                'id' => 'passport',
                'code' => 'passport_number',
                'label' => 'Número de pasaporte o DNI',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Ej: AB1234567'
            ],
            [
                'id' => 'email',
                'code' => 'email',
                'label' => 'Correo electrónico (para enviar el audio tour)',
                'type' => 'email',
                'required' => true,
                'placeholder' => 'ejemplo@correo.com'
            ]
        ];
    }
    
    // 🔥 Si el producto tiene servicio de audioguía, añadir pregunta relacionada
    if (!empty($product['services'])) {
        foreach ($product['services'] as $service) {
            if (($service['code'] ?? '') === 'audioguide') {
                $questions[] = [
                    'id' => 'audioguide_language',
                    'code' => 'audioguide_language',
                    'label' => 'Idioma para la audioguía',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Español', 'Inglés', 'Francés', 'Alemán', 'Italiano'],
                    'placeholder' => 'Selecciona el idioma de la audioguía'
                ];
                break;
            }
        }
    }
    
    // 🔥 Si aún no hay preguntas, usar preguntas por defecto
    if (empty($questions)) {
        $questions = [
            [
                'id' => 'full_name',
                'code' => 'full_name',
                'label' => 'Nombre completo',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Nombre y apellidos'
            ],
            [
                'id' => 'date_of_birth',
                'code' => 'date_of_birth',
                'label' => 'Fecha de nacimiento',
                'type' => 'date',
                'required' => true,
                'placeholder' => 'DD/MM/YYYY'
            ]
        ];
    }
    
    wp_send_json_success([
        'questions' => $questions,
        'count' => count($questions),
        'product_name' => $product['title'] ?? '',
        'has_passenger_info' => $product['has_passenger_info'] ?? false
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