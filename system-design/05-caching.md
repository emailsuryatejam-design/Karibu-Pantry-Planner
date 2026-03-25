# Caching

> **Sources:**
> - [awesome-system-design-resources: Caching](https://github.com/ashishps1/awesome-system-design-resources#caching)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Redis Documentation](https://redis.io/docs/)
> - [AWS ElastiCache Best Practices](https://docs.aws.amazon.com/AmazonElastiCache/latest/red-ug/best-practices.html)

Cache stores frequently accessed data in a fast-access layer to reduce latency and backend load.

## Caching Strategies

### Cache-Aside (Lazy Loading)
```
1. App checks cache
2. Cache miss → fetch from DB
3. Store in cache
4. Return data
```
- App controls caching logic
- Only caches what's requested
- Con: first request always slow (cold start); data can go stale

### Write-Through
```
1. App writes to cache
2. Cache synchronously writes to DB
3. Both always in sync
```
- Consistent, no stale data
- Con: write latency increased; caches data that may never be read

### Write-Behind (Write-Back)
```
1. App writes to cache
2. Cache asynchronously writes to DB later
```
- Best write performance
- Con: risk of data loss if cache fails before flush

### Read-Through
- Cache sits in front of DB; app always reads from cache
- Cache handles fetching from DB on miss
- Cache is the source of truth for reads

---

## Cache Eviction Policies

When cache is full, one of these determines what gets removed:

| Policy | Description | Best For |
|---|---|---|
| LRU (Least Recently Used) | Remove item not accessed longest | General purpose (most common) |
| LFU (Least Frequently Used) | Remove item accessed fewest times | When frequency matters more than recency |
| FIFO | Remove oldest inserted item | Simple, ordered queues |
| Random | Remove random item | When access patterns are uniform |
| TTL (Time-To-Live) | Remove after expiry time | When freshness matters (sessions, tokens) |

---

## Distributed Caching

When a single cache node isn't enough (too much data, too many reads):

### Strategies
- **Replication**: multiple cache nodes with same data — high availability, eventually consistent
- **Sharding/Partitioning**: data split across nodes — more capacity, consistent hashing for routing

### Tools
| Tool | Type | Key Features |
|---|---|---|
| **Redis** | In-memory key-value | Persistence, pub/sub, sorted sets, transactions, Lua scripts |
| **Memcached** | In-memory key-value | Simpler, multi-threaded, no persistence |
| **Hazelcast** | Distributed cache/compute | JVM-based, near-cache |

---

## Cache Invalidation

The hardest problem in caching.

### Strategies
1. **TTL-based**: set expiry, accept temporary stale data
2. **Event-driven**: on write to DB, publish event → cache deletes/updates key
3. **Write-through**: cache updated on every write (always fresh, but adds write latency)
4. **Cache-busting keys**: `asset.v3.css` — new URL = fresh cache

### The Cache Stampede Problem
Many requests hit cache simultaneously after expiry, all go to DB at once.

**Solutions:**
- **Probabilistic early expiration**: randomly expire before TTL ends
- **Mutex/lock**: only one request fetches from DB, others wait
- **Background refresh**: proactively refresh before expiry

---

## Where to Cache

```
Client Browser Cache
      ↓
  CDN Edge Cache       ← static assets, API responses
      ↓
  Load Balancer Cache
      ↓
  Application Cache    ← in-process (e.g., LRU HashMap)
      ↓
  Distributed Cache    ← Redis/Memcached
      ↓
  Database Query Cache ← Postgres query result cache
      ↓
     Database
```

### Cache Hit Rate
- Aim for > 95% hit rate for effective caching
- Monitor: cache hits, misses, evictions, memory usage

---

## What to Cache

Good candidates:
- Database query results
- Rendered HTML pages or fragments
- Session data
- User profiles, preferences
- Computed aggregates (leaderboard scores, counts)
- Static assets (via CDN)

Bad candidates:
- Highly dynamic data that changes per-request
- Sensitive data (passwords, PII) unless encrypted
- Very large objects (pushes useful data out)

---

## Redis Data Structures

| Structure | Commands | Use Case |
|---|---|---|
| String | GET, SET, INCR | Counters, sessions |
| Hash | HGET, HSET | User profiles |
| List | LPUSH, RPOP | Queues, activity feeds |
| Set | SADD, SISMEMBER | Unique visitors, tags |
| Sorted Set | ZADD, ZRANGE | Leaderboards, rate limiting |
| Bitmap | SETBIT, BITCOUNT | Daily active users |
| HyperLogLog | PFADD, PFCOUNT | Cardinality estimation |
