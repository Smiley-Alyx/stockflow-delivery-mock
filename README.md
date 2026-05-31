# Stockflow Delivery Mock

`stockflow-delivery-mock` is an external sandbox service that emulates a delivery
provider integration for the `stockflow-market` case study. It is **not** a real
carrier API: shipments, labels, and tracking events are simulated for local demos
and integration testing.

The service is built with PHP 8.3 and Slim. It communicates with the marketplace
through RabbitMQ and AsyncAPI contracts, and exposes an HTTP API for health
checks, shipment inspection, and debug tooling.

## Local run

```bash
make install
make serve
```

The HTTP server listens on `http://localhost:8082`.

## Git hooks

Install project hooks and the commit message template:

```bash
composer install-git-hooks
```

Commit messages must follow conventional commits as `type(scope): subject`, where
`scope` is required and written in kebab-case.

Examples:

```text
feat(shipment-lifecycle): add create and cancel shipment service
refactor(status-history): extract invalid transition handling
test(rabbitmq): cover shipment request happy path over amqp
docs(delivery-flow): describe shipment status progression sequence
infra(observability): add prometheus metrics endpoint
chore(bootstrap): initialize delivery mock service
```

## Docker Compose

Start the service with a local RabbitMQ instance:

```bash
make docker-up
```

| Service | URL |
| --- | --- |
| Delivery mock HTTP | `http://localhost:8082` |
| RabbitMQ AMQP | `localhost:5674` |
| RabbitMQ management UI | `http://localhost:15674` |

Use `stockflow` as both username and password for the local RabbitMQ environment.

Stop the containers:

```bash
make docker-down
```

## HTTP endpoints

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/` | Service metadata |
| `GET` | `/health` | Liveness probe |
| `GET` | `/ready` | Readiness probe |
| `GET` | `/shipments` | List shipments |
| `GET` | `/shipments/{shipment_id}` | Shipment details |
| `POST` | `/shipments/{shipment_id}/advance-status` | Advance shipment to the next default status |
| `POST` | `/shipments/{shipment_id}/mark-delivered` | Mark shipment as delivered |
| `POST` | `/shipments/{shipment_id}/mark-failed` | Mark shipment as delivery failed |
| `POST` | `/shipments/{shipment_id}/cancel` | Cancel shipment |
| `POST` | `/debug/reset` | Clear in-memory shipment state (requires debug mode) |

Additional debug, metrics, and messaging endpoints will be added in later steps.

## Configuration

| Variable | Default | Description |
| --- | --- | --- |
| `DELIVERY_MOCK_SERVICE_NAME` | `stockflow-delivery-mock` | Service identifier in responses and logs |
| `DELIVERY_MOCK_HTTP_PORT` | `8080` | HTTP port inside the container |
| `DELIVERY_MOCK_DEBUG_ENABLED` | `false` | Enable verbose error responses |
| `RABBITMQ_HOST` | `rabbitmq` | RabbitMQ host |
| `RABBITMQ_PORT` | `5672` | RabbitMQ AMQP port |
| `RABBITMQ_USER` | `stockflow` | RabbitMQ username |
| `RABBITMQ_PASSWORD` | `stockflow` | RabbitMQ password |
| `RABBITMQ_VHOST` | `/` | RabbitMQ virtual host |

## Tests

```bash
make test
```

## Portfolio scope

This repository is part of a highload-oriented marketplace backend case study.
It demonstrates async integration patterns, idempotent message handling,
retry/DLQ flows, failure simulation, and observability for an external delivery
provider boundary.
