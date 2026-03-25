# Distributed Systems

> **Sources:**
> - [awesome-system-design-resources: Distributed Systems](https://github.com/ashishps1/awesome-system-design-resources#distributed-systems)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Google Raft Paper — In Search of an Understandable Consensus Algorithm](https://raft.github.io/raft.pdf)
> - [Google Paxos Paper](https://lamport.azurewebsites.net/pubs/paxos-simple.pdf)
> - [Amazon Dynamo Paper](https://www.allthingsdistributed.com/files/amazon-dynamo-sosp2007.pdf)
> - [Designing Data-Intensive Applications (DDIA) — Ch. 8, 9](https://dataintensive.net/)

## Consistent Hashing

Used to distribute data evenly across nodes while minimizing remapping when nodes are added/removed.

### How It Works
1. Map both nodes and keys onto a circular ring (0 to 2^32)
2. Each key is assigned to the first node clockwise from its position

### Adding/Removing Nodes
- Only keys between the new/removed node and its predecessor are remapped
- Traditional hashing: `key % N` — changing N remaps ALL keys

### Virtual Nodes (vnodes)
- Each physical node owns multiple positions on the ring
- More even distribution, better load balancing
- Used by: Cassandra (each node has 256 vnodes by default)

---

## Consensus Algorithms

How distributed nodes agree on a value even when some nodes fail.

### Raft
- Simpler than Paxos, more understandable
- Elects a **leader** who handles all writes
- Leader replicates to followers; commits after quorum (majority) acknowledges
- Leader election: timeout → candidate → vote request → becomes leader
- Used by: etcd, CockroachDB, Consul, TiKV

### Paxos
- More complex, academically foundational
- Roles: Proposer, Acceptor, Learner
- Phases: Prepare → Promise → Accept → Accepted
- Used by: Google Spanner, Chubby

### Quorum
- Write quorum W + Read quorum R > N (total replicas) guarantees reading latest write
- Example: N=3, W=2, R=2 → R+W=4 > 3 ✓
- DynamoDB, Cassandra use tunable quorum

---

## Gossip Protocol

How nodes in a cluster share state information without a central coordinator.

### How It Works
1. Periodically, each node selects K random peers and shares its state
2. Peers merge received state with their own
3. Information propagates through cluster exponentially fast

### Properties
- **Scalable**: O(log N) rounds to reach all nodes
- **Fault-tolerant**: no single point of failure
- **Eventually consistent**: all nodes converge to same state

### Used By
- Cassandra (membership, failure detection)
- DynamoDB
- AWS S3

---

## Circuit Breaker

Prevents cascade failures when a downstream service is failing.

### States
```
CLOSED → (failures exceed threshold) → OPEN → (timeout) → HALF-OPEN
                                                               ↓
                                              success → CLOSED | failure → OPEN
```

- **Closed**: requests pass through normally
- **Open**: requests fail immediately (no waiting), fallback triggered
- **Half-Open**: limited requests probe if service recovered

### Implementation
- Count failures in sliding window
- Trip open after N failures in T seconds
- Reset after timeout + successful probe

### Tools
- Netflix Hystrix (legacy), Resilience4j (Java), Polly (.NET)

---

## Service Discovery

How services find each other in a dynamic cluster where IPs change.

### Client-Side Discovery
- Client queries service registry, gets list of instances, picks one
- Client does load balancing
- Example: Netflix Eureka + Ribbon

### Server-Side Discovery
- Client → Load Balancer → Service Registry lookup → Service
- Example: AWS ALB + ECS service discovery

### Service Registries
- **Consul**: health checking, DNS/HTTP interface
- **etcd**: key-value store used for config and discovery
- **Zookeeper**: distributed coordination (older, heavier)

---

## Distributed Tracing

Track a request as it flows through multiple services.

### Concepts
- **Trace**: full journey of one request (unique Trace ID)
- **Span**: one operation within a trace (has parent Span ID)
- **Context propagation**: trace ID passed in headers across services

### Tools
- **Jaeger**, **Zipkin** (open source)
- **AWS X-Ray**, **Datadog APM**, **Honeycomb**
- **OpenTelemetry**: vendor-neutral instrumentation standard

---

## Distributed Locking

Prevent concurrent operations on shared resources across multiple nodes.

### Redis-Based Lock (Redlock)
```
1. Try to SET key with NX (not exists) + EX (expiry)
2. If SET succeeds → you hold the lock
3. Release: delete key (only if you own it — use Lua script)
```

### Concerns
- **Clock drift**: use expiry, not absolute timestamps
- **Lock extension**: heartbeat to extend lock if still processing
- **Fencing token**: monotonically increasing token to detect stale lock holders

### Alternatives
- **Zookeeper/etcd**: stronger guarantees via consensus
- **Database row-level lock**: simpler, but DB becomes bottleneck

---

## Rate Limiting in Distributed Systems

Challenge: multiple servers each have separate counters.

### Solutions
- **Centralized store**: all servers check same Redis counter
  - Pro: accurate
  - Con: Redis becomes bottleneck, adds network hop
- **Sticky sessions**: same user always hits same server
  - Con: uneven load distribution
- **Approximate rate limiting**: each server tracks locally, periodic sync
  - Con: slight over/under counting at edges

---

## Health Checks & Monitoring

### Types
- **Liveness**: is the process alive? (restart if not)
- **Readiness**: is the service ready to receive traffic? (remove from LB if not)
- **Startup**: has the app finished initializing?

### Observability Pillars (Three Pillars)
1. **Metrics**: numeric measurements over time (latency, error rate, CPU) — Prometheus + Grafana
2. **Logs**: discrete events with context — ELK stack, Loki
3. **Traces**: request flow across services — Jaeger, Zipkin

### Key Metrics (USE Method)
- **U**tilization: % time resource is busy
- **S**aturation: work queued, unable to process
- **E**rrors: error rate
