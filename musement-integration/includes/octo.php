<?php
/**
 * Funciones para el estándar OCTO y notificaciones a Ventrata/Salesforce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Envía el payload OCTO a Ventrata/Salesforce.
 *
 * Credentials are read from constants (set in wp-config.php) with a DB fallback.
 */
function musement_send_octo_to_ventrata($payload) {
    $endpoint = defined('MUSEMENT_VENTRATA_ENDPOINT')
        ? MUSEMENT_VENTRATA_ENDPOINT
        : (string) get_option('musement_ventrata_endpoint', '');

    $api_key = defined('MUSEMENT_VENTRATA_API_KEY')
        ? MUSEMENT_VENTRATA_API_KEY
        : (string) get_option('musement_ventrata_api_key', '');

    if (!$endpoint || !$api_key) {
        error_log('[OCTO] Configuración incompleta: endpoint o API key faltante');
        return false;
    }

    $response = wp_remote_post($endpoint, [
        'timeout' => 15,
        'headers' => [
            'Authorization'     => 'Bearer ' . $api_key,
            'Content-Type'      => 'application/json',
            'Octo-Capabilities' => 'octo/cart,octo/questions,octo/extras,ventrata/dashboard',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        error_log('[OCTO] Error: ' . $response->get_error_message());
        return false;
    }

    $status = wp_remote_retrieve_response_code($response);
    error_log('[OCTO] Notificación enviada. Status: ' . $status);

    return $status >= 200 && $status < 300;
}

/**
 * Endpoint AJAX para enviar OCTO después de tener vouchers.
 */
add_action('wp_ajax_musement_send_octo_with_vouchers', 'musement_send_octo_with_vouchers');
add_action('wp_ajax_nopriv_musement_send_octo_with_vouchers', 'musement_send_octo_with_vouchers');

function musement_send_octo_with_vouchers() {
    check_ajax_referer('musement_ajax', 'nonce');

    $order_uuid    = sanitize_text_field($_POST['order_uuid'] ?? '');
    $customer_data = [
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name'  => sanitize_text_field($_POST['last_name']  ?? ''),
        'email'      => sanitize_email($_POST['email']           ?? ''),
        'phone'      => sanitize_text_field($_POST['phone']      ?? ''),
    ];

    if (!$order_uuid) {
        wp_send_json_error(['message' => 'Falta el UUID de la orden']);
        return;
    }

    // Obtener la orden actualizada de Musement.
    $order = musement_remote_get_order($order_uuid);

    if (is_wp_error($order)) {
        wp_send_json_error(['message' => $order->get_error_message()]);
        return;
    }

    // Construir payload OCTO.
    $octo_payload = function_exists('musement_build_octo_payload')
        ? musement_build_octo_payload($order, $customer_data)
        : [
            'order_uuid' => $order_uuid,
            'customer'   => $customer_data,
            'status'     => 'CONFIRMED',
        ];

    // Enviar a Ventrata/Salesforce.
    $sent = musement_send_octo_to_ventrata($octo_payload);

    wp_send_json_success([
        'sent'    => $sent,
        'message' => $sent ? 'Payload enviado correctamente' : 'Error al enviar',
    ]);
}