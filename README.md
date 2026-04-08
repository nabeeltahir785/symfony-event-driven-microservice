# Event-Driven Microservices Platform

> A production-grade Symfony 7 microservices architecture demonstrating asynchronous event processing with **RabbitMQ** (task queues) and **Apache Kafka** (event streaming), backed by **PostgreSQL** and fully orchestrated with **Docker Compose**.

---

## Architecture

```
                                    ┌──────────────────────────────────┐
                                    │         Docker Network           │
                                    │                                  │
 ┌──────────┐   POST /api/users    │  ┌───────────┐   ┌───────────┐  │
 │  Client   │ ──────────────────► │  │   Nginx   │──►│  PHP-FPM  │  │
 │  (cURL)   │ ◄────────────────── │  │  :80      │   │  User API │  │
 └──────────┘   JSON Response      │  └───────────┘   └─────┬─────┘  │
                                    │                        │        │
                                    │          ┌─────────────┼────────┤
                                    │          │             │        │
                                    │    ┌─────▼─────┐ ┌────▼────┐   │
                                    │    │ RabbitMQ   │ │  Kafka  │   │
                                    │    │ :5672      │ │  :9092  │   │
                                    │    │ :15672 (UI)│ │         │   │
                                    │    └─────┬─────┘ └────┬────┘   │
                                    │          │            │        │
                                    │    ┌─────▼──────┐ ┌──▼──────┐  │
                                    │    │Notification│ │Analytics│  │
                                    │    │  Worker    │ │ Worker  │  │
                                    │    └────────────┘ └────┬────┘  │
                                    │                        │       │
                                    │               ┌────────▼────┐  │
                                    │               │ PostgreSQL  │  │
                                    │               │ :5432       │  │
                                    │               └─────────────┘  │
                                    └──────────────────────────────────┘
```

### Event Flow

1. **Client** sends `POST /api/users` with user data
2. **User Service** validates input, persists user to PostgreSQL
3. **User Service** dispatches `UserCreatedMessage` → **RabbitMQ** (via Symfony Messenger)
4. **User Service** publishes `UserEventMessage` → **Kafka** (via php-rdkafka)
5. **Notification Worker** consumes from RabbitMQ, simulates email dispatch
6. **Analytics Worker** consumes from Kafka, persists event to `analytics_events` table

### Why Dual Brokers?

| Aspect | RabbitMQ | Kafka |
|---|---|---|
| **Pattern** | Point-to-point task queue | Append-only event log |
| **Use Case** | Commands: "Send this notification" | Events: "Something happened" |
| **Delivery** | Once — acknowledged and removed | Replayable — retained by offset |
| **Scaling** | Competing consumers | Consumer groups + partitions |
| **Integration** | Symfony Messenger (framework-native) | php-rdkafka (low-level client) |

---

## Tech Stack

| Component | Technology | Version |
|---|---|---|
| Framework | Symfony | 7.0 |
| Language | PHP | 8.3 |
| Database | PostgreSQL | 16 |
| Message Broker | RabbitMQ | 3.13 |
| Event Streaming | Apache Kafka | 3.7 (KRaft) |
| ORM | Doctrine | 3.x |
| Task Queue | Symfony Messenger | 7.0 |
| Kafka Client | php-rdkafka | PECL |
| Containerization | Docker + Compose | Latest |

---

## Quick Start

### Prerequisites
- [Docker](https://www.docker.com/) installed and running

### One-Command Setup

```bash
cd user-microservice
make fresh
```

This will:
1. Build all Docker images (PHP with rdkafka + amqp extensions)
2. Start PostgreSQL, RabbitMQ, Kafka, Nginx, and both workers
3. Install Composer dependencies
4. Create the database and apply schema

### Seed Sample Data

```bash
make seed
```

---

## API Documentation

### Create User
```bash
curl -X POST http://localhost/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

**Response** `201 Created`:
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "john@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "createdAt": "2024-01-15T10:30:00+00:00",
    "updatedAt": "2024-01-15T10:30:00+00:00"
  },
  "message": "User created successfully. Events dispatched to RabbitMQ and Kafka."
}
```

### List Users (Paginated)
```bash
curl http://localhost/api/users?page=1&limit=10
```

### Get Single User
```bash
curl http://localhost/api/users/{id}
```

### Update User
```bash
curl -X PUT http://localhost/api/users/{id} \
  -H "Content-Type: application/json" \
  -d '{"firstName": "Jonathan"}'
```

### Delete User
```bash
curl -X DELETE http://localhost/api/users/{id}
```

### Health Check
```bash
curl http://localhost/api/health
```

**Response**:
```json
{
  "status": "healthy",
  "service": "user-microservice",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "checks": {
    "database": { "status": "healthy", "driver": "pdo_pgsql" },
    "rabbitmq": { "status": "healthy", "host": "rabbitmq", "port": 5672 },
    "kafka": { "status": "healthy", "host": "kafka", "port": 9092 }
  }
}
```

---

## Management UIs

| Service | URL | Credentials |
|---|---|---|
| RabbitMQ Management | http://localhost:15672 | `rmq_user` / `rmq_secret` |
| Kafka UI | http://localhost:8080 | — |
| PgAdmin | http://localhost:5050 | `admin@admin.com` / `admin` |

---

## Project Structure

```
user-microservice/
├── app/                              # Symfony Application
│   ├── config/
│   │   ├── packages/
│   │   │   ├── doctrine.yaml         # PostgreSQL + ORM configuration
│   │   │   ├── messenger.yaml        # RabbitMQ transport + DLQ
│   │   │   ├── monolog.yaml          # Structured logging
│   │   │   └── validator.yaml        # Input validation
│   │   ├── services.yaml             # DI container + Kafka wiring
│   │   └── routes.yaml               # Attribute-based routing
│   ├── src/
│   │   ├── Command/
│   │   │   └── ConsumeKafkaCommand.php    # Kafka consumer (php-rdkafka)
│   │   ├── Controller/
│   │   │   ├── HealthController.php       # Service health endpoint
│   │   │   └── UserController.php         # RESTful CRUD API
│   │   ├── DTO/
│   │   │   ├── CreateUserDTO.php          # Validated creation payload
│   │   │   ├── UpdateUserDTO.php          # Partial update payload
│   │   │   └── UserResponseDTO.php        # Response serialization
│   │   ├── Entity/
│   │   │   ├── AnalyticsEvent.php         # Kafka event store
│   │   │   └── User.php                   # Core user entity (UUID)
│   │   ├── EventSubscriber/
│   │   │   └── ExceptionSubscriber.php    # Global JSON error handler
│   │   ├── Message/
│   │   │   ├── UserCreatedMessage.php     # RabbitMQ message
│   │   │   └── UserEventMessage.php       # Kafka event envelope
│   │   ├── MessageHandler/
│   │   │   └── NotificationHandler.php    # RabbitMQ consumer
│   │   ├── Repository/
│   │   │   ├── AnalyticsEventRepository.php
│   │   │   └── UserRepository.php
│   │   └── Service/
│   │       ├── KafkaProducerService.php   # Direct rdkafka producer
│   │       └── UserService.php            # Business orchestrator
│   └── .env                          # Environment configuration
├── nginx/
│   └── default.conf                  # Nginx → PHP-FPM proxy
├── php/
│   └── Dockerfile                    # PHP 8.3 + rdkafka + amqp
├── docker-compose.yml                # Full infrastructure
└── Makefile                          # Developer commands
```

---

## Make Commands

```bash
make help           # Show all available commands
make up             # Start all containers
make down           # Stop all containers
make fresh          # Full reset: rebuild + install + migrate
make logs           # Tail all service logs
make logs-workers   # Tail worker logs only
make shell          # Open shell in PHP container
make install        # Install Composer dependencies
make migrate        # Run database schema updates
make seed           # Seed sample users via API
make health         # Check service health
make test           # Run PHPUnit tests
make consume-rmq    # Start RabbitMQ consumer manually
make consume-kafka  # Start Kafka consumer manually
```

---

## Design Decisions & Interview Talking Points

### 1. Dual Broker Architecture
RabbitMQ handles **commands** (fire-and-forget tasks with retry/DLQ) while Kafka handles **events** (append-only log for analytics/audit replay). This mirrors real-world systems where different messaging semantics coexist.

### 2. Framework-Native vs Low-Level Integration
- **RabbitMQ**: Uses Symfony Messenger's AMQP transport — demonstrates framework fluency
- **Kafka**: Uses `php-rdkafka` extension directly — demonstrates understanding of Kafka's consumer group/offset model which doesn't cleanly map to Messenger's push-based architecture

### 3. Clean Architecture Layers
```
Controller → Service → Repository → Entity
    ↓            ↓
   DTO      Message/Event
```
Controllers handle HTTP concerns only. Services orchestrate business logic and event dispatching. Repositories abstract data access. DTOs decouple API contracts from domain entities.

### 4. Event-Driven Patterns
- **Event Sourcing Lite**: Analytics events are stored as an append-only log in PostgreSQL, enabling audit trails and event replay
- **Dead-Letter Queue**: Failed RabbitMQ messages are routed to a Doctrine-backed `failed` transport for inspection and retry
- **Idempotent Processing**: UUID-based message keying enables deduplication at the consumer level

### 5. Production Readiness
- Health check endpoint with database + broker connectivity verification
- Structured JSON logging (Monolog) with dedicated channels per service
- Input validation via Symfony Validator with human-readable error responses
- Global exception handling that converts exceptions to clean JSON API errors
- Docker health checks with dependency ordering

### 6. Kafka KRaft Mode
Uses Kafka without Zookeeper (KRaft mode) — the modern deployment approach since Kafka 3.3+, reducing infrastructure complexity.

---

## License

This project is for demonstration and educational purposes.