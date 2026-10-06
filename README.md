# Integración de Musement para WordPress

Plugin de WordPress que **integra Musement** (marketplace de actividades de TUI) en la web propia de una agencia de turismo. Muestra los tours de Musement, consulta disponibilidad y horarios, gestiona carrito y checkout propios con pago por Stripe y, cuando el pago se confirma, sincroniza el pedido con **Musement (API de Sales / OCTO)** y con **Salesforce**.

Desarrollado entre junio y septiembre de 2026. El historial de este repositorio reproduce las **28 versiones reales** del plugin (0.13.306 → 2.12.022), cada una como un commit con la fecha de su publicación y una etiqueta `vX.Y.Z`.

## Qué hace

- Shortcodes `[musement_tours]` y `[musement_checkout]` y bloques de Gutenberg (selector de producto, listado de tours).
- Consulta de catálogo, disponibilidad y franjas horarias contra la API de Musement (OAuth con client credentials).
- Carrito en sesión y pedido local (`wp_musement_orders`) con estados `pending_payment` → `paid` → `confirmed`.
- Pago con Stripe Checkout o con una pasarela hospedada genérica, con webhooks REST y verificación de firma.
- Proceso tras el pago: alta de la compra en Musement, envío del JSON del pedido a Salesforce y entradas en PDF combinadas (FPDF/FPDI).
- Flujo de certificación de Musement: calendario y productos certificados.
- Panel de ajustes en el administrador de WordPress.

## Estructura

```text
musement-integration/
├── musement-integration.php   # cabecera del plugin y configuración
├── includes/                  # API, carrito, checkout, pagos, webhooks, Salesforce, OCTO, PDF
│   └── vendor/                # FPDF y FPDI (terceros, con sus licencias)
├── blocks/                    # bloques de Gutenberg
├── assets/                    # JS y CSS del frontend
└── README.md                  # documentación técnica original del plugin
```

## Nota

Este es el código original, sin refactorizar. Para publicarlo se han sustituido las credenciales (client id y secret de Musement, claves de Stripe y tokens) por `***` y se han anonimizado los dominios y la marca de la empresa. Para usarlo, configura tus propias claves en `musement-integration.php` o en los ajustes del plugin.

Autor: Breixo A. Herrera
