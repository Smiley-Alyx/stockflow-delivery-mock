# Delivery messaging contracts

This folder contains the AsyncAPI contract and JSON Schemas for RabbitMQ
integration between `stockflow-market` and `stockflow-delivery-mock`.

## Layout

```text
contracts/
  asyncapi.yaml
  messages/
    common/
    delivery.*.v1.json
  examples/
    delivery.*.v1.json
```

## Message catalog

| Direction | Routing key | Producer | Consumer |
| --- | --- | --- | --- |
| Request | `delivery.shipment.requested.v1` | marketplace | delivery mock |
| Request | `delivery.shipment.cancel_requested.v1` | marketplace | delivery mock |
| Event | `delivery.shipment.created.v1` | delivery mock | marketplace |
| Event | `delivery.shipment.creation_failed.v1` | delivery mock | marketplace |
| Event | `delivery.shipment.status_changed.v1` | delivery mock | marketplace |
| Event | `delivery.shipment.cancelled.v1` | delivery mock | marketplace |
| Event | `delivery.shipment.cancel_failed.v1` | delivery mock | marketplace |

## RabbitMQ topology

| Resource | Name |
| --- | --- |
| Exchange | `stockflow.delivery` |
| Dead-letter exchange | `stockflow.delivery.dlx` |
| Request queue | `stockflow.delivery.requests` |
| Retry queue | `stockflow.delivery.requests.retry` |
| DLQ | `stockflow.delivery.requests.dlq` |

Routing keys match message names exactly.

## Required headers

Every message must carry these AMQP headers or JSON metadata headers:

| Header | Description |
| --- | --- |
| `message_id` | Unique ID of this message instance |
| `correlation_id` | Business correlation ID for the delivery flow |
| `causation_id` | `message_id` of the message that caused this one |
| `idempotency_key` | Caller-provided retry-safe key |
| `schema_version` | Payload schema version, currently `v1` |
| `occurred_at` | UTC timestamp in ISO-8601 |
| `producer` | Producing service name |

Schema: [`messages/common/message-headers.json`](messages/common/message-headers.json)

## Correlation and causation

Correlation is preserved end-to-end across shipment creation, status updates,
and cancellation.

```text
marketplace                         delivery mock
    |  shipment.requested              |
    |  correlation_id = cor_123        |
    | ------------------------------>  |
    |                                  |
    |  shipment.created                |
    |  correlation_id = cor_123        |
    |  causation_id = incoming msg_id  |
    | <------------------------------  |
    |                                  |
    |  shipment.status_changed         |
    |  correlation_id = cor_123        |
    |  causation_id = prior event id   |
    | <------------------------------  |
    |                                  |
    |  shipment.cancel_requested       |
    |  correlation_id = cor_123        |
    | ------------------------------>  |
    |                                  |
    |  shipment.cancelled              |
    |  correlation_id = cor_123        |
    |  causation_id = incoming msg_id  |
    | <------------------------------  |
```

Rules for outgoing events from the delivery mock:

1. Copy `correlation_id` from the incoming request unchanged.
2. Set `causation_id` to the incoming request `message_id`.
3. Generate a new unique `message_id` for every published event.
4. Reuse the incoming `idempotency_key` for the primary outcome event of the
   same operation, or derive a deterministic response key for status updates.

## Idempotency

The delivery mock treats `(shipment_id, operation, idempotency_key)` as the
idempotency scope:

| Operation | Example key |
| --- | --- |
| Create shipment | `idem-shp-ord_demo_001` |
| Cancel shipment | `idem-cancel-shp_demo_001` |
| Status update event | `idem-shp-status-shp_demo_001-label_generated` |

Retry behavior:

- Same `idempotency_key` for the same shipment and operation must return the
  same logical result.
- Duplicate create requests must not create duplicate shipments.
- Duplicate cancel requests must be safe and return the same cancellation outcome.
- Outbound result events are stored by operation scope. Retries republish the
  original event with the same `message_id` and payload so the marketplace can
  safely reconcile duplicate deliveries.

## Shipment statuses

Contract enum: [`messages/common/shipment-status.json`](messages/common/shipment-status.json)

```text
created
label_generated
picked_up
in_transit
out_for_delivery
delivered
delivery_failed
return_requested
return_in_transit
returned
cancelled
```

## Failure codes

Contract enum: [`messages/common/failure-code.json`](messages/common/failure-code.json)

Used by `delivery.shipment.creation_failed.v1` and
`delivery.shipment.cancel_failed.v1`.

## Examples

Full request/event examples with headers and payload live in
[`examples/`](examples/).

Happy path sequence:

1. `delivery.shipment.requested.v1`
2. `delivery.shipment.created.v1`
3. `delivery.shipment.status_changed.v1` (one or more transitions)
4. `delivery.shipment.status_changed.v1` with `current_status = delivered`

Cancel path:

1. `delivery.shipment.cancel_requested.v1`
2. `delivery.shipment.cancelled.v1`

Negative path examples:

- `delivery.shipment.creation_failed.v1`
- `delivery.shipment.cancel_failed.v1`

## Validation

JSON Schemas are in [`messages/`](messages/). They can be used by producers and
consumers independently of the AsyncAPI document.

```bash
# Optional local validation with AsyncAPI CLI
asyncapi validate contracts/asyncapi.yaml
```
