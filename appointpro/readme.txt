=== AppointPro – Ultimate WordPress Appointment Booking & Scheduling System ===
Contributors: appointpro
Tags: booking, appointments, scheduling, calendar, stripe, staff, reservations
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The ultimate, enterprise-grade appointment booking and scheduling system for WordPress.

== Description ==

AppointPro is a high-performance, modern appointment booking and scheduling plugin built from the ground up for businesses of all sizes worldwide.

Whether you run a medical clinic, salon, spa, fitness studio, law firm, educational consultancy, or repair business, AppointPro automates your client scheduling, payments, staff management, and reminders with elegance and precision.

### Key Features:
* **8-Step Booking Wizard**: Fast, responsive, accessible customer booking flow.
* **Intelligent Availability Engine**: Real-time slot calculation accounting for staff hours, breaks, buffer times, and holidays with zero double bookings.
* **Modular Payment Gateways**: Built-in support for Stripe (Credit Cards, Apple Pay), PayPal, and Cash on Arrival / Bank Transfer.
* **Customer & Staff Portals**: Dedicated self-service dashboards for customers and team members.
* **Interactive Administrative Calendar**: Day, Week, and Month views with conflict checking and iCal export.
* **Shared Resource Management**: Prevent double allocation of exclusive rooms, equipment, or vehicles.
* **Multi-Location Support**: Configure branches with dedicated hours and staff.
* **Recurring & Group Bookings**: Book repeated sessions or multi-participant classes.
* **Coupons & Promotional Discounts**: Fixed or percentage discounts with custom expiry and limits.
* **Multi-Channel Notifications**: HTML email templates, SMS, and WhatsApp Cloud API support.
* **2-Way Google Calendar Sync**: Synchronize appointments and avoid personal calendar overlaps.
* **Optional WooCommerce Bridge**: Route bookings through WooCommerce cart and checkout.
* **Optional AI Assistant**: Conversational booking guide powered by OpenAI, Google Gemini, or Claude.
* **REST API & Webhooks**: Full API coverage with HMAC-SHA256 signed event delivery.
* **100% Translation Ready**: Includes POT file and full RTL support for Arabic and Hebrew.

== Installation ==

1. Upload `appointpro.zip` via **Plugins** -> **Add New** -> **Upload Plugin** in your WordPress admin.
2. Click **Activate Plugin**.
3. Use the **AppointPro** admin menu to configure your business settings, staff, and services.
4. Add the `[appointpro_booking]` shortcode or Gutenberg block to any page.

== Frequently Asked Questions ==

= Does AppointPro prevent double bookings? =
Yes. AppointPro implements atomic transactional checking and temporary reservation locks to eliminate race conditions.

= Does it require external paid libraries? =
No. All required runtime dependencies and compiled assets are included in the package.

== Changelog ==

= 1.0.0 =
* Initial commercial release of AppointPro.
