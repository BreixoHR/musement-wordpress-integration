<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_payment_gateway(): string
{
    if (defined('MUSEMENT_PAYMENT_GATEWAY')) {
        return sanitize_key(MUSEMENT_PAYMENT_GATEWAY);
    }

    return sanitize_key((string) get_option('musement_payment_gateway', 'manual'));
}

function musement_payment_create_session(array $order)
{
    $gateway = musement_payment_gateway();
    $context = musement_payment_context($order, $gateway);

    $payment = apply_filters('musement_payment_create_session', null, $order, $gateway, $context);

    if (is_wp_error($payment)) {
        return $payment;
    }

    if (is_array($payment)) {
        return wp_parse_args($payment, [
            'gateway' => $gateway,
            'payment_reference' => '',
            'payment_url' => '',
            'status' => 'pending_payment',
        ]);
    }

    return new WP_Error(
        'musement_payment_gateway_missing',
        __('La pasarela de pago todavia no esta configurada.', 'musement-integration'),
        [
            'gateway' => $gateway,
            'order_reference' => $order['order_reference'] ?? '',
        ]
    );
}

function musement_payment_context(array $order, string $gateway = ''): array
{
    $gateway = $gateway ?: musement_payment_gateway();
    $order_reference = sanitize_text_field($order['order_reference'] ?? '');

    return [
        'gateway' => $gateway,
        'order_reference' => $order_reference,
        'success_url' => add_query_arg([
            'musement_order' => $order_reference,
            'payment_status' => 'success',
        ], musement_checkout_url()),
        'cancel_url' => add_query_arg([
            'musement_order' => $order_reference,
            'payment_status' => 'cancelled',
        ], musement_checkout_url()),
        'return_url' => add_query_arg([
            'order_reference' => $order_reference,
        ], rest_url('musement/v1/payment-return/' . rawurlencode($gateway))),
        'webhook_url' => rest_url('musement/v1/payment-webhook/' . rawurlencode($gateway)),
    ];
}

function musement_payment_complete_order_by_reference(string $order_reference, string $payment_reference)
{
    $order = musement_orders_get_by_reference($order_reference);

    if (!$order) {
        return new WP_Error(
            'musement_order_not_found',
            __('No se encontro el pedido local.', 'musement-integration')
        );
    }

    return musement_payment_complete_order((int) $order['id'], $payment_reference);
}

function musement_payment_complete_order(int $order_id, string $payment_reference)
{
    $order = musement_orders_get($order_id);

    if (!$order) {
        return new WP_Error(
            'musement_order_not_found',
            __('No se encontro el pedido local.', 'musement-integration')
        );
    }

    if ($order['status'] === 'confirmed') {
        return $order;
    }

    musement_orders_update($order_id, [
        'status' => 'paid',
        'payment_reference' => $payment_reference,
    ]);

    $order = musement_orders_get($order_id);

    return musement_payment_sync_paid_order($order);
}

function musement_payment_sync_paid_order(array $order)
{
    $order_id = (int) ($order['id'] ?? 0);
    $stored = json_decode($order['integration_errors_json'] ?? '[]', true) ?: [];
    $cart_uuid = $stored['musement_cart_uuid'] ?? musement_remote_cart_get_id();
    if ($cart_uuid) {
        $musement_order_res = musement_remote_create_order($cart_uuid, 'NONE');
        if (is_wp_error($musement_order_res)) {
            $errors['musement_order'] = [
                'message' => $musement_order_res->get_error_message(),
                'data' => $musement_order_res->get_error_data(),
            ];
        } else {
            musement_orders_update($order_id, [
                'musement_response_json' => $musement_order_res,
                'musement_booking_reference' => $musement_order_res['uuid'] ?? $musement_order_res['identifier'] ?? '',
            ]);
        }

        if (!empty($musement_order_res)) {
            $musement_uuid = is_array($musement_order_res) ? ($musement_order_res['uuid'] ?? $musement_order_res['identifier'] ?? '') : '';
            if ($musement_uuid) {
                $mark = musement_remote_mark_order_paid($musement_uuid);
                if (is_wp_error($mark)) {
                    $errors['musement_mark_paid'] = [
                        'message' => $mark->get_error_message(),
                        'data' => $mark->get_error_data(),
                    ];
                    musement_orders_update($order_id, ['integration_errors_json' => $errors]);
                } else {
                    $existing = json_decode((string) ($order['integration_errors_json'] ?? '[]'), true) ?: [];
                    $existing['marked_paid'] = true;
                    musement_orders_update($order_id, ['integration_errors_json' => $existing]);
                }
            }
        }
    }
    if (!$order_id) {
        return new WP_Error(
            'musement_order_missing_id',
            __('El pedido local no tiene identificador.', 'musement-integration')
        );
    }

    musement_orders_update($order_id, [
        'status' => 'sent_to_integrations',
    ]);

    $errors = [];
    $salesforce_response = musement_salesforce_notify_order($order);

    if (is_wp_error($salesforce_response)) {
        $errors['salesforce'] = [
            'message' => $salesforce_response->get_error_message(),
            'data' => $salesforce_response->get_error_data(),
        ];
    } else {
        musement_orders_update($order_id, [
            'salesforce_response_json' => $salesforce_response,
            'salesforce_order_id' => musement_salesforce_reference($salesforce_response),
        ]);
    }

    $musement_response = musement_sales_create_booking($order);

    if (is_wp_error($musement_response)) {
        $errors['musement'] = [
            'message' => $musement_response->get_error_message(),
            'data' => $musement_response->get_error_data(),
        ];
    } else {
        musement_orders_update($order_id, [
            'musement_response_json' => $musement_response,
            'musement_booking_reference' => musement_sales_reference($musement_response),
        ]);
    }

    if ($errors) {
        musement_orders_update($order_id, [
            'status' => 'integration_failed',
            'integration_errors_json' => $errors,
        ]);

        return new WP_Error(
            'musement_integration_failed',
            __('El pago esta confirmado, pero una o mas integraciones han fallado.', 'musement-integration'),
            $errors
        );
    }

    musement_orders_update($order_id, [
        'status' => 'confirmed',
        'integration_errors_json' => [],
    ]);

    musement_cart_clear();

    return musement_orders_get($order_id);
}


/**
 * Construye el payload en formato estándar OCTO a partir de una orden de Musement
 * 
 * @param array $order_data Datos de la orden obtenidos de Musement API
 * @return array Payload listo para enviar como JSON
 */
function musement_build_octo_payload($order_data, $customer_data = [], $voucher_urls = []) {
    $items = $order_data['items'] ?? [];
    $total_price = $order_data['total_price'] ?? ['value' => 0, 'currency' => 'EUR'];
    $order_reference = $order_data['identifier'] ?? $order_data['uuid'] ?? '';

    // Datos de contacto con prioridad al formulario
    $first_name = !empty($customer_data['first_name']) ? $customer_data['first_name'] : ($order_data['customer']['firstname'] ?? '');
    $last_name  = !empty($customer_data['last_name'])  ? $customer_data['last_name']  : ($order_data['customer']['lastname'] ?? '');
    $email      = !empty($customer_data['email'])      ? $customer_data['email']      : ($order_data['customer']['email'] ?? '');
    $phone      = !empty($customer_data['phone'])      ? $customer_data['phone']      : ($order_data['customer']['mobile'] ?? '');

    $bookings = [];

    foreach ($items as $item) {
        $product_data = $item['product'] ?? [];
        $price = $item['retail_price_in_order_currency'] ?? $item['total_retail_price_in_order_currency'] ?? [];
        $amount = (float) ($price['value'] ?? 0);

        $date_time = $product_data['date'] ?? '';
        $local_date_start = !empty($date_time) ? date('Y-m-d\TH:i:sP', strtotime($date_time)) : null;

        // Obtener tipo de unidad desde ticket_holder_code
        $ticket_holder_code = $product_data['price_tag']['ticket_holder_code'] ?? '';
        $unit_type = 'ADULT';
        if (strpos($ticket_holder_code, 'child') !== false) $unit_type = 'CHILD';
        elseif (strpos($ticket_holder_code, 'infant') !== false) $unit_type = 'INFANT';
        elseif (strpos($ticket_holder_code, 'senior') !== false) $unit_type = 'SENIOR';
        elseif (strpos($ticket_holder_code, 'youth') !== false) $unit_type = 'YOUTH';

        // Construir unitItems para este booking
        $unit_items = [
            [
                'id' => $item['uuid'] ?? wp_generate_uuid4(),
                'unitId' => $product_data['id'] ?? '',
                'unitType' => $unit_type,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'contact' => [
                    'fullName' => trim($first_name . ' ' . $last_name),
                    'emailAddress' => $email,
                    'phoneNumber' => $phone
                ],
                'pricing' => [
                    'original' => (int) ($amount * 100),
                    'retail'   => (int) ($amount * 100),
                    'currency' => $price['currency'] ?? 'EUR'
                ]
            ]
        ];

        $bookings[] = [
            'id' => $item['uuid'] ?? wp_generate_uuid4(),
            'productId' => $product_data['activity_uuid'] ?? '',
            'optionId' => $product_data['price_tag']['price_feature_code'] ?? 'DEFAULT',
            'status' => 'CONFIRMED',
            'localDateTimeStart' => $local_date_start,
            'unitItems' => $unit_items,
            'contact' => [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'emailAddress' => $email,
                'phoneNumber' => $phone
            ],
            'pricing' => [
                'original' => (int) ($amount * 100),
                'retail'   => (int) ($amount * 100),
                'currency' => $price['currency'] ?? 'EUR'
            ]
        ];
    }

    // Si no hay items, al menos un booking vacío para que no falle el esquema
    if (empty($bookings)) {
        $bookings[] = [
            'id' => wp_generate_uuid4(),
            'productId' => '',
            'optionId' => 'DEFAULT',
            'status' => 'CONFIRMED',
            'localDateTimeStart' => null,
            'unitItems' => [],
            'contact' => [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'emailAddress' => $email,
                'phoneNumber' => $phone
            ],
            'pricing' => ['original' => 0, 'retail' => 0, 'currency' => 'EUR']
        ];
    }

    $payload = [
        'webhook' => [
            'id' => wp_generate_uuid4(),
            'event' => 'order_update',
            'url' => defined('MUSEMENT_VENTRATA_WEBHOOK_URL') ? MUSEMENT_VENTRATA_WEBHOOK_URL : '',
            'retryOnError' => true,
            'useContactLanguage' => true,
            'capabilities' => ['octo/cart', 'octo/questions', 'octo/extras', 'ventrata/dashboard']
        ],
        'order' => [
            'id' => $order_data['uuid'] ?? wp_generate_uuid4(),
            'testMode' => false,
            'supplierReference' => $order_reference,
            'status' => 'CONFIRMED',
            'utcCreatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'utcUpdatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'bookings' => $bookings,
            'contact' => [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'emailAddress' => $email,
                'phoneNumber' => $phone
            ],
            'pricing' => [
                'original' => (int) (($total_price['value'] ?? 0) * 100),
                'retail'   => (int) (($total_price['value'] ?? 0) * 100),
                'currency' => $total_price['currency'] ?? 'EUR'
            ]
        ]
    ];

    if (!empty($voucher_urls)) {
        $payload['vouchers'] = $voucher_urls;
    }

    return $payload;
}