# Event-Driven Microservices Platform

[![CI](https://github.com/nabeeltahir785/symfony-event-driven-microservice/actions/workflows/ci.yml/badge.svg)](https://github.com/nabeeltahir785/symfony-event-driven-microservice/actions/workflows/ci.yml)

> A production-grade Symfony 7 microservices architecture demonstrating asynchronous event processing with **RabbitMQ** (task queues) and **Apache Kafka** (event streaming), secured with **JWT authentication**, backed by **PostgreSQL**, and fully orchestrated with **Docker Compose**.

---

## Architecture

```
                                    ┌──────────────────────────────────┐
                                    │         Docker Network           │
                                    │                                  │
 ┌──────────┐   POST /api/users    │  ┌───────────┐   ┌───────────┐  │
 │  Client   │ ──────────────────► │  │   Nginx   │──►│  PHP-FPM  │  │
 │  (cURL)   │ ◄────────────────── │  │  :80      │   │  User API │  │
 └──────────┘   JSON + JWT         │  └───────────┘   └─────┬─────┘  │
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

1. **Client** authenticates via `POST /api/auth/login` to obtain a JWT token
2. **Client** sends `POST /api/users` with `Authorization: Bearer <token>`
3. **User Service** validates input, persists user to PostgreSQL
4. **User Service** dispatches `UserCreatedMessage` → **RabbitMQ** (via Symfony Messenger)
5. **User Service** publishes `UserEventMessage` → **Kafka** (via php-rdkafka)
6. **Notification Worker** consumes from RabbitMQ, simulates email dispatch
7. **Analytics Worker** consumes from Kafka, persists event to `analytics_events` table

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
| Authentication | JWT (Lexik) | 2.x |
| ORM | Doctrine + Migrations | 3.x |
| Task Queue | Symfony Messenger | 7.0 |
| Kafka Client | php-rdkafka | PECL |
| API Docs | NelmioApiDoc (Swagger) | 4.x |
| Static Analysis | PHPStan (Level 8) | 1.x |
| Code Style | PHP CS Fixer (PSR-12) | 3.x |
| CI/CD | GitHub Actions | — |
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
1. Build all Docker images (PHP with rdkafka + amqp + pcntl extensions)
2. Start PostgreSQL, RabbitMQ, Kafka, Nginx, and both workers
3. Install Composer dependencies
4. Run Doctrine migrations
5. Generate JWT keypair

---

## Authentication

### Register an API User

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret123"}'
```

### Login (Get JWT Token)

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret123"}'
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### Use Token in Requests

```bash
export TOKEN="eyJ0eXAiOiJKV1Q..."

curl http://localhost/api/users \
  -H "Authorization: Bearer $TOKEN"
```

---

## API Documentation

### Swagger UI

Interactive API docs are available at: **http://localhost/api/doc**

### Create User
```bash
curl -X POST http://localhost/api/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
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
curl http://localhost/api/users?page=1&limit=10 \
  -H "Authorization: Bearer $TOKEN"
```

### Get Single User
```bash
curl http://localhost/api/users/{id} \
  -H "Authorization: Bearer $TOKEN"
```

### Update User
```bash
curl -X PUT http://localhost/api/users/{id} \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"firstName": "Jonathan"}'
```

### Delete User
```bash
curl -X DELETE http://localhost/api/users/{id} \
  -H "Authorization: Bearer $TOKEN"
```

### Health Check (Public)
```bash
curl http://localhost/api/health
```

**Response:**
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
| Swagger UI | http://localhost/api/doc | — |
| RabbitMQ Management | http://localhost:15672 | `rmq_user` / `rmq_secret` |
| Kafka UI | http://localhost:8080 | — |
| PgAdmin | http://localhost:5050 | `admin@admin.com` / `admin` |

---

## Project Structure

```
user-microservice/
├── .github/
│   └── workflows/
│       └── ci.yml                        # GitHub Actions: PHPStan + CS Fixer + PHPUnit
├── app/
│   ├── config/
│   │   ├── packages/
│   │   │   ├── doctrine.yaml             # PostgreSQL + ORM + Migrations
│   │   │   ├── lexik_jwt_authentication.yaml  # JWT keypair config
│   │   │   ├── messenger.yaml            # RabbitMQ transport + DLQ + retry
│   │   │   ├── monolog.yaml              # Structured logging + correlation ID
│   │   │   ├── nelmio_api_doc.yaml       # Swagger/OpenAPI config
│   │   │   ├── security.yaml             # JWT firewalls + access control
│   │   │   └── validator.yaml            # Input validation
│   │   ├── services.yaml                 # DI container + tagged services
│   │   └── routes.yaml                   # Attribute-based routing
│   ├── src/
│   │   ├── Command/
│   │   │   └── ConsumeKafkaCommand.php       # Kafka consumer (graceful shutdown)
│   │   ├── Contract/
│   │   │   ├── EventPublisherInterface.php   # Kafka publisher abstraction
│   │   │   ├── HealthCheckInterface.php      # Health check contract
│   │   │   ├── MessageInterface.php          # Message envelope contract
│   │   │   └── UserServiceInterface.php      # User service abstraction
│   │   ├── Controller/
│   │   │   ├── AuthController.php            # JWT registration
│   │   │   ├── HealthController.php          # Service health (OpenAPI)
│   │   │   └── UserController.php            # RESTful CRUD (OpenAPI)
│   │   ├── DTO/
│   │   │   ├── CreateUserDTO.php             # Validated creation payload
│   │   │   ├── RequestParseTrait.php         # DRY JSON parsing
│   │   │   ├── UpdateUserDTO.php             # Partial update payload
│   │   │   └── UserResponseDTO.php           # Response serialization
│   │   ├── Entity/
│   │   │   ├── AnalyticsEvent.php            # Kafka event store
│   │   │   ├── ApiUser.php                   # JWT auth entity
│   │   │   └── User.php                      # Core user entity (UUID)
│   │   ├── Enum/
│   │   │   └── UserEventType.php             # PHP 8.2 backed enum
│   │   ├── EventListener/
│   │   │   └── CorrelationIdListener.php     # X-Request-ID middleware
│   │   ├── EventSubscriber/
│   │   │   └── ExceptionSubscriber.php       # Global JSON error handler
│   │   ├── HealthCheck/
│   │   │   ├── AbstractTcpHealthCheck.php    # DRY socket probe
│   │   │   ├── DatabaseHealthCheck.php       # PostgreSQL health
│   │   │   ├── KafkaHealthCheck.php          # Kafka health
│   │   │   └── RabbitMQHealthCheck.php       # RabbitMQ health
│   │   ├── Message/
│   │   │   ├── UserCreatedMessage.php        # RabbitMQ message
│   │   │   └── UserEventMessage.php          # Kafka event envelope (enum-typed)
│   │   ├── MessageHandler/
│   │   │   └── NotificationHandler.php       # RabbitMQ consumer
│   │   ├── Monolog/
│   │   │   └── CorrelationIdProcessor.php    # Distributed trace injection
│   │   ├── Repository/
│   │   │   ├── AbstractDoctrineRepository.php # DRY persistence
│   │   │   ├── AnalyticsEventRepository.php
│   │   │   ├── ApiUserRepository.php
│   │   │   └── UserRepository.php
│   │   └── Service/
│   │       ├── EventDispatcherService.php    # RabbitMQ + Kafka dispatch
│   │       ├── KafkaProducerService.php      # Direct rdkafka producer
│   │       ├── UserService.php               # Business orchestrator
│   │       └── ValidationService.php         # Extracted validator
│   ├── tests/
│   │   ├── Unit/                             # 16 test files, 119+ tests
│   │   └── Integration/                      # 2 test files, 17+ tests
│   ├── .php-cs-fixer.dist.php                # PSR-12 code style rules
│   ├── phpstan.neon                          # PHPStan level 8
│   ├── phpunit.xml.dist                      # PHPUnit configuration
│   └── .env                                  # Environment configuration
├── nginx/
│   └── default.conf                          # Nginx → PHP-FPM proxy
├── php/
│   └── Dockerfile                            # Multi-stage build (PHP 8.3 + extensions)
├── docker-compose.yml                        # Full infrastructure
└── Makefile                                  # Developer commands
```

---

## Make Commands

```bash
make help              # Show all available commands
make up                # Start all containers
make down              # Stop all containers
make fresh             # Full reset: rebuild + install + migrate + jwt-keys
make logs              # Tail all service logs
make logs-workers      # Tail worker logs only
make shell             # Open shell in PHP container
make install           # Install Composer dependencies
make migrate           # Run Doctrine migrations
make jwt-keys          # Generate JWT keypair
make test              # Run all PHPUnit tests
make test-unit         # Run unit tests only
make test-integration  # Run integration tests only
make phpstan           # Run PHPStan static analysis (level 8)
make lint              # Run PHP CS Fixer (dry-run)
make lint-fix          # Run PHP CS Fixer (auto-fix)
make seed              # Seed sample users via API
make health            # Check service health
make consume-rmq       # Start RabbitMQ consumer manually
make consume-kafka     # Start Kafka consumer manually
```

---

## Design Decisions & Interview Talking Points

### 1. Dual Broker Architecture
RabbitMQ handles **commands** (fire-and-forget tasks with retry/DLQ) while Kafka handles **events** (append-only log for analytics/audit replay). This mirrors real-world systems where different messaging semantics coexist.

### 2. Framework-Native vs Low-Level Integration
- **RabbitMQ**: Uses Symfony Messenger's AMQP transport — demonstrates framework fluency
- **Kafka**: Uses `php-rdkafka` extension directly — demonstrates understanding of Kafka's consumer group/offset model which doesn't cleanly map to Messenger's push-based architecture

### 3. SOLID & DRY Architecture
- **Single Responsibility**: `UserService`, `ValidationService`, `EventDispatcherService` each own one concern
- **Open/Closed**: Health checks use tagged iterator — add new checks without modifying `HealthController`
- **Dependency Inversion**: All services depend on interfaces (`UserServiceInterface`, `EventPublisherInterface`, `HealthCheckInterface`)
- **DRY**: `AbstractDoctrineRepository`, `AbstractTcpHealthCheck`, `RequestParseTrait` eliminate duplication

### 4. PHP 8.2+ Modern Features
- **Backed Enums**: `UserEventType` replaces string constants with type-safe enum cases
- **Readonly Constructor Promotion**: All services use promoted constructor parameters
- **Union Types & Intersection Types**: Test mocks use `MockObject&Interface` intersection syntax

### 5. JWT Stateless Authentication
- Separate `ApiUser` entity from domain `User` — authentication ≠ domain model
- Stateless JWT with `json_login` authenticator
- Public endpoints (health, auth, docs) vs protected API routes

### 6. Graceful Shutdown with PCNTL Signals
The Kafka consumer traps `SIGTERM` and `SIGINT` via `pcntl_signal()`, allowing Kubernetes to safely terminate the pod mid-iteration without corrupting event processing.

### 7. Distributed Tracing via Correlation IDs
Every HTTP request receives an `X-Request-ID` (passed through or auto-generated UUID). The `CorrelationIdProcessor` injects this into every Monolog log record, enabling cross-service trace correlation.

### 8. Dead-Letter Queue & Retry Strategy
Failed RabbitMQ messages retry 3 times with exponential backoff (1s → 2s → 4s), then route to a Doctrine-backed `failed` transport for manual inspection via `messenger:failed:show`.

### 9. Comprehensive Test Suite
- **136+ unit tests** covering entities, DTOs, messages, services, health checks, subscribers, and handlers
- **17+ integration tests** testing the real service pipeline with only infrastructure mocked
- Tests use PHP 8.2 intersection types for mock type safety

### 10. CI/CD Pipeline
GitHub Actions runs PHPStan (level 8), PHP CS Fixer (dry-run), and PHPUnit on every push/PR — enforcing code quality gates before merge.

### 11. Kafka KRaft Mode
Uses Kafka without Zookeeper (KRaft mode) — the modern deployment approach since Kafka 3.3+, reducing infrastructure complexity.

### 12. Docker Multi-Stage Build
The Dockerfile uses a builder stage for Composer dependency installation and a slim runtime stage — reducing final image size and following container best practices.

---

## License

This project is for demonstration and educational purposes.