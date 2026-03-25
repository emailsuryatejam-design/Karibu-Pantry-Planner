# Low-Level Design (LLD) & Design Patterns

> **Sources:**
> - [AlgoMaster - Low Level Design Course](https://algomaster.io/learn/lld)
> - [Refactoring Guru — Design Patterns](https://refactoring.guru/design-patterns)
> - [Gang of Four — Design Patterns: Elements of Reusable Object-Oriented Software](https://en.wikipedia.org/wiki/Design_Patterns)
> - [awesome-system-design-resources](https://github.com/ashishps1/awesome-system-design-resources)

## OOP Principles

### SOLID

| Principle | Meaning | Violation Example |
|---|---|---|
| **S** Single Responsibility | Class has one reason to change | `UserService` handles auth + email + DB |
| **O** Open/Closed | Open for extension, closed for modification | `if type == "A"` chains instead of polymorphism |
| **L** Liskov Substitution | Subclass can replace base class without breaking behavior | `Square` overriding `Rectangle.setWidth` and breaking area calculation |
| **I** Interface Segregation | Don't force classes to implement methods they don't use | One fat `Animal` interface with `fly()` and `swim()` |
| **D** Dependency Inversion | Depend on abstractions, not concretions | `OrderService` directly instantiates `MySQLDatabase` |

### Other Key Principles
- **DRY** (Don't Repeat Yourself): extract duplicate logic
- **KISS** (Keep It Simple, Stupid): prefer simplest working solution
- **YAGNI** (You Aren't Gonna Need It): don't build for hypothetical future
- **Law of Demeter**: talk to direct friends only (`a.b.c.method()` is a violation)

---

## Design Patterns (GoF)

### Creational Patterns

**Singleton**
- Only one instance of a class; global access point
- Use: DB connection pool, logger, config
```python
class Config:
    _instance = None
    def __new__(cls):
        if not cls._instance:
            cls._instance = super().__new__(cls)
        return cls._instance
```

**Factory Method**
- Define interface for creating objects; subclasses decide which class to instantiate
- Use: create different DB drivers based on config, notification senders

**Abstract Factory**
- Creates families of related objects without specifying concrete classes
- Use: UI themes (LightTheme.createButton(), DarkTheme.createButton())

**Builder**
- Construct complex objects step by step
- Use: query builders, HTTP request builders, complex config objects
```python
Pizza.builder().size("large").crust("thin").addTopping("cheese").build()
```

**Prototype**
- Clone an existing object instead of creating from scratch
- Use: object templates, undo/redo, game entity spawning

---

### Structural Patterns

**Adapter**
- Converts interface of one class to what a client expects
- Use: legacy API integration, third-party library wrappers

**Decorator**
- Add behavior to objects dynamically without subclassing
- Use: middleware chains, logging wrappers, data stream compression
```python
# Component → LoggingDecorator(CachingDecorator(Component))
```

**Proxy**
- Surrogate that controls access to another object
- Use: lazy initialization, access control, logging, caching

**Facade**
- Simplified interface to complex subsystem
- Use: library APIs that hide internal complexity (SDK facade over 10 services)

**Composite**
- Treat individual objects and groups uniformly
- Use: file system (File and Folder both implement `list()`), UI component tree

**Bridge**
- Decouple abstraction from implementation so both can vary independently
- Use: rendering engines across platforms, notification via different channels

---

### Behavioral Patterns

**Observer**
- Object (subject) notifies list of dependents (observers) when state changes
- Use: event systems, UI state management, pub/sub
```python
subject.subscribe(observer)
subject.notify()  # all observers get called
```

**Strategy**
- Define a family of algorithms, encapsulate each, make them interchangeable
- Use: sorting strategies, payment methods, compression algorithms

**Command**
- Encapsulate a request as an object
- Use: undo/redo systems, queued operations, transaction logging

**Iterator**
- Sequential access to elements without exposing underlying structure
- Use: custom collection traversal

**State**
- Object changes behavior when its state changes
- Use: order state machine (pending → processing → shipped → delivered)

**Template Method**
- Define skeleton of algorithm in base class; subclasses fill in steps
- Use: data processing pipelines, report generators

**Chain of Responsibility**
- Pass request along a chain until one handler handles it
- Use: middleware, event bubbling, validation pipelines

---

## UML Diagrams (Quick Reference)

### Class Diagram Relationships
```
A ──────▷ B        A inherits from B (IS-A)
A - - -▷ B        A implements interface B
A ────── B         A is associated with B
A ◇────── B        A aggregates B (B can exist without A)
A ◆────── B        A composes B (B can't exist without A)
A - - -> B         A depends on B (uses B)
```

### Sequence Diagram
Shows interactions between objects over time. Good for API flows and message-passing.

---

## Common LLD Interview Problems

| Problem | Key Patterns |
|---|---|
| LRU Cache | HashMap + Doubly Linked List |
| Parking Lot | Strategy (pricing), Factory (spot type), Observer (availability) |
| Elevator System | State pattern, Strategy (scheduling algorithm) |
| ATM | State pattern, Command pattern |
| Tic Tac Toe | Simple OOP: Board, Player, Game classes |
| Library Management | Composite (shelf/book), Observer (notifications) |
| Ride Sharing (Uber) | Strategy (matching), Observer (location updates), State (trip status) |
| Hotel Booking | Template method, Factory |
| Snake and Ladder | Observer, Board as Composite |

### Approach for LLD Problems
1. List key requirements (functional)
2. Identify entities/actors
3. Define relationships between classes
4. Draw class diagram
5. Implement key methods
6. Apply patterns where they genuinely simplify the design (don't force patterns)
