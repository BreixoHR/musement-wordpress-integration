<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_sales_endpoint(): string
{
    if (defined('MUSEMENT_SALES_ENDPOINT')) {
        return MUSEMENT_SALES_ENDPOINT;
    }

    return (string) get_option('musement_sales_endpoint', '');
}

function musement_sales_headers(): array
{
    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Accept-Language' => 'es-ES',
        'X-Musement-Currency' => 'EUR',
        'X-Musement-Market' => 'es',
        'X-Musement-Version' => '3.4.0',
    ];

    if (defined('MUSEMENT_APPLICATION')) {
        $headers['X-Musement-Application'] = MUSEMENT_APPLICATION;
    }

    if (defined('MUSEMENT_API_KEY')) {
        $headers['Authorization'] = 'Bearer ' . MUSEMENT_API_KEY;
    }

    return $headers;
}

function musement_sales_booking_payload(array $order): array
{
    return [
        'merchant_order_reference' => $order['order_reference'] ?? '',
        'customer' => $order['customer'] ?? [],
        'items' => array_map(function ($item) {
            return [
                'type' => 'musement',
                'product_identifier' => $item['product_identifier'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
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

function musement_sales_create_booking(array $order)
{
    $endpoint = musement_sales_endpoint();

    if (!$endpoint) {
        return new WP_Error(
            'musement_sales_endpoint_missing',
            __('Falta configurar el endpoint de Musement/Sales.', 'musement-integration')
        );
    }

    $response = wp_remote_post($endpoint, [
        'timeout' => 25,
        'headers' => musement_sales_headers(),
        'body' => wp_json_encode(musement_sales_booking_payload($order)),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error(
            'musement_sales_error',
            __('Musement/Sales no ha confirmado la reserva.', 'musement-integration'),
            [
                'status' => $status_code,
                'body' => $body,
            ]
        );
    }

    return is_array($body) ? $body : [];
}

function musement_sales_reference(array $response): string
{
    return sanitize_text_field(
        $response['booking_reference']
        ?? $response['identifier']
        ?? $response['order_identifier']
        ?? $response['uuid']
        ?? ''
    );
}
