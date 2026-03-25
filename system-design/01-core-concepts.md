# Core Concepts

> **Sources:**
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [awesome-system-design-resources: Core Concepts](https://github.com/ashishps1/awesome-system-design-resources#core-concepts)
> - [Designing Data-Intensive Applications (DDIA) — Martin Kleppmann](https://dataintensive.net/)

## Scalability

The ability of a system to handle increasing load by adding resources.

### Vertical Scaling (Scale Up)
- Add more power (CPU, RAM) to existing machine
- Limit: single point of failure, hardware ceiling
- Good for: databases with strong consistency needs

### Horizontal Scaling (Scale Out)
- Add more machines to distribute load
- Requires stateless services or distributed state management
- Good for: web servers, microservices, read replicas

### Scalability Bottlenecks
- **CPU-bound**: computation heavy — scale horizontally
- **I/O-bound**: database/disk/network — add caching, read replicas, async processing
- **Memory-bound**: large data in RAM — partition data, offload to disk/cache

---

## Availability

Percentage of time a system is operational.

```
Availability = Uptime / (Uptime + Downtime)
```

| SLA | Downtime/Year | Downtime/Month |
|---|---|---|
| 99% | 3.65 days | 7.2 hours |
| 99.9% | 8.7 hours | 43.8 minutes |
| 99.99% | 52 minutes | 4.4 minutes |
| 99.999% | 5.2 minutes | 26 seconds |

### Achieving High Availability
- **Redundancy**: eliminate single points of failure
- **Replication**: multiple copies of data
- **Failover**: automatic switching to backup
- **Health checks**: detect and replace unhealthy instances
- **Circuit breakers**: prevent cascade failures

### Availability Patterns
- **Active-Active**: all instances serve traffic simultaneously
- **Active-Passive**: standby instance takes over on failure

---

## CAP Theorem

A distributed system can guarantee only **2 of 3**:

- **C**onsistency: every read returns the most recent write
- **A**vailability: every request receives a response (not necessarily latest)
- **P**artition Tolerance: system continues despite network partitions

> In practice, partition tolerance is required. So real choice is **CP vs AP**.

| System Type | Examples | Trade-off |
|---|---|---|
| CP | HBase, Zookeeper, MongoDB (default) | Returns error when partitioned |
| AP | Cassandra, CouchDB, DynamoDB | Returns stale data when partitioned |

---

## Consistency Models

From strongest to weakest:

1. **Strong Consistency** — all reads reflect latest write. High latency.
2. **Sequential Consistency** — operations appear in a consistent order across nodes.
3. **Causal Consistency** — causally related operations are seen in order.
4. **Eventual Consistency** — all nodes converge to the same state given enough time. Best performance.

---

## Fault Tolerance

The ability to continue operating when components fail.

### Failure Types
- **Crash failures**: node stops responding
- **Omission failures**: messages dropped
- **Byzantine failures**: node sends incorrect data

### Strategies
- **Redundancy** at every layer (servers, databases, data centers)
- **Replication** with consensus (Raft, Paxos)
- **Graceful degradation**: serve reduced functionality rather than full outage
- **Bulkhead pattern**: isolate failures to prevent system-wide impact

---

## Latency vs Throughput

- **Latency**: time for one request to complete (milliseconds)
- **Throughput**: number of requests handled per unit time (req/sec)

They trade off: optimizing for throughput (batching) often increases per-request latency.

### Latency Numbers to Know

| Operation | Latency |
|---|---|
| L1 cache reference | 0.5 ns |
| Main memory reference | 100 ns |
| SSD random read | 16 µs |
| HDD random read | 4 ms |
| Same datacenter round trip | 0.5 ms |
| Cross-continent round trip | 150 ms |
