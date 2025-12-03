# Harba Booking System – Fullstack Case Solution

A complete appointment booking system built for the Harba Fullstack Developer Case.

## 🧰 Tech Stack

- Symfony 7.1 (PHP 8.4)
- MySQL 8
- Doctrine ORM
- Vue 3 + TypeScript + Vite + Tailwind
- Docker Compose (php-fpm, nginx, mysql, node/vite)

## ✅ Features

This solution fulfills all required and bonus criteria:

- Providers, services & bookings
- 30-minute slot generator
- Validation using Symfony Validator
- Admin panel + admin-only endpoints
- Token authentication
- Soft deletes on bookings
- Rate limiting on login
- Automated environment setup
- PHPUnit test suite (6+ tests)
- Swagger / OpenAPI documentation
- Vue 3 frontend consuming Symfony API

---

## 🚀 Quick Start

Make sure **Docker** and **Composer** are installed.

Start the entire system:

```bash
  composer app:up
```

This command automatically:

- Builds & starts Docker containers
- Installs backend dependencies
- Runs migrations (dev + test)
- Seeds demo data
- Installs frontend dependencies
- Starts Vue/Vite dev server

After startup:

- Frontend: `http://localhost:5173`
- Backend API: `http://localhost:8080`
- Swagger Docs: `http://localhost:8080/api/docs`

---

## 📦 Composer Scripts

**Start backend + frontend + migrations + seed**

```bash
  composer app:up
```

**Run PHPUnit tests**

```bash
  composer app:test
```

**Reset dev + test database**

```bash
  composer app:reset
```

**Seed demo data**

```bash
  composer app:seed
```

---

## 🐳 Docker Architecture

Services:

- `php-fpm` → Symfony backend
- `nginx` → Serves backend on port `8080`
- `mysql` → Persistent DB on port `3306`
- `frontend` → Node/Vite dev server on port `5173`

Start manually:

```bash
  docker compose up --build
```

---

## 🗄 Database Schema

### User

- `id`
- `email`
- `password`
- `roles`
- `apiToken`

### Provider

- `id`
- `name`
- `workingHours` (JSON)

### Service

- `id`
- `name`
- `durationMinutes`

### Booking

- `id`
- `user`
- `provider`
- `service`
- `startAt`
- `cancelledAt`
- `deletedAt`
- `note`

---

## 🔐 Authentication

Login returns a token:

```json
{
  "token": "abc123..."
}
```

Use it in requests:

```http
Authorization: Bearer <token>
```

---

## 📌 API Endpoints

### Auth

| Method | Endpoint        | Description               |
|--------|-----------------|---------------------------|
| POST   | `/api/register` | Register user             |
| POST   | `/api/login`    | Login & receive API token |
| GET    | `/api/me`       | Get authenticated user    |

---

### Public Endpoints

| Method | Endpoint                    | Description        |
|--------|-----------------------------|--------------------|
| GET    | `/api/services`             | List services      |
| GET    | `/api/providers`            | List providers     |
| GET    | `/api/providers/{id}/slots` | Get provider slots |

Example with filters:

```text
/api/providers/1/slots?from=2025-01-01&to=2025-01-31
```

---

### Authenticated User Endpoints

| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| POST   | `/api/bookings`              | Create booking (supports note)     |
| GET    | `/api/my/bookings`          | List own bookings                  |
| POST   | `/api/bookings/{id}/cancel` | Cancel booking                     |

---

### Admin Endpoints

| Method | Endpoint                              | Description                          |
|--------|----------------------------------------|--------------------------------------|
| GET    | `/api/admin/bookings`                | Get all bookings grouped by user     |
| POST   | `/api/admin/bookings/{id}/delete`    | Soft delete booking                  |

Admin role required: `ROLE_ADMIN`.

---

## 🧮 Booking Rules

- Slots are 30 minutes
- Only next 30 days
- Respects working hours
- A slot cannot be double-booked
- Cancelled bookings free the slot
- Deleted bookings are completely hidden
- User may leave a booking note

---

## 🧪 PHPUnit Tests

Includes tests for:

- Registration
- Login
- SlotGenerator rules
- Booking creation with notes
- Cancel vs delete behaviour
- Repository filtering
- Admin permissions

Run tests:

```bash
composer app:test
```

Uses dedicated `harba_test` database with automatic rollback.

---

## 📘 Swagger / OpenAPI Docs

View API docs:

```text
http://localhost:8080/api/docs
```

Includes:

- All endpoints
- Schemas
- Request/response examples
- Error formats

---

## 🖥 Frontend (Vue 3 + TypeScript + Tailwind)

Features implemented:

- Login / Register
- Select provider & service
- Date range filtering
- Slot picker
- Booking note modal
- User bookings
- Cancel booking

Admin dashboard:

- Grouped bookings
- View notes
- Cancel / delete

Frontend starts automatically with:

```bash
  composer app:up
```

Manual start:

```bash
  docker compose exec frontend npm run dev -- --host 0.0.0.0 --port 5173
```

---

## 🏗 Folder Structure

```text
backend/
  src/
  tests/
  migrations/
frontend/
composer.json
docker-compose.yml
openapi.yaml
README.md
```

---

## 🌱 Demo Data (Seeded Automatically)

### Provider

- Harbor Master 1

Working hours:

- Mon–Fri: 09:00–17:00
- Sat: 10:00–14:00
- Sun: closed

### Service

- Standard Booking (30 minutes)

---

## ✔ Bonus Features

- Login rate limiting
- Soft deletes
- DTO validation
- Admin dashboard
- Booking notes
- Automatic DB setup + migrations
- Fully documented API
- PHPUnit tests
