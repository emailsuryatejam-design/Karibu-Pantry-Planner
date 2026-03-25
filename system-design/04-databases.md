# Database Fundamentals

> **Sources:**
> - [awesome-system-design-resources: Database Fundamentals](https://github.com/ashishps1/awesome-system-design-resources#databases)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Designing Data-Intensive Applications (DDIA) — Ch. 3, 5, 6](https://dataintensive.net/)
> - [Amazon Dynamo Paper](https://www.allthingsdistributed.com/files/amazon-dynamo-sosp2007.pdf)
> - [Google Bigtable Paper](https://static.googleusercontent.com/media/research.google.com/en//archive/bigtable-osdi06.pdf)

## SQL vs NoSQL

| Feature | SQL (Relational) | NoSQL |
|---|---|---|
| Schema | Fixed, structured | Flexible, dynamic |
| Relationships | Joins across tables | Denormalized, embedded |
| ACID | Full support | Varies (often eventual) |
| Scaling | Vertical (harder to shard) | Horizontal (built for scale) |
| Query | SQL (expressive) | Limited / custom |
| Use case | Complex queries, transactions | High write volume, flexible schema |

### When to Use SQL
- Banking, payments (strong ACID required)
- Complex reporting and analytics
- Relationships between entities are important
- Schema is stable

### When to Use NoSQL
- High write throughput (millions of events/sec)
- Schema changes frequently
- Data is document-like or key-value
- Geographic distribution / multi-region writes

---

## NoSQL Types

| Type | Examples | Best For |
|---|---|---|
| Key-Value | Redis, DynamoDB | Sessions, caching, leaderboards |
| Document | MongoDB, Firestore | User profiles, catalogs, CMS |
| Wide-Column | Cassandra, HBase | Time-series, IoT, event logs |
| Graph | Neo4j, Amazon Neptune | Social networks, recommendation engines |
| Time-Series | InfluxDB, TimescaleDB | Metrics, monitoring |

---

## ACID Properties

| Property | Meaning |
|---|---|
| **A**tomicity | All operations in a transaction succeed, or none do |
| **C**onsistency | Database moves from one valid state to another |
| **I**solation | Concurrent transactions don't interfere |
| **D**urability | Committed transactions survive crashes |

### Isolation Levels (from weakest to strongest)
1. **Read Uncommitted** — dirty reads possible
2. **Read Committed** — no dirty reads, non-repeatable reads possible
3. **Repeatable Read** — same rows, phantom rows possible
4. **Serializable** — full isolation, lowest concurrency

---

## Indexing

Indexes trade write performance for read performance.

### B-Tree Index
- Default index type in most databases
- Good for: range queries, sorting, exact match
- Operations: O(log n)

### Hash Index
- Exact match only (no range queries)
- O(1) lookups
- Used by: Redis, some in-memory stores

### Composite Index
- Index on multiple columns: `(user_id, created_at)`
- Follow the **left-prefix rule**: query must use leftmost columns
- Column order matters: put high-cardinality, frequently-filtered columns first

### Full-Text Index
- Tokenizes text for search queries
- Used by: Elasticsearch, PostgreSQL `tsvector`

### Covering Index
- Index contains all columns needed for a query — no table lookup required

---

## Replication

Copying data across multiple nodes for availability and read scaling.

### Leader-Follower (Primary-Replica)
- All writes go to leader
- Followers replicate and serve reads
- Failover: promote a follower to leader on failure

### Replication Modes
| Mode | Behavior | Trade-off |
|---|---|---|
| Synchronous | Leader waits for follower to confirm write | Strong consistency, higher latency |
| Asynchronous | Leader doesn't wait | Lower latency, potential data loss on failover |
| Semi-synchronous | Wait for at least 1 follower | Balance |

### Multi-Leader / Active-Active
- Multiple nodes accept writes
- Conflict resolution needed (last-write-wins, CRDTs, application logic)

### Leaderless (Quorum)
- Any node accepts writes
- Quorum: write to W nodes, read from R nodes where W + R > N
- Used by: Cassandra, DynamoDB

---

## Sharding (Horizontal Partitioning)

Splitting data across multiple database nodes by a **shard key**.

### Sharding Strategies

**Range-Based**
- Shard by value range: users A–M on shard 1, N–Z on shard 2
- Pro: good for range queries
- Con: hotspots (all new users on same shard)

**Hash-Based**
- `shard = hash(key) % num_shards`
- Pro: even distribution
- Con: range queries require scatter-gather; resharding is expensive

**Directory-Based**
- Lookup table maps keys to shards
- Pro: flexible
- Con: lookup table is a bottleneck/SPOF

### Consistent Hashing
- Nodes and keys mapped to a ring
- Adding/removing nodes only remaps keys of neighboring nodes (vs. rehashing all keys)
- Used by: Cassandra, DynamoDB, Memcached

---

## Bloom Filters

Probabilistic data structure to test whether an element is in a set.

- **False positives possible** (says "yes" when answer is "no")
- **No false negatives** (if it says "no", definitely not in set)
- Very memory efficient
- Used by: Cassandra (avoid disk reads for missing keys), Chrome (safe browsing), HBase

---

## Database Patterns

### Read Replicas
- Offload read traffic from primary
- Eventual consistency: replica may lag by seconds

### CQRS (Command Query Responsibility Segregation)
- Separate write model (commands) from read model (queries)
- Enables optimized schemas for each

### Connection Pooling
- Reuse database connections instead of creating new ones per request
- Tools: PgBouncer (Postgres), HikariCP (Java)

### Database per Service (Microservices)
- Each service owns its data store
- No direct cross-service DB queries
- Join via API or event-driven sync
