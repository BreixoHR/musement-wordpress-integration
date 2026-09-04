<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bloque de Gutenberg "Musement: Producto individual".
 *
 * Permite insertar el widget de compra de UNA actividad concreta
 * eligiendola de un desplegable, sin que el usuario tenga que
 * escribir ni copiar ningun shortcode a mano.
 */

function musement_get_cached_activities_list(): array
{
    $cache = get_option('musement_products_cache', []);
    $list = [];

    if (!is_array($cache)) {
        return $list;
    }

    foreach ($cache as $uuid => $activity) {
        if (!is_array($activity)) {
            continue;
        }

        $list[] = [
            'uuid' => (string) ($activity['uuid'] ?? $uuid),
            'title' => (string) ($activity['title'] ?? 'Actividad sin titulo'),
        ];
    }

    return $list;
}

function musement_render_product_block($attributes)
{
    $uuid = sanitize_text_field($attributes['uuid'] ?? '');

    if (!$uuid) {
        if (!current_user_can('edit_posts')) {
            return '';
        }

        return '<p style="padding:12px;background:#fff3cd;border:1px solid #ffe69c;border-radius:6px;">'
            . esc_html__('Bloque "Musement: Producto individual": selecciona una actividad en el panel lateral del editor.', 'musement-integration')
            . '</p>';
    }

    return musement_certified_calendar_shortcode([
        'uuid' => $uuid,
        'title' => sanitize_text_field($attributes['title'] ?? ''),
        'show_details' => !empty($attributes['showDetails']) ? 'true' : 'false',
    ]);
}

add_action('init', function () {
    register_block_type('musement-integration/product', [
        'attributes' => [
            'uuid' => [
                'type' => 'string',
                'default' => '',
            ],
            'title' => [
                'type' => 'string',
                'default' => '',
            ],
            'showDetails' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ],
        'render_callback' => 'musement_render_product_block',
    ]);
});

add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'musement-product-block',
        MUSEMENT_INTEGRATION_URL . 'blocks/product-picker.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render'],
        MUSEMENT_INTEGRATION_VERSION,
        true
    );

    wp_localize_script('musement-product-block', 'MUSEMENT_PRODUCTS', [
        'items' => musement_get_cached_activities_list(),
        'settingsUrl' => admin_url('admin.php?page=musement-settings'),
    ]);
});
