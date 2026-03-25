# System Design Trade-offs

> **Sources:**
> - [awesome-system-design-resources: System Design Tradeoffs](https://github.com/ashishps1/awesome-system-design-resources#system-design-tradeoffs)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [CAP Theorem — Eric Brewer's original talk](https://people.eecs.berkeley.edu/~brewer/cs262b-2004/PODC-keynote.pdf)
> - [Designing Data-Intensive Applications (DDIA)](https://dataintensive.net/)

The core skill in system design is making informed trade-offs. There's no universally right answer — context determines the choice.

---

## Consistency vs Availability (CAP)

| Choice | Pick When |
|---|---|
| Consistency (CP) | Financial transactions, inventory counts, distributed locks |
| Availability (AP) | Social feeds, caching, analytics, search |

---

## Latency vs Consistency

| Trade-off | Explanation |
|---|---|
| Synchronous replication | Strong consistency, higher write latency |
| Asynchronous replication | Low write latency, risk of data loss/stale reads |
| Eventual consistency | Best performance, short window of inconsistency |

---

## SQL vs NoSQL

| Choose SQL When | Choose NoSQL When |
|---|---|
| ACID transactions required | Schema is flexible / changes often |
| Complex joins and queries | Massive scale, high write throughput |
| Stable, relational data model | Key-value, document, or time-series data |
| Regulatory compliance | Multi-region, globally distributed writes |

---

## Normalization vs Denormalization

| Approach | Trade-off |
|---|---|
| Normalized (3NF) | Less storage, harder to query (joins), consistent writes |
| Denormalized | Faster reads, more storage, risk of inconsistency |

Rule of thumb: normalize for write-heavy systems, denormalize for read-heavy systems.

---

## Push vs Pull

| Model | How It Works | Use When |
|---|---|---|
| Push | Server sends data to client when available | Real-time updates (notifications, chat) |
| Pull | Client polls server for new data | Infrequent updates, simpler infrastructure |
| Hybrid | Client pulls to sync, server pushes for real-time | Most production systems |

---

## Horizontal vs Vertical Scaling

| Approach | Pro | Con | Use When |
|---|---|---|---|
| Vertical | Simple, no code changes | Hardware ceiling, SPOF | Early stage, stateful DBs |
| Horizontal | Near-unlimited scale | Requires stateless design | Web servers, microservices |

---

## Monolith vs Microservices

| Choose Monolith When | Choose Microservices When |
|---|---|
| Small team, early product | Large teams with independent domains |
| Simple domain | Some components need very different scaling |
| Fast iteration needed | Deployment independence is valuable |
| Don't know your boundaries yet | Teams need technology flexibility |

---

## Synchronous vs Asynchronous Communication

| Synchronous | Asynchronous |
|---|---|
| Simple, request-response | Decoupled, resilient |
| Caller blocked until response | Caller continues immediately |
| Tight coupling | Better for high-throughput workloads |
| Good for real-time user actions | Good for background jobs, events |

---

## Strong vs Eventual Consistency

| Strong | Eventual |
|---|---|
| All reads see latest write | Reads may temporarily return stale data |
| Higher latency | Lower latency, higher availability |
| Use: banking, inventory | Use: social media, DNS, caching |

---

## Read-Heavy vs Write-Heavy System Design

| System Type | Optimizations |
|---|---|
| Read-heavy | Add caches, read replicas, CDN, denormalize |
| Write-heavy | Batch writes, async processing, LSM-tree stores (Cassandra), partition by write key |
| Mixed | CQRS — separate read/write paths |

---

## Storage Trade-offs

| Trade-off | Details |
|---|---|
| Block storage vs Object storage | Block: low latency, structured (EBS, SAN). Object: cheap, scalable, unstructured (S3, GCS) |
| In-memory vs Disk | Memory: fast, expensive, volatile. Disk: slow, cheap, durable |
| SSD vs HDD | SSD: ~100x faster random reads, higher cost. HDD: large sequential storage |

---

## Network Trade-offs

| Trade-off | Details |
|---|---|
| TCP vs UDP | Reliability vs speed (use UDP for streaming/gaming) |
| REST vs gRPC | Simplicity/interoperability vs efficiency/speed |
| Polling vs WebSockets | Simple infra vs real-time |

---

## Summary: Questions to Ask Before Deciding

1. What is the read/write ratio?
2. How much data? How fast does it grow?
3. What is acceptable latency? (p50? p99?)
4. What is required availability SLA?
5. Does this need strong consistency or is eventual okay?
6. What are the failure modes and their impact?
7. What is the team's operational capacity?
