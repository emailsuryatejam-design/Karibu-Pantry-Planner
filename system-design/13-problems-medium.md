# Medium System Design Problems

> **Sources:**
> - [AlgoMaster - System Design Problems (Medium)](https://algomaster.io/learn/system-design)
> - [awesome-system-design-resources: Medium Problems](https://github.com/ashishps1/awesome-system-design-resources#medium)
> - [High Scalability Blog](http://highscalability.com/)
> - [Engineering Blogs: Netflix](https://netflixtechblog.com/), [Uber](https://www.uber.com/en-US/blog/engineering/), [Instagram](https://instagram-engineering.com/)

---

## 1. Design WhatsApp / Chat System

**Core Requirements:** 1-on-1 messaging, group chat, message status (sent/delivered/read), online presence.

### Key Components

**Message Flow**
```
Sender → WebSocket Server → Message Queue (Kafka) → Delivery Service → Recipient
                           → Message Store (Cassandra)
```

**WebSocket Connection Management**
- Each server maintains open WebSocket connections
- Connection mapping: `user_id → WebSocket server ID` stored in Redis
- If recipient on different server, route via message queue

**Message Storage**
- Cassandra: partition key = `conversation_id`, clustering key = `timestamp DESC`
- Enables fast range queries: "get last 50 messages in conversation"

**Message Status**
- `SENT` → saved to DB
- `DELIVERED` → recipient's device received (ACK from client)
- `READ` → user opened the conversation

**Group Chat**
- Fan-out to all members on group message
- For large groups (1000+): async fan-out via queue to avoid blocking

**Presence**
- Heartbeat: client sends ping every 30 seconds
- Store `user_id → last_seen` in Redis with TTL
- WebSocket disconnect → update last_seen

### Trade-offs
- Push vs pull: push via WebSocket for online users, pull on reconnect
- Message ordering: use sequence numbers or vector clocks for groups

---

## 2. Design Instagram / Photo Sharing

**Core Requirements:** Upload photos, follow users, news feed, likes/comments.

### Photo Upload Flow
```
Client → API Gateway → Upload Service → Object Storage (S3)
                                      → CDN origin
                                      → Message Queue
                                             ↓
                                      Media Processing Service
                                      (resize, thumbnail, ML tagging)
```

### News Feed: Push vs Pull

**Pull (Fan-out on Read)**
- Query tweets from all followed users, merge and sort
- Pro: no storage overhead
- Con: slow for users following many accounts (celebrity problem)

**Push (Fan-out on Write)**
- On post: write to all followers' feed timelines in Redis
- Pro: fast reads
- Con: expensive for celebrities with millions of followers

**Hybrid (Twitter approach)**
- Regular users: push to followers' timelines
- Celebrities: don't push; pull their posts at read time and merge

### Key Schema
```
users: { id, username, bio, profile_pic_url }
posts: { id, user_id, image_url, caption, created_at }
follows: { follower_id, followee_id }
feed: { user_id, post_id, created_at }  ← pre-computed timeline
```

---

## 3. Design Netflix / Video Streaming

**Core Requirements:** Upload videos, transcode to multiple resolutions, serve adaptive streaming.

### Upload & Transcoding
```
Creator → Upload Service → Raw Storage (S3)
                         → Message Queue
                               ↓
                         Transcoding Service (FFmpeg)
                         → 1080p, 720p, 480p, 360p
                         → Per-device codecs (H.264, VP9, HEVC)
                               ↓
                         CDN Origin → Global CDN Edge Nodes
```

### Adaptive Bitrate Streaming (ABR)
- Video split into small segments (2–4 seconds each)
- Client player monitors network bandwidth
- Switches to higher/lower quality segment dynamically
- Protocol: **HLS** (Apple) or **MPEG-DASH** (open standard)

### Key Design Points
- **CDN** is critical — majority of traffic is video bytes
- **Metadata service**: separate from video files — stores titles, descriptions, thumbnails
- **Recommendation engine**: offline ML pipeline, results cached
- **Resume playback**: store `user_id, video_id, position` in DB

---

## 4. Design Twitter / Social Feed

**Core Requirements:** Post tweets (280 chars), follow users, home timeline, search.

### Timeline Generation
(See Instagram — same push/pull hybrid approach)

### Search (Full-Text)
- **Elasticsearch** cluster indexes tweet content
- Write path: new tweet → Kafka → indexing service → Elasticsearch
- Read path: search query → Elasticsearch → ranked results

### Trending Topics
- Count hashtag occurrences in sliding time window
- Approximate with Count-Min Sketch (space-efficient)
- Aggregate by region for localized trends

### Twitter's Actual Architecture
- **Manhattan**: distributed key-value store for tweets
- **Finagle**: RPC framework
- **Snowflake**: distributed ID generation (64-bit, time-ordered, unique across nodes)

---

## 5. Design Spotify / Music Streaming

**Core Requirements:** Stream songs, playlists, search, recommendations, offline mode.

### Audio Storage & Streaming
- Songs stored as multiple quality variants (128kbps, 256kbps, 320kbps)
- Serve via CDN; most popular tracks cached at edge
- Pre-buffering: player downloads next 30s of audio ahead

### Audio Licensing
- Track metadata: artist, album, rights info
- Geo-restrictions: licensing varies by country
- DRM: Widevine/FairPlay to prevent raw download

### Recommendations
- **Collaborative filtering**: "users who liked X also liked Y"
- **Content-based**: audio fingerprinting, BPM, genre
- **Real-time listening graph**: Kafka stream of play events → ML pipeline

### Offline Mode
- Download encrypted tracks to device
- Offline playback key stored on device, validated periodically
- Sync listening history on reconnect

---

## 6. Design Reddit / Discussion Platform

**Core Requirements:** Post content, comment threads, upvotes/downvotes, subreddits, feed ranking.

### Post Ranking (Hot Algorithm)
```
score = (upvotes - downvotes) * time_decay_factor
```
- Recalculate periodically or on vote events
- Cache rankings in Redis sorted sets: `ZADD subreddit:hot score post_id`
- Serve from Redis for fast reads

### Threaded Comments
- Store as adjacency list: `comment { id, post_id, parent_id, content, author_id }`
- Reconstruct tree on read
- For deep trees, consider closure table for efficient subtree queries

### Feed Types
- **Hot**: high upvotes, time-weighted
- **New**: sorted by creation time
- **Top**: all-time or time-filtered (this week, year)
- **Rising**: gaining votes quickly

---

## 7. Design Uber / Ride Sharing

(Moved to Hard problems as it's genuinely complex — see `14-problems-hard.md`)

---

## 8. Design Payment System (Stripe-like)

**Core Requirements:** Accept payments, charge cards, transfer money, handle failures.

### Key Principles
- **Idempotency**: every request has an idempotency key — retries safe
- **Exactly-once processing**: critical; double charges are catastrophic
- **Audit trail**: every transaction logged, immutable

### Architecture
```
Client → API Gateway → Payment Service → Payment Processor (Visa/Mastercard)
                               ↓
                        Ledger Service (append-only log)
                               ↓
                        Notification Service
```

### Double-Spend Prevention
- Database-level: optimistic locking (`UPDATE wallet SET balance = balance - amount WHERE balance >= amount`)
- Check balance at debit time; fail if insufficient
- Distributed lock on wallet during transaction

### Reconciliation
- Periodic job compares internal ledger vs payment processor records
- Alerts on discrepancies
