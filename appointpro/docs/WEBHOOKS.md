# AppointPro – Outgoing Webhooks Documentation

## Overview
AppointPro can notify external services (such as Zapier, Make, n8n, CRM tools, or custom endpoints) in real-time when booking events occur.

## Available Events
- `appointment.created`
- `appointment.confirmed`
- `appointment.pending`
- `appointment.cancelled`
- `appointment.rescheduled`
- `payment.completed`
- `payment.failed`

## Security & Signature Verification
Every outgoing webhook request includes an HMAC-SHA256 signature in the `X-AppointPro-Signature` HTTP header computed using your configured secret key.

### Verification Example (PHP):
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_APPOINTPRO_SIGNATURE'] ?? '';
$secretKey = 'your_configured_secret_key';

$expectedSignature = hash_hmac('sha256', $payload, $secretKey);

if (hash_equals($expectedSignature, $signature)) {
    // Webhook is authentic
} else {
    // Reject request (403 Forbidden)
}
```
