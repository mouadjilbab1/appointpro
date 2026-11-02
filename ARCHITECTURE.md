# ARCHITECTURE – AppointPro

## 1. System Overview
AppointPro is a high-performance, enterprise-grade WordPress appointment booking and scheduling system. It is designed with a decoupled modular architecture following Clean Architecture and Domain-Driven Design (DDD) principles adapted for WordPress.

### Key Architectural Characteristics
- **Namespace Root**: `AppointPro\`
- **PHP Version**: 8.1+ (Strict types enabled across all PHP files)
- **Database Prefix**: Dynamic via `$wpdb->prefix . 'appointpro_'`
- **Dependency Injection**: Lightweight, high-performance Inversion of Control (IoC) Service Container (`AppointPro\Core\Container`).
- **Data Access**: Repository Pattern isolating raw `$wpdb` queries, prepared statements, and transactional safety.
- **Service Layer**: Pure business logic separated from WordPress hooks and HTTP/REST presentation.
- **REST API**: WordPress REST API controllers (`/wp-json/appointpro/v1/`) with strict permission callbacks and JSON schemas.
- **Frontend & Admin**: React 18+ with standard modern component design, clean CSS design tokens, and RTL support.
- **Storage Strategy**: Dedicated custom database tables for transactional scalability and zero postmeta bloat.

---

## 2. Directory Structure

```
appointpro/
├── appointpro.php              # Plugin Bootstrap Entrypoint
├── uninstall.php               # Complete Uninstaller with Data Retention Check
├── readme.txt                  # WordPress Plugin Directory & CodeCanyon metadata
├── composer.json               # PSR-4 Autoloading & Development dependencies
├── package.json                # Frontend asset build scripts
├── webpack.config.js           # Compilation configuration for React & SCSS
├── includes/
│   ├── Core/                   # Container, Plugin Lifecycle, Configuration, Hooks
│   ├── Database/               # Schema Migrations, DB Connection, Query Helpers
│   ├── Installer/              # Activation, Database Table Creator, Default Seeds
│   ├── Security/               # Nonces, Capabilities, Sanitization, CSRF/XSS Guards
│   ├── Helpers/                # Formatting, Currency, Timezone, Date Math
│   ├── Permissions/            # Capability Registration, Object-level Authorization
│   ├── Services/               # Service & Category Management
│   ├── Staff/                  # Staff Profiles, Working Hours, Schedules, Breaks
│   ├── Locations/              # Multi-location Management & Restrictions
│   ├── Resources/              # Shared Resource Conflict Prevention
│   ├── Customers/              # Customer CRM, Profiles, Notes, Accounts
│   ├── Availability/           # Timezone-aware Real-time Slot Engine
│   ├── Booking/                # Booking Engine, Calculations, Transitions, Wizard
│   ├── Calendar/               # Admin Interactive Calendar (Day, Week, Month)
│   ├── Payments/               # Payment Manager, Adapters (Stripe, PayPal, Offline, WC)
│   ├── Coupons/                # Discount & Promotional Rules Engine
│   ├── Notifications/          # Notification Dispatcher, Email, SMS, WhatsApp
│   ├── Integrations/           # Google Calendar 2-Way Sync, WooCommerce, Elementor
│   ├── Recurring/              # Recurring Appointment Series Engine
│   ├── Group/                  # Multi-capacity Group Booking Logic
│   ├── Cart/                   # Appointment Cart & Sequential Chain Booking
│   ├── WaitingList/            # Automated Waiting List & Slot Claim System
│   ├── Portals/                # Customer & Staff Dedicated Portals
│   ├── Reports/                # Analytics, Revenue Reports, CSV Exporters
│   ├── AI/                     # Modular AI Assistant (OpenAI/Gemini/Anthropic)
│   ├── REST/                   # REST API Controllers & Endpoints
│   ├── Webhooks/               # Outgoing Event Dispatcher & Verification
│   ├── ImportExport/           # Data Migration Tools (CSV, JSON)
│   └── Privacy/                # GDPR Export & Erasure Hooks
├── admin/                      # React Admin Application & Traditional Fallbacks
│   ├── src/                    # React Source Components & Pages
│   └── build/                  # Compiled Production Admin Assets
├── frontend/                   # Customer Booking Wizard & Portal UI
│   ├── src/                    # React / Vanilla JS Multi-step Form Components
│   └── build/                  # Compiled Production Frontend Assets
├── assets/                     # Shared CSS, SVGs, Brand Logos
├── languages/                  # .pot Translation Templates and .po/.mo Files
├── database/                   # Versioned SQL Migration Definitions
└── tests/                      # Automated Unit, Integration, and API Test Suites
```

---

## 3. Core Components & Lifecycle

### 3.1 Service Container (`AppointPro\Core\Container`)
A dependency injection container that manages the lifecycle of all services, repositories, and controllers:
- `bind(string $abstract, callable|string $concrete)`
- `singleton(string $abstract, callable|string $concrete)`
- `get(string $abstract)`
- `has(string $abstract)`

### 3.2 Plugin Bootstrap (`AppointPro\Core\Plugin`)
Orchestrates:
1. Autoloader registration (Composer PSR-4).
2. Service provider registration.
3. Database migration checks.
4. Hook initialization (`init`, `admin_menu`, `rest_api_init`, `wp_enqueue_scripts`).
5. Internationalization setup (`load_plugin_textdomain`).

### 3.3 Security & Authorization Model
- Role-based capabilities (`appointpro_manage_settings`, `appointpro_manage_appointments`, `appointpro_view_reports`, `appointpro_staff_access`).
- Explicit object-level authorization (Staff can only view/edit their own bookings; Customers can only view their own records).
- All requests must supply verified WordPress Nonces (Admin AJAX & REST API headers).
- Inputs sanitized via specialized validators; outputs escaped using `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Parameterized SQL queries via `$wpdb->prepare` exclusively.

---

## 4. Availability Engine Architecture
The calculation engine operates deterministically in UTC:
1. **Time Boundaries**: Defined by location hours, staff weekly schedules, and service notice/horizon rules.
2. **Subtractions**:
   - Staff breaks and approved time off/holidays.
   - Existing confirmed, pending, and locked slots.
   - Pre-buffer and post-buffer padding.
   - Resource collision intervals (if service requires shared rooms/equipment).
3. **Chunking**: Generates discrete booking intervals based on service duration and step frequency.
4. **Concurrency Guard**:
   - Two-phase commit: Temporary slot reservation lock (database table `appointpro_slots_lock`) with 10-minute expiry.
   - Atomic verification on final booking submission within a database transaction.

---

## 5. Payment Gateway Architecture
- Interface: `AppointPro\Payments\GatewayInterface`
  - `getId(): string`
  - `getTitle(): string`
  - `processPayment(Appointment $appointment, array $data): PaymentResult`
  - `handleWebhook(array $payload, array $headers): WebhookResult`
  - `refund(Payment $payment, float $amount): RefundResult`
- Gateway Adapters:
  - `StripeGateway`: PaymentIntents, Webhook signatures, Apple Pay/Google Pay via Elements.
  - `PayPalGateway`: PayPal Orders API v2, Webhook signature verification.
  - `WooCommerceGateway`: Seamless cart injection, checkout handoff, order status synchronization.
  - `OfflineGateway`: Cash on Arrival, Direct Bank Transfer with bank instructions.

---

## 6. Notification System
- Asynchronous queue table `appointpro_notifications` processed via WP-Cron with server cron recommendations.
- Templating engine supports custom placeholders: `{customer_name}`, `{service_name}`, `{staff_name}`, `{appointment_date}`, `{appointment_time}`, `{booking_reference}`, `{zoom_link}`, `{cancellation_link}`.
- Delivery channels:
  - Email (HTML with responsive templates).
  - SMS (Twilio / Webhook gateway).
  - WhatsApp Business Cloud API.
