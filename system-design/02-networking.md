# Networking Fundamentals

> **Sources:**
> - [awesome-system-design-resources: Networking Fundamentals](https://github.com/ashishps1/awesome-system-design-resources#networking-fundamentals)
> - [AlgoMaster - System Design Fundamentals](https://algomaster.io/learn/system-design)
> - [Cloudflare Learning Center — DNS, CDN, Load Balancing](https://www.cloudflare.com/learning/)

## OSI Model (7 Layers)

| Layer | Name | Protocols/Tech |
|---|---|---|
| 7 | Application | HTTP, HTTPS, DNS, FTP, SMTP |
| 6 | Presentation | SSL/TLS, encoding |
| 5 | Session | Sockets |
| 4 | Transport | TCP, UDP |
| 3 | Network | IP, ICMP, routing |
| 2 | Data Link | Ethernet, MAC |
| 1 | Physical | Cables, signals |

For system design interviews, focus on layers 4 and 7.

---

## TCP vs UDP

| Feature | TCP | UDP |
|---|---|---|
| Connection | Connection-oriented | Connectionless |
| Reliability | Guaranteed delivery, ordered | Best-effort, no ordering |
| Speed | Slower (handshake, ACKs) | Faster |
| Use case | HTTP, file transfer, databases | Streaming, gaming, DNS |

---

## DNS (Domain Name System)

Translates domain names → IP addresses.

### Resolution Flow
1. Browser checks local cache
2. OS checks `/etc/hosts` and local cache
3. Query sent to **Recursive Resolver** (usually your ISP)
4. Resolver queries **Root Name Server** → TLD server → Authoritative server
5. IP returned and cached at each level

### Record Types
- **A**: domain → IPv4
- **AAAA**: domain → IPv6
- **CNAME**: domain → another domain (alias)
- **MX**: mail server
- **TXT**: arbitrary text (used for SPF, DKIM)
- **NS**: name servers for domain

---

## Load Balancing

Distributes incoming traffic across multiple servers.

### Algorithms
- **Round Robin**: requests distributed in order (simple, ignores server load)
- **Weighted Round Robin**: servers with more capacity get more requests
- **Least Connections**: routes to server with fewest active connections
- **IP Hash**: same client always routes to same server (session stickiness)
- **Random**: randomly pick a server

### Layer 4 vs Layer 7 Load Balancing
- **L4 (Transport)**: routes based on IP/TCP without reading content — fast, less flexible
- **L7 (Application)**: routes based on HTTP headers, URL path, cookies — smart routing, SSL termination

### Tools
- **NGINX, HAProxy**: software load balancers
- **AWS ALB/NLB**: cloud-managed
- **Cloudflare, Fastly**: edge-level

---

## Proxy vs Reverse Proxy

| Type | Who uses it | Purpose |
|---|---|---|
| Forward Proxy | Client-side | Hide client identity, caching, filtering (e.g., VPN, corporate proxy) |
| Reverse Proxy | Server-side | Hide server identity, load balancing, SSL termination, caching (e.g., NGINX, Cloudflare) |

---

## CDN (Content Delivery Network)

A geographically distributed network of edge servers that cache static content close to users.

### How it Works
1. User requests `image.example.com/photo.jpg`
2. DNS resolves to nearest CDN edge server
3. Edge serves cached content if available (cache hit)
4. On cache miss: edge fetches from origin, caches, serves

### Benefits
- Reduced latency (closer to user)
- Reduced origin server load
- DDoS protection (absorbs traffic at edge)
- Better availability

### Cache Invalidation in CDNs
- **TTL-based**: content expires after N seconds
- **Purge API**: manually invalidate specific content
- **Versioned URLs**: `style.v2.css` avoids stale cache issues

### Tools
- **Cloudflare, Akamai, Fastly** (global CDNs)
- **AWS CloudFront, Azure CDN**

---

## HTTP/HTTPS

### HTTP Methods
| Method | Use |
|---|---|
| GET | Read resource |
| POST | Create resource |
| PUT | Replace resource |
| PATCH | Partial update |
| DELETE | Remove resource |

### Status Codes
| Range | Meaning |
|---|---|
| 2xx | Success (200 OK, 201 Created, 204 No Content) |
| 3xx | Redirect (301 Permanent, 302 Temporary, 304 Not Modified) |
| 4xx | Client error (400 Bad Request, 401 Unauth, 403 Forbidden, 404 Not Found, 429 Rate Limited) |
| 5xx | Server error (500 Internal, 502 Bad Gateway, 503 Unavailable, 504 Timeout) |

### HTTP/2 vs HTTP/3
- **HTTP/2**: multiplexing (multiple requests on one connection), header compression, server push
- **HTTP/3**: built on QUIC (UDP-based), eliminates head-of-line blocking, faster connection setup
