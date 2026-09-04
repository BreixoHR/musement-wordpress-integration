<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_salesforce_endpoint(): string
{
    if (defined('MUSEMENT_SALESFORCE_ENDPOINT')) {
        return MUSEMENT_SALESFORCE_ENDPOINT;
    }

    return (string) get_option('musement_salesforce_endpoint', '');
}

function musement_salesforce_headers(): array
{
    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    if (defined('MUSEMENT_SALESFORCE_API_KEY')) {
        $headers['Authorization'] = 'Bearer ' . MUSEMENT_SALESFORCE_API_KEY;
    }

    return $headers;
}

function musement_salesforce_order_payload(array $order): array
{
    return [
        'source' => [
            'site_url' => home_url('/'),
            'plugin_version' => MUSEMENT_INTEGRATION_VERSION,
        ],
        'order' => [
            'reference' => $order['order_reference'] ?? '',
            'status' => $order['status'] ?? '',
            'total' => (float) ($order['total'] ?? 0),
            'currency' => $order['currency'] ?? 'EUR',
        ],
        'customer' => $order['customer'] ?? [],
        'items' => array_map(function ($item) {
            return [
                'activity_uuid' => $item['activity_uuid'] ?? '',
                'title' => $item['title'] ?? '',
                'type' => 'musement',
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
        'payment' => [
            'status' => 'paid',
            'reference' => $order['payment_reference'] ?? '',
            'amount' => (float) ($order['total'] ?? 0),
            'currency' => $order['currency'] ?? 'EUR',
        ],
    ];
}

function musement_salesforce_notify_order(array $order)
{
    $endpoint = musement_salesforce_endpoint();

    if (!$endpoint) {
        return new WP_Error(
            'musement_salesforce_endpoint_missing',
            __('Falta configurar el endpoint de Salesforce.', 'musement-integration')
        );
    }

    $response = wp_remote_post($endpoint, [
        'timeout' => 25,
        'headers' => musement_salesforce_headers(),
        'body' => wp_json_encode(musement_salesforce_order_payload($order)),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error(
            'musement_salesforce_error',
            __('Salesforce no ha aceptado el pedido.', 'musement-integration'),
            [
                'status' => $status_code,
                'body' => $body,
            ]
        );
    }

    return is_array($body) ? $body : [];
}

function musement_salesforce_reference(array $response): string
{
    return sanitize_text_field(
        $response['salesforce_order_id']
        ?? $response['order_id']
        ?? $response['id']
        ?? $response['reference']
        ?? ''
    );
}
