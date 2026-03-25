# Architectural Patterns

> **Sources:**
> - [awesome-system-design-resources: Architectural Patterns](https://github.com/ashishps1/awesome-system-design-resources#architectural-patterns)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Martin Fowler — Microservices](https://martinfowler.com/articles/microservices.html)
> - [Martin Fowler — CQRS](https://martinfowler.com/bliki/CQRS.html)
> - [Netflix Tech Blog](https://netflixtechblog.com/)

## Monolith vs Microservices

### Monolith
All components in a single deployable unit.

**Pros:**
- Simple development, testing, deployment
- No network latency between components
- Easier debugging and tracing

**Cons:**
- Scaling requires scaling the entire app
- One bug can take down everything
- Large codebase becomes hard to manage
- Technology lock-in

### Microservices
System decomposed into small, independently deployable services.

**Pros:**
- Independent scaling per service
- Technology flexibility per service
- Smaller codebases, focused teams
- Fault isolation

**Cons:**
- Distributed systems complexity (network failures, latency)
- Operational overhead (many services to deploy, monitor)
- Data consistency challenges (no shared DB)
- Debugging across services is harder

### When to Choose What
- **Start with monolith**: simpler, fast iteration, premature decomposition is harmful
- **Move to microservices**: when specific components need independent scaling, teams are large, deployment coupling slows you down

---

## Service-Oriented Architecture (SOA)

Precursor to microservices. Services communicate via enterprise service bus (ESB). Heavier, more formal contracts. Mostly replaced by microservices in modern systems.

---

## Event-Driven Architecture

Services communicate by producing and consuming events rather than direct calls.

```
Service A publishes event → Event Bus → Service B reacts
                                      → Service C reacts
```

### Patterns
- **Event Notification**: fire-and-forget, consumer decides what to do
- **Event-Carried State Transfer**: event contains all data needed (no follow-up fetch)
- **Event Sourcing**: state is derived from sequence of events (append-only log)

### Benefits
- Loose coupling: producers don't know about consumers
- Easy to add new consumers without touching producers
- Natural audit trail if using event sourcing

### Challenges
- Harder to trace flows (requires distributed tracing)
- Eventual consistency — UI may lag
- Event schema evolution (backward compatibility)

---

## CQRS (Command Query Responsibility Segregation)

Separate the write path (commands) from the read path (queries).

```
Write:  Command → Command Handler → Write DB (normalized)
Read:   Query   → Query Handler  → Read DB/View (denormalized for speed)
```

### Benefits
- Optimize read and write sides independently
- Read model can be rebuilt from commands (if using event sourcing)
- Different scaling strategies per side

### Tradeoffs
- Eventual consistency between write and read models
- More code to maintain
- Overkill for simple CRUD applications

---

## Serverless

Run code without managing servers. Cloud provider handles infrastructure.

### Functions as a Service (FaaS)
- AWS Lambda, Google Cloud Functions, Azure Functions
- Event-triggered, stateless, auto-scaling to zero
- Billed per invocation (ms granularity)

### Benefits
- No server management
- Automatic scaling
- Pay per use (cost-efficient for sporadic workloads)

### Limitations
- **Cold start**: first invocation has latency (100ms – 3s)
- **Execution limits**: max 15 min (Lambda), stateless
- **Vendor lock-in**
- Debugging and local testing is harder

### Best For
- Webhook processors
- Scheduled jobs (cron)
- Event processors (S3 upload → resize image)
- Low-traffic APIs

---

## Peer-to-Peer (P2P)

Nodes communicate directly without a central server.

### Types
- **Unstructured P2P**: Gnutella, early BitTorrent — flood messages
- **Structured P2P (DHT)**: BitTorrent, IPFS — data location via distributed hash table

### Use Cases
- File sharing (BitTorrent)
- Blockchain (Bitcoin, Ethereum)
- Video conferencing (WebRTC)
- CDN edge computing

---

## Strangler Fig Pattern

Gradually replace a monolith by routing some traffic to new microservices while keeping the rest in the monolith.

```
All traffic → Monolith
     ↓ (over time)
New feature traffic → New Service
Old traffic → Monolith (shrinking)
     ↓ (eventually)
All traffic → New Services (monolith retired)
```

---

## Sidecar Pattern

Deploy a helper container alongside the main application container (same pod in Kubernetes).

**Common uses:**
- Service mesh proxy (Envoy/Istio) handles network: retries, mTLS, tracing
- Log collector sidecar ships logs to aggregator
- Config/secret loader

---

## API Gateway Pattern

Single entry point for all client traffic.

```
Mobile App  ─────┐
Web App     ───── API Gateway ──→ User Service
3rd Party   ─────┘              → Order Service
                                → Payment Service
```

Handles: auth, rate limiting, SSL termination, routing, logging.

Tools: Kong, AWS API Gateway, NGINX, Traefik

---

## BFF (Backend for Frontend)

A dedicated API layer for each client type (web, mobile, third-party).

```
Web App    → BFF-Web    →┐
Mobile App → BFF-Mobile →├→ Downstream Services
3rd Party  → BFF-Public →┘
```

**Why:** Different clients have different data needs. Mobile needs compact payloads; web may need richer data. Avoids one bloated general API.
