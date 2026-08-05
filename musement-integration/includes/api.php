<?php
/**
 * Musement API client helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Candado real (a nivel de servidor MySQL, no de PHP) para evitar que dos
 * peticiones concurrentes para la misma clave (p.ej. mismo cart_uuid, o mismo
 * "añadir al carrito") se ejecuten en paralelo. Un transient normal
 * (get_transient/set_transient) no es atómico: si dos peticiones llegan casi
 * al mismo tiempo en procesos PHP distintos, ambas pueden leer "no hay
 * candado" antes de que ninguna escriba el suyo. GET_LOCK lo gestiona el
 * propio servidor MySQL, así que sí es atómico entre procesos.
 */
function musement_order_lock_acquire(string $lock_name, int $timeout = 0): bool {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, $timeout)) === 1;
}

function musement_order_lock_release(string $lock_name): void {
    global $wpdb;
    $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
}

function musement_integration_api_request(string $endpoint, array $query_args = [])
{
    $base_url = 'https://api.musement.com/api/v3/';
    $url = add_query_arg($query_args, trailingslashit($base_url) . ltrim($endpoint, '/'));

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error(
            'musement_api_error',
            __('Musement API request failed.', 'musement-integration'),
            [
                'status' => $status_code,
                'body' => $data,
            ]
        );
    }

    return $data;
}

function musement_remote_get_activity_tickets(string $order_uuid, string $activity_uuid) {
    $token = musement_get_access_token();
    if (!$token || !$order_uuid || !$activity_uuid) {
        return new WP_Error('musement_missing_data', 'Missing token, order uuid or activity uuid.');
    }

    $endpoint = sprintf('https://sandbox.musement.com/api/v3/orders/%s/activities/%s/tickets', rawurlencode($order_uuid), rawurlencode($activity_uuid));
    $res = wp_remote_get($endpoint, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
            'Accept-Language' => 'es-ES',
        ],
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('musement_tickets_failed', 'List tickets failed', ['status' => $status, 'body' => $body]);
    }

    return is_array($body) ? $body : [];
}


function musement_remote_download_activity_voucher(string $order_uuid, string $activity_uuid) {
    $token = musement_get_access_token();
    if (!$token || !$order_uuid || !$activity_uuid) {
        return new WP_Error('musement_missing_data', 'Missing token, order uuid or activity uuid.');
    }

    $endpoint = sprintf('https://sandbox.musement.com/api/v3/orders/%s/activities/%s/voucher', rawurlencode($order_uuid), rawurlencode($activity_uuid));
    $res = wp_remote_get($endpoint, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
        ],
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $content_type = wp_remote_retrieve_header($res, 'content-type');

    if ($status !== 200 || stripos($content_type ?? '', 'application/pdf') === false) {
        return new WP_Error('musement_voucher_unavailable', 'Voucher not available or invalid content-type', ['status' => $status, 'headers' => wp_remote_retrieve_headers($res), 'body' => wp_remote_retrieve_body($res)]);
    }

    $body = wp_remote_retrieve_body($res);

    $uploads = wp_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . 'musement_vouchers';
    if (!wp_mkdir_p($dir)) {
        return new WP_Error('musement_save_dir_failed', 'Could not create directory: ' . $dir);
    }

    $filename = 'voucher-' . sanitize_file_name($order_uuid) . '-' . sanitize_file_name($activity_uuid) . '.pdf';
    $path = $dir . '/' . $filename;

    if (false === file_put_contents($path, $body)) {
        return new WP_Error('musement_save_failed', 'Could not write PDF to disk.');
    }

    return [
        'path' => $path,
        'url'  => trailingslashit($uploads['baseurl']) . 'musement_vouchers/' . $filename,
    ];
}

function musement_remote_mark_order_paid(string $order_uuid) {
    $token = musement_get_access_token();
    if (!$token || !$order_uuid) {
        return new WP_Error('musement_missing_data', 'Missing token or order uuid.');
    }

    $endpoint = 'https://sandbox.musement.com/api/v3/payments/no/payment';
    $body = ['uuid' => $order_uuid];

    $res = wp_remote_post($endpoint, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $data = json_decode(wp_remote_retrieve_body($res), true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('musement_payment_mark_failed', 'Musement mark-paid failed', ['status' => $status, 'body' => $data]);
    }

    return is_array($data) ? $data : [];
}

function musement_get_activities($lat = 37.1773, $lng = -3.5986, $limit = 6): array
{
    $lat = (float) $lat;
    $lng = (float) $lng;
    $limit = max(1, min(12, (int) $limit));

    $url = add_query_arg([
            'lat' => $lat,
            'lng' => $lng,
            'limit' => $limit,
    ], 'https://sandbox.musement.com/api/v3/activities');


    $token = musement_get_access_token();
    
    $res = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Accept-Language' => 'es-ES',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);

    if (is_wp_error($res)) {
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);

    return is_array($body['data'] ?? null) ? $body['data'] : [];
}

function musement_get_activities_with_prices($lat = 37.1773, $lng = -3.5986, $limit = 6): array
{
    $activities = musement_get_activities($lat, $lng, $limit);

    $prices = get_option('musement_product_prices', []);

    $items = [];

    foreach ($activities as $activity) {

        $uuid = $activity['uuid'] ?? '';

        $custom_price = $prices[$uuid] ?? null;

        $items[] = [
            'uuid' => $uuid,
            'title' => $activity['title'] ?? '',
            'description' => $activity['description'] ?? '',
            'image' => $activity['cover_image_url'] ?? '',
            'price' => $custom_price ?? ($activity['retail_price']['value'] ?? null),
            'price_formatted' => $custom_price ? $custom_price . ' €' : ($activity['retail_price']['formatted_value'] ?? ''),
        ];
    }

    return $items;
}


function musement_sync_products($lat = 37.1773, $lng = -3.5986, $limit = 12)
{
    // Igual que en musement_activities() (ajax.php): si hay una ciudad
    // seleccionada en ajustes, se respeta y se busca por ciudad. Antes esta
    // función ignoraba siempre la ciudad guardada y buscaba por lat/lng fijas,
    // así que elegir ciudad y sincronizar no cambiaba nada.
    $city_id = (int) get_option('musement_city_id', 0);

    $activities = $city_id
        ? musement_get_activities_by_city($city_id, $limit)
        : musement_get_activities($lat, $lng, $limit);

    $stored = [];

    foreach ($activities as $a) {
        $stored[$a['uuid']] = [
            'uuid' => $a['uuid'],
            'title' => $a['title'] ?? '',
            'image' => $a['cover_image_url'] ?? '',
        ];
    }

    update_option('musement_products_cache', $stored);

    return $stored;
}


function musement_get_admin_products($lat = 37.1773, $lng = -3.5986, $limit = 12)
{
    $activities = musement_get_activities($lat, $lng, $limit);

    $prices = get_option('musement_product_prices', []);

    $products = [];

    foreach ($activities as $a) {

        $uuid = $a['uuid'] ?? '';

        $products[$uuid] = [
            'uuid' => $uuid,
            'title' => $a['title'] ?? '',
            'image' => $a['cover_image_url'] ?? '',

            'price_original' => $a['retail_price']['value'] ?? null,

            'price_custom' => $prices[$uuid] ?? null,
        ];
    }

    return $products;
}

function musement_get_access_token() {

    $cached = get_transient('musement_access_token');
    if ($cached) {
        return $cached;
    }

    $response = wp_remote_post('https://sandbox.musement.com/api/v3/login', [
        'headers' => [
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'client_id' => MUSEMENT_CLIENT_ID,
            'client_secret' => MUSEMENT_CLIENT_SECRET,
            'grant_type' => 'client_credentials'
        ])
    ]);

    // Esta función es la única de todo el plugin que no dejaba rastro en el log
    // cuando fallaba -- cualquier fallo de login se traducía en un 401 "malformed
    // request" varias capas más arriba (ciudades vacías, catálogo vacío...) sin
    // ninguna pista de la causa real. Se loguea el status y el cuerpo tal cual
    // los devuelve Musement para poder diagnosticar credenciales/red.
    if (is_wp_error($response)) {
        error_log('[MUSEMENT] ❌ Login fallido (WP_Error): ' . $response->get_error_message());
        return null;
    }

    $status = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $body = json_decode($raw_body, true);

    if ($status < 200 || $status >= 300) {
        error_log('[MUSEMENT] ❌ Login fallido. HTTP ' . $status . '. Respuesta: ' . $raw_body);
        return null;
    }

    if (empty($body['access_token'])) {
        error_log('[MUSEMENT] ❌ Login sin access_token en la respuesta. HTTP ' . $status . '. Respuesta: ' . $raw_body);
        return null;
    }

    // cache 50 min (tokens suelen durar 1h)
    set_transient('musement_access_token', $body['access_token'], 50 * MINUTE_IN_SECONDS);

    return $body['access_token'];
}

/**
 * Obtiene los detalles completos de una orden por su UUID
 * 
 * @param string $order_uuid El UUID de la orden en Musement
 * @return array|WP_Error Los datos de la orden o un error
 */
function musement_remote_get_order(string $order_uuid) {
    $token = musement_get_access_token();

    if (!$token || !$order_uuid) {
        return new WP_Error('musement_missing_data', 'Falta el token o el UUID de la orden.');
    }

    $endpoint = sprintf('https://sandbox.musement.com/api/v3/orders/%s', $order_uuid);

    $res = wp_remote_get($endpoint, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
        ],
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error(
            'musement_get_order_failed',
            'Error al obtener la orden',
            ['status' => $status, 'body' => $body]
        );
    }

    return is_array($body) ? $body : [];
}

/**
 * Extrae las URLs de los vouchers de una orden
 * 
 * @param array $order_data Los datos de la orden obtenidos de la API
 * @return array Lista de vouchers con actividad, URL y estado
 */
function musement_extract_vouchers_from_order(array $order_data): array {
    $vouchers = [];
    
    if (empty($order_data['items']) || !is_array($order_data['items'])) {
        return $vouchers;
    }
    
    foreach ($order_data['items'] as $item) {
        $activity_name = $item['product']['name'] ?? $item['name'] ?? 'Actividad';
        $item_status = $item['status'] ?? 'unknown';
        
        if (!empty($item['vouchers']) && is_array($item['vouchers'])) {
            foreach ($item['vouchers'] as $voucher) {
                $vouchers[] = [
                    'activity_name' => $activity_name,
                    'url' => $voucher['url'] ?? '',
                    'status' => $item_status,
                    'available' => ($item_status === 'OK')
                ];
            }
        }
    }
    
    return $vouchers;
}



/**
 * Descarga un ticket PDF específico
 * 
 * @param string $order_uuid El UUID de la orden
 * @param string $activity_uuid El UUID de la actividad
 * @param string $ticket_id El ID del ticket
 * @return array|WP_Error Información del archivo o error
 */
function musement_remote_download_ticket_pdf($order_uuid, $activity_uuid, $ticket_id) {
    $token = musement_get_access_token();
    
    if (!$token || !$order_uuid || !$activity_uuid || !$ticket_id) {
        return new WP_Error('musement_missing_data', 'Faltan parámetros requeridos.');
    }

    $endpoint = sprintf(
        'https://sandbox.musement.com/api/v3/orders/%s/activities/%s/tickets/%s',
        rawurlencode($order_uuid),
        rawurlencode($activity_uuid),
        rawurlencode($ticket_id)
    );
    
    $res = wp_remote_get($endpoint, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Version' => '3.4.0',
        ],
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $status = wp_remote_retrieve_response_code($res);
    $content_type = wp_remote_retrieve_header($res, 'content-type');

    if ($status !== 200 || stripos($content_type ?? '', 'application/pdf') === false) {
        return new WP_Error(
            'musement_ticket_download_failed',
            'No se pudo descargar el ticket PDF',
            ['status' => $status, 'content_type' => $content_type]
        );
    }

    $body = wp_remote_retrieve_body($res);
    $uploads_dir = wp_upload_dir();
    $ticket_dir = $uploads_dir['basedir'] . '/musement-tickets/';

    if (!file_exists($ticket_dir)) {
        wp_mkdir_p($ticket_dir);
    }

    $filename = sprintf('ticket-%s-%s-%s.pdf', 
        sanitize_file_name($order_uuid),
        sanitize_file_name($activity_uuid),
        sanitize_file_name($ticket_id)
    );
    $filepath = $ticket_dir . $filename;

    if (file_put_contents($filepath, $body) === false) {
        return new WP_Error('musement_ticket_save_failed', 'No se pudo guardar el archivo PDF.');
    }

    return [
        'path' => $filepath,
        'url' => str_replace($uploads_dir['basedir'], $uploads_dir['baseurl'], $filepath),
        'filename' => $filename
    ];
}