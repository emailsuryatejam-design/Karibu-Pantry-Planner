# Hard System Design Problems

> **Sources:**
> - [AlgoMaster - System Design Problems (Hard)](https://algomaster.io/learn/system-design)
> - [awesome-system-design-resources: Hard Problems](https://github.com/ashishps1/awesome-system-design-resources#hard)
> - [Google MapReduce Paper](https://static.googleusercontent.com/media/research.google.com/en//archive/mapreduce-osdi04.pdf)
> - [Google File System Paper](https://static.googleusercontent.com/media/research.google.com/en//archive/gfs-sosp2003.pdf)
> - [Uber H3 Geospatial Indexing](https://eng.uber.com/h3/)
> - [Google Docs Operational Transformation](https://drive.googleblog.com/2010/09/whats-different-about-new-google-docs.html)

---

## 1. Design Uber / Ride Sharing

**Core Requirements:** Match riders to drivers, real-time location tracking, trip management, pricing.

### Key Challenges
1. Real-time location updates (millions of drivers moving constantly)
2. Geospatial querying (find drivers near a location)
3. Matching algorithm (fast, fair, efficient)
4. Dynamic pricing (surge)

### Location Service
```
Driver App → Location Updates (every 4 seconds)
           → WebSocket / HTTP → Location Service → Redis Geo
```

**Redis Geo** stores driver locations as geospatial data:
```
GEOADD drivers:active lng lat driver_id
GEORADIUS drivers:active lng lat 5 km → nearby drivers
```

Alternative: **S2 geometry** (Google) or **H3** (Uber) — divide map into hierarchical hexagonal cells for efficient geospatial indexing.

### Matching Service
```
Rider requests ride → Supply Matching Service
                     → Query nearby available drivers (Redis Geo)
                     → Rank by: distance, acceptance rate, driver rating
                     → Send request to top N drivers (parallel)
                     → First to accept gets the trip
```

### Trip State Machine
```
REQUESTED → ACCEPTED → DRIVER_EN_ROUTE → ARRIVED → IN_PROGRESS → COMPLETED
                                                                 → CANCELLED
```

### Surge Pricing
- Monitor: requests vs available supply per geo cell
- If ratio exceeds threshold, multiply base fare by surge multiplier
- Update surge map every 1 minute, cache in Redis

### Architecture
```
Rider App ──→ API Gateway → Trip Service → DB (trips)
Driver App ──→             → Matching Service → Redis (driver locations)
                           → Pricing Service → Surge Map (Redis)
                           → Notification Service → Push Notifications
                           → Payment Service
```

---

## 2. Design Google Docs / Collaborative Editor

**Core Requirements:** Multiple users editing same document in real-time, changes sync to all users.

### Core Challenge: Concurrent Edits
If User A and User B both insert text at position 5 simultaneously, naive application of both operations will corrupt the document.

### Operational Transformation (OT)
- Transform operations against each other to account for concurrent changes
- Google Docs uses OT
- Algorithm: transform(op_A, op_B) → adjusted op that applies correctly after op_B

### CRDTs (Conflict-free Replicated Data Types)
- Data structure that automatically resolves conflicts
- Figma, collaborative tools use CRDTs
- Each character assigned unique ID; merge by unique IDs

### Architecture
```
Client A ──→ WebSocket Server → Operation Queue (ordered)
Client B ──→                  → Document Service → Persistent Store
                              → Broadcast to all connected clients
```

### Operational Flow
1. User makes edit → generates operation (insert/delete at position)
2. Client assigns sequence number, sends to server
3. Server applies to canonical document, transforms against concurrent ops
4. Broadcasts transformed operation to all other clients
5. Clients apply transformation locally

### Cursor & Presence
- Each user's cursor position stored: `doc_id → { user_id: position }`
- Broadcast cursor updates via WebSocket
- Show colored cursors per user

---

## 3. Design Zoom / Video Conferencing

**Core Requirements:** Real-time video/audio calls, screen sharing, up to hundreds of participants.

### Protocols
- **WebRTC**: peer-to-peer media streaming, NAT traversal (STUN/TURN servers)
- **UDP**: chosen over TCP (latency over reliability for media)
- **RTP/SRTP**: Real-time Transport Protocol for media streams

### Small Groups (1-1, up to ~10)
- **P2P mesh**: each participant sends video directly to all others
- Scales as O(N²) connections — breaks down for larger groups

### Larger Groups (10+)
- **SFU (Selective Forwarding Unit)**: media server receives one stream from each participant, forwards selected streams to others
- Each participant sends once, receives multiple streams
- Much more efficient than mesh

### Architecture
```
Client A ──→ STUN/TURN Server (NAT traversal)
             → SFU Media Server (receives, forwards streams)
                     ↓
Client B ←── SFU forwards A's stream
Client C ←── SFU forwards A's + B's streams
```

### Screen Sharing
- Captured as additional video stream
- Higher resolution, lower frame rate vs camera
- Subscribed to by choice (saves bandwidth)

### Recording
- SFU writes incoming streams to object storage
- Async transcoding and mixing after call

---

## 4. Design a Web Crawler

**Core Requirements:** Discover and index billions of web pages.

### Architecture
```
Seed URLs → URL Frontier (priority queue) → Fetcher → HTML Parser
                  ↑                                        ↓
                  └──────────── New URLs ──────────────────┘
                                                            ↓
                                                   Content Store (S3)
                                                            ↓
                                                      Indexer
```

### URL Frontier
- Priority queue: prioritize by PageRank, freshness, crawl frequency
- **Politeness**: respect `robots.txt`; don't hammer same domain (1 req/sec per domain)
- **URL deduplication**: Bloom filter to skip already-seen URLs

### Distributed Crawling
- Multiple fetcher workers in parallel
- Partition URL space: assign URL ranges or domains to workers by hash
- URL frontier stored in distributed queue (Kafka)

### Content Deduplication
- Simhash or MinHash to detect near-duplicate pages
- Store hash of content; skip if hash seen before

### Challenges
- **Dynamic content**: JavaScript-rendered pages (require headless browser)
- **Trap detection**: infinite URL spaces (calendars, filters)
- **Crawl traps**: noindex meta tags, robots.txt exclusions
- **Scale**: crawling 1B pages → need ~1K fetchers running continuously

---

## 5. Design Google Maps

**Core Requirements:** Map rendering, routing/directions, real-time traffic, search (places).

### Map Data & Tile Rendering
- World divided into tiles at each zoom level (256×256 px PNG/vector tiles)
- Pre-rendered and cached at all zoom levels → stored in object storage
- Client fetches tiles by `(zoom, x, y)` coordinates
- **Vector tiles**: send raw data, render on device — smaller, scalable text

### Routing (Shortest Path)
- **Dijkstra** / **A\*** for simple shortest path
- **Contraction Hierarchies**: pre-process graph by adding shortcut edges for faster queries — Google Maps uses this
- Road network modeled as directed weighted graph (nodes=intersections, edges=roads)

### Real-Time Traffic
```
GPS data from users → Traffic Service → Segment speed estimates
                                       → Update road weights in graph
                                       → Recalculate ETAs
```

- Speed data aggregated over road segments (not individual users)
- Update routing weights every 1-5 minutes

### ETA Prediction
- Historical data + real-time traffic + ML model
- Features: time of day, day of week, weather, events, road type

### Places Search
- Inverted index (Elasticsearch) over place names and addresses
- Geo-indexed for spatial queries ("coffee shops near me")
- Ranked by relevance + distance + rating

---

## 6. Design Distributed Locking Service

**Core Requirements:** Mutual exclusion across distributed nodes.

### Requirements
- Mutual exclusion: only one holder at a time
- Deadlock-free: lock released even if holder crashes (TTL)
- Fault-tolerant: service survives node failures

### Single Redis Instance
```
SET lock:resource_name client_id NX PX 30000
# NX = only set if not exists
# PX 30000 = expire in 30 seconds
```

Release (Lua for atomicity):
```lua
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
```

### Redlock (Multi-Node)
- Use N=5 Redis nodes
- To acquire: try to acquire lock on all 5 in parallel; succeed if majority (3+) acquired within timeout
- Provides stronger guarantees against single node failure

### ZooKeeper / etcd Alternative
- Stronger guarantees via Paxos/Raft consensus
- Use ZooKeeper ephemeral nodes: node disappears if client disconnects
- More complex, lower throughput than Redis-based

### Fencing Tokens
- Lock service issues monotonically increasing token with each lock grant
- Resource (DB, service) rejects requests with stale (lower) tokens
- Handles GC pauses and network partitions that cause stale lock holders to act

---

## 7. Design a Search Autocomplete System (Typeahead)

**Core Requirements:** Return top K suggestions for a prefix in < 100ms.

### Data Structure: Trie
- Each node represents a character
- Each node stores top K completions for that prefix
- Tradeoffs: fast prefix search, expensive to update

### Pre-computation Approach
- Batch job: compute top K queries for every prefix daily
- Store in a distributed key-value store or CDN
- On prefix input → cache lookup (< 5ms)

### Real-Time Signal
- Kafka stream of user searches
- Streaming aggregation to update prefix counts
- Periodic rebuild of trie / top-K table

### Architecture
```
User types "ca" → API Gateway → Autocomplete Service
                              → Redis: GET prefix:ca → ["cat", "car", "cake"]
                              → Return JSON in < 50ms
```
