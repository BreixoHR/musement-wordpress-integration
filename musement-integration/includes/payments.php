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
 * Contacto en formato OCTO/Ventrata (mismos nombres de campo que usa
 * MusementBookingService en Salesforce). Los campos que Musement no nos
 * da (title, age, birthdate, streetAddress...) se dejan a null/vacío en vez
 * de inventarse -- pero se incluye la clave para que la forma del objeto sea
 * la que el parser de Salesforce espera.
 */
function musement_octo_contact_payload(string $first_name, string $last_name, string $email, string $phone, string $country = 'ES', string $language = 'es'): array {
    return [
        'id' => null,
        'reference' => null,
        'tags' => [],
        'title' => null,
        'fullName' => trim($first_name . ' ' . $last_name),
        'firstName' => $first_name,
        'lastName' => $last_name,
        'emailAddress' => $email,
        'phoneNumber' => $phone,
        'age' => null,
        'birthdate' => null,
        'locales' => [$language],
        'streetAddress' => null,
        'city' => null,
        'state' => null,
        'postalCode' => null,
        'country' => $country,
        'nationality' => null,
        'notes' => null,
        'allowMarketing' => false,
        'taxId' => null,
        'idNumber' => null,
        'company' => null,
        'photoUrl' => null,
    ];
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
    $order_id = $order_data['uuid'] ?? wp_generate_uuid4();

    // Datos de contacto con prioridad al formulario
    $first_name = !empty($customer_data['first_name']) ? $customer_data['first_name'] : ($order_data['customer']['firstname'] ?? '');
    $last_name  = !empty($customer_data['last_name'])  ? $customer_data['last_name']  : ($order_data['customer']['lastname'] ?? '');
    $email      = !empty($customer_data['email'])      ? $customer_data['email']      : ($order_data['customer']['email'] ?? '');
    $phone      = !empty($customer_data['phone'])      ? $customer_data['phone']      : ($order_data['customer']['mobile'] ?? '');

    $order_contact = musement_octo_contact_payload($first_name, $last_name, $email, $phone);

    $bookings = [];
    // El precio que Musement guarda en la orden es siempre su NETO -- no sabe
    // nada del margen configurado en wp-admin. Sin esto, Salesforce recibía
    // el neto de Musement en vez de lo que el cliente pagó de verdad. Se suma
    // aquí, línea a línea, y se acumula para el total del pedido más abajo.
    $order_total_with_margin = 0.0;
    $order_total_currency = $total_price['currency'] ?? 'EUR';

    foreach ($items as $item) {
        $product_data = $item['product'] ?? [];
        $price = $item['retail_price_in_order_currency'] ?? $item['total_retail_price_in_order_currency'] ?? [];
        $amount = (float) ($price['value'] ?? 0);
        $currency = $price['currency'] ?? 'EUR';

        $item_quantity_for_margin = max(1, (int) ($item['quantity'] ?? 1));
        $item_margin_percent = function_exists('musement_product_margin_percent')
            ? musement_product_margin_percent((string) ($product_data['activity_uuid'] ?? ''))
            : 0.0;
        // Redondea por unidad y LUEGO multiplica por la cantidad -- mismo
        // orden que usa el frontend (certified_calendar.php) y la
        // verificación de Stripe (musement_create_order()), para que no haya
        // desajustes de céntimos entre lo cobrado y lo que ve Salesforce.
        $net_unit_amount = $item_quantity_for_margin > 0 ? $amount / $item_quantity_for_margin : $amount;
        $marked_up_unit_amount = round($net_unit_amount * (1 + ($item_margin_percent / 100)), 2);
        $amount = round($marked_up_unit_amount * $item_quantity_for_margin, 2);
        $order_total_with_margin += $amount;
        $order_total_currency = $currency;

        $date_time = $product_data['date'] ?? '';
        $local_date_start = !empty($date_time) ? date('Y-m-d\TH:i:sP', strtotime($date_time)) : null;

        // Obtener tipo de unidad desde ticket_holder_code
        $ticket_holder_code = $product_data['price_tag']['ticket_holder_code'] ?? '';
        $unit_type = 'ADULT';
        if (strpos($ticket_holder_code, 'child') !== false) $unit_type = 'CHILD';
        elseif (strpos($ticket_holder_code, 'infant') !== false) $unit_type = 'INFANT';
        elseif (strpos($ticket_holder_code, 'senior') !== false) $unit_type = 'SENIOR';
        elseif (strpos($ticket_holder_code, 'youth') !== false) $unit_type = 'YOUTH';

        $unit_id = $product_data['id'] ?? '';
        $unit_name = $product_data['price_tag']['ticket_holder'] ?? $unit_type;
        $booking_id = $item['uuid'] ?? wp_generate_uuid4();

        // Vouchers de este item concreto, si Musement ya los generó
        $item_vouchers = [];
        foreach ((array) ($item['vouchers'] ?? []) as $voucher) {
            if (!empty($voucher['url'])) {
                $item_vouchers[] = [
                    'deliveryFormat' => 'PDF_URL',
                    'deliveryValue' => $voucher['url'],
                ];
            }
        }

        // OCTO no agrupa participantes con un "quantity": cada unidad física
        // (cada entrada/persona) es su propio unitItem. Musement sí nos da la
        // cantidad como un entero en el mismo item de carrito, así que lo
        // repartimos en tantos unitItems como corresponda.
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unit_amount = $quantity > 0 ? $amount / $quantity : $amount;
        $unit_items = [];

        for ($i = 0; $i < $quantity; $i++) {
            $unit_items[] = [
                'id' => $quantity > 1 ? $booking_id . '-' . ($i + 1) : $booking_id,
                'uuid' => $quantity > 1 ? $booking_id . '-' . ($i + 1) : $booking_id,
                'unitId' => $unit_id,
                'unitType' => $unit_type,
                'unit' => [
                    'id' => $unit_id,
                    'internalName' => $unit_name,
                    'type' => $unit_type,
                ],
                'status' => 'CONFIRMED',
                'localDateTimeStart' => $local_date_start,
                'contact' => $order_contact,
                'ticket' => [
                    'redemptionMethod' => 'DIGITAL',
                    'deliveryOptions' => $item_vouchers,
                ],
                'questionAnswers' => [],
                'extraItems' => [],
                'pricing' => [
                    'original' => (int) round($unit_amount * 100),
                    'retail'   => (int) round($unit_amount * 100),
                    'net'      => 0,
                    'currency' => $currency,
                    'currencyPrecision' => 2,
                    'includedTaxes' => [],
                ],
            ];
        }

        $bookings[] = [
            'id' => $booking_id,
            'uuid' => $booking_id,
            'testMode' => false,
            'resellerReference' => null,
            'supplierReference' => $booking_id,
            'tags' => [],
            'status' => 'CONFIRMED',
            'utcCreatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'utcUpdatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'utcConfirmedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'productId' => $product_data['activity_uuid'] ?? '',
            'product' => [
                'id' => $product_data['activity_uuid'] ?? '',
                'internalName' => $product_data['title'] ?? '',
            ],
            'optionId' => $product_data['price_tag']['price_feature_code'] ?? 'DEFAULT',
            'option' => [
                'id' => $product_data['price_tag']['price_feature_code'] ?? 'DEFAULT',
                'internalName' => $product_data['price_tag']['price_feature'] ?? 'DEFAULT',
            ],
            'cancellable' => true,
            'confirmable' => false,
            'localDateTimeStart' => $local_date_start,
            'localDateTimeEnd' => $local_date_start,
            'unitItems' => $unit_items,
            'contact' => $order_contact,
            'orderId' => $order_id,
            'orderReference' => $order_reference,
            'voucher' => [
                'redemptionMethod' => 'DIGITAL',
                'deliveryOptions' => $item_vouchers,
            ],
            'questionAnswers' => [],
            'extraItems' => [],
            'pricing' => [
                'original' => (int) round($amount * 100),
                'retail'   => (int) round($amount * 100),
                'net'      => 0,
                'currency' => $currency,
                'currencyPrecision' => 2,
                'includedTaxes' => [],
            ],
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
            'contact' => $order_contact,
            'orderId' => $order_id,
            'orderReference' => $order_reference,
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
            'id' => $order_id,
            'testMode' => false,
            'supplierReference' => $order_reference,
            'settlementMethod' => 'DIRECT',
            'settlementMethods' => ['DIRECT'],
            'emailReceipt' => false,
            'quote' => false,
            'status' => 'CONFIRMED',
            'utcCreatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'utcUpdatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'utcConfirmedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'cancellable' => true,
            'confirmable' => false,
            'bookings' => $bookings,
            'contact' => $order_contact,
            'questionAnswers' => [],
            'pricing' => [
                // Con margen aplicado (suma de las líneas de arriba), no el
                // neto en bruto de Musement.
                'original' => (int) round($order_total_with_margin * 100),
                'retail'   => (int) round($order_total_with_margin * 100),
                'net'      => 0,
                'currency' => $order_total_currency,
                'currencyPrecision' => 2,
                'includedTaxes' => [],
            ]
        ]
    ];

    if (!empty($voucher_urls)) {
        $payload['vouchers'] = $voucher_urls;
    }

    return $payload;
}