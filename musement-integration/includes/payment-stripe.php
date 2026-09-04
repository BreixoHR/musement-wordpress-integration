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
    $cart_uuid = sanitize_text_field($order['cart_uuid'] ?? '');
    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'automatic_payment_methods[enabled]' => 'true',
        'metadata[order_reference]' => $order_reference,
        'metadata[source]' => 'musement-integration',
        'description' => sprintf('Musement order %s', $order_reference),
    ];

    // El cart_uuid queda grabado en Stripe (el cliente no puede falsearlo) para poder
    // comprobar despues, en musement_stripe_verify_payment_for_cart(), que el pago que
    // se presenta como prueba realmente se hizo para este carrito y no para otro.
    if ($cart_uuid !== '') {
        $payload['metadata[cart_uuid]'] = $cart_uuid;
    }

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

/**
 * Consulta un PaymentIntent directamente en Stripe (fuente de verdad server-to-server;
 * nunca hay que fiarse de un "ya pague" que llegue solo desde el navegador).
 */
function musement_stripe_retrieve_payment_intent(string $payment_intent_id) {
    $secret_key = musement_stripe_secret_key();

    if ($secret_key === '' || $payment_intent_id === '') {
        return new WP_Error(
            'musement_stripe_missing_data',
            __('Falta la Secret Key de Stripe o el identificador del pago.', 'musement-integration')
        );
    }

    $response = wp_remote_get(
        'https://api.stripe.com/v1/payment_intents/' . rawurlencode($payment_intent_id),
        [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
            ],
        ]
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        return new WP_Error(
            'musement_stripe_payment_intent_not_found',
            __('No se pudo verificar el pago en Stripe.', 'musement-integration'),
            ['status_code' => $status_code]
        );
    }

    return $body;
}

/**
 * Comprueba, contra la API de Stripe (no contra lo que mande el navegador), que un pago:
 * - existe y se ha completado de verdad (status succeeded),
 * - se hizo para ESTE carrito (metadata.cart_uuid grabado al crear el PaymentIntent),
 * - el importe/moneda cobrados coinciden con el total real del carrito,
 * - y no se ha usado ya antes para confirmar otra reserva (evita reutilizar el mismo
 *   pago para "pagar" varios carritos).
 *
 * Sin esta comprobacion, musement_create_order() marcaba la reserva como pagada en
 * Musement sin haber verificado nunca que el cliente habia pagado de verdad.
 *
 * @return true|WP_Error
 */
function musement_stripe_verify_payment_for_cart(string $payment_intent_id, string $cart_uuid, float $expected_amount, string $expected_currency) {
    if ($payment_intent_id === '' || $cart_uuid === '') {
        return new WP_Error(
            'musement_stripe_payment_missing',
            __('Falta confirmar el pago antes de crear la reserva.', 'musement-integration')
        );
    }

    $lock_name = 'musement_pi_' . md5($payment_intent_id);

    if (!musement_order_lock_acquire($lock_name, 10)) {
        return new WP_Error(
            'musement_stripe_payment_busy',
            __('Este pago ya se esta procesando, espera unos segundos.', 'musement-integration')
        );
    }

    $used_key = 'musement_pi_used_' . md5($payment_intent_id);

    if (get_transient($used_key)) {
        musement_order_lock_release($lock_name);
        return new WP_Error(
            'musement_stripe_payment_already_used',
            __('Este pago ya se ha utilizado para confirmar otra reserva.', 'musement-integration')
        );
    }

    $intent = musement_stripe_retrieve_payment_intent($payment_intent_id);

    if (is_wp_error($intent)) {
        musement_order_lock_release($lock_name);
        return $intent;
    }

    if (($intent['status'] ?? '') !== 'succeeded') {
        musement_order_lock_release($lock_name);
        return new WP_Error(
            'musement_stripe_payment_not_succeeded',
            __('El pago todavia no se ha completado.', 'musement-integration'),
            ['status' => $intent['status'] ?? '']
        );
    }

    $intent_cart_uuid = (string) ($intent['metadata']['cart_uuid'] ?? '');

    if ($intent_cart_uuid === '' || !hash_equals($intent_cart_uuid, $cart_uuid)) {
        musement_order_lock_release($lock_name);
        return new WP_Error(
            'musement_stripe_payment_cart_mismatch',
            __('El pago verificado no corresponde a este carrito.', 'musement-integration')
        );
    }

    $expected_amount_cents = (int) round($expected_amount * 100);
    $intent_amount = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
    $intent_currency = strtolower((string) ($intent['currency'] ?? ''));

    if ($intent_amount !== $expected_amount_cents || $intent_currency !== strtolower($expected_currency)) {
        musement_order_lock_release($lock_name);
        return new WP_Error(
            'musement_stripe_payment_amount_mismatch',
            __('El importe pagado no coincide con el del carrito.', 'musement-integration'),
            ['expected' => $expected_amount_cents, 'paid' => $intent_amount]
        );
    }

    // Se marca como usado ANTES de soltar el candado para cerrar la ventana de carrera:
    // si dos peticiones llegan casi a la vez para el mismo payment_intent_id, la segunda
    // debe encontrar ya el transient puesto en cuanto adquiera el candado.
    set_transient($used_key, true, DAY_IN_SECONDS);
    musement_order_lock_release($lock_name);

    return true;
}
