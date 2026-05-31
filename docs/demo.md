# Demo guide

Commands for a local walkthrough of `stockflow-delivery-mock`. Assumes the
repository root as working directory.

Part of the StockFlow ecosystem:

- [stockflow-market](https://github.com/Smiley-Alyx/stockflow-market)
- [stockflow-erp-mock](https://github.com/Smiley-Alyx/stockflow-erp-mock)
- [stockflow-payment-mock](https://github.com/Smiley-Alyx/stockflow-payment-mock)
- [stockflow-delivery-mock](https://github.com/Smiley-Alyx/stockflow-delivery-mock)

## Prerequisites

- PHP 8.3+
- Composer
- Docker and Docker Compose (for RabbitMQ stack)

## Quick start

Install dependencies and hooks:

```bash
make install
```

Run tests:

```bash
make test
```

Start HTTP only (in-memory, no RabbitMQ):

```bash
make serve
```

Start full stack (HTTP + worker + RabbitMQ):

```bash
make docker-up
```

| Service | URL |
| --- | --- |
| HTTP API | http://localhost:8082 |
| RabbitMQ AMQP | localhost:5674 |
| RabbitMQ UI | http://localhost:15674 (user/pass: `stockflow`) |

Stop:

```bash
make docker-down
```

## Health and metrics

```bash
curl -s http://localhost:8082/health | jq
curl -s http://localhost:8082/ready | jq
curl -s http://localhost:8082/metrics | head -20
```

## HTTP shipment debug

Create a shipment through the lifecycle service is normally driven by RabbitMQ.
For HTTP-only inspection, advance an existing shipment or use integration tests.

List shipments (HTTP process memory):

```bash
curl -s http://localhost:8082/shipments | jq
```

Advance status (requires an existing `shipment_id`):

```bash
curl -s -X POST http://localhost:8082/shipments/shp_demo_001/advance-status | jq
```

Mark delivered:

```bash
curl -s -X POST http://localhost:8082/shipments/shp_demo_001/mark-delivered | jq
```

Cancel:

```bash
curl -s -X POST http://localhost:8082/shipments/shp_demo_001/cancel | jq
```

## Failure mode demo

Debug endpoints require `DELIVERY_MOCK_DEBUG_ENABLED=true` (enabled in
Docker Compose by default).

Show current mode:

```bash
curl -s http://localhost:8082/debug/failure-mode | jq
```

Simulate provider outage (messages go to retry queue):

```bash
curl -s -X POST http://localhost:8082/debug/failure-mode \
  -H 'Content-Type: application/json' \
  -d '{"mode":"provider_unavailable"}'
```

Simulate address rejection:

```bash
curl -s -X POST http://localhost:8082/debug/failure-mode \
  -H 'Content-Type: application/json' \
  -d '{"mode":"invalid_address"}'
```

Reset shipments, idempotency, published events, and failure mode:

```bash
curl -s -X POST http://localhost:8082/debug/reset | jq
```

Return to normal processing:

```bash
curl -s -X POST http://localhost:8082/debug/failure-mode \
  -H 'Content-Type: application/json' \
  -d '{"mode":"normal"}'
```

## RabbitMQ messaging demo

Example payload: [`contracts/examples/delivery.shipment.requested.v1.json`](../contracts/examples/delivery.shipment.requested.v1.json)

Publish a create request from the host (with Docker stack running):

```bash
docker compose exec rabbitmq rabbitmqadmin publish \
  exchange=stockflow.delivery \
  routing_key=delivery.shipment.requested.v1 \
  payload='{"shipment_id":"shp_demo_001","order_id":"ord_demo_001","delivery_address":{"recipient_name":"Jane Doe","country_code":"RU","city":"Moscow","postal_code":"101000","street_line1":"Red Square 1"},"carrier_profile":{"carrier_code":"stockflow-express","service_level":"standard"}}' \
  properties='{"headers":{"message_id":"msg_demo_001","correlation_id":"cor_demo_001","causation_id":"msg_order_001","idempotency_key":"idem-shp-demo-001","schema_version":"v1","occurred_at":"2026-05-31T10:20:00Z","producer":"stockflow-market"}}'
```

Watch worker logs:

```bash
docker compose logs -f delivery-mock-worker
```

Inspect queues in the management UI or CLI:

```bash
docker compose exec rabbitmq rabbitmqadmin list queues name messages
```

Expected happy-path events on the exchange (marketplace would consume these):

- `delivery.shipment.created.v1`
- `delivery.shipment.status_changed.v1`

Publish cancel:

```bash
docker compose exec rabbitmq rabbitmqadmin publish \
  exchange=stockflow.delivery \
  routing_key=delivery.shipment.cancel_requested.v1 \
  payload='{"shipment_id":"shp_demo_001","order_id":"ord_demo_001","reason":"customer_cancelled"}' \
  properties='{"headers":{"message_id":"msg_cancel_001","correlation_id":"cor_demo_001","causation_id":"msg_demo_001","idempotency_key":"idem-cancel-demo-001","schema_version":"v1","occurred_at":"2026-05-31T10:25:00Z","producer":"stockflow-market"}}'
```

## Idempotency demo

Send the same create request twice with the same `idempotency_key` but different
`message_id` values. The worker should:

- create exactly one shipment
- publish `created` + `status_changed` on first attempt
- replay the same outbound `message_id`s on second attempt

Check metrics:

```bash
curl -s http://localhost:8082/metrics | grep delivery_idempotent_replays_total
```

## Retry / DLQ demo

1. Set failure mode to `provider_unavailable`
2. Publish a shipment request
3. Observe retry queue depth increasing, then requeue attempts in worker logs
4. Set failure mode back to `normal`
5. Confirm successful processing after requeue

Metrics to watch:

```bash
curl -s http://localhost:8082/metrics | grep -E 'delivery_request_(retries|retry_requeues|dlq)_total'
```

To force DLQ quickly, lower retries:

```bash
# in docker-compose environment
RABBITMQ_MAX_RETRY_ATTEMPTS=1
```

## Run consumer locally (without Docker worker)

With RabbitMQ reachable at `localhost:5674`:

```bash
export RABBITMQ_HOST=127.0.0.1
export RABBITMQ_PORT=5674
export DELIVERY_MOCK_DEBUG_ENABLED=true
make consume
```

## Contract validation

Optional AsyncAPI validation:

```bash
asyncapi validate contracts/asyncapi.yaml
```

## CI parity

Local checks matching GitHub Actions:

```bash
composer test
docker compose config --quiet
docker build -t stockflow-delivery-mock:local .
```

## Portfolio talking points

When presenting this service:

1. **Boundary isolation** — marketplace depends on contracts, not carrier internals
2. **Idempotency at two layers** — domain state and outbound events
3. **Explicit failure taxonomy** — invalid vs business vs transient
4. **Operability** — metrics, structured logs, debug failure modes
5. **Known limitation** — in-memory split between HTTP and worker; SQLite or
   shared DB is the natural next step for unified inspection

Further reading:

- [Architecture](architecture.md)
- [Delivery flow](delivery-flow.md)
- [Failure modes](failure-modes.md)
- [Contracts](../contracts/README.md)
