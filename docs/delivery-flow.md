# Delivery flow

This document describes the shipment lifecycle as implemented by
`stockflow-delivery-mock`, including happy path, cancellation, idempotent
retries, and status progression.

Contract details live in [`contracts/README.md`](../contracts/README.md).

Part of the StockFlow ecosystem:
[stockflow-market](https://github.com/Smiley-Alyx/stockflow-market),
[stockflow-erp-mock](https://github.com/Smiley-Alyx/stockflow-erp-mock),
[stockflow-payment-mock](https://github.com/Smiley-Alyx/stockflow-payment-mock),
[stockflow-delivery-mock](https://github.com/Smiley-Alyx/stockflow-delivery-mock).

## Shipment statuses

```text
created
  -> label_generated -> picked_up -> in_transit -> out_for_delivery -> delivered

delivery_failed -> return_requested -> return_in_transit -> returned

created | label_generated | picked_up  -> cancelled   (before in_transit)
```

Terminal statuses: `delivered`, `returned`, `cancelled`.

After a successful create request, the worker automatically advances the
shipment from `created` to `label_generated` and publishes a
`delivery.shipment.status_changed.v1` event.

Further transitions are driven by:

- HTTP debug endpoints (`advance-status`, `mark-delivered`, `mark-failed`, `cancel`)
- Future marketplace-driven events (not implemented in this mock)

## Happy path — create shipment

```mermaid
sequenceDiagram
  participant M as stockflow-market
  participant Q as RabbitMQ
  participant W as delivery-mock worker
  participant D as Domain + events

  M->>Q: delivery.shipment.requested.v1
  Q->>W: consume message
  W->>D: validate headers + map payload
  D->>D: create shipment (created)
  D->>D: advance to label_generated
  D->>Q: delivery.shipment.created.v1
  D->>Q: delivery.shipment.status_changed.v1
  W->>Q: ack
```

Expected outbound sequence:

1. `delivery.shipment.created.v1`
2. `delivery.shipment.status_changed.v1` with `current_status = label_generated`

Header propagation rules:

- Outgoing events copy `correlation_id` from the request
- `causation_id` is set to the incoming `message_id`
- Each published event gets a new unique `message_id`

## Cancel flow

Cancellation is allowed while the shipment is in `created`, `label_generated`,
or `picked_up`. Attempts after `in_transit` produce
`delivery.shipment.cancel_failed.v1`.

```mermaid
sequenceDiagram
  participant M as stockflow-market
  participant Q as RabbitMQ
  participant W as delivery-mock worker

  M->>Q: delivery.shipment.cancel_requested.v1
  Q->>W: consume message
  alt cancel allowed
    W->>Q: delivery.shipment.cancelled.v1
    W->>Q: ack
  else invalid state / not found
    W->>Q: delivery.shipment.cancel_failed.v1
    W->>Q: ack
  end
```

## Idempotent retry

When the marketplace retries with the same `idempotency_key` for the same
shipment and operation:

```mermaid
sequenceDiagram
  participant M as stockflow-market
  participant W as delivery-mock worker
  participant I as Idempotency + event store

  M->>W: shipment.requested (idem_key = X, message_id = A)
  W->>I: record create + store outbound events
  W-->>M: created + status_changed

  M->>W: shipment.requested (idem_key = X, message_id = B)
  W->>I: find existing record
  W-->>M: replay same events (same message_ids as first attempt)
  Note over W: no duplicate shipment created
```

Domain idempotency prevents duplicate shipments. The published-event store
ensures outbound replays use the original event identities.

## Creation failure

When the provider rejects creation (validation failure mode or business rule):

```mermaid
sequenceDiagram
  participant M as stockflow-market
  participant W as delivery-mock worker

  M->>W: delivery.shipment.requested.v1
  W-->>M: delivery.shipment.creation_failed.v1
  Note over W: no shipment persisted
```

Common `failure_code` values: `address_invalid`, `creation_failed`.

## Invalid message

Malformed payloads or missing contract headers are rejected without retry:

```mermaid
sequenceDiagram
  participant Q as RabbitMQ
  participant W as delivery-mock worker

  Q->>W: invalid message
  W->>Q: basic_reject (no requeue)
  Note over W: delivery_invalid_messages_total++
```

## Transient failure and retry

Simulated provider outages (`provider_unavailable`, `timeout`, publish failure)
raise `RetryableMessageException`:

```mermaid
sequenceDiagram
  participant Q as RabbitMQ
  participant W as delivery-mock worker
  participant R as retry queue

  Q->>W: shipment.requested.v1
  W--xW: RetryableMessageException
  W->>R: publish with x-retry-count
  W->>Q: ack original

  R->>W: retry message (after delay)
  W->>Q: republish to stockflow.delivery
  W->>Q: ack retry message
```

After requeue, the message is processed again on the main requests queue. If
retries are exhausted, the message moves to DLQ.

## HTTP inspection flow

The HTTP API mirrors domain operations for local debugging:

| Action | Endpoint | Effect |
| --- | --- | --- |
| List | `GET /shipments` | All shipments in HTTP process memory |
| Detail | `GET /shipments/{id}` | Shipment + status history |
| Advance | `POST /shipments/{id}/advance-status` | Next default happy-path status |
| Deliver | `POST /shipments/{id}/mark-delivered` | Force `delivered` |
| Fail | `POST /shipments/{id}/mark-failed` | Force `delivery_failed` |
| Cancel | `POST /shipments/{id}/cancel` | Cancel when allowed |

Important: shipments created by the RabbitMQ worker are **not** visible on HTTP
until shared persistence is introduced. For messaging demos, rely on published
events and metrics; for HTTP demos, create shipments via debug endpoints or
seed through tests.

## Metrics emitted per flow

| Flow | Example metrics |
| --- | --- |
| Successful create | `delivery_requests_total{outcome="created"}`, `delivery_events_published_total` |
| Idempotent replay | `delivery_idempotent_replays_total` |
| Creation failed | `delivery_requests_total{outcome="creation_failed"}` |
| Retry scheduled | `delivery_request_retries_total` |
| Retry requeued | `delivery_request_retry_requeues_total` |
| DLQ | `delivery_request_dlq_total` |

See [`demo.md`](demo.md) for commands to observe these locally.
