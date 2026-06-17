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

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_musement-settings') {
        return;
    }
    wp_enqueue_style(
        'musement-admin-settings',
        MUSEMENT_INTEGRATION_URL . 'assets/admin-settings.css',
        [],
        MUSEMENT_INTEGRATION_VERSION
    );
    wp_enqueue_script(
        'musement-admin-settings',
        MUSEMENT_INTEGRATION_URL . 'assets/admin-settings.js',
        ['jquery'],
        MUSEMENT_INTEGRATION_VERSION,
        true
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
    register_setting('musement_settings_group', 'musement_product_prices');   // registered once
    register_setting('musement_settings_group', 'musement_payment_gateway');
    register_setting('musement_settings_group', 'musement_payment_create_endpoint');
    register_setting('musement_settings_group', 'musement_payment_api_key');
    register_setting('musement_settings_group', 'musement_payment_webhook_secret');
    register_setting('musement_settings_group', 'musement_stripe_publishable_key');
    register_setting('musement_settings_group', 'musement_stripe_secret_key');
    register_setting('musement_settings_group', 'musement_stripe_webhook_secret');
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
    <div class="wrap musement-admin-wrap">
        <div class="musement-header">
            <div class="musement-header__brand">
                <span class="dashicons dashicons-location-alt"></span>
                <h1>Panel de Control Musement</h1>
            </div>
            <div class="musement-header__actions">
                <span class="musement-version">v<?php echo esc_html(MUSEMENT_INTEGRATION_VERSION); ?></span>
            </div>
        </div>

        <form method="post" action="options.php" class="musement-admin-form">
            <?php settings_fields('musement_settings_group'); ?>
            
            <div class="musement-dashboard">
                <!-- Navigation Tabs -->
                <div class="musement-tabs-nav">
                    <button type="button" class="musement-tab-btn is-active" data-tab="connectivity">
                        <span class="dashicons dashicons-admin-links"></span> Conectividad API
                    </button>
                    <button type="button" class="musement-tab-btn" data-tab="payments">
                        <span class="dashicons dashicons-cart"></span> Configuración de Pagos
                    </button>
                    <button type="button" class="musement-tab-btn" data-tab="city">
                        <span class="dashicons dashicons-location"></span> Configuración de Ciudad
                    </button>
                    <button type="button" class="musement-tab-btn" data-tab="tours">
                        <span class="dashicons dashicons-images-alt2"></span> Tours y Márgenes
                    </button>
                </div>

                <!-- Tabs Content -->
                <div class="musement-tabs-content">
                    
                    <!-- Tab Connectivity -->
                    <div class="musement-tab-pane is-active" id="tab-connectivity">
                        <div class="musement-card">
                            <div class="musement-card__header">
                                <h3><span class="dashicons dashicons-admin-network"></span> Credenciales de la API de Musement</h3>
                                <p>Configura los datos de autenticación necesarios para conectarte al entorno sandbox de Musement.</p>
                            </div>
                            <div class="musement-card__body">
                                <div class="musement-grid">
                                    <div class="musement-field-group">
                                        <label for="musement_application">Application ID</label>
                                        <input type="text" id="musement_application" name="musement_application" value="<?php echo esc_attr(get_option('musement_application', '')); ?>" placeholder="Ej: demotours...">
                                        <span class="musement-field-help">Identificador único de tu aplicación en el sandbox de Musement.</span>
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_api_key">API Key Musement (Client ID)</label>
                                        <input type="text" id="musement_api_key" name="musement_api_key" value="<?php echo esc_attr(get_option('musement_api_key', '')); ?>">
                                        <span class="musement-field-help">Client ID de la API de Musement.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="musement-card">
                            <div class="musement-card__header">
                                <h3><span class="dashicons dashicons-cloud"></span> Conexión con Salesforce</h3>
                                <p>Configuración del endpoint para la sincronización de órdenes a Salesforce (Ventrata).</p>
                            </div>
                            <div class="musement-card__body">
                                <div class="musement-grid">
                                    <div class="musement-field-group">
                                        <label for="musement_salesforce_endpoint">Salesforce Endpoint</label>
                                        <input type="url" id="musement_salesforce_endpoint" name="musement_salesforce_endpoint" value="<?php echo esc_attr(get_option('musement_salesforce_endpoint', '')); ?>" placeholder="https://...">
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_salesforce_api_key">Salesforce API Key</label>
                                        <input type="password" id="musement_salesforce_api_key" name="musement_salesforce_api_key" value="<?php echo esc_attr(get_option('musement_salesforce_api_key', '')); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Payments -->
                    <div class="musement-tab-pane" id="tab-payments">
                        <div class="musement-card">
                            <div class="musement-card__header">
                                <h3><span class="dashicons dashicons-admin-generic"></span> Pasarela de Pago General</h3>
                                <p>Configura el método de pago por defecto y credenciales asociadas.</p>
                            </div>
                            <div class="musement-card__body">
                                <div class="musement-grid">
                                    <div class="musement-field-group">
                                        <label for="musement_payment_gateway">Pasarela Seleccionada</label>
                                        <?php $gateway = get_option('musement_payment_gateway', 'hosted'); ?>
                                        <select id="musement_payment_gateway" name="musement_payment_gateway">
                                            <option value="hosted" <?php selected($gateway, 'hosted'); ?>>Hosted (Redirección)</option>
                                            <option value="stripe" <?php selected($gateway, 'stripe'); ?>>Stripe (Integrado)</option>
                                            <option value="redsys" <?php selected($gateway, 'redsys'); ?>>Redsys</option>
                                        </select>
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_payment_create_endpoint">Endpoint de Creación de Pago</label>
                                        <input type="text" id="musement_payment_create_endpoint" name="musement_payment_create_endpoint" value="<?php echo esc_attr(get_option('musement_payment_create_endpoint', '')); ?>">
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_payment_api_key">Payment API Key</label>
                                        <input type="password" id="musement_payment_api_key" name="musement_payment_api_key" value="<?php echo esc_attr(get_option('musement_payment_api_key', '')); ?>">
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_payment_webhook_secret">Webhook Secret</label>
                                        <input type="password" id="musement_payment_webhook_secret" name="musement_payment_webhook_secret" value="<?php echo esc_attr(get_option('musement_payment_webhook_secret', '')); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="musement-card musement-card--stripe">
                            <div class="musement-card__header">
                                <h3><span class="dashicons dashicons-shield"></span> Configuración de Stripe</h3>
                                <p>Requerido si has seleccionado Stripe como pasarela de pago.</p>
                            </div>
                            <div class="musement-card__body">
                                <div class="musement-grid">
                                    <div class="musement-field-group">
                                        <label for="musement_stripe_publishable_key">Stripe Publishable Key</label>
                                        <input type="text" id="musement_stripe_publishable_key" name="musement_stripe_publishable_key" value="<?php echo esc_attr(get_option('musement_stripe_publishable_key', '')); ?>" placeholder="pk_test_...">
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_stripe_secret_key">Stripe Secret Key</label>
                                        <input type="password" id="musement_stripe_secret_key" name="musement_stripe_secret_key" value="<?php echo esc_attr(get_option('musement_stripe_secret_key', '')); ?>" placeholder="sk_test_...">
                                    </div>
                                    <div class="musement-field-group">
                                        <label for="musement_stripe_webhook_secret">Stripe Webhook Secret</label>
                                        <input type="password" id="musement_stripe_webhook_secret" name="musement_stripe_webhook_secret" value="<?php echo esc_attr(get_option('musement_stripe_webhook_secret', '')); ?>" placeholder="whsec_...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab City -->
                    <div class="musement-tab-pane" id="tab-city">
                        <div class="musement-card">
                            <div class="musement-card__header">
                                <h3><span class="dashicons dashicons-location-alt"></span> Selección de Ciudad de Destino</h3>
                                <p>Selecciona la ciudad de la cual deseas importar las actividades y tours de Musement.</p>
                            </div>
                            <div class="musement-card__body">
                                <div class="musement-city-dashboard">
                                    <div class="musement-city-selector-section">
                                        <div class="musement-field-group">
                                            <label>Ciudad</label>
                                            <?php musement_field_city_selector(); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="musement-city-info-section">
                                        <div class="musement-city-info-grid">
                                            <div class="musement-city-info-item">
                                                <span class="musement-info-label">Ciudad seleccionada</span>
                                                <span class="musement-info-val"><?php echo esc_html(get_option('musement_city_name', 'Ninguna')); ?></span>
                                            </div>
                                            <div class="musement-city-info-item">
                                                <span class="musement-info-label">Latitud</span>
                                                <span class="musement-info-val"><?php echo esc_html(get_option('musement_city_lat', '-')); ?></span>
                                            </div>
                                            <div class="musement-city-info-item">
                                                <span class="musement-info-label">Longitud</span>
                                                <span class="musement-info-val"><?php echo esc_html(get_option('musement_city_lng', '-')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Tours -->
                    <div class="musement-tab-pane" id="tab-tours">
                        <div class="musement-card">
                            <div class="musement-card__header musement-card__header--tours">
                                <div>
                                    <h3><span class="dashicons dashicons-tickets"></span> Catálogo de Actividades y Márgenes</h3>
                                    <p>Configura el porcentaje de margen para cada actividad. Las actividades sin margen no se mostrarán en la web.</p>
                                </div>
                                <div class="musement-search-wrap">
                                    <span class="dashicons dashicons-search"></span>
                                    <input type="text" id="musement-tour-search" placeholder="Buscar tour por nombre...">
                                </div>
                            </div>
                            <div class="musement-card__body musement-products-table-wrapper">
                                <?php musement_products_field(); ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
            <div class="musement-footer-actions">
                <?php submit_button('Guardar Cambios', 'primary large musement-btn-submit', 'submit', false); ?>
            </div>
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
    $margins = get_option('musement_product_margins', []);
    
    if (empty($products)) {
        echo '<div class="notice notice-warning"><p>No hay productos disponibles. Selecciona una ciudad primero y guarda los cambios.</p></div>';
        echo '<a href="' . admin_url('admin.php?page=musement-settings&sync_products=1') . '" class="button">Sincronizar productos ahora</a>';
        return;
    }
    
    echo '<div class="notice notice-info">';
    echo '<p>📌 <strong>Configuración de márgenes</strong>: Introduce el porcentaje de margen que quieres aplicar sobre el precio original de Musement.</p>';
    echo '<p>💡 El precio final se calculará automáticamente como: <strong>Precio original + (Precio original × Margen / 100)</strong></p>';
    echo '</div>';
    
    echo '<table class="widefat striped musement-tours-table">';
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
        $original_price = 0;
        if (!empty($activity['products']) && is_array($activity['products'])) {
            $original_price = $activity['products'][0]['retail_price']['value'] ?? 0;
        }
        
        $current_margin = isset($margins[$activity_uuid]) ? $margins[$activity_uuid] : '';
        $final_price = ($original_price > 0 && $current_margin !== '') ? $original_price * (1 + ($current_margin / 100)) : 0;
        
        echo '<tr class="musement-tour-row">';
        echo '<td><strong>' . esc_html($activity['title'] ?? 'Actividad sin título') . '</strong><br>';
        echo '<small>' . esc_html($activity_uuid) . '</small></td>';
        echo '<td>';
        if ($original_price > 0) {
            echo number_format($original_price, 2, ',', '.') . ' €';
        } else {
            echo '<span style="color: #999;">No disponible</span>';
        }
        echo '</td>';
        echo '<td>';
        echo '<input type="text" pattern="[0-9]+([,][0-9]+)?" class="margin-input musement-margin-input"';
        echo '       name="musement_product_margins[' . esc_attr($activity_uuid) . ']"';
        echo '       value="' . esc_attr($current_margin) . '"';
        echo '       data-original-price="' . esc_attr($original_price) . '"';
        echo '       style="width: 80px;" placeholder="0"> %';
        echo '</td>';
        echo '<td>';
        if ($final_price > 0) {
            echo '<strong class="musement-final-price" style="color: #2e7d32;">' . number_format($final_price, 2, ',', '.') . ' €</strong>';
        } else {
            echo '<strong class="musement-final-price" style="color: #999;">-</strong>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '<p class="description">El margen se aplica sobre el precio original de Musement. Si no introduces margen, la actividad no se mostrará en el frontend.</p>';
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
/**
 * Fetch activities for a given city and store a simplified cache used by the admin UI.
 *
 * OPTIMISED: only makes one API call per city (was making up to N*8 calls).
 * Availability data is intentionally skipped here — it changes daily and is
 * fetched on-demand in the frontend AJAX handlers.
 *
 * @param int|string $city_id
 * @return bool
 */
function musement_fetch_activities_for_city($city_id) {
    $city_id = (string) $city_id;
    $token   = function_exists('musement_get_access_token') ? musement_get_access_token() : null;

    if (!$token) {
        error_log('[MUSEMENT] No token available');
        return false;
    }

    // Single API call: fetch up to 50 activities for the city.
    $activities_url = add_query_arg([
        'city_in' => $city_id,
        'limit'   => 50,
    ], 'https://sandbox.musement.com/api/v3/activities');

    $res = wp_remote_get($activities_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization'          => 'Bearer ' . $token,
            'Accept'                 => 'application/json',
            'X-Musement-Application' => MUSEMENT_APPLICATION,
            'X-Musement-Currency'    => 'EUR',
            'X-Musement-Market'      => 'es',
            'X-Musement-Version'     => '3.4.0',
        ],
    ]);

    if (is_wp_error($res)) {
        error_log('[MUSEMENT] Error fetching activities: ' . $res->get_error_message());
        return false;
    }

    $body       = json_decode(wp_remote_retrieve_body($res), true);
    $activities = $body['data'] ?? [];

    if (empty($activities)) {
        error_log('[MUSEMENT] No activities found for city ' . $city_id);
        return false;
    }

    // Build a lightweight map: uuid => { title, products[] }.
    // Product prices are taken from the retail_price on the activity itself;
    // availability is NOT fetched here to avoid N*8 HTTP calls on every admin save.
    $map = [];
    foreach ($activities as $activity) {
        $uuid = $activity['uuid'] ?? '';
        if (!$uuid) continue;

        $retail_price = $activity['retail_price'] ?? [];

        $map[$uuid] = [
            'title'    => $activity['title'] ?? 'Actividad sin título',
            'products' => [
                [
                    'product_identifier' => $uuid,
                    'name'               => $activity['title'] ?? 'Entrada',
                    'retail_price'       => [
                        'value'           => $retail_price['value']    ?? 0,
                        'currency'        => $retail_price['currency'] ?? 'EUR',
                        'formatted_value' => isset($retail_price['value'])
                            ? number_format($retail_price['value'], 2) . ' €'
                            : '0 €',
                    ],
                ],
            ],
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

// (Debug endpoints removed — they output raw HTML and exit, which is unsafe in production.)
