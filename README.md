# Laravel API

An API-only Laravel 13 application with Sail, PostgreSQL, Redis queues, Memcached, Mailpit, Sanctum, and `nwidart/laravel-modules`.

## Local setup

Requirements: Docker Compose.

```sh
test -f .env || cp .env.example .env
./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The API is available at `http://localhost/api/v1`. Mailpit is available at `http://localhost:8025`. Email notifications are queued in Redis and handled by the `queue` service.

Run tests with:

```sh
./vendor/bin/sail artisan test
```

## API endpoints

All responses and validation errors are JSON. Protected endpoints require `Authorization: Bearer <token>`.

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/api/v1/health` | Public | JSON application health check |
| POST | `/api/v1/register` | Public | Create an unverified account; queues a verification email |
| POST | `/api/v1/login` | Public | Issue a Sanctum token for a verified account |
| GET | `/api/v1/email/verify/{id}/{hash}?expires=...&signature=...` | Signed link | Verify the account email |
| POST | `/api/v1/email/verification-notification` | Token | Resend the verification email |
| POST | `/api/v1/logout` | Token | Revoke the current token |
| POST | `/api/v1/forgot-password` | Public | Request a password reset email |
| POST | `/api/v1/reset-password` | Public | Reset a password using the emailed token |
| PUT | `/api/v1/password` | Token | Change password with current password; revokes other tokens |
| GET | `/api/v1/profile` | Token | Read the current user profile |
| PATCH | `/api/v1/profile` | Token | Update name or email; email changes require verification |
| DELETE | `/api/v1/profile` | Token | Delete the current account and its tokens |

Registration and password reset require `password_confirmation`. Passwords must be at least eight characters. Forgot-password always returns a generic response to avoid exposing account existence.

## Project modules

- `Modules/Identity`: registration, token authentication, email verification, and password flows.
- `Modules/Users`: current-user profile operations.

Web routes are disabled; the application exposes no UI.
