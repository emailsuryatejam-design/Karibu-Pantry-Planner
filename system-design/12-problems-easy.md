# Easy System Design Problems

> **Sources:**
> - [AlgoMaster - System Design Problems (Easy)](https://algomaster.io/learn/system-design)
> - [awesome-system-design-resources: Easy Problems](https://github.com/ashishps1/awesome-system-design-resources#easy)
> - [Alex Xu — System Design Interview Vol. 1](https://www.amazon.com/System-Design-Interview-insiders-Second/dp/B08CMF2CQF)

Use the framework from `10-interview-framework.md` for all problems.

---

## 1. URL Shortener (e.g., bit.ly)

**Core Requirements:** Shorten a URL, redirect via short code.

### Key Design Decisions
- **Short code generation**: base62 encoding of auto-increment ID or random string (6-8 chars)
- **Storage**: simple key-value: `short_code → original_url`
- **Redirect**: return HTTP 301 (permanent, cached by browser) or 302 (temporary, tracks clicks)

### Schema
```
urls: { id, short_code, original_url, user_id, created_at, expiry }
```

### Architecture
```
Client → Load Balancer → URL Service → Redis (short_code → url)
                                     → MySQL (durable storage)
```

### Scale Considerations
- Cache hot URLs in Redis (most URLs are read-heavy after creation)
- Use a separate analytics service for click tracking (don't block redirects)
- CDN can't easily cache redirects — stay server-side

---

## 2. Key-Value Store

**Core Requirements:** GET(key), PUT(key, value), DELETE(key).

### Key Design Decisions
- **In-memory only** (like Redis) vs **persistent** (like LevelDB/RocksDB)
- **Data structure**: hash map for O(1) get/put
- **Replication**: leader-follower for durability
- **Partitioning**: consistent hashing across nodes

### Architecture
```
Client → Request Router (consistent hashing) → Node A
                                              → Node B
                                              → Node C
```

### Persistence (WAL)
Write-Ahead Log: every write appended to log before applying. On restart, replay log to restore state.

---

## 3. Rate Limiter

**Core Requirements:** Limit each user to N requests per time window.

### Algorithm: Token Bucket (recommended)
```
For each user:
  - Bucket capacity: 100 tokens
  - Refill rate: 10 tokens/second
  - Each request costs 1 token
  - Reject if bucket empty
```

### Implementation with Redis
```
SCRIPT:
  tokens = GET user:{id}:tokens
  last_refill = GET user:{id}:last_refill
  now = current_time
  refill_amount = (now - last_refill) * refill_rate
  tokens = min(capacity, tokens + refill_amount)
  if tokens >= 1: tokens -= 1; ALLOW
  else: REJECT
```

Use Lua script in Redis for atomicity.

### Headers to Return
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1704067200
```

---

## 4. Authentication Service

**Core Requirements:** Register, login, session management.

### JWT Flow
```
1. POST /login { username, password }
2. Server validates credentials
3. Issues JWT: { sub: userId, exp: +15min }
4. Client stores JWT (memory or httpOnly cookie)
5. Every request: Authorization: Bearer <JWT>
6. Server verifies signature (no DB lookup needed)
```

### Refresh Token Pattern
- Access token: short-lived (15 min)
- Refresh token: long-lived (30 days), stored in DB
- On access token expiry: use refresh token to get new access token

### Security Considerations
- Hash passwords with bcrypt/argon2 (never MD5/SHA1)
- Store refresh tokens in DB (enables revocation)
- httpOnly, Secure cookie flags for web
- Rate limit login endpoint

---

## 5. Content Delivery Network (CDN) Design

**Core Requirements:** Serve static assets with low latency globally.

### Architecture
```
Origin Server (source of truth)
      ↓
Multiple Edge PoPs (Points of Presence) globally
      ↓
Client fetches from nearest edge
```

### Cache Flow
1. User requests `cdn.example.com/image.jpg`
2. DNS resolves to nearest edge (GeoDNS)
3. Edge checks cache → hit: serve; miss: fetch from origin, cache, serve
4. Cache controlled by `Cache-Control: max-age=86400` headers

### Key Features to Discuss
- **Purge API**: invalidate cached content
- **Origin shielding**: only one edge fetches from origin on miss (reduces origin load)
- **SSL termination** at edge
- **Anycast routing**: same IP, routed to nearest server

---

## 6. Notification Service

**Core Requirements:** Send push, email, SMS notifications to users.

### Architecture
```
Event Source → Notification Service → Template Engine → Provider
                                                       → Email (SendGrid)
                                                       → SMS (Twilio)
                                                       → Push (FCM/APNs)
```

### Key Design Decisions
- **Message queue** between service and providers (handle provider downtime)
- **Template system**: store templates in DB, render with user-specific data
- **Priority queues**: transactional (password reset) vs marketing
- **User preferences**: respect opt-outs, channel preferences

### Reliability
- Retry with exponential backoff on provider failure
- Dead letter queue for permanently failed messages
- Deduplication: track `notification_id` to prevent duplicates
