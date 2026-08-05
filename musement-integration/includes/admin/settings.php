<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ADMIN MENU
 */
add_action('admin_menu', function () {
    add_menu_page(
        'Musement Settings',
        'Musement',
        'manage_options',
        'musement-settings',
        'musement_settings_page',
        'dashicons-location-alt',
        56
    );
});

add_action('admin_init', function() {
    // Sanitizar márgenes antes de guardar
    add_filter('sanitize_option_musement_product_margins', function($margins) {
        if (!is_array($margins)) {
            return [];
        }
        $sanitized = [];
        foreach ($margins as $product_id => $margin) {
            $margin = sanitize_text_field($margin);
            if ($margin !== '' && is_numeric($margin)) {
                $sanitized[$product_id] = (float) $margin;
            }
        }
        return $sanitized;
    }, 10, 1);
});

/**
 * REGISTER SETTINGS + FIELDS
 */
add_action('admin_init', function () {

    // Register options
    register_setting('musement_settings_group', 'musement_api_key');
    register_setting('musement_settings_group', 'musement_application');
    register_setting('musement_settings_group', 'musement_salesforce_endpoint');
    register_setting('musement_settings_group', 'musement_salesforce_api_key');
    register_setting('musement_settings_group', 'musement_product_prices');
    register_setting('musement_settings_group', 'musement_payment_gateway');
    register_setting('musement_settings_group', 'musement_payment_create_endpoint');
    register_setting('musement_settings_group', 'musement_payment_api_key');
    register_setting('musement_settings_group', 'musement_payment_webhook_secret');
    register_setting('musement_settings_group', 'musement_stripe_publishable_key');
    register_setting('musement_settings_group', 'musement_stripe_secret_key');
    register_setting('musement_settings_group', 'musement_stripe_webhook_secret');
    register_setting('musement_settings_group', 'musement_product_prices');
    register_setting('musement_settings_group', 'musement_city_lat');
    register_setting('musement_settings_group', 'musement_city_lng');
    register_setting('musement_settings_group', 'musement_city_name');
    register_setting('musement_settings_group', 'musement_city_id');
    register_setting('musement_settings_group', 'musement_product_margins');


    /**
     * SECTION
     */
    add_settings_section(
        'musement_main_section',
        'Configuración principal',
        function () {
            echo '<p>Configura las credenciales de Musement, Salesforce y pagos.</p>';
        },
        'musement-settings'
    );

    /**
     * FIELDS
     */

    add_settings_field(
        'musement_api_key',
        'API Key Musement',
        'musement_field_api_key',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_application',
        'Application ID',
        'musement_field_application',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_salesforce_endpoint',
        'Salesforce Endpoint',
        'musement_field_salesforce_endpoint',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_salesforce_api_key',
        'Salesforce API Key',
        'musement_field_salesforce_api_key',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_payment_gateway',
        'Payment Gateway',
        'musement_field_payment_gateway',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_payment_create_endpoint',
        'Payment Create Endpoint',
        'musement_field_payment_create_endpoint',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_payment_api_key',
        'Payment API Key',
        'musement_field_payment_api_key',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_payment_webhook_secret',
        'Webhook Secret',
        'musement_field_payment_webhook_secret',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_stripe_publishable_key',
        'Stripe Publishable Key',
        'musement_field_stripe_publishable_key',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_stripe_secret_key',
        'Stripe Secret Key',
        'musement_field_stripe_secret_key',
        'musement-settings',
        'musement_main_section'
    );

    add_settings_field(
        'musement_stripe_webhook_secret',
        'Stripe Webhook Secret',
        'musement_field_stripe_webhook_secret',
        'musement-settings',
        'musement_main_section'
    );
    add_settings_field(
        'musement_city_id',
        'Ciudad',
        'musement_field_city_selector',
        'musement-settings',
        'musement_main_section'
    );
    add_settings_field(
        'musement_products',
        'Productos y precios',
        'musement_products_field',
        'musement-settings',
        'musement_main_section'
    );


    
});

/**
 * PAGE RENDER
 */
function musement_settings_page() {
    ?>
    <div class="wrap">
        <h1>Musement Settings</h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('musement_settings_group');
            do_settings_sections('musement-settings');
            submit_button('Guardar configuración');
            ?>
        </form>
    </div>
    <?php
}

/**
 * INPUT FIELDS
 */

function musement_field_api_key() {
    $value = get_option('musement_api_key', '');
    echo '<input class="regular-text" type="text" name="musement_api_key" value="' . esc_attr($value) . '">';
}

function musement_field_application() {
    $value = get_option('musement_application', '');
    echo '<input class="regular-text" type="text" name="musement_application" value="' . esc_attr($value) . '">';
}

function musement_field_salesforce_endpoint() {
    $value = get_option('musement_salesforce_endpoint', '');
    echo '<input class="regular-text" type="text" name="musement_salesforce_endpoint" value="' . esc_attr($value) . '">';
}

function musement_field_salesforce_api_key() {
    $value = get_option('musement_salesforce_api_key', '');
    echo '<input class="regular-text" type="password" name="musement_salesforce_api_key" value="' . esc_attr($value) . '">';
}

function musement_field_payment_gateway() {
    $value = get_option('musement_payment_gateway', 'hosted');
    ?>
    <select name="musement_payment_gateway">
        <option value="hosted" <?php selected($value, 'hosted'); ?>>Hosted</option>
        <option value="stripe" <?php selected($value, 'stripe'); ?>>Stripe</option>
        <option value="redsys" <?php selected($value, 'redsys'); ?>>Redsys</option>
    </select>
    <?php
}

function musement_field_payment_create_endpoint() {
    $value = get_option('musement_payment_create_endpoint', '');
    echo '<input class="regular-text" type="text" name="musement_payment_create_endpoint" value="' . esc_attr($value) . '">';
}

function musement_field_payment_api_key() {
    $value = get_option('musement_payment_api_key', '');
    echo '<input class="regular-text" type="password" name="musement_payment_api_key" value="' . esc_attr($value) . '">';
}

function musement_field_payment_webhook_secret() {
    $value = get_option('musement_payment_webhook_secret', '');
    echo '<input class="regular-text" type="password" name="musement_payment_webhook_secret" value="' . esc_attr($value) . '">';
}

function musement_field_stripe_publishable_key() {
    $value = get_option('musement_stripe_publishable_key', '');
    echo '<input class="regular-text" type="text" name="musement_stripe_publishable_key" value="' . esc_attr($value) . '" placeholder="pk_test_...">';
}

function musement_field_stripe_secret_key() {
    $value = get_option('musement_stripe_secret_key', '');
    echo '<input class="regular-text" type="password" name="musement_stripe_secret_key" value="' . esc_attr($value) . '" placeholder="sk_test_...">';
}

function musement_field_stripe_webhook_secret() {
    $value = get_option('musement_stripe_webhook_secret', '');
    echo '<input class="regular-text" type="password" name="musement_stripe_webhook_secret" value="' . esc_attr($value) . '" placeholder="whsec_...">';
}


function musement_products_field() {
    $products = get_option('musement_products_cache', []);
    $margins = get_option('musement_product_margins', []); // Cambiamos a margins
    
    if (empty($products)) {
        echo '<div class="notice notice-warning"><p>No hay productos disponibles. Selecciona una ciudad primero y guarda los cambios.</p></div>';
        echo '<a href="' . admin_url('admin.php?page=musement-settings&sync_products=1') . '" class="button">Sincronizar productos ahora</a>';
        return;
    }
    
    echo '<div class="notice notice-info">';
    echo '<p>📌 <strong>Configuración de márgenes</strong>: Introduce el porcentaje de margen que quieres aplicar sobre el precio original de Musement.</p>';
    echo '<p>💡 El precio final se calculará automáticamente como: <strong>Precio original + (Precio original × Margen / 100)</strong></p>';
    echo '</div>';
    
    echo '<table class="widefat striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th width="40%">Actividad</th>';
    echo '<th width="20%">Precio original (referencia)</th>';
    echo '<th width="20%">Margen (%)</th>';
    echo '<th width="20%">Precio final estimado</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($products as $activity_uuid => $activity) {
        // Obtener precio original de la actividad (si está disponible)
        $original_price = 0;
        if (!empty($activity['products']) && is_array($activity['products'])) {
            // Tomar el precio del primer producto como referencia
            $original_price = $activity['products'][0]['retail_price']['value'] ?? 0;
        }
        
        $current_margin = isset($margins[$activity_uuid]) ? $margins[$activity_uuid] : '';
        $final_price = ($original_price > 0 && $current_margin !== '') ? $original_price * (1 + ($current_margin / 100)) : 0;
        
        echo '<tr>';
        echo '<td><strong>' . esc_html($activity['title'] ?? 'Actividad sin título') . '</strong><br>';
        echo '<small>' . esc_html($activity_uuid) . '</small>';
        echo '</td>';
        echo '<td>';
        if ($original_price > 0) {
            echo number_format($original_price, 2) . ' €';
        } else {
            echo '<span style="color: #999;">No disponible</span>';
        }
        echo '</td>';
        echo '<td>';
        echo '<input type="text" pattern="[0-9]+([,][0-9]+)?"';
        echo '       name="musement_product_margins[' . esc_attr($activity_uuid) . ']"';
        echo '       value="' . esc_attr($current_margin) . '"';
        echo '       style="width: 80px;" placeholder="0"';
        echo '       oninput="this.value = this.value.replace(/\\./g, \',\').replace(/[^0-9,]/g, \'\')"';
        echo '       onblur="if(this.value === \'\') this.value = \'0\'; if(this.value.endsWith(\',\')) this.value = this.value.slice(0, -1);"> %';
        echo '</td>';
        echo '<td>';
        if ($final_price > 0) {
            echo '<strong style="color: #2e7d32;">' . number_format($final_price, 2) . ' €</strong>';
        } else {
            echo '<span style="color: #999;">-</span>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '<p class="description">El margen se aplica sobre el precio original de Musement. Si no introduces margen, la actividad no se mostrará en el frontend.</p>';

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const marginInputs = document.querySelectorAll('.margin-input');
            
            marginInputs.forEach(function(input) {
                // Formatear valor inicial (reemplazar punto por coma)
                if (input.value && input.value.includes('.')) {
                    input.value = input.value.replace(/\./g, ',');
                }
                
                input.addEventListener('input', function(e) {
                    // Solo permitir números, coma y backspace
                    let value = this.value;
                    
                    // Reemplazar cualquier punto por coma
                    value = value.replace(/\./g, ',');
                    
                    // Eliminar cualquier caracter que no sea número, coma o guión
                    value = value.replace(/[^0-9,-]/g, '');
                    
                    // Asegurar que solo haya una coma
                    const parts = value.split(',');
                    if (parts.length > 2) {
                        value = parts[0] + ',' + parts.slice(1).join('');
                    }
                    
                    this.value = value;
                });
                
                // Al perder el foco, validar
                input.addEventListener('blur', function() {
                    let value = this.value;
                    if (value === '' || value === '-') {
                        this.value = '';
                    } else if (value.endsWith(',')) {
                        this.value = value.slice(0, -1);
                    }
                });
            });
        });
    </script>
    <?php
}

function musement_get_cities() {

    $cached = get_transient('musement_cities');

    if ($cached !== false) {
        return $cached;
    }

        // Use token + X-Musement headers like other client helpers
        $token = function_exists('musement_get_access_token') ? musement_get_access_token() : null;

        $res = wp_remote_get(
            'https://sandbox.musement.com/api/v3/cities',
            [
                'timeout' => 25,
                'headers' => [
                    'Accept-Language' => 'es-ES',
                    'X-Musement-Application' => defined('MUSEMENT_APPLICATION') ? MUSEMENT_APPLICATION : get_option('musement_application', ''),
                    'X-Musement-Currency' => 'EUR',
                    'X-Musement-Market' => 'es',
                    'X-Musement-Version' => '3.4.0',
                    'Authorization' => $token ? 'Bearer ' . $token : '',
                    'Accept' => 'application/json',
                ],
            ]
        );

    if (is_wp_error($res)) {
        return [];
    }

        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (is_array($body['data'] ?? null)) {
            $cities = $body['data'];
        } elseif (is_array($body)) {
            $cities = $body;
        } else {
            return [];
        }

        set_transient('musement_cities', $cities, 12 * HOUR_IN_SECONDS);

        return $cities;
}

add_action('update_option_musement_city_id', function ($old, $new) {

    $cities = musement_get_cities();

    foreach ($cities as $city) {

        if ((string) ($city['id'] ?? '') !== (string) $new) {
            continue;
        }

        update_option('musement_city_name', $city['name'] ?? '');

        update_option(
            'musement_city_lat',
            (float) ($city['latitude'] ?? 0)
        );

        update_option(
            'musement_city_lng',
            (float) ($city['longitude'] ?? 0)
        );

        break;
    }

    // When the city option is updated, also fetch activities for that city
    if ($new) {
        musement_fetch_activities_for_city($new);
    }

}, 10, 2);

/**
 * Fetch activities for a given city and store a simplified cache used by the admin UI.
 *
 * @param int|string $city_id
 * @return bool
 */
function musement_fetch_activities_for_city($city_id) {
    $city_id = (string) $city_id;
    $token = function_exists('musement_get_access_token') ? musement_get_access_token() : null;
    
    if (!$token) {
        error_log('[MUSEMENT] No token available');
        return false;
    }
    
    // 1. Obtener actividades de la ciudad
    $activities_url = add_query_arg([
        'city_in' => $city_id,
        'limit' => 50,
    ], 'https://sandbox.musement.com/api/v3/activities');
    
    $res = wp_remote_get($activities_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency' => 'EUR',
            'X-Musement-Market' => 'es',
            'X-Musement-Version' => '3.4.0',
        ],
    ]);
    
    if (is_wp_error($res)) {
        error_log('[MUSEMENT] Error: ' . $res->get_error_message());
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($res), true);
    $activities = $body['data'] ?? [];
    
    if (empty($activities)) {
        error_log('[MUSEMENT] No activities found');
        return false;
    }
    
    $map = [];
    
    // 2. Para cada actividad, buscar una fecha con disponibilidad
    foreach ($activities as $activity) {
        $uuid = $activity['uuid'] ?? '';
        if (!$uuid) continue;
        
        $products = [];
        
        // Buscar disponibilidad en los próximos 60 días
        for ($i = 7; $i <= 60; $i += 7) {
            $test_date = date('Y-m-d', strtotime("+$i days"));
            $availability_url = sprintf(
                'https://sandbox.musement.com/api/v3/activities/%s/dates/%s',
                $uuid,
                $test_date
            );
            
            $avail_res = wp_remote_get($availability_url, [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'X-Musement-Application' => MUSEMENT_APPLICATION,
                    'X-Musement-Currency' => 'EUR',
                    'X-Musement-Market' => 'es',
                    'X-Musement-Version' => '3.4.0',
                ],
            ]);
            
            if (!is_wp_error($avail_res)) {
                $avail_data = json_decode(wp_remote_retrieve_body($avail_res), true);
                
                // Buscar productos en la estructura correcta según la docu
                if (isset($avail_data['ticket_products']) && is_array($avail_data['ticket_products'])) {
                    $products = $avail_data['ticket_products'];
                    break;
                }
                if (isset($avail_data['data']['ticket_products']) && is_array($avail_data['data']['ticket_products'])) {
                    $products = $avail_data['data']['ticket_products'];
                    break;
                }
            }
        }
        
        // Si no hay productos, mostrar mensaje en el admin
        if (empty($products)) {
            error_log("[MUSEMENT] No products found for activity: {$activity['title']}");
        }
        
        // Normalizar productos para el admin
        $normalized_products = [];
        foreach ($products as $product) {
            $price = $product['retail_price'] ?? ['value' => 0, 'currency' => 'EUR'];
            $normalized_products[] = [
                'product_identifier' => $product['uuid'] ?? $product['id'] ?? '',
                'name' => $product['name'] ?? 'Entrada',
                'retail_price' => [
                    'value' => $price['value'] ?? 0,
                    'currency' => $price['currency'] ?? 'EUR',
                    'formatted_value' => isset($price['value']) ? number_format($price['value'], 2) . ' €' : '0 €',
                ],
            ];
        }
        
        $map[$uuid] = [
            'title' => $activity['title'] ?? 'Actividad sin título',
            'products' => $normalized_products,
        ];
    }
    
    if (!empty($map)) {
        update_option('musement_products_cache', $map);
        error_log('[MUSEMENT] Cache actualizado con ' . count($map) . ' actividades');
        return true;
    }
    
    return false;
}

function musement_field_city_selector() {

    $cities = musement_get_cities();

    $selected = get_option('musement_city_id', '');
    // If no cities were loaded, show a helpful message and the reload button
    if (empty($cities)) {
        echo '<div style="display:flex;gap:8px;align-items:center;">';
        echo '<em>No se han cargado ciudades.</em>';
        echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=musement-settings&refresh_cities=1')) . '">Recargar ciudades</a>';
        $app = get_option('musement_application', '');
        echo '<div style="margin-left:12px;color:#666;">Application ID: <strong>' . esc_html($app ?: '(no establecido)') . '</strong></div>';
        echo '</div>';
        return;
    }

    echo '<select name="musement_city_id" style="min-width:320px;">';

    echo '<option value="">Selecciona una ciudad</option>';

    foreach ($cities as $city) {

        $id   = $city['id'] ?? '';
        $name = $city['name'] ?? '';

        if (!$id || !$name) {
            continue;
        }

        echo '<option value="' . esc_attr($id) . '" ' .
            selected($selected, $id, false) .
        '>';

        echo esc_html($name);

        echo '</option>';
    }

    echo '</select>';
    echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=musement-settings&refresh_cities=1')) . '">Recargar ciudades</a>';
}

add_action('admin_init', function () {

    if (isset($_GET['sync']) && current_user_can('manage_options')) {
        musement_sync_products();
    }
    // Allow manual refresh of cities from admin UI
    if (isset($_GET['refresh_cities']) && current_user_can('manage_options')) {
        delete_transient('musement_cities');
        // Re-fetch immediately to repopulate transient
        musement_get_cities();
        wp_safe_redirect(admin_url('admin.php?page=musement-settings'));
        exit;
    }
});

// TEMPORAL - Para depurar la API
// DEBUG TEMPORAL - Para ver qué devuelve la API de disponibilidad
add_action('admin_init', function() {
    if (isset($_GET['debug_products']) && current_user_can('manage_options')) {
        $token = musement_get_access_token();
        $uuid = '037e288f-036f-41fd-8268-0b9309579aff'; // Una actividad de ejemplo
        
        echo '<h2>Debug de productos para actividad: ' . $uuid . '</h2>';
        
        // Probar diferentes fechas
        $fechas = [
            date('Y-m-d', strtotime('+7 days')),
            date('Y-m-d', strtotime('+14 days')),
            date('Y-m-d', strtotime('+30 days')),
            date('Y-m-d', strtotime('+60 days')),
        ];
        
        foreach ($fechas as $fecha) {
            echo '<h3>Fecha: ' . $fecha . '</h3>';
            
            $url = 'https://sandbox.musement.com/api/v3/activities/' . $uuid . '/dates/' . $fecha;
            
            $res = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Musement-Application' => MUSEMENT_APPLICATION,
                    'X-Musement-Version' => '3.4.0',
                ],
            ]);
            
            if (is_wp_error($res)) {
                echo '<p>Error: ' . $res->get_error_message() . '</p>';
                continue;
            }
            
            $data = json_decode(wp_remote_retrieve_body($res), true);
            echo '<pre>';
            echo 'Status code: ' . wp_remote_retrieve_response_code($res) . "\n";
            echo 'Tiene ticket_products? ' . (isset($data['ticket_products']) ? 'SI' : 'NO') . "\n";
            echo 'Tiene products? ' . (isset($data['products']) ? 'SI' : 'NO') . "\n";
            echo 'Estructura: ' . print_r(array_keys($data), true) . "\n";
            
            if (isset($data['ticket_products'])) {
                echo 'Cantidad de productos: ' . count($data['ticket_products']) . "\n";
                echo 'Primer producto: ' . print_r($data['ticket_products'][0] ?? 'ninguno', true);
            }
            echo '</pre><hr>';
        }
        
        exit;
    }
});

add_action('admin_init', function() {
    if (isset($_GET['debug_search']) && current_user_can('manage_options')) {
        $token = musement_get_access_token();
        
        // Buscar actividades con disponibilidad real
        $url = add_query_arg([
            'city_in' => get_option('musement_city_id', ''),
            'available_from' => date('Y-m-d', strtotime('+7 days')),
            'available_to' => date('Y-m-d', strtotime('+30 days')),
            'limit' => 5,
        ], 'https://sandbox.musement.com/api/v3/activities');
        
        echo '<h2>Buscando actividades con disponibilidad</h2>';
        echo '<p>URL: ' . $url . '</p>';
        
        $res = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-Musement-Application' => MUSEMENT_APPLICATION,
                'X-Musement-Version' => '3.4.0',
            ],
        ]);
        
        $data = json_decode(wp_remote_retrieve_body($res), true);
        
        echo '<pre>';
        echo 'Status: ' . wp_remote_retrieve_response_code($res) . "\n";
        
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $activity) {
                echo "\n---\n";
                echo "UUID: {$activity['uuid']}\n";
                echo "Título: {$activity['title']}\n";
                echo "Tiene retail_price: " . (isset($activity['retail_price']) ? 'SI' : 'NO') . "\n";
            }
        } else {
            print_r($data);
        }
        echo '</pre>';
        
        exit;
    }
});
