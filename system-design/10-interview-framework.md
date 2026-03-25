# System Design Interview Framework

> **Sources:**
> - [AlgoMaster - System Design Interview Course](https://algomaster.io/learn/system-design)
> - [awesome-system-design-resources: Interview Problems](https://github.com/ashishps1/awesome-system-design-resources#system-design-interview-problems)
> - [Grokking the System Design Interview](https://www.designgurus.io/course/grokking-the-system-design-interview)
> - [Alex Xu — System Design Interview Vol. 1 & 2](https://www.amazon.com/System-Design-Interview-insiders-Second/dp/B08CMF2CQF)

Most people fail not because they lack knowledge — they jump straight to architecture for 1,000,000 users without first understanding requirements. This framework fixes that.

## The 5-Step Framework

```
1. Clarify Requirements       (5 min)
2. Estimate Scale             (3 min)
3. Define APIs                (5 min)
4. Design the Data Model      (5 min)
5. High-Level Architecture    (15 min)
   → Deep Dives on components (10 min)
```

---

## Step 1: Clarify Requirements

Never assume. Ask questions before drawing anything.

### Functional Requirements
- What are the core features? (ask to prioritize — pick top 3)
- Who are the users? (end consumers, internal services, 3rd parties?)
- What actions can they perform?
- Are there different user roles/permissions?

### Non-Functional Requirements
- **Scale**: DAU (daily active users)? Reads/writes per second?
- **Latency**: what's acceptable? Real-time? Near real-time?
- **Availability**: 99.9%? 99.99%?
- **Consistency**: strong or eventual?
- **Durability**: can we lose any data?
- **Data size**: how large are individual records? Total storage?

### Common Clarifying Questions
- "Should I focus on the happy path first or handle edge cases?"
- "Is this a read-heavy or write-heavy system?"
- "Do we need global distribution or single region?"
- "What is the read/write ratio?"

---

## Step 2: Estimate Scale

Back-of-envelope calculations to guide your design decisions.

### Useful Numbers
- 1 DAU = ~10 requests/day on average
- 100M DAU × 10 req = 1B req/day ÷ 86,400 sec ≈ **~12,000 RPS**
- Typical server handles 1K–10K RPS depending on workload
- 1 char = 1 byte; 1 tweet = ~300 bytes; 1 photo = ~300KB; 1 video = ~50MB

### Storage Estimation Template
```
Daily writes × record size × retention period = total storage
Example: 1M new users/day × 1KB profile = 1GB/day = ~365GB/year
```

### Bandwidth Estimation
```
Peak RPS × average payload size = bandwidth
Example: 10K RPS × 10KB = 100MB/s = 800Mbps
```

---

## Step 3: Define APIs

Establishes the contract between client and system. Makes the design concrete.

### Template
```
GET    /resource/{id}              → Read
POST   /resource                   → Create
PUT    /resource/{id}              → Full Update
PATCH  /resource/{id}              → Partial Update
DELETE /resource/{id}              → Delete
```

### Example: URL Shortener APIs
```
POST /shorten
Body: { "url": "https://long.example.com/path" }
Response: { "short_url": "https://short.ly/abc123" }

GET /{short_code}
Response: 301 Redirect to original URL
```

Include: auth headers, pagination params, error responses.

---

## Step 4: Design the Data Model

Identify entities, their relationships, and how they'll be stored.

### Questions to Answer
- What are the main entities?
- What are the relationships (1:1, 1:N, N:M)?
- What queries will be most common? (drives schema design)
- SQL or NoSQL? (use trade-offs from `09-tradeoffs.md`)

### Example: Twitter-like System
```
User: { id, username, email, created_at }
Tweet: { id, user_id, content, created_at, like_count }
Follow: { follower_id, followee_id, created_at }
Like: { user_id, tweet_id, created_at }
```

---

## Step 5: High-Level Architecture

Start simple, then add complexity only where justified.

### Template Components to Consider
```
Clients (Web/Mobile)
    ↓
CDN (static assets)
    ↓
Load Balancer
    ↓
API Gateway (auth, rate limiting)
    ↓
Services (stateless, horizontally scalable)
    ↓
Cache (Redis) ←→ Database (primary)
                       ↓
                  Read Replicas
                       ↓
                Message Queue (Kafka)
                       ↓
                Async Workers
                       ↓
                Object Storage (S3)
```

### How to Walk Through It
1. Start from the client
2. Trace a single request end-to-end
3. Identify where state lives
4. Identify potential bottlenecks

---

## Deep Dive Areas

After the high-level, interviewer will pick specific areas. Be ready to go deep on:

- **Database design**: indexes, sharding strategy, schema
- **Caching**: what to cache, where, invalidation strategy
- **Scaling bottlenecks**: where is the single point of failure?
- **Real-time features**: WebSockets, pub/sub
- **Failure handling**: what happens when service X goes down?
- **Consistency**: how do we handle concurrent writes?

---

## Scale-Up Checklist

When asked "how would this handle 10x scale?":

- [ ] Add CDN for static assets
- [ ] Add read replicas for DB
- [ ] Add caching layer (Redis)
- [ ] Horizontally scale stateless services
- [ ] Move to async for non-critical operations
- [ ] Partition/shard the database
- [ ] Add message queue to decouple services
- [ ] Add rate limiting at API gateway

---

## Common Mistakes to Avoid

| Mistake | Fix |
|---|---|
| Jumping to architecture immediately | Always clarify requirements first |
| Designing for 1M users from day 1 | Design for current scale, show how to scale |
| Using one DB for everything | Match storage to access patterns |
| Ignoring failure modes | Always address SPOF |
| Over-engineering | Start simple, justify complexity |
| Not quantifying estimates | Give numbers, even rough ones |
