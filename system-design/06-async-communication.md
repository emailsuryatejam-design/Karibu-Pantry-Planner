# Async Communication

> **Sources:**
> - [awesome-system-design-resources: Message Queues](https://github.com/ashishps1/awesome-system-design-resources#message-queues)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Apache Kafka Documentation](https://kafka.apache.org/documentation/)
> - [Designing Data-Intensive Applications (DDIA) — Ch. 11](https://dataintensive.net/)

Decouples services so the sender doesn't wait for the receiver to finish processing.

## Message Queues

A queue where producers send messages and consumers read them.

```
Producer → [Message Queue] → Consumer
```

### Benefits
- **Decoupling**: services don't need to know about each other
- **Load leveling**: smooth out traffic spikes; consumers process at their own pace
- **Reliability**: messages persist until consumed
- **Retry**: failed messages can be retried

### Key Concepts
- **Producer**: writes messages to queue
- **Consumer**: reads and processes messages
- **Point-to-point**: one producer, one consumer per message
- **Dead Letter Queue (DLQ)**: where failed/unprocessable messages go after max retries

### Tools
| Tool | Best For |
|---|---|
| **RabbitMQ** | Task queues, routing, complex topologies |
| **Amazon SQS** | Managed, simple queuing, serverless |
| **Redis (as queue)** | Lightweight, fast, already in stack |

---

## Pub/Sub (Publish-Subscribe)

Publisher broadcasts messages to a topic; all subscribers receive them.

```
Publisher → [Topic] → Subscriber 1
                    → Subscriber 2
                    → Subscriber 3
```

vs. message queue: pub/sub delivers to ALL subscribers; queue delivers to ONE consumer.

### Use Cases
- Event broadcasting (user signed up → send email + create profile + notify admins)
- Real-time notifications
- Log aggregation
- Microservice event fanout

---

## Apache Kafka

Distributed event streaming platform. Not just a queue — a durable, ordered log.

### Core Concepts
- **Topic**: named stream of events
- **Partition**: topics split into ordered partitions for parallelism
- **Offset**: unique position of a message in a partition
- **Consumer Group**: multiple consumers share partitions for parallel processing
- **Broker**: Kafka server; cluster = multiple brokers
- **Retention**: messages kept for configurable period (not deleted after consumption)

```
Topic: user-events
  Partition 0: [msg1, msg4, msg7, ...]
  Partition 1: [msg2, msg5, msg8, ...]
  Partition 2: [msg3, msg6, msg9, ...]
```

### Why Kafka?
- **High throughput**: millions of messages/sec
- **Durability**: persisted to disk, replicated
- **Replay**: consumers can re-read old messages
- **Decoupled scaling**: producers and consumers scale independently

### Kafka vs Traditional Queue

| Feature | Kafka | RabbitMQ/SQS |
|---|---|---|
| Message retention | Configurable (days/weeks) | Deleted after consumption |
| Consumer replay | Yes | No |
| Throughput | Very high | Moderate |
| Complexity | Higher | Lower |
| Use case | Event streaming, audit logs | Task queues, RPC |

---

## CDC (Change Data Capture)

Captures every change (INSERT, UPDATE, DELETE) in a database and streams them as events.

```
Database → CDC Tool → Kafka Topic → Consumers
```

### Use Cases
- Sync primary DB to read replicas/search index (Elasticsearch)
- Build audit logs
- Invalidate caches on DB change
- Feed analytics pipelines

### Tools
- **Debezium** (Kafka Connect connector for Postgres, MySQL, MongoDB)
- **Maxwell's Daemon** (MySQL → Kafka)
- **AWS DMS** (managed CDC)

---

## Async Communication Patterns

### Fire and Forget
- Producer sends message and doesn't wait for response
- Use for: emails, notifications, logging

### Request-Reply via Queue
- Producer sends message with `reply_to` queue
- Consumer processes and sends response to that queue
- Use for: async RPC, background jobs with result retrieval

### Saga Pattern (Distributed Transactions)
- Long-running business process across multiple services, each step publishes events
- **Choreography**: each service reacts to events from others (decentralized)
- **Orchestration**: central saga orchestrator tells each service what to do

### Outbox Pattern
- Prevents dual-write problem (write to DB + publish to queue)
- Write to DB + an outbox table in same transaction
- Separate process reads outbox and publishes to queue
- Guarantees at-least-once delivery

---

## Delivery Guarantees

| Guarantee | Behavior | Risk |
|---|---|---|
| At-most-once | Message sent once, may be lost | Data loss |
| At-least-once | Retries until ACK'd, may duplicate | Duplicate processing |
| Exactly-once | Delivered exactly once | Most complex, highest overhead |

Most systems implement **at-least-once + idempotent consumers** as the practical approach.
