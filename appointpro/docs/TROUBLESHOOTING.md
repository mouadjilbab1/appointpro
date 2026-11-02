# AppointPro – Troubleshooting & Diagnostics Guide

## 1. No Time Slots Appearing in Booking Wizard
- **Check Working Schedules**: Go to **AppointPro** -> **Staff**, select your staff member, and ensure at least one working day is toggled active with valid hours (e.g., 09:00 to 17:00).
- **Check Service Notice & Horizon**: In **AppointPro** -> **Services**, verify that `Minimum Notice Hours` is not blocking slots for today, and `Max Advance Days` allows the requested date.
- **Check Holidays / Days Off**: Verify that no whole-day holiday is active under **Special Days**.

## 2. Emails Not Sending
- AppointPro queues emails asynchronously into the `appointpro_notifications` database table and dispatches them via WP-Cron every 5 minutes.
- If your site has low traffic, WordPress WP-Cron only fires when a visitor loads a page. For production websites, we strongly recommend configuring a real server cron job:
  ```bash
  * * * * * curl -s https://yourdomain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
  ```
- Use a standard SMTP plugin (e.g. WP Mail SMTP) to ensure deliverability and avoid server spam filters.

## 3. Double Booking Prevention Under High Traffic
- AppointPro implements a 2-phase lock:
  1. A 10-minute temporary reservation lock is placed in `appointpro_slot_locks` during the checkout session.
  2. An atomic SQL verification is executed inside a transaction right before final insertion.
- If two visitors attempt to check out the exact same slot concurrently, the second visitor will immediately receive a friendly prompt to choose another slot without corrupting calendar data.
