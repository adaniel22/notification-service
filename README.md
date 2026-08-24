# notification-service

Event consumer for the microservices webshop. It is one component of the stack orchestrated by [shop-infra](https://github.com/adaniel22/shop-infra).

## Overview

This is a **long-running worker, not a web server**. It subscribes to order events on NATS and processes them — currently by logging the order details, with real notification delivery (email, push) as the intended next step.

It is also the only non-NestJS service in the stack. `order-service` is TypeScript, this one is PHP/Laravel, and they share nothing but the NATS message contract — which is precisely the point: the event-driven design makes the services language-independent.

The service has **no database** and **no exposed port**. Its container's main process is the artisan command itself:

```bash
php artisan nats:listen-orders
```

The command opens a NATS connection, registers the subscription, and then loops on `$client->process(1)` indefinitely until the container is stopped.

## Where it fits

```
order-service  ──publish 'order.created'──▶  NATS  ──deliver──▶  notification-service
   (NestJS)                                                          (Laravel)
```

`order-service` publishes best-effort: if this consumer or the broker is down, ordering still succeeds. Events are consumed with a plain NATS subscription, so messages published while this service is offline are not replayed.

### The message contract

The subject is **`order.created`**. The NestJS client wraps every message in a `{ pattern, data }` envelope, so the payload has to be unwrapped before use:

```php
$envelope = json_decode($message->body, true);
$data = $envelope['data'] ?? [];
```

Fields read from `data`: `orderId`, `totalAmount`, `itemCount` (the publisher also sends `createdAt`). Each is defaulted, so a malformed message logs placeholders instead of throwing. Every event is written to the Laravel log via `logger()->info()` in addition to the console output.

## Configuration

NATS connection settings come from `NATS_HOST` and `NATS_PORT`, read through `config/nats.php`:

```php
return [
    'host' => env('NATS_HOST', 'localhost'),
    'port' => (int) env('NATS_PORT', 4222),
];
```

The command reads `config('nats.host')` / `config('nats.port')` — never `env()` directly. This is a Laravel requirement, not a style preference: once the config is cached (`php artisan config:cache`), `env()` calls outside config files return `null`.

## Tech stack

- Laravel 13 (PHP 8.3)
- [`basis-company/nats`](https://github.com/basis-company/nats) — PHP NATS client
- Docker (`php:8.3-cli-alpine`)

## Running this service

Normally you do not start this service on its own — it runs as part of the Docker Compose stack in [shop-infra](https://github.com/adaniel22/shop-infra), which provides the NATS broker and injects the environment variables. See that repo for the full setup. There is no port mapping and no health endpoint; check that it is alive with `docker compose logs -f notification-service`.

In the stack it runs in production mode:

| Variable | Value in the stack |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Laravel application key — supply your own, never commit it |
| `LOG_CHANNEL` | `stderr`, so events land in the container log |
| `NATS_HOST` | `shop-nats` |
| `NATS_PORT` | `4222` |

For local development against a reachable NATS server:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan nats:listen-orders
```

Publish a test event on `order.created` (from `order-service` or the NATS CLI) and the details appear on stdout.
