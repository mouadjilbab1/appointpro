# AppointPro – REST API Documentation

Base URL: `/wp-json/appointpro/v1/`  
Authentication: WordPress Cookie / Nonce (`X-WP-Nonce`) or Application Passwords.

---

## Endpoints

### 1. Dashboard
- `GET /dashboard`
  - Returns KPI aggregations, upcoming list, and revenue chart data.
  - Requires: `appointpro_view_reports` or `manage_options`.

### 2. Services & Categories
- `GET /services` (Public)
  - Returns active categories and their assigned services.
- `POST /services`
  - Create or update service.
  - Requires: `appointpro_manage_services`.
- `DELETE /services/{id}`
  - Soft-deletes / archives a service.
- `GET /categories` (Public)
- `POST /categories`
- `DELETE /categories/{id}`

### 3. Staff & Schedules
- `GET /staff` (Public)
- `POST /staff`
- `GET /staff/{id}`
- `GET /staff/{id}/schedule`
- `POST /staff/{id}/schedule`
  - Body: JSON array of 7 days with breaks.
- `POST /staff/special-days`

### 4. Slot Availability
- `GET /availability?service_id={id}&staff_id={id}&date=YYYY-MM-DD` (Public)
  - Returns computed bookable time slots.
- `POST /availability/lock` (Public)
  - Temporarily reserves slot for 10 minutes.
- `POST /availability/release`

### 5. Appointments
- `GET /appointments?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
  - Returns calendar events.
- `POST /appointments` (Public)
  - Submits a booking request.
- `POST /appointments/calculate` (Public)
  - Pre-calculates price and deposits on server.
- `POST /appointments/{id}/status`
- `POST /appointments/{id}/reschedule`
- `POST /appointments/{id}/cancel`

### 6. Payments
- `GET /payments/gateways` (Public)
- `POST /payments/charge` (Public)
- `POST /payments/webhook/{gateway}` (Public, HMAC validated)
- `GET /payments` (Admin)

### 7. Coupons
- `GET /coupons`
- `POST /coupons`
- `POST /coupons/validate` (Public)
- `DELETE /coupons/{id}`

### 8. Reports
- `GET /reports?days=30`
- `GET /reports/export?start_date=...&end_date=...`
  - Returns CSV file attachment.
