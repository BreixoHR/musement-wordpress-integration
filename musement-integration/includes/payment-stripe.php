<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_stripe_publishable_key(): string
{
    if (defined('MUSEMENT_STRIPE_PUBLISHABLE_KEY')) {
        return (string) MUSEMENT_STRIPE_PUBLISHABLE_KEY;
    }

    return (string) get_option('musement_stripe_publishable_key', '');
}

function musement_stripe_secret_key(): string
{
    if (defined('MUSEMENT_STRIPE_SECRET_KEY')) {
        return (string) MUSEMENT_STRIPE_SECRET_KEY;
    }

    return (string) get_option('musement_stripe_secret_key', '');
}

function musement_stripe_webhook_secret(): string
{
    if (defined('MUSEMENT_STRIPE_WEBHOOK_SECRET')) {
        return (string) MUSEMENT_STRIPE_WEBHOOK_SECRET;
    }

    return (string) get_option('musement_stripe_webhook_secret', '');
}

function musement_stripe_is_configured(): bool
{
    return musement_stripe_publishable_key() !== ''
        && musement_stripe_secret_key() !== '';
}


add_filter('musement_payment_create_session', 'musement_stripe_payment_create_session', 10, 4);

function musement_stripe_payment_create_session($payment, array $order, string $gateway, array $context)
{
    if ($payment !== null || $gateway !== 'stripe') {
        return $payment;
    }

    if (!musement_stripe_is_configured()) {
        return new WP_Error(
            'musement_stripe_not_configured',
            __('Faltan las claves publica o secreta de Stripe.', 'musement-integration'),
            [
                'gateway' => 'stripe',
            ]
        );
    }

    return musement_stripe_create_payment_intent($order, $context);
}


function musement_stripe_create_payment_intent(array $order, array $context)
{
    $secret_key = musement_stripe_secret_key();

    if ($secret_key === '') {
        return new WP_Error(
            'musement_stripe_missing_secret_key',
            __('Falta configurar la Secret Key de Stripe.', 'musement-integration')
        );
    }

    $order_reference = sanitize_text_field($order['order_reference'] ?? '');
    $currency = strtolower(sanitize_text_field($order['currency'] ?? 'EUR'));
    $amount = (int) round(((float) ($order['total'] ?? 0)) * 100);

    if ($order_reference === '') {
        return new WP_Error(
            'musement_stripe_missing_order_reference',
            __('El pedido no tiene referencia para Stripe.', 'musement-integration')
        );
    }

    if ($amount <= 0) {
        return new WP_Error(
            'musement_stripe_invalid_amount',
            __('El importe del pedido no es valido para Stripe.', 'musement-integration')
        );
    }

    $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'automatic_payment_methods[enabled]' => 'true',
        'metadata[order_reference]' => $order_reference,
        'metadata[source]' => 'musement-integration',
        'description' => sprintf('Musement order %s', $order_reference),
    ];

    if (!empty($customer['email'])) {
        $payload['receipt_email'] = sanitize_email($customer['email']);
    }

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'timeout' => 25,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
        ],
        'body' => $payload,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        return new WP_Error(
            'musement_stripe_payment_intent_failed',
            __('Stripe no pudo crear el PaymentIntent.', 'musement-integration'),
            [
                'status_code' => $status_code,
                'stripe_error' => is_array($body['error'] ?? null) ? $body['error'] : null,
            ]
        );
    }

    if (empty($body['id']) || empty($body['client_secret'])) {
        return new WP_Error(
            'musement_stripe_payment_intent_incomplete',
            __('Stripe creo una respuesta incompleta para el pago.', 'musement-integration'),
            [
                'status_code' => $status_code,
                'stripe_response' => $body,
            ]
        );
    }

    return [
        'gateway' => 'stripe',
        'payment_reference' => sanitize_text_field($body['id']),
        'client_secret' => sanitize_text_field($body['client_secret']),
        'status' => sanitize_text_field($body['status'] ?? 'requires_payment_method'),
    ];
}
