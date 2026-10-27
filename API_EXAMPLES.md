# API request and response examples

These examples use `curl` and JSON. Start the stack and apply migrations first:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

Mail sent by the API is queued in Redis and delivered by the `queue` service. View it in Mailpit at <http://localhost:8025>.

Set the API base URL to match the port used by Docker Compose. Sail's default is `http://localhost`; if using a custom `APP_PORT`, include it here.

```bash
BASE_URL=http://localhost
```

All endpoints below are relative to `$BASE_URL`. Protected routes require a bearer token. Examples use `jq` to extract values from responses; replace values in angle brackets with the values from your own API responses or Mailpit.

## Health check

```bash
curl -i "$BASE_URL/api/v1/health"
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
```

```json
{
  "status": "ok"
}
```

## Register

Registering creates an unverified user and queues the verification email.

```bash
curl -i -X POST "$BASE_URL/api/v1/register" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Taylor User",
    "email": "taylor@example.test",
    "password": "Example-password-123",
    "password_confirmation": "Example-password-123"
  }'
```

```http
HTTP/1.1 201 Created
Content-Type: application/json
```

```json
{
  "message": "Registration successful. Check your email to verify your account.",
  "user": {
    "id": 1,
    "name": "Taylor User",
    "email": "taylor@example.test",
    "email_verified_at": null
  }
}
```

Open Mailpit and find **Verify your email address**. Its signed verification link contains the user's ID, email hash, expiry, and signature.

## Verify email

Open the full signed URL from the verification email. Do not remove or edit the `expires` or `signature` query parameters.

```bash
curl -i '<SIGNED_VERIFICATION_URL_FROM_MAILPIT>'
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
```

```json
{
  "message": "Email address verified."
}
```

The link is temporary and signed. An invalid or expired signature is rejected.

## Login

Login is allowed only after email verification. Save the returned token for authenticated requests.

```bash
LOGIN_RESPONSE=$(curl -sS -X POST "$BASE_URL/api/v1/login" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "taylor@example.test",
    "password": "Example-password-123"
  }')

printf '%s\n' "$LOGIN_RESPONSE" | jq .
TOKEN=$(printf '%s\n' "$LOGIN_RESPONSE" | jq -r '.token')
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
```

```json
{
  "token": "<SANCTUM_BEARER_TOKEN>",
  "user": {
    "id": 1,
    "name": "Taylor User",
    "email": "taylor@example.test",
    "email_verified_at": "2026-10-27T03:00:00.000000Z"
  }
}
```

An unverified account or invalid credentials receives `422 Unprocessable Content` with a JSON `message` and `errors` object.

## Resend verification email

Requires a bearer token. This endpoint is rate-limited.

```bash
curl -i -X POST "$BASE_URL/api/v1/email/verification-notification" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

```json
{
  "message": "Verification email sent."
}
```

If the email is already verified:

```json
{
  "message": "Email address is already verified."
}
```

## Read profile

```bash
curl -i "$BASE_URL/api/v1/profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

```json
{
  "user": {
    "id": 1,
    "name": "Taylor User",
    "email": "taylor@example.test",
    "email_verified_at": "2026-10-27T03:00:00.000000Z"
  }
}
```

## Update profile

Only `name` and `email` can be updated. Changing the email clears its verification status and queues a new verification email. Protected fields such as `email_verified_at` are ignored.

```bash
curl -i -X PATCH "$BASE_URL/api/v1/profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Taylor Updated",
    "email": "taylor.updated@example.test"
  }'
```

```json
{
  "message": "Profile updated.",
  "user": {
    "id": 1,
    "name": "Taylor Updated",
    "email": "taylor.updated@example.test",
    "email_verified_at": null
  }
}
```

## Forgot password

This endpoint returns the same response whether or not the email belongs to an account. If an account exists, Mailpit receives a **Reset your password** email containing the reset token.

```bash
curl -i -X POST "$BASE_URL/api/v1/forgot-password" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"taylor@example.test"}'
```

```json
{
  "message": "If an account exists for that email, a password reset email has been sent."
}
```

## Reset password

Copy the token from the reset email. Resetting the password revokes all existing Sanctum tokens and queues a password-changed email.

```bash
curl -i -X POST "$BASE_URL/api/v1/reset-password" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "taylor@example.test",
    "token": "<RESET_TOKEN_FROM_MAILPIT>",
    "password": "Another-password-456",
    "password_confirmation": "Another-password-456"
  }'
```

```json
{
  "message": "Password has been reset."
}
```

## Change password

Requires a bearer token and the current password. Other tokens for the account are revoked; the token used for this request remains active. A password-changed email is queued.

```bash
curl -i -X PUT "$BASE_URL/api/v1/password" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "current_password": "Another-password-456",
    "password": "Changed-password-789",
    "password_confirmation": "Changed-password-789"
  }'
```

```json
{
  "message": "Password has been changed."
}
```

## Logout

Revokes the current bearer token.

```bash
curl -i -X POST "$BASE_URL/api/v1/logout" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

```json
{
  "message": "Logged out successfully."
}
```

## Delete account

Deletes the authenticated account and its tokens. This action cannot be undone.

```bash
curl -i -X DELETE "$BASE_URL/api/v1/profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

```json
{
  "message": "Account deleted."
}
```

## Common errors

Validation errors return `422 Unprocessable Content`, for example when a password confirmation does not match:

```json
{
  "message": "The password field confirmation does not match.",
  "errors": {
    "password": [
      "The password field confirmation does not match."
    ]
  }
}
```

Protected endpoints without a valid bearer token return `401 Unauthorized`:

```json
{
  "message": "Unauthenticated."
}
```

## Email messages in Mailpit

| Trigger | Subject |
|---|---|
| Register, resend verification, or change profile email | Verify your email address |
| Forgot password | Reset your password |
| Reset or change password | Your password has been changed |
