<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'musement_payment_register_rest_routes');

function musement_payment_register_rest_routes(): void
{
    register_rest_route('musement/v1', '/payment-webhook/(?P<gateway>[a-zA-Z0-9_-]+)', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'musement_payment_webhook_rest',
        'permission_callback' => '__return_true',
        'args' => [
            'gateway' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
    ]);

    register_rest_route('musement/v1', '/payment-return/(?P<gateway>[a-zA-Z0-9_-]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'musement_payment_return_rest',
        'permission_callback' => '__return_true',
        'args' => [
            'gateway' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
    ]);
}

function musement_payment_webhook_payload(WP_REST_Request $request): array
{
    $body = trim($request->get_body());

    if ($body !== '') {
        $json = json_decode($body, true);

        if (is_array($json)) {
            return $json;
        }
    }

    return $request->get_params();
}

function musement_payment_webhook_rest(WP_REST_Request $request)
{
    $gateway = sanitize_key((string) $request['gateway']);
    $payload = musement_payment_webhook_payload($request);

    $is_valid = (bool) apply_filters(
        'musement_payment_webhook_is_valid',
        false,
        $request,
        $payload,
        $gateway
    );

    if (!$is_valid) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Webhook de pago no validado. Falta implementar la verificacion de firma de la pasarela.', 'musement-integration'),
            'gateway' => $gateway,
        ], 401);
    }

    $event = apply_filters(
        'musement_payment_webhook_event',
        null,
        $request,
        $payload,
        $gateway
    );

    if (!is_array($event)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Webhook valido, pero no se pudo normalizar el evento de pago.', 'musement-integration'),
            'gateway' => $gateway,
        ], 422);
    }

    $event = wp_parse_args($event, [
        'paid' => false,
        'order_reference' => '',
        'payment_reference' => '',
        'event_reference' => '',
    ]);

    if (!$event['paid']) {
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Evento recibido sin pago confirmado.', 'musement-integration'),
            'gateway' => $gateway,
            'event_reference' => sanitize_text_field($event['event_reference']),
        ], 202);
    }

    $order_reference = sanitize_text_field($event['order_reference']);
    $payment_reference = sanitize_text_field($event['payment_reference'] ?: $event['event_reference']);

    if (!$order_reference || !$payment_reference) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Falta order_reference o payment_reference en el evento de pago.', 'musement-integration'),
            'gateway' => $gateway,
        ], 422);
    }

    $result = musement_payment_complete_order_by_reference($order_reference, $payment_reference);

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result->get_error_message(),
            'gateway' => $gateway,
            'order_reference' => $order_reference,
            'data' => $result->get_error_data(),
        ], 502);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Pago confirmado y pedido sincronizado.', 'musement-integration'),
        'gateway' => $gateway,
        'order_reference' => $result['order_reference'],
        'status' => $result['status'],
        'salesforce_order_id' => $result['salesforce_order_id'] ?? '',
        'musement_booking_reference' => $result['musement_booking_reference'] ?? '',
    ], 200);
}

function musement_payment_return_rest(WP_REST_Request $request)
{
    $gateway = sanitize_key((string) $request['gateway']);
    $order_reference = sanitize_text_field((string) $request->get_param('order_reference'));
    $status = sanitize_text_field((string) $request->get_param('status'));

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Retorno de pasarela recibido. La confirmacion real debe llegar por webhook.', 'musement-integration'),
        'gateway' => $gateway,
        'order_reference' => $order_reference,
        'status' => $status,
    ], 200);
}
