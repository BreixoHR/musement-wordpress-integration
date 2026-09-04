<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('musement_payment_create_session', 'musement_hosted_payment_create_session', 10, 4);
add_filter('musement_payment_webhook_is_valid', 'musement_hosted_payment_webhook_is_valid', 10, 4);
add_filter('musement_payment_webhook_event', 'musement_hosted_payment_webhook_event', 10, 4);

function musement_hosted_payment_endpoint(): string
{
    if (defined('MUSEMENT_PAYMENT_CREATE_ENDPOINT')) {
        return MUSEMENT_PAYMENT_CREATE_ENDPOINT;
    }

    return (string) get_option('musement_payment_create_endpoint', '');
}

function musement_hosted_payment_api_key(): string
{
    if (defined('MUSEMENT_PAYMENT_API_KEY')) {
        return MUSEMENT_PAYMENT_API_KEY;
    }

    return (string) get_option('musement_payment_api_key', '');
}

function musement_hosted_payment_webhook_secret(): string
{
    if (defined('MUSEMENT_PAYMENT_WEBHOOK_SECRET')) {
        return MUSEMENT_PAYMENT_WEBHOOK_SECRET;
    }

    return (string) get_option('musement_payment_webhook_secret', '');
}

function musement_hosted_payment_headers(): array
{
    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    $api_key = musement_hosted_payment_api_key();

    if ($api_key) {
        $headers['Authorization'] = 'Bearer ' . $api_key;
    }

    return $headers;
}

function musement_hosted_payment_payload(array $order, array $context): array
{
    return [
        'source' => [
            'site_url' => home_url('/'),
            'plugin_version' => MUSEMENT_INTEGRATION_VERSION,
        ],
        'order' => [
            'reference' => $order['order_reference'] ?? '',
            'total' => (float) ($order['total'] ?? 0),
            'currency' => $order['currency'] ?? 'EUR',
        ],
        'buyer' => $order['customer'] ?? [],
        'items' => array_map(function ($item) {
            return [
                'activity_uuid' => $item['activity_uuid'] ?? '',
                'title' => $item['title'] ?? '',
                'product_identifier' => $item['product_identifier'] ?? '',
                'product_name' => $item['product_name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'currency' => $item['currency'] ?? 'EUR',
                'date' => $item['date'] ?? '',
                'time' => $item['time'] ?? '',
                'language' => $item['language'] ?? '',
                'pickup' => $item['pickup'] ?? '',
            ];
        }, $order['items'] ?? []),
        'payment_methods' => [
            'card',
            'stripe',
        ],
        'selected_payment_method' => sanitize_key($order['selected_payment_method'] ?? 'stripe'),
        'urls' => [
            'success' => $context['success_url'] ?? '',
            'cancel' => $context['cancel_url'] ?? '',
            'return' => $context['return_url'] ?? '',
            'webhook' => $context['webhook_url'] ?? '',
        ],
    ];
}

function musement_hosted_payment_create_session($payment, array $order, string $gateway, array $context)
{
    if ($payment !== null || $gateway !== 'hosted') {
        return $payment;
    }

    $endpoint = musement_hosted_payment_endpoint();

    if (!$endpoint) {
        return null;
    }

    $response = wp_remote_post($endpoint, [
        'timeout' => 25,
        'headers' => musement_hosted_payment_headers(),
        'body' => wp_json_encode(musement_hosted_payment_payload($order, $context)),
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        return null;
    }

    $payment_url = $body['payment_url'] ?? $body['checkout_url'] ?? $body['redirect_url'] ?? '';

    if (!$payment_url) {
        return null;
    }

    return [
        'gateway' => 'hosted',
        'payment_reference' => sanitize_text_field($body['payment_reference'] ?? $body['session_id'] ?? ''),
        'payment_url' => esc_url_raw($payment_url),
        'status' => sanitize_text_field($body['status'] ?? 'pending_payment'),
    ];
}

function musement_hosted_payment_webhook_is_valid($is_valid, WP_REST_Request $request, array $payload, string $gateway): bool
{
    if ($is_valid || $gateway !== 'hosted') {
        return (bool) $is_valid;
    }

    $secret = musement_hosted_payment_webhook_secret();

    if (!$secret) {
        return false;
    }

    $sent_secret = (string) $request->get_header('x-musement-webhook-secret');

    if ($sent_secret && hash_equals($secret, $sent_secret)) {
        return true;
    }

    $signature = (string) $request->get_header('x-musement-signature');

    if (!$signature) {
        return false;
    }

    $expected = hash_hmac('sha256', $request->get_body(), $secret);

    return hash_equals($expected, $signature);
}

function musement_hosted_payment_webhook_event($event, WP_REST_Request $request, array $payload, string $gateway)
{
    if ($event !== null || $gateway !== 'hosted') {
        return $event;
    }

    $status = strtolower((string) ($payload['status'] ?? $payload['payment_status'] ?? ''));
    $paid = in_array($status, ['paid', 'succeeded', 'success', 'completed'], true);

    return [
        'paid' => $paid,
        'order_reference' => sanitize_text_field($payload['order_reference'] ?? $payload['order']['reference'] ?? ''),
        'payment_reference' => sanitize_text_field($payload['payment_reference'] ?? $payload['transaction_id'] ?? $payload['session_id'] ?? ''),
        'event_reference' => sanitize_text_field($payload['event_id'] ?? $payload['id'] ?? ''),
    ];
}
