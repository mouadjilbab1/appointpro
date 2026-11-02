# DATABASE SCHEMA – AppointPro

All tables are prefixed dynamically with `{$wpdb->prefix}appointpro_`.
All datetime and timestamp values stored in standard UTC (`Y-m-d H:i:s`).

---

## 1. Table Definitions

### 1.1 Categories: `{$wpdb->prefix}appointpro_categories`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique category ID |
| `name` | VARCHAR(191) | NOT NULL | Category name |
| `description` | TEXT | NULL | Category description |
| `color` | VARCHAR(32) | DEFAULT '#4f46e5' | Hex badge color |
| `position` | INT | DEFAULT 0 | Sorting order |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.2 Services: `{$wpdb->prefix}appointpro_services`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique service ID |
| `category_id` | BIGINT UNSIGNED | NULL, INDEX | Foreign key to categories |
| `name` | VARCHAR(191) | NOT NULL | Service title |
| `description` | LONGTEXT | NULL | Detailed description |
| `duration` | INT UNSIGNED | NOT NULL | Duration in minutes |
| `price` | DECIMAL(10,2) | NOT NULL DEFAULT 0.00 | Standard service price |
| `deposit_type` | ENUM('none','fixed','percentage') | DEFAULT 'none' | Deposit requirement type |
| `deposit_amount` | DECIMAL(10,2) | DEFAULT 0.00 | Deposit value |
| `buffer_before` | INT UNSIGNED | DEFAULT 0 | Buffer minutes before |
| `buffer_after` | INT UNSIGNED | DEFAULT 0 | Buffer minutes after |
| `min_notice_hours` | INT UNSIGNED | DEFAULT 2 | Minimum hours before booking |
| `max_advance_days` | INT UNSIGNED | DEFAULT 90 | Max booking horizon in days |
| `capacity_min` | INT UNSIGNED | DEFAULT 1 | Minimum participants |
| `capacity_max` | INT UNSIGNED | DEFAULT 1 | Maximum capacity (group booking) |
| `color` | VARCHAR(32) | DEFAULT '#3b82f6' | Calendar color |
| `image_url` | VARCHAR(500) | NULL | Featured image URL |
| `requires_approval` | TINYINT(1) | DEFAULT 0 | 1 = Pending admin approval |
| `allow_cancellation` | TINYINT(1) | DEFAULT 1 | 1 = Customer can cancel |
| `cancellation_deadline_hours` | INT UNSIGNED | DEFAULT 24 | Cutoff hours for cancellation |
| `allow_rescheduling` | TINYINT(1) | DEFAULT 1 | 1 = Customer can reschedule |
| `is_recurring_enabled` | TINYINT(1) | DEFAULT 0 | 1 = Supports recurring intervals |
| `status` | ENUM('active','hidden','archived') | DEFAULT 'active' | Service visibility |
| `position` | INT | DEFAULT 0 | Display ordering |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.3 Staff: `{$wpdb->prefix}appointpro_staff`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique staff ID |
| `wp_user_id` | BIGINT UNSIGNED | NULL, INDEX | Linked WordPress user ID |
| `full_name` | VARCHAR(191) | NOT NULL | Staff member full name |
| `email` | VARCHAR(191) | NOT NULL, INDEX | Contact email address |
| `phone` | VARCHAR(50) | NULL | Contact phone number |
| `avatar_url` | VARCHAR(500) | NULL | Staff photo URL |
| `bio` | TEXT | NULL | Staff biography / specialties |
| `auto_assign` | TINYINT(1) | DEFAULT 1 | Eligible for round-robin assignment |
| `status` | ENUM('active','inactive') | DEFAULT 'active' | Operational status |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.4 Staff Services: `{$wpdb->prefix}appointpro_staff_services`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique relationship ID |
| `staff_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Link to staff |
| `service_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Link to service |
| `custom_price` | DECIMAL(10,2) | NULL | Override price if not null |
| `custom_duration` | INT UNSIGNED | NULL | Override duration if not null |
| `capacity` | INT UNSIGNED | NULL | Override capacity if not null |
| UNIQUE KEY `staff_service` | (`staff_id`, `service_id`) | | Unique relationship constraint |

### 1.5 Locations: `{$wpdb->prefix}appointpro_locations`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique location ID |
| `name` | VARCHAR(191) | NOT NULL | Location or branch name |
| `address` | TEXT | NULL | Physical address |
| `phone` | VARCHAR(50) | NULL | Location phone |
| `email` | VARCHAR(191) | NULL | Location email |
| `timezone` | VARCHAR(100) | DEFAULT 'UTC' | Location specific timezone |
| `status` | ENUM('active','inactive') | DEFAULT 'active' | Operational status |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.6 Resources: `{$wpdb->prefix}appointpro_resources`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique resource ID |
| `name` | VARCHAR(191) | NOT NULL | Resource title (e.g. Room 101, Laser B) |
| `type` | VARCHAR(50) | DEFAULT 'room' | Resource category / type |
| `capacity` | INT UNSIGNED | DEFAULT 1 | Simultaneous appointments allowed |
| `status` | ENUM('active','maintenance','inactive') | DEFAULT 'active' | Resource status |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.7 Resource Services: `{$wpdb->prefix}appointpro_resource_services`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `resource_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Link to resource |
| `service_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Link to service requiring resource |
| UNIQUE KEY `resource_service` | (`resource_id`, `service_id`) | | Unique constraint |

### 1.8 Weekly Schedules: `{$wpdb->prefix}appointpro_schedules`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `staff_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Staff member |
| `location_id` | BIGINT UNSIGNED | NULL, INDEX | Location (NULL = default/all) |
| `day_of_week` | TINYINT UNSIGNED | NOT NULL | 0 = Sunday, 1 = Monday ... 6 = Saturday |
| `start_time` | TIME | NOT NULL | Workday start (e.g. 09:00:00) |
| `end_time` | TIME | NOT NULL | Workday end (e.g. 17:00:00) |
| `is_working` | TINYINT(1) | DEFAULT 1 | 1 = Working day, 0 = Off day |
| UNIQUE KEY `staff_day_loc` | (`staff_id`, `location_id`, `day_of_week`) | | Single schedule per day/location |

### 1.9 Schedule Breaks: `{$wpdb->prefix}appointpro_schedule_breaks`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `schedule_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Reference to schedule |
| `start_time` | TIME | NOT NULL | Break start (e.g. 12:30:00) |
| `end_time` | TIME | NOT NULL | Break end (e.g. 13:30:00) |
| `label` | VARCHAR(100) | DEFAULT 'Lunch' | Break description |

### 1.10 Special Days & Time-off: `{$wpdb->prefix}appointpro_special_days`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `staff_id` | BIGINT UNSIGNED | NULL, INDEX | Staff member (NULL = business-wide) |
| `start_date` | DATE | NOT NULL | Date or start of holiday |
| `end_date` | DATE | NOT NULL | Date or end of holiday |
| `start_time` | TIME | NULL | If partial working day |
| `end_time` | TIME | NULL | If partial working day |
| `is_day_off` | TINYINT(1) | DEFAULT 1 | 1 = Complete holiday, 0 = custom hours |
| `reason` | VARCHAR(191) | NULL | Leave note or holiday name |

### 1.11 Customers: `{$wpdb->prefix}appointpro_customers`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Customer ID |
| `wp_user_id` | BIGINT UNSIGNED | NULL, INDEX | Linked WordPress user |
| `first_name` | VARCHAR(100) | NOT NULL | Customer first name |
| `last_name` | VARCHAR(100) | NOT NULL | Customer last name |
| `email` | VARCHAR(191) | NOT NULL, INDEX | Customer email address |
| `phone` | VARCHAR(50) | NULL, INDEX | Customer phone number |
| `notes` | TEXT | NULL | Internal admin notes |
| `locale` | VARCHAR(10) | DEFAULT 'en_US' | Customer language preference |
| `created_at` | DATETIME | NOT NULL | Registration timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.12 Appointments: `{$wpdb->prefix}appointpro_appointments`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Appointment ID |
| `booking_reference` | VARCHAR(32) | NOT NULL, UNIQUE INDEX | Human readable code (e.g. AP-873912) |
| `service_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Booked service |
| `staff_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Assigned staff member |
| `location_id` | BIGINT UNSIGNED | NULL, INDEX | Selected business location |
| `start_time` | DATETIME | NOT NULL, INDEX | Slot start (UTC) |
| `end_time` | DATETIME | NOT NULL, INDEX | Slot end (UTC) |
| `status` | ENUM('pending','confirmed','cancelled','rejected','completed','no-show') | DEFAULT 'confirmed', INDEX | Lifecycle state |
| `total_price` | DECIMAL(10,2) | NOT NULL DEFAULT 0.00 | Total price before discounts |
| `discount_amount` | DECIMAL(10,2) | NOT NULL DEFAULT 0.00 | Applied coupon discount |
| `final_price` | DECIMAL(10,2) | NOT NULL DEFAULT 0.00 | Final price after discount |
| `paid_amount` | DECIMAL(10,2) | NOT NULL DEFAULT 0.00 | Total amount captured |
| `payment_status` | ENUM('unpaid','partial','paid','refunded') | DEFAULT 'unpaid', INDEX | Payment state |
| `customer_notes` | TEXT | NULL | Notes entered by customer |
| `internal_notes` | TEXT | NULL | Staff/admin internal notes |
| `series_id` | BIGINT UNSIGNED | NULL, INDEX | If recurring appointment |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |
| `updated_at` | DATETIME | NOT NULL | Last update timestamp |

### 1.13 Appointment Customers (Participants): `{$wpdb->prefix}appointpro_appointment_customers`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Participant record ID |
| `appointment_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Parent appointment |
| `customer_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Customer participant |
| `number_of_persons` | INT UNSIGNED | DEFAULT 1 | Seats booked by this customer |
| `status` | ENUM('registered','cancelled') | DEFAULT 'registered' | Participant status |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |

### 1.14 Extras: `{$wpdb->prefix}appointpro_extras`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Extra item ID |
| `service_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Associated service |
| `name` | VARCHAR(191) | NOT NULL | Extra name (e.g. Aromatherapy) |
| `description` | TEXT | NULL | Description |
| `price` | DECIMAL(10,2) | NOT NULL DEFAULT 0.00 | Additional price |
| `duration` | INT UNSIGNED | DEFAULT 0 | Additional minutes added |
| `max_quantity` | INT UNSIGNED | DEFAULT 1 | Maximum selectable |
| `status` | ENUM('active','inactive') | DEFAULT 'active' | Visibility |

### 1.15 Appointment Extras: `{$wpdb->prefix}appointpro_appointment_extras`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `appointment_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Parent appointment |
| `extra_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Selected extra |
| `quantity` | INT UNSIGNED | DEFAULT 1 | Quantity selected |
| `price` | DECIMAL(10,2) | NOT NULL | Price charged per unit |

### 1.16 Resource Allocations: `{$wpdb->prefix}appointpro_resource_allocations`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Allocation ID |
| `appointment_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Parent appointment |
| `resource_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Allocated resource |
| `start_time` | DATETIME | NOT NULL, INDEX | Start UTC |
| `end_time` | DATETIME | NOT NULL, INDEX | End UTC |

### 1.17 Payments: `{$wpdb->prefix}appointpro_payments`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Payment record ID |
| `appointment_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Parent appointment |
| `gateway` | VARCHAR(50) | NOT NULL | Gateway ID (stripe, paypal, wc, offline) |
| `transaction_id` | VARCHAR(191) | NULL, INDEX | External gateway reference |
| `amount` | DECIMAL(10,2) | NOT NULL | Captured amount |
| `currency` | VARCHAR(10) | NOT NULL DEFAULT 'USD' | ISO currency code |
| `type` | ENUM('full','deposit','remaining') | DEFAULT 'full' | Payment installment type |
| `status` | ENUM('pending','completed','failed','refunded') | DEFAULT 'pending', INDEX | Transaction status |
| `gateway_response` | LONGTEXT | NULL | JSON raw gateway transaction payload |
| `created_at` | DATETIME | NOT NULL | Timestamp |

### 1.18 Coupons: `{$wpdb->prefix}appointpro_coupons`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Coupon ID |
| `code` | VARCHAR(50) | NOT NULL, UNIQUE INDEX | Promo code (e.g. SUMMER25) |
| `discount_type` | ENUM('fixed','percentage') | NOT NULL | Discount mode |
| `discount_value` | DECIMAL(10,2) | NOT NULL | Discount amount or % |
| `min_order_amount` | DECIMAL(10,2) | DEFAULT 0.00 | Minimum order threshold |
| `usage_limit_total` | INT UNSIGNED | NULL | Max total redemptions |
| `usage_limit_per_customer` | INT UNSIGNED | DEFAULT 1 | Max redemptions per customer |
| `used_count` | INT UNSIGNED | DEFAULT 0 | Current redemption count |
| `expires_at` | DATETIME | NULL | Expiry date |
| `status` | ENUM('active','inactive') | DEFAULT 'active' | Operational status |
| `created_at` | DATETIME | NOT NULL | Creation timestamp |

### 1.19 Coupon Usage: `{$wpdb->prefix}appointpro_coupon_usage`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `coupon_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Coupon applied |
| `customer_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Customer who redeemed |
| `appointment_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Booking used on |
| `discount_amount` | DECIMAL(10,2) | NOT NULL | Value of discount applied |
| `used_at` | DATETIME | NOT NULL | Redemption timestamp |

### 1.20 Custom Fields: `{$wpdb->prefix}appointpro_custom_fields`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Field ID |
| `label` | VARCHAR(191) | NOT NULL | Field title |
| `field_type` | ENUM('text','textarea','number','select','checkbox','radio','date') | NOT NULL | Form control type |
| `options` | TEXT | NULL | JSON array of options for select/radio |
| `is_required` | TINYINT(1) | DEFAULT 0 | 1 = Mandatory |
| `services` | TEXT | NULL | JSON array of service IDs (NULL = all) |
| `position` | INT | DEFAULT 0 | Sorting order in checkout wizard |

### 1.21 Custom Field Values: `{$wpdb->prefix}appointpro_custom_field_values`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `appointment_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Appointment reference |
| `field_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Custom field reference |
| `value` | LONGTEXT | NULL | Customer submitted answer |

### 1.22 Notifications Queue & Logs: `{$wpdb->prefix}appointpro_notifications`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Notification ID |
| `appointment_id` | BIGINT UNSIGNED | NULL, INDEX | Related appointment |
| `recipient` | VARCHAR(191) | NOT NULL | Email or phone number |
| `channel` | ENUM('email','sms','whatsapp') | NOT NULL | Delivery transport |
| `event` | VARCHAR(100) | NOT NULL | Event name (created, reminder, etc.) |
| `subject` | VARCHAR(255) | NULL | Subject line (email) |
| `message` | LONGTEXT | NOT NULL | Rendered message body |
| `status` | ENUM('queued','sent','failed') | DEFAULT 'queued', INDEX | Dispatch status |
| `attempts` | TINYINT UNSIGNED | DEFAULT 0 | Delivery retry counter |
| `error_message` | TEXT | NULL | Failure diagnostic details |
| `scheduled_for` | DATETIME | NOT NULL, INDEX | Trigger timestamp |
| `sent_at` | DATETIME | NULL | Timestamp of actual delivery |

### 1.23 Waiting List: `{$wpdb->prefix}appointpro_waiting_list`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Waiting list ID |
| `service_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Desired service |
| `staff_id` | BIGINT UNSIGNED | NULL, INDEX | Desired staff member (NULL = any) |
| `customer_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Customer waiting |
| `desired_date` | DATE | NOT NULL, INDEX | Desired date |
| `status` | ENUM('waiting','notified','booked','expired','cancelled') | DEFAULT 'waiting', INDEX | State |
| `offer_expires_at` | DATETIME | NULL | Expiry of notified reservation claim |
| `created_at` | DATETIME | NOT NULL | Entry timestamp |

### 1.24 Recurring Series: `{$wpdb->prefix}appointpro_recurring_series`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Series ID |
| `customer_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Customer owner |
| `frequency` | ENUM('daily','weekly','monthly','custom') | NOT NULL | Repetition type |
| `repeat_interval` | INT UNSIGNED | DEFAULT 1 | e.g. every 2 weeks |
| `total_occurrences` | INT UNSIGNED | NOT NULL | Target total bookings |
| `completed_occurrences` | INT UNSIGNED | DEFAULT 0 | Bookings successfully confirmed |
| `status` | ENUM('active','completed','cancelled') | DEFAULT 'active' | Series status |
| `created_at` | DATETIME | NOT NULL | Timestamp |

### 1.25 Audit Logs: `{$wpdb->prefix}appointpro_audit_logs`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Log ID |
| `user_id` | BIGINT UNSIGNED | NULL, INDEX | Performing WP user |
| `action` | VARCHAR(100) | NOT NULL, INDEX | Action key (e.g. appointment_rescheduled) |
| `entity_type` | VARCHAR(50) | NOT NULL | e.g. appointment, service, staff |
| `entity_id` | BIGINT UNSIGNED | NOT NULL, INDEX | ID of modified entity |
| `details` | LONGTEXT | NULL | JSON payload of before/after changes |
| `ip_address` | VARCHAR(45) | NULL | Request IP address |
| `created_at` | DATETIME | NOT NULL, INDEX | Timestamp |

### 1.26 Webhooks: `{$wpdb->prefix}appointpro_webhooks`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Webhook ID |
| `name` | VARCHAR(191) | NOT NULL | Friendly hook name |
| `target_url` | VARCHAR(500) | NOT NULL | HTTPS destination URL |
| `secret_key` | VARCHAR(100) | NOT NULL | HMAC secret key for signing |
| `events` | TEXT | NOT NULL | JSON array of subscribed event topics |
| `status` | ENUM('active','inactive') | DEFAULT 'active' | Operational status |
| `created_at` | DATETIME | NOT NULL | Timestamp |

### 1.27 Webhook Logs: `{$wpdb->prefix}appointpro_webhook_logs`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Log entry ID |
| `webhook_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Target webhook |
| `event` | VARCHAR(100) | NOT NULL | Event fired |
| `payload` | LONGTEXT | NOT NULL | Transmitted JSON payload |
| `response_code` | INT | NULL | HTTP response code |
| `response_body` | TEXT | NULL | HTTP response body |
| `attempts` | TINYINT UNSIGNED | DEFAULT 1 | Attempt count |
| `status` | ENUM('success','failed') | NOT NULL | Result |
| `created_at` | DATETIME | NOT NULL, INDEX | Timestamp |

### 1.28 Integrations & Sync: `{$wpdb->prefix}appointpro_integrations`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Record ID |
| `provider` | VARCHAR(50) | NOT NULL | e.g. google_calendar, zoom, twilio |
| `staff_id` | BIGINT UNSIGNED | NULL, INDEX | Staff link (if user-level auth) |
| `access_token` | TEXT | NULL | Encrypted access token |
| `refresh_token` | TEXT | NULL | Encrypted refresh token |
| `token_expires_at`| DATETIME | NULL | Token expiration |
| `settings` | LONGTEXT | NULL | JSON integration configurations |
| `last_synced_at` | DATETIME | NULL | Timestamp of last sync execution |
| UNIQUE KEY `provider_staff` | (`provider`, `staff_id`) | | Unique provider per staff/global |

---

## 2. Concurrency Lock Table: `{$wpdb->prefix}appointpro_slot_locks`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Lock ID |
| `staff_id` | BIGINT UNSIGNED | NOT NULL, INDEX | Staff being reserved |
| `start_time` | DATETIME | NOT NULL, INDEX | Slot start (UTC) |
| `end_time` | DATETIME | NOT NULL, INDEX | Slot end (UTC) |
| `session_token`| VARCHAR(64) | NOT NULL, INDEX | Customer browser checkout session token |
| `expires_at` | DATETIME | NOT NULL, INDEX | Lock timeout timestamp (UTC) |
| UNIQUE KEY `staff_slot_lock` | (`staff_id`, `start_time`, `end_time`) | | Hard uniqueness prevents double checkout |
