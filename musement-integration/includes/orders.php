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

    // 🔥 VERIFICAR SI LA COLUMNA EXISTE
    $column_exists = $wpdb->get_var(
        "SHOW COLUMNS FROM {$table} LIKE 'participant_data_json'"
    );

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
        participant_data_json LONGTEXT NULL, -- 🔥 NUEVO
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_reference (order_reference),
        KEY status (status),
        KEY customer_email (customer_email)
    ) {$charset_collate};";

    dbDelta($sql);

    // 🔥 SI LA COLUMNA NO EXISTE, AGREGARLA
    if (!$column_exists) {
        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN participant_data_json LONGTEXT NULL AFTER integration_errors_json"
        );
    }

    update_option('musement_orders_db_version', '1.1.0');
}

function musement_orders_maybe_install(): void
{
    if (get_option('musement_orders_db_version') !== '1.1.0') {
        musement_orders_install();
    }
}
add_action('plugins_loaded', 'musement_orders_maybe_install');

/**
 * 🔥 CREAR ORDEN CON DATOS DE PARTICIPANTES
 */
function musement_orders_create(array $customer, array $items, array $summary, string $payment_reference = '', array $participant_data = []): int
{
    global $wpdb;

    $now = current_time('mysql');
    $reference = 'MUS-' . strtoupper(wp_generate_password(10, false, false));

    // 🔥 EXTRAER PREGUNTAS DE LOS ITEMS
    $questions_data = [];
    foreach ($items as $item) {
        if (!empty($item['questions']) && is_array($item['questions'])) {
            $activity_uuid = $item['activity_uuid'] ?? '';
            $questions_data[$activity_uuid] = [
                'product_name' => $item['product_name'] ?? $item['title'] ?? '',
                'questions' => $item['questions']
            ];
        }
    }

    // 🔥 COMBINAR CON DATOS DE PARTICIPANTES DIRECTOS
    if (!empty($participant_data)) {
        foreach ($participant_data as $activity_uuid => $data) {
            if (!isset($questions_data[$activity_uuid])) {
                $questions_data[$activity_uuid] = [
                    'product_name' => $data['product_name'] ?? '',
                    'questions' => $data['questions'] ?? []
                ];
            } else {
                // Combinar datos
                $questions_data[$activity_uuid]['questions'] = array_merge(
                    $questions_data[$activity_uuid]['questions'],
                    $data['questions'] ?? []
                );
            }
        }
    }

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
            'participant_data_json' => !empty($questions_data) ? wp_json_encode($questions_data) : null,
        ],
        ['%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    return (int) $wpdb->insert_id;
}

/**
 * OBTENER ORDEN CON DATOS DE PARTICIPANTES
 */
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
    $row['participant_data'] = json_decode($row['participant_data_json'] ?? '{}', true) ?: [];

    unset($row['items_json']);
    unset($row['customer_json']);
    unset($row['participant_data_json']);

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
    $row['participant_data'] = json_decode($row['participant_data_json'] ?? '{}', true) ?: [];

    unset($row['items_json']);
    unset($row['customer_json']);
    unset($row['participant_data_json']);

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
        'participant_data_json', // 🔥 NUEVO
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

/**
 * 🔥 ACTUALIZAR DATOS DE PARTICIPANTES DE UNA ORDEN
 */
function musement_orders_update_participant_data(int $order_id, array $participant_data): bool
{
    global $wpdb;

    $existing = musement_orders_get($order_id);
    if (!$existing) {
        return false;
    }

    // Combinar datos existentes con nuevos
    $existing_participant_data = $existing['participant_data'] ?? [];
    $merged_data = array_merge_recursive($existing_participant_data, $participant_data);

    return false !== $wpdb->update(
        musement_orders_table(),
        [
            'participant_data_json' => wp_json_encode($merged_data),
            'updated_at' => current_time('mysql')
        ],
        ['id' => $order_id]
    );
}