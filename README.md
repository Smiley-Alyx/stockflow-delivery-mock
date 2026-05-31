# Stockflow Delivery Mock

`stockflow-delivery-mock` is an external sandbox service that emulates a delivery
provider integration for the `stockflow-market` case study. It is **not** a real
carrier API: shipments, labels, and tracking events are simulated for local demos
and integration testing.

The service is built with PHP 8.3 and Slim. It communicates with the marketplace
through RabbitMQ and AsyncAPI contracts, and exposes an HTTP API for health
checks, shipment inspection, and debug tooling.

## StockFlow ecosystem

Part of the StockFlow ecosystem:

- [stockflow-market](https://github.com/Smiley-Alyx/stockflow-market) — marketplace backend case study
- [stockflow-erp-mock](https://github.com/Smiley-Alyx/stockflow-erp-mock) — external ERP / inventory integration mock
- [stockflow-payment-mock](https://github.com/Smiley-Alyx/stockflow-payment-mock) — external payment provider mock
- [stockflow-delivery-mock](https://github.com/Smiley-Alyx/stockflow-delivery-mock) — external delivery provider mock (this repository)

`stockflow-market` orchestrates checkout and order fulfillment. Each external mock
implements one provider boundary over RabbitMQ with AsyncAPI contracts, shared
header conventions (`correlation_id`, `idempotency_key`, `causation_id`), and
retry/DLQ handling:

| Service | Exchange | Responsibility |
| --- | --- | --- |
| [stockflow-erp-mock](https://github.com/Smiley-Alyx/stockflow-erp-mock) | `stockflow.inventory` | Reserve and release stock in the external ERP sandbox |
| [stockflow-payment-mock](https://github.com/Smiley-Alyx/stockflow-payment-mock) | `stockflow.payment` | Authorize, capture, and refund card payments |
| **stockflow-delivery-mock** (this repo) | `stockflow.delivery` | Create shipments and publish tracking status events |

A typical checkout in the case study chains these boundaries: the marketplace
reserves inventory, requests payment authorization (and later capture), then
requests shipment creation once the order is paid. The same `correlation_id`
ties messages across all three integrations so the market can reconstruct the
full order timeline.

See [`docs/architecture.md`](docs/architecture.md#stockflow-ecosystem) for the
end-to-end diagram and links to sibling repositories.

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

Run the RabbitMQ consumer locally:

```bash
make consume
```

Docker Compose starts a separate worker container that runs `bin/consume-requests.php`.

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
| `GET` | `/debug/failure-mode` | Show active provider failure simulation mode |
| `POST` | `/debug/failure-mode` | Set failure simulation mode |
| `GET` | `/metrics` | Prometheus metrics (`DELIVERY_MOCK_METRICS_ENABLED`) |

Additional messaging contract and portfolio docs live in [`docs/`](docs/) and
[`contracts/`](contracts/).

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
| `RABBITMQ_EXCHANGE` | `stockflow.delivery` | Topic exchange for delivery messages |
| `RABBITMQ_DLX` | `stockflow.delivery.dlx` | Dead-letter exchange |
| `RABBITMQ_REQUESTS_QUEUE` | `stockflow.delivery.requests` | Incoming request queue |
| `RABBITMQ_RETRY_QUEUE` | `stockflow.delivery.requests.retry` | Retry queue |
| `RABBITMQ_DLQ` | `stockflow.delivery.requests.dlq` | Dead-letter queue |
| `RABBITMQ_SETUP_TOPOLOGY` | `true` | Declare exchange/queues on startup |
| `RABBITMQ_PREFETCH_COUNT` | `1` | Consumer prefetch |
| `RABBITMQ_CONSUMER_TIMEOUT_SECONDS` | `30` | `wait()` timeout for graceful shutdown |
| `DELIVERY_MOCK_PUBLISH_EVENTS` | `true` | Enable outbound event publishing |
| `DELIVERY_MOCK_METRICS_ENABLED` | `true` | Expose Prometheus metrics at `/metrics` |
| `DELIVERY_MOCK_FAILURE_MODE_STATE_FILE` | `var/state/failure-mode.json` | Shared failure mode state path |
| `RABBITMQ_MAX_RETRY_ATTEMPTS` | `3` | Max retry attempts before DLQ |
| `RABBITMQ_RETRY_DELAY_MS` | `5000` | Delay before retry requeue |

## RabbitMQ consumer

The delivery mock consumes:

| Routing key | Handler |
| --- | --- |
| `delivery.shipment.requested.v1` | Create shipment in provider state |
| `delivery.shipment.cancel_requested.v1` | Cancel shipment when allowed |

The consumer validates contract headers, maps payloads to domain commands, and
acks successful processing. Invalid messages and processing failures are nacked
without requeue and routed to DLQ through queue dead-letter settings.

Graceful shutdown is supported via `SIGTERM`/`SIGINT` when the `pcntl` extension
is available.

### Outbound events

After processing incoming requests, the delivery mock publishes:

| Routing key | When |
| --- | --- |
| `delivery.shipment.created.v1` | Shipment accepted by provider |
| `delivery.shipment.creation_failed.v1` | Shipment creation rejected (contract mapper) |
| `delivery.shipment.status_changed.v1` | Status transition (auto label generation after create) |
| `delivery.shipment.cancelled.v1` | Shipment cancelled successfully |
| `delivery.shipment.cancel_failed.v1` | Cancel rejected (not found / invalid state) |

Outgoing headers copy `correlation_id` from the request, set `causation_id` to the
incoming `message_id`, and generate a new `message_id` per published event.

Set `DELIVERY_MOCK_PUBLISH_EVENTS=false` to disable RabbitMQ publishing in local
tests while keeping handler behavior.

## Tests

```bash
make test
```

## Documentation

| Document | Description |
| --- | --- |
| [`docs/architecture.md`](docs/architecture.md) | Components, layering, trade-offs |
| [`docs/delivery-flow.md`](docs/delivery-flow.md) | Sequence diagrams and status model |
| [`docs/failure-modes.md`](docs/failure-modes.md) | Simulated failures, retry/DLQ behavior |
| [`docs/demo.md`](docs/demo.md) | Local demo commands |

## Messaging contracts

AsyncAPI contract, JSON Schemas, and examples live in [`contracts/`](contracts/).
See [`contracts/README.md`](contracts/README.md) for routing keys, headers,
correlation/idempotency rules, and RabbitMQ topology.

## Portfolio scope

This repository is part of the [StockFlow ecosystem](#stockflow-ecosystem): a
highload-oriented marketplace backend case study with external service mocks.
It demonstrates async integration patterns, idempotent message handling,
retry/DLQ flows, failure simulation, and observability for an external delivery
provider boundary.
