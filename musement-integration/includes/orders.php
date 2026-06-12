<?php

if (!defined('ABSPATH')) {
    exit;
}

function musement_orders_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'musement_orders';
}

function musement_orders_install(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = musement_orders_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_reference VARCHAR(64) NOT NULL,
        customer_email VARCHAR(190) NOT NULL DEFAULT '',
        status VARCHAR(40) NOT NULL DEFAULT 'pending_payment',
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(8) NOT NULL DEFAULT 'EUR',
        items_json LONGTEXT NULL,
        customer_json LONGTEXT NULL,
        payment_reference VARCHAR(190) NOT NULL DEFAULT '',
        salesforce_response_json LONGTEXT NULL,
        salesforce_order_id VARCHAR(190) NOT NULL DEFAULT '',
        musement_response_json LONGTEXT NULL,
        musement_booking_reference VARCHAR(190) NOT NULL DEFAULT '',
        integration_errors_json LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_reference (order_reference),
        KEY status (status),
        KEY customer_email (customer_email)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option('musement_orders_db_version', '1.1.0');
}

function musement_orders_maybe_install(): void
{
    if (get_option('musement_orders_db_version') !== '1.1.0') {
        musement_orders_install();
    }
}
add_action('plugins_loaded', 'musement_orders_maybe_install');

function musement_orders_create(array $customer, array $items, array $summary, string $payment_reference = ''): int
{
    global $wpdb;

    $now = current_time('mysql');
    $reference = 'MUS-' . strtoupper(wp_generate_password(10, false, false));

    $wpdb->insert(
        musement_orders_table(),
        [
            'order_reference' => $reference,
            'customer_email' => sanitize_email($customer['email'] ?? ''),
            'status' => $payment_reference ? 'paid' : 'pending_payment',
            'total' => (float) ($summary['total'] ?? 0),
            'currency' => sanitize_text_field($summary['currency'] ?? 'EUR'),
            'items_json' => wp_json_encode($items),
            'customer_json' => wp_json_encode($customer),
            'payment_reference' => sanitize_text_field($payment_reference),
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    return (int) $wpdb->insert_id;
}

function musement_orders_get(int $order_id): ?array
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . musement_orders_table() . ' WHERE id = %d', $order_id),
        ARRAY_A
    );

    if (!$row) {
        return null;
    }

    $row['items'] = json_decode($row['items_json'] ?: '[]', true) ?: [];
    $row['customer'] = json_decode($row['customer_json'] ?: '[]', true) ?: [];

    return $row;
}

function musement_orders_get_by_reference(string $order_reference): ?array
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . musement_orders_table() . ' WHERE order_reference = %s', $order_reference),
        ARRAY_A
    );

    if (!$row) {
        return null;
    }

    $row['items'] = json_decode($row['items_json'] ?: '[]', true) ?: [];
    $row['customer'] = json_decode($row['customer_json'] ?: '[]', true) ?: [];

    return $row;
}

function musement_orders_update(int $order_id, array $data): bool
{
    global $wpdb;

    $allowed = [
        'status',
        'payment_reference',
        'salesforce_response_json',
        'salesforce_order_id',
        'musement_response_json',
        'musement_booking_reference',
        'integration_errors_json',
    ];

    $update = [];

    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            $update[$key] = is_scalar($data[$key]) ? (string) $data[$key] : wp_json_encode($data[$key]);
        }
    }

    if (!$update) {
        return false;
    }

    $update['updated_at'] = current_time('mysql');

    return false !== $wpdb->update(
        musement_orders_table(),
        $update,
        ['id' => $order_id]
    );
}
