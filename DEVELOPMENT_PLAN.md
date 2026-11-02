# DEVELOPMENT PLAN – AppointPro

## Phase 1: Project Architecture & Environment Initialization
- Create workspace and plugin directory structure (`appointpro/`).
- Initialize `composer.json` with PSR-4 autoloading (`AppointPro\\`).
- Initialize `package.json` with frontend build dependencies.
- Formulate database migration engine and create all SQL migrations.
- Set up automated testing framework (PHPUnit + Mock harness).
- Write core project management documents.

## Phase 2: Core Infrastructure & Foundational Layer
- Implement `AppointPro\Core\Container` (IoC Container).
- Implement `AppointPro\Core\Plugin` main lifecycle orchestrator.
- Implement `AppointPro\Database\MigrationManager` with versioning and `$wpdb` integration.
- Implement `AppointPro\Installer\Installer` (activation, tables, initial defaults).
- Implement `AppointPro\Security\Security` (nonces, capability enforcement, sanitization).
- Implement `AppointPro\Permissions\PermissionsManager`.
- Implement `AppointPro\Helpers\Formatting`, `Currency`, and `DateTimeHelper`.
- Implement Admin menu registration, settings storage, and asset enqueuing.

## Phase 3: Business Management Modules
- **Categories & Services**: Repositories, Service CRUD, Extras, validation.
- **Staff**: Staff profiles, WP user link, working hours, break periods, holidays.
- **Locations**: Multi-location branches, location schedules.
- **Resources**: Shared equipment/rooms, capacity limits, resource-service mapping.
- **Customers**: CRM records, duplicate detection, WordPress user synchronization.

## Phase 4: Availability Engine & Booking Core
- **Availability Engine**: Timezone-aware slot generation, buffer calculation, schedule overlays, break subtraction.
- **Slot Reservation & Lock**: Atomic concurrency protection via `appointpro_slot_locks`.
- **Booking Engine**: 8-step wizard controller, server-side recalculation of prices, durations, and deposit requirements.
- **Appointment Lifecycle**: State transitions (pending, confirmed, cancelled, rejected, completed).
- **Calendar Engine**: Month/Week/Day queries, drag-and-drop collision detection.
- **Portals**: Customer portal and Staff portal backend controllers and shortcodes.

## Phase 5: Commercial & Advanced Features
- **Payment Gateways**: Stripe, PayPal, WooCommerce integration, Offline/Cash/Bank.
- **Coupons & Discounts**: Validation, limits, calculations.
- **Notifications**: Multi-channel (Email HTML templates, SMS, WhatsApp), scheduled retry queue.
- **Google Calendar Sync**: 2-way synchronization, external busy events.
- **Recurring Bookings**: Series generator, individual occurrence validator.
- **Group Bookings**: Multi-seat booking and capacity constraints.
- **Cart & Chained Bookings**: Sequential services with chained availability.
- **Waiting List**: Auto-notification on cancellations.
- **Reports & Analytics**: Real database metrics, revenue, no-shows, CSV exports.

## Phase 6: REST API, Webhooks, AI Assistant & Customization
- **REST API**: Full REST controllers under `/wp-json/appointpro/v1/`.
- **Webhooks**: Event publisher, HMAC-SHA256 signature, retry queue, delivery logging.
- **AI Assistant**: Optional OpenAI/Gemini/Anthropic provider with zero slot hallucination.
- **Customization & Form Builder**: Custom fields, color schemes, custom labels.
- **Page Builders**: Gutenberg block & Elementor widget integrations.

## Phase 7: Frontend UI & Admin React Application
- Admin React Application: Modern dashboard, calendar view, interactive tables, setup wizard.
- Frontend Booking Wizard: Responsive, accessible, multi-step booking experience with real-time slot loading.
- Production asset compilation via webpack / npm scripts.

## Phase 8: Testing, Quality Assurance, Documentation & Packaging
- Automated Unit & Integration test suite execution.
- Security audit (nonce, capability, SQLi, XSS, CSRF).
- Translation files (`languages/appointpro.pot` and Arabic, French, Spanish, German PO/MO).
- CodeCanyon commercial packaging: production ZIP generator excluding dev dependencies.
- Final user and developer documentation suite.
