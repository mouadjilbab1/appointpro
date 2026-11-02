# CHANGELOG – AppointPro

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-02
### Added
- **Core Architecture**: Namespace root `AppointPro\`, PSR-4 autoloading, IoC Service Container, dynamic `$wpdb->prefix . 'appointpro_'` database tables, and centralized Permissions and Security managers.
- **Database Schema**: 28 dedicated custom database tables plus concurrency temporary slot reservation table (`appointpro_slot_locks`).
- **Services & Categories**: Unlimited services, hierarchical categories, duration, buffer times before/after, deposit rules, group capacities, service add-on extras.
- **Staff Management**: Team profiles, WordPress user linking, weekly schedules, recurring breaks, special working days, holidays, and automatic round-robin staff assignment.
- **Multi-Location & Resource Management**: Branch locations with timezone support; shared room and equipment tracking with concurrency collision prevention.
- **Availability Engine**: High-performance, timezone-aware slot generation accounting for working hours, breaks, buffers, and active slot reservation locks.
- **Booking Engine**: 8-step frontend wizard with complete server-side price recalculation, status state machines, and booking reference generation (`AP-XXXXXX`).
- **Payment System**: Modular gateway interface supporting Stripe Elements, PayPal REST API v2, Offline Cash/Bank Wire, and optional WooCommerce cart bridge.
- **Promotions & Notifications**: Promo coupon engine with usage limits; multi-channel notification dispatcher supporting HTML emails, SMS, and WhatsApp with delayed WP-Cron queue processor.
- **Recurring & Group Bookings**: Repetitive appointment series with individual occurrence validation; multi-participant group bookings.
- **Customer & Staff Portals**: Frontend self-service dashboards with object-level security (`[appointpro_customer_portal]`, `[appointpro_staff_portal]`).
- **Reports & Analytics**: Real database metrics for revenue, collected vs outstanding balances, chart points, and CSV data export.
- **REST API & Webhooks**: 17 authenticated REST API controllers and outgoing webhook dispatcher with HMAC-SHA256 signatures.
- **AI Assistant**: Optional OpenAI/Gemini/Claude natural language inquiry guide with zero hallucination.
- **User Interface**: React 18 single-page administrative dashboard and responsive frontend booking wizard with full LTR and RTL styling.
- **Automated Testing Suite**: 21 unit and integration test cases covering availability, pricing, recurrence, security, and migrations with 100% pass rate.
- **Documentation**: 15 complete guides covering installation, configuration, API endpoints, webhooks, troubleshooting, and developer hooks.
- **Packaging**: Commercial installable `appointpro.zip` package with optimized production autoloader.
