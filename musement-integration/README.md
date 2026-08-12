# Musement Integration

Plugin WordPress para mostrar actividades de Musement, permitir seleccion de productos/horarios, preparar checkout propio y sincronizar pedidos pagados con Salesforce y Musement/Sales.

Version actual: `0.13.5`

## Estado Del Plugin

El plugin ya tiene:

- Shortcode de listado de tours: `[musement_tours]`
- Consulta de disponibilidad/productos Musement.
- Carrito local en sesion.
- Pagina automatica de checkout con shortcode `[musement_checkout]`.
- Selector visual de metodo de pago: tarjeta/Stripe.
- Creacion de pedido local tecnico en `wp_musement_orders`.
- Capa preparada para pasarela de pago.
- Adaptador generico para plataforma de pago hospedada con tarjeta/Stripe.
- Webhook REST generico para pasarelas.
- Sincronizacion post-pago con Salesforce.
- Sincronizacion post-pago con Musement/Sales.

Todavia falta:

- Integrar la pasarela real, por ejemplo Stripe o Redsys.
- Confirmar el contrato tecnico exacto de la plataforma de pago hospedada.
- Implementar la verificacion de firma concreta de la pasarela elegida.
- Configurar los endpoints definitivos de Salesforce y Musement/Sales.

## Flujo Funcional

1. El usuario ve tours con `[musement_tours]`.
2. Elige fecha y pulsa `Ver horarios`.
3. El plugin consulta Musement y devuelve slots/productos comprables.
4. El usuario elige cantidad y pulsa `Anadir`.
5. El producto se guarda en carrito local.
6. El usuario va a checkout.
7. Rellena datos personales y pulsa `Pagar`.
8. Se crea un pedido local en estado `pending_payment`.
9. La futura pasarela debera redirigir al usuario o iniciar el pago.
10. Cuando la pasarela confirme el pago por webhook:
    - el pedido pasa a `paid`
    - se envia JSON a Salesforce
    - se comunica la compra a Musement/Sales
    - si todo va bien, el pedido pasa a `confirmed`

## Flujo Tecnico Post-Pago

La funcion central tras pago correcto es:

```php
musement_payment_complete_order($order_id, $payment_reference);
```

Esta funcion esta en:

```text
includes/payments.php
```

Hace lo siguiente:

1. Busca el pedido local.
2. Marca el pedido como `paid`.
3. Llama a `musement_payment_sync_paid_order()`.
4. Envia el pedido a Salesforce con `musement_salesforce_notify_order()`.
5. Envia el pedido a Musement/Sales con `musement_sales_create_booking()`.
6. Guarda respuestas y referencias.
7. Marca el pedido como `confirmed` o `integration_failed`.

## Estados Del Pedido

Estados usados en `wp_musement_orders.status`:

```text
pending_payment       Pedido creado, pago pendiente.
paid                  Pago confirmado.
sent_to_integrations  Enviando a Salesforce y Musement/Sales.
confirmed             Salesforce y Musement/Sales respondieron OK.
integration_failed    Pago OK, pero fallo Salesforce o Musement/Sales.
cancelled             Reservado para cancelaciones futuras.
refunded              Reservado para reembolsos futuros.
```

El estado mas delicado es `integration_failed`: significa que el cliente pago, pero una integracion fallo. Hay que revisar `integration_errors_json`.

## Tabla Local

El plugin crea la tabla:

```text
wp_musement_orders
```

Campos principales:

```text
id
order_reference
customer_email
status
total
currency
items_json
customer_json
payment_reference
salesforce_response_json
salesforce_order_id
musement_response_json
musement_booking_reference
integration_errors_json
created_at
updated_at
```

Esta tabla no pretende ser un CRM ni una gestion completa de pedidos. Es una trazabilidad tecnica minima para no perder informacion si falla un webhook, Salesforce o Musement/Sales.

## Shortcodes

Listado de tours:

```text
[musement_tours]
```

Parametros opcionales:

```text
[musement_tours lat="37.1773" lng="-3.5986" limit="6" title="Tours disponibles"]
```

Checkout:

```text
[musement_checkout]
```

El plugin intenta crear automaticamente una pagina publicada con slug:

```text
checkout-musement
```

Y contenido:

```text
[musement_checkout]
```

## Configuracion En wp-config.php

Salesforce:

```php
define('MUSEMENT_SALESFORCE_ENDPOINT', 'https://tu-endpoint-salesforce');
define('MUSEMENT_SALESFORCE_API_KEY', '***');
```

Musement/Sales:

```php
define('MUSEMENT_SALES_ENDPOINT', 'https://endpoint-musement-sales');
define('MUSEMENT_APPLICATION', '***');
define('MUSEMENT_API_KEY', '***');
```

Pasarela hospedada:

```php
define('MUSEMENT_PAYMENT_GATEWAY', 'hosted');
define('MUSEMENT_PAYMENT_CREATE_ENDPOINT', 'https://tu-plataforma-pago/checkout/create');
define('MUSEMENT_PAYMENT_API_KEY', '***');
define('MUSEMENT_PAYMENT_WEBHOOK_SECRET', '***');
```

Con esta configuracion, WordPress crea el pedido local y envia el JSON del pedido a vuestra plataforma de pago. La plataforma muestra tarjeta/Stripe y debe devolver una URL de pago.

Debug de payloads:

```php
define('MUSEMENT_DEBUG_PAYLOADS', true);
```

Con este modo activo, al pulsar `Pagar` el navegador muestra en consola un preview del JSON que se enviaria a Salesforce. No usar en produccion porque contiene datos personales del comprador.

## JSON Enviado A Salesforce

Salesforce recibe un `POST` con JSON parecido a:

```json
{
  "source": {
    "site_url": "https://example.com/",
    "plugin_version": "0.13.3"
  },
  "order": {
    "reference": "MUS-ABC1234567",
    "status": "paid",
    "total": 50,
    "currency": "EUR"
  },
  "customer": {
    "first_name": "Juan",
    "last_name": "Perez",
    "email": "juan@example.com",
    "phone": "+34600000000"
  },
  "items": [
    {
      "activity_uuid": "activity-uuid",
      "title": "Tour",
      "type": "musement",
      "product_identifier": "123456789",
      "product_name": "Adulto",
      "quantity": 2,
      "unit_price": 25,
      "currency": "EUR",
      "date": "2026-05-10",
      "time": "10:00",
      "language": "es",
      "pickup": ""
    }
  ],
  "payment": {
    "status": "paid",
    "reference": "stripe_or_redsys_reference",
    "amount": 50,
    "currency": "EUR"
  }
}
```

Salesforce deberia responder algo parecido a:

```json
{
  "salesforce_order_id": "006XXXXXXXXXXXX",
  "status": "received"
}
```

El plugin guarda esa respuesta completa en `salesforce_response_json`.

## JSON Enviado A Musement/Sales

Musement/Sales recibe un `POST` construido en:

```text
includes/musement-sales.php
```

Payload base:

```json
{
  "merchant_order_reference": "MUS-ABC1234567",
  "customer": {
    "first_name": "Juan",
    "last_name": "Perez",
    "email": "juan@example.com",
    "phone": "+34600000000"
  },
  "items": [
    {
      "type": "musement",
      "product_identifier": "123456789",
      "quantity": 2,
      "date": "2026-05-10",
      "time": "10:00",
      "language": "es",
      "pickup": ""
    }
  ],
  "payment": {
    "status": "paid",
    "reference": "stripe_or_redsys_reference",
    "amount": 50,
    "currency": "EUR"
  }
}
```

Este payload puede necesitar ajustes cuando Musement confirme el contrato exacto del endpoint Sales.

## Pasarela De Pago

El plugin ya deja preparada la capa de pasarela y un adaptador generico para una plataforma de pago hospedada.

Cuando el usuario pulsa `Pagar`, se llama a:

```php
musement_payment_create_session($order);
```

Esa funcion lanza el filtro:

```php
apply_filters('musement_payment_create_session', null, $order, $gateway, $context);
```

La pasarela debe devolver:

```php
[
    'gateway' => 'stripe',
    'payment_reference' => 'checkout_session_id',
    'payment_url' => 'https://checkout.stripe.com/...',
    'status' => 'pending_payment',
]
```

El `$context` incluye:

```php
[
    'gateway' => 'stripe',
    'order_reference' => 'MUS-ABC1234567',
    'success_url' => 'https://web.com/checkout-musement/?musement_order=...',
    'cancel_url' => 'https://web.com/checkout-musement/?musement_order=...',
    'return_url' => 'https://web.com/wp-json/musement/v1/payment-return/stripe?...',
    'webhook_url' => 'https://web.com/wp-json/musement/v1/payment-webhook/stripe',
]
```

### Webhook Generico

Ruta REST ya disponible:

```text
POST /wp-json/musement/v1/payment-webhook/{gateway}
```

Ejemplo:

```text
POST /wp-json/musement/v1/payment-webhook/stripe
POST /wp-json/musement/v1/payment-webhook/redsys
POST /wp-json/musement/v1/payment-webhook/hosted
```

Por seguridad, esta ruta no marca pedidos como pagados por defecto. La pasarela debe implementar dos filtros:

```php
add_filter('musement_payment_webhook_is_valid', function ($is_valid, $request, $payload, $gateway) {
    // Verificar firma real: Stripe-Signature, Redsys signature, etc.
    return $is_valid;
}, 10, 4);

add_filter('musement_payment_webhook_event', function ($event, $request, $payload, $gateway) {
    // Normalizar evento de la pasarela.
    return [
        'paid' => true,
        'order_reference' => 'MUS-ABC1234567',
        'payment_reference' => 'payment_or_session_id',
        'event_reference' => 'webhook_event_id',
    ];
}, 10, 4);
```

Si el evento normalizado incluye entre sus parametros el siguiente:  `paid => true`, el plugin ejecuta:

```php
musement_payment_complete_order_by_reference($order_reference, $payment_reference);
```

Eso marca el pedido como pagado y dispara Salesforce + Musement/Sales.

### Plataforma De Pago Hospedada

Para el checkout hospedado que muestra tarjeta/Stripe, configurar:

```php
define('MUSEMENT_PAYMENT_GATEWAY', 'hosted');
define('MUSEMENT_PAYMENT_CREATE_ENDPOINT', 'https://tu-plataforma-pago/checkout/create');
define('MUSEMENT_PAYMENT_API_KEY', '***');
define('MUSEMENT_PAYMENT_WEBHOOK_SECRET', '***');
```

Al pulsar `Pagar`, el plugin envia un `POST` a `MUSEMENT_PAYMENT_CREATE_ENDPOINT` con:

```json
{
  "source": {
    "site_url": "https://example.com/",
    "plugin_version": "0.13.3"
  },
  "order": {
    "reference": "MUS-ABC1234567",
    "total": 23.99,
    "currency": "EUR"
  },
  "buyer": {
    "first_name": "Juan",
    "last_name": "Perez",
    "email": "juan@example.com",
    "phone": "+34600000000"
  },
  "items": [
    {
      "activity_uuid": "activity-uuid",
      "title": "Entrada a la Monumento de Cordoba con Audioguia",
      "product_identifier": "123456789",
      "product_name": "Adultos (+14 anos)",
      "quantity": 1,
      "unit_price": 23.99,
      "currency": "EUR",
      "date": "2026-05-15",
      "time": "10:30",
      "language": "es",
      "pickup": ""
    }
  ],
  "payment_methods": [
    "card",
    "stripe"
  ],
  "selected_payment_method": "stripe",
  "urls": {
    "success": "https://example.com/checkout-musement/?musement_order=MUS-ABC1234567&payment_status=success",
    "cancel": "https://example.com/checkout-musement/?musement_order=MUS-ABC1234567&payment_status=cancelled",
    "return": "https://example.com/wp-json/musement/v1/payment-return/hosted?order_reference=MUS-ABC1234567",
    "webhook": "https://example.com/wp-json/musement/v1/payment-webhook/hosted"
  }
}
```

La plataforma debe responder:

```json
{
  "payment_url": "https://tu-plataforma-pago/checkout/session/abc",
  "payment_reference": "session_abc",
  "status": "pending_payment"
}
```

Tambien se aceptan estos nombres alternativos para la URL:

```text
checkout_url
redirect_url
```

Cuando el pago se confirme, la plataforma debe llamar al webhook:

```text
POST /wp-json/musement/v1/payment-webhook/hosted
```

Payload esperado:

```json
{
  "status": "paid",
  "order_reference": "MUS-ABC1234567",
  "payment_reference": "payment_123",
  "event_id": "evt_123"
}
```

Tambien se aceptan estados:

```text
paid
succeeded
success
completed
```

Seguridad del webhook hospedado:

- Header simple permitido: `X-Musement-Webhook-Secret: secreto-compartido-webhook`
- Firma HMAC permitida: `X-Musement-Signature`
- La firma HMAC esperada es `hash_hmac('sha256', raw_body, MUSEMENT_PAYMENT_WEBHOOK_SECRET)`.

### Retorno De Pasarela

Ruta REST auxiliar:

```text
GET /wp-json/musement/v1/payment-return/{gateway}
```

Esta ruta no confirma pagos. Solo sirve como retorno visual o tecnico. La confirmacion real debe llegar siempre por webhook firmado.

## Pasarela Pendiente De Implementacion Concreta

Ahora mismo hay adaptador generico `hosted`, pero no hay adaptador especifico directo de Stripe/Redsys.

Lo que debe hacer la futura pasarela:

1. Crear una sesion de pago desde `musement_payment_create_session()`.
2. Guardar `order_reference` en metadata de la pasarela.
3. Recibir webhook seguro.
4. Verificar firma.
5. Confirmar que el pago esta `paid` o `succeeded`.
6. Buscar el pedido local.
7. Llamar:

```php
musement_payment_complete_order($order_id, $payment_reference);
```

Ejemplo conceptual para Stripe:

```text
checkout.session.completed
-> metadata.order_reference
-> buscar pedido local
-> musement_payment_complete_order($order_id, $session_id)
```

## Endpoints AJAX Actuales

Actividades:

```text
musement_activities
```

Disponibilidad:

```text
musement_availability
```

Carrito:

```text
musement_cart_add
musement_cart_get
musement_cart_remove
```

Checkout:

```text
musement_checkout_start
```

Confirmacion de prueba para admin:

```text
musement_checkout_test_paid
```

`musement_checkout_test_paid` no es un webhook real. Solo sirve para pruebas manuales con un usuario administrador.

## Archivos Principales

```text
musement-integration.php      Bootstrap, shortcodes, assets.
includes/api.php              Cliente basico de actividades.
includes/ajax.php             Actividades y disponibilidad.
includes/cart.php             Carrito local en sesion.
includes/checkout.php         Inicio de checkout y confirmacion de prueba.
includes/orders.php           Tabla tecnica de pedidos.
includes/payments.php         Capa de pago y sincronizacion post-pago.
includes/payment-webhooks.php Webhook REST generico de pasarela.
includes/payment-hosted.php   Adaptador para plataforma de pago hospedada.
includes/salesforce.php       Envio de JSON a Salesforce.
includes/musement-sales.php   Envio de compra a Musement/Sales.
assets/frontend.js            UI de tours, carrito y checkout.
assets/frontend.css           Estilos frontend.
```

## Pendientes Recomendados

1. Confirmar endpoint real de creacion de checkout hospedado.
2. Confirmar formato exacto de respuesta de la plataforma.
3. Confirmar firma real del webhook.
4. Confirmar payload final de Salesforce.
5. Confirmar payload final de Musement/Sales.
6. Anadir pantalla admin simple para ver pedidos con `integration_failed`, si Salesforce no cubre ese monitoreo.
