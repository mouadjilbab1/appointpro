# AppointPro – Staff Manual

## Introduction
AppointPro includes dedicated capabilities and an independent frontend portal designed specifically for your team members.

## Staff Capabilities
- `appointpro_view_own_appointments`: View only the appointments assigned to the logged-in staff member.
- `appointpro_edit_own_schedule`: Customize personal working hours, break periods, and days off.

## Staff Frontend Portal
Staff can access their schedule and appointments directly from the frontend without needing access to the WordPress admin panel:
- **Shortcode**: `[appointpro_staff_portal]`
- **Features**:
  - Daily & weekly assigned appointment roster.
  - Client names, contact numbers, and special customer instructions.
  - Ability to mark appointments as **Completed** or **No-Show**.
  - Approve or reject pending bookings when permitted by management.

## Synchronizing with External Calendars
Staff members can connect their Google Calendar account to export appointments and automatically import personal busy periods to prevent overlapping bookings.
