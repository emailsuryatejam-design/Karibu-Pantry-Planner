# API Fundamentals

> **Sources:**
> - [awesome-system-design-resources: API Fundamentals](https://github.com/ashishps1/awesome-system-design-resources#api-design)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Stripe API Design Principles](https://stripe.com/blog/idempotency)
> - [RFC 6749 — OAuth 2.0](https://datatracker.ietf.org/doc/html/rfc6749)

## REST vs GraphQL vs gRPC

| Feature | REST | GraphQL | gRPC |
|---|---|---|---|
| Protocol | HTTP | HTTP | HTTP/2 |
| Data format | JSON/XML | JSON | Protobuf (binary) |
| Over/under-fetching | Common problem | Solved by client queries | N/A |
| Schema | OpenAPI/Swagger | Typed schema | .proto file |
| Real-time | Polling/WebSockets needed | Subscriptions | Streaming |
| Use case | Public APIs, CRUD | Flexible client needs (mobile) | Internal microservices |

### REST Best Practices
- Use nouns for resources: `/users`, `/orders/{id}`
- Use HTTP methods correctly (GET idempotent, POST not)
- Version your API: `/v1/users`
- Use appropriate status codes
- Pagination: `?page=2&limit=20` or cursor-based

### GraphQL Key Concepts
- **Query**: read data
- **Mutation**: write data
- **Subscription**: real-time updates
- Solves N+1 problem with DataLoader batching
- Tradeoff: complex queries can hammer databases

---

## WebSockets

Full-duplex, persistent connection between client and server over a single TCP connection.

### How it Works
1. Client sends HTTP upgrade request
2. Server responds with `101 Switching Protocols`
3. Bidirectional messages flow until either side closes

### When to Use
- Real-time chat (WhatsApp, Slack)
- Live notifications
- Collaborative editing (Google Docs)
- Live sports scores, stock tickers

### WebSocket vs Polling vs SSE

| Method | Direction | Use Case |
|---|---|---|
| Short Polling | Client pulls periodically | Simple, infrequent updates |
| Long Polling | Client waits for server response | Semi-real-time, simpler infra |
| SSE (Server-Sent Events) | Server → Client only | Notifications, live feeds |
| WebSockets | Bidirectional | Chat, real-time collaboration |

---

## Webhooks

Server-to-server HTTP callbacks. Server A notifies Server B when an event occurs.

```
Payment Provider ──POST /webhook──▶ Your Server
                 { event: "payment.completed", ... }
```

### Design Considerations
- **Retry logic**: sender must retry on failure (exponential backoff)
- **Idempotency**: receiver must handle duplicate deliveries
- **Verification**: HMAC signature on payload to verify sender
- **Async processing**: don't process in webhook handler — queue it

---

## Rate Limiting

Controls how many requests a client can make in a time window.

### Algorithms

**Token Bucket**
- Bucket holds N tokens; refills at fixed rate
- Each request consumes 1 token
- Allows bursting up to bucket size
- Used by: AWS, Stripe

**Leaky Bucket**
- Requests queued and processed at fixed rate
- Smooths out bursts into steady flow
- Used by: NGINX

**Fixed Window Counter**
- Count requests per fixed window (e.g., 100 req/min)
- Problem: boundary spike — 100 at 0:59, 100 at 1:01 = 200 in 2 seconds

**Sliding Window Log**
- Track timestamp of each request in a log
- Count requests in last N seconds
- Accurate but memory-intensive

**Sliding Window Counter**
- Hybrid: weighted count from previous window + current window
- Good balance of accuracy and memory

### Where to Rate Limit
- **API Gateway**: global, before hitting services
- **Service-level**: per-endpoint granularity
- **Redis**: shared counter across multiple server instances

---

## Idempotency

An operation is idempotent if calling it multiple times produces the same result as calling it once.

| Method | Idempotent? |
|---|---|
| GET | Yes |
| PUT | Yes |
| DELETE | Yes |
| POST | No (creates duplicate) |
| PATCH | Depends on implementation |

### Idempotency Keys (for POST)
Client sends a unique `Idempotency-Key: <uuid>` header. Server stores result keyed to that ID and returns the same response for duplicate requests.

```
POST /payments
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
{ "amount": 100, "currency": "USD" }
```

Server: if key seen before → return cached response. If new → process and cache.

---

## API Authentication & Authorization

### API Keys
- Simple, static tokens passed in header or query param
- No expiry by default, hard to rotate
- Use for: server-to-server, public read APIs

### OAuth 2.0
- Delegated authorization (user grants app access to their data)
- Issues access tokens with scopes and expiry
- Flows: Authorization Code (web), Client Credentials (server-to-server), PKCE (mobile)

### JWT (JSON Web Token)
- Stateless token: `header.payload.signature`
- Server can verify without DB lookup (signature check)
- Payload contains claims: `sub`, `exp`, `iat`, roles
- Tradeoff: can't revoke before expiry (use short-lived tokens + refresh tokens)

### API Gateway
Single entry point for all API traffic. Handles:
- Authentication/authorization
- Rate limiting
- Request routing
- SSL termination
- Logging and monitoring
- Request/response transformation
