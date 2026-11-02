<?php
declare(strict_types=1);

namespace AppointPro\Booking;

use AppointPro\Availability\AvailabilityEngine;
use AppointPro\Coupons\CouponService;
use AppointPro\Customers\CustomerRepository;
use AppointPro\Helpers\Formatting;
use AppointPro\Notifications\NotificationDispatcher;
use AppointPro\Resources\ResourceRepository;
use AppointPro\Services\ServiceRepository;
use AppointPro\Staff\StaffRepository;
use Exception;
use RuntimeException;

/**
 * Core Booking Engine: Validates, Prices, Schedules, and Commits Appointments.
 */
class BookingEngine {
    protected ServiceRepository $serviceRepo;
    protected StaffRepository $staffRepo;
    protected CustomerRepository $customerRepo;
    protected ResourceRepository $resourceRepo;
    protected AvailabilityEngine $availabilityEngine;

    public function __construct(
        ?ServiceRepository $serviceRepo = null,
        ?StaffRepository $staffRepo = null,
        ?CustomerRepository $customerRepo = null,
        ?ResourceRepository $resourceRepo = null,
        ?AvailabilityEngine $availabilityEngine = null
    ) {
        $this->serviceRepo = $serviceRepo ?: new ServiceRepository();
        $this->staffRepo = $staffRepo ?: new StaffRepository();
        $this->customerRepo = $customerRepo ?: new CustomerRepository();
        $this->resourceRepo = $resourceRepo ?: new ResourceRepository();
        $this->availabilityEngine = $availabilityEngine ?: new AvailabilityEngine();
    }

    /**
     * Calculate definitive price breakdown for a booking request on the server.
     *
     * @param int $serviceId
     * @param int $staffId
     * @param array<int, int> $extras [extra_id => quantity]
     * @param int $numberOfPersons
     * @param string|null $couponCode
     * @return array{
     *     base_price: float,
     *     extras_price: float,
     *     subtotal: float,
     *     discount: float,
     *     final_price: float,
     *     deposit_amount: float,
     *     deposit_type: string,
     *     balance_due: float
     * }
     */
    public function calculatePricing(
        int $serviceId,
        int $staffId,
        array $extras = [],
        int $numberOfPersons = 1,
        ?string $couponCode = null
    ): array {
        $service = $this->serviceRepo->find($serviceId);
        if (!$service) {
            throw new RuntimeException(__('Invalid service selected.', 'appointpro'));
        }

        global $wpdb;
        $staffSvcTable = $wpdb->prefix . 'appointpro_staff_services';

        // Check for custom staff price override
        $customPrice = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_price FROM `{$staffSvcTable}` WHERE staff_id = %d AND service_id = %d LIMIT 1",
            $staffId,
            $serviceId
        ));

        $unitPrice = ($customPrice !== null && $customPrice !== '') ? (float) $customPrice : (float) $service['price'];
        $baseTotal = $unitPrice * max(1, $numberOfPersons);

        // Calculate Extras
        $extrasTotal = 0.0;
        if (!empty($extras)) {
            $extraTable = $wpdb->prefix . 'appointpro_extras';
            foreach ($extras as $extraId => $qty) {
                $qty = max(0, (int) $qty);
                if ($qty <= 0) {
                    continue;
                }
                $extraRow = $wpdb->get_row($wpdb->prepare(
                    "SELECT price FROM `{$extraTable}` WHERE id = %d AND service_id = %d AND status = 'active'",
                    $extraId,
                    $serviceId
                ), ARRAY_A);

                if ($extraRow) {
                    $extrasTotal += ((float) $extraRow['price']) * $qty;
                }
            }
        }

        $subtotal = $baseTotal + $extrasTotal;
        $discount = 0.0;

        // Apply Coupon if valid
        if (!empty($couponCode)) {
            $couponService = new CouponService();
            $couponResult = $couponService->validateAndCalculateDiscount($couponCode, $subtotal, $serviceId);
            if ($couponResult['valid']) {
                $discount = (float) $couponResult['discount'];
            }
        }

        $finalPrice = max(0.0, round($subtotal - $discount, 2));

        // Calculate Deposit Requirement
        $depositType = $service['deposit_type'] ?? 'none';
        $depositVal = (float) ($service['deposit_amount'] ?? 0.0);
        $depositAmount = 0.0;

        if ($depositType === 'fixed') {
            $depositAmount = min($finalPrice, $depositVal);
        } elseif ($depositType === 'percentage') {
            $depositAmount = round(($finalPrice * ($depositVal / 100.0)), 2);
        }

        $balanceDue = max(0.0, round($finalPrice - $depositAmount, 2));

        return [
            'base_price' => round($baseTotal, 2),
            'extras_price' => round($extrasTotal, 2),
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'final_price' => $finalPrice,
            'deposit_amount' => $depositAmount,
            'deposit_type' => $depositType,
            'balance_due' => $balanceDue,
        ];
    }

    /**
     * Create an appointment with complete server-side revalidation and transactional safety.
     *
     * @param array<string, mixed> $payload
     * @return array{success: bool, appointment_id: int, booking_reference: string, payment_status: string}
     * @throws Exception
     */
    public function createAppointment(array $payload): array {
        global $wpdb;

        $serviceId = (int) ($payload['service_id'] ?? 0);
        $staffId = (int) ($payload['staff_id'] ?? 0);
        $locationId = !empty($payload['location_id']) ? (int) $payload['location_id'] : null;
        $utcStart = sanitize_text_field($payload['start_time'] ?? '');
        $utcEnd = sanitize_text_field($payload['end_time'] ?? '');
        $customerData = (array) ($payload['customer'] ?? []);
        $extras = (array) ($payload['extras'] ?? []);
        $customFields = (array) ($payload['custom_fields'] ?? []);
        $couponCode = !empty($payload['coupon_code']) ? sanitize_text_field($payload['coupon_code']) : null;
        $customerNotes = sanitize_textarea_field($payload['customer_notes'] ?? '');
        $sessionToken = sanitize_text_field($payload['session_token'] ?? '');
        $numberOfPersons = max(1, (int) ($payload['number_of_persons'] ?? 1));

        $service = $this->serviceRepo->find($serviceId);
        if (!$service || $service['status'] !== 'active') {
            throw new RuntimeException(__('Selected service is currently unavailable.', 'appointpro'));
        }

        // Automatic staff assignment if none specified
        if ($staffId <= 0) {
            $date = gmdate('Y-m-d', strtotime($utcStart));
            $staffId = $this->staffRepo->autoAssignStaff($serviceId, $date);
            if (!$staffId) {
                throw new RuntimeException(__('No available staff member could be assigned for this slot.', 'appointpro'));
            }
        }

        // START TRANSACTION
        $wpdb->query('START TRANSACTION');

        try {
            // Re-validate availability atomically
            $apptTable = $wpdb->prefix . 'appointpro_appointments';
            $collision = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$apptTable}`
                 WHERE staff_id = %d
                 AND status IN ('confirmed', 'pending')
                 AND (start_time < %s AND end_time > %s)",
                $staffId,
                $utcEnd,
                $utcStart
            ));

            if ($collision > 0) {
                throw new RuntimeException(__('The requested time slot was just booked by another customer. Please choose another time.', 'appointpro'));
            }

            // Check resource availability
            $requiredResource = $this->resourceRepo->getResourceForService($serviceId);
            if ($requiredResource) {
                $resAvailable = $this->resourceRepo->isResourceAvailable((int) $requiredResource['id'], $utcStart, $utcEnd);
                if (!$resAvailable) {
                    throw new RuntimeException(__('Required equipment/room is not available at this time.', 'appointpro'));
                }
            }

            // Customer find or create
            $wpUserId = get_current_user_id() ?: null;
            $customerId = $this->customerRepo->findOrCreate($customerData, $wpUserId);

            // Compute definitive pricing
            $pricing = $this->calculatePricing($serviceId, $staffId, $extras, $numberOfPersons, $couponCode);

            // Determine status based on approval requirements
            $requiresApproval = (int) ($service['requires_approval'] ?? 0);
            $initialStatus = $requiresApproval ? 'pending' : 'confirmed';

            // Generate unique booking reference
            $bookingRef = Formatting::generateBookingReference();
            $now = gmdate('Y-m-d H:i:s');

            // Insert Appointment
            $inserted = $wpdb->insert($apptTable, [
                'booking_reference' => $bookingRef,
                'service_id' => $serviceId,
                'staff_id' => $staffId,
                'location_id' => $locationId,
                'start_time' => $utcStart,
                'end_time' => $utcEnd,
                'status' => $initialStatus,
                'total_price' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount'],
                'final_price' => $pricing['final_price'],
                'paid_amount' => 0.00,
                'payment_status' => 'unpaid',
                'customer_notes' => $customerNotes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (!$inserted) {
                throw new RuntimeException(__('Failed to create appointment record.', 'appointpro'));
            }

            $appointmentId = (int) $wpdb->insert_id;

            // Insert Appointment Customer (Participant)
            $participantTable = $wpdb->prefix . 'appointpro_appointment_customers';
            $wpdb->insert($participantTable, [
                'appointment_id' => $appointmentId,
                'customer_id' => $customerId,
                'number_of_persons' => $numberOfPersons,
                'status' => 'registered',
                'created_at' => $now,
            ]);

            // Insert Extras
            if (!empty($extras)) {
                $apptExtrasTable = $wpdb->prefix . 'appointpro_appointment_extras';
                $extraDefTable = $wpdb->prefix . 'appointpro_extras';
                foreach ($extras as $extraId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) continue;
                    $extraPrice = (float) $wpdb->get_var($wpdb->prepare("SELECT price FROM `{$extraDefTable}` WHERE id = %d", $extraId));
                    $wpdb->insert($apptExtrasTable, [
                        'appointment_id' => $appointmentId,
                        'extra_id' => $extraId,
                        'quantity' => $qty,
                        'price' => $extraPrice,
                    ]);
                }
            }

            // Allocate Resource
            if ($requiredResource) {
                $this->resourceRepo->allocate($appointmentId, (int) $requiredResource['id'], $utcStart, $utcEnd);
            }

            // Save Custom Fields
            if (!empty($customFields)) {
                $cfvTable = $wpdb->prefix . 'appointpro_custom_field_values';
                foreach ($customFields as $fieldId => $val) {
                    $wpdb->insert($cfvTable, [
                        'appointment_id' => $appointmentId,
                        'field_id' => (int) $fieldId,
                        'value' => is_array($val) ? json_encode($val) : sanitize_text_field((string) $val),
                    ]);
                }
            }

            // Record Coupon Usage
            if (!empty($couponCode) && $pricing['discount'] > 0) {
                $couponService = new CouponService();
                $couponService->recordUsage($couponCode, $customerId, $appointmentId, $pricing['discount']);
            }

            // Log Audit
            $this->logAudit('appointment_created', $appointmentId, [
                'booking_reference' => $bookingRef,
                'final_price' => $pricing['final_price'],
                'status' => $initialStatus,
            ]);

            // Release temporary slot lock
            if ($sessionToken) {
                $this->availabilityEngine->releaseSlotLock($sessionToken);
            }

            $wpdb->query('COMMIT');

            // Queue notification
            $dispatcher = new NotificationDispatcher();
            $dispatcher->queueEventNotification(
                $initialStatus === 'confirmed' ? 'appointment_confirmed' : 'appointment_pending',
                $appointmentId
            );

            return [
                'success' => true,
                'appointment_id' => $appointmentId,
                'booking_reference' => $bookingRef,
                'payment_status' => 'unpaid',
                'status' => $initialStatus,
                'final_price' => $pricing['final_price'],
                'deposit_amount' => $pricing['deposit_amount'],
            ];
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Reschedule an appointment to a new slot.
     *
     * @param int $appointmentId
     * @param string $newUtcStart
     * @param string $newUtcEnd
     * @param int|null $newStaffId
     * @return bool
     */
    public function reschedule(int $appointmentId, string $newUtcStart, string $newUtcEnd, ?int $newStaffId = null): bool {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';

        $appt = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$apptTable}` WHERE id = %d LIMIT 1", $appointmentId), ARRAY_A);
        if (!$appt) {
            throw new RuntimeException(__('Appointment not found.', 'appointpro'));
        }

        $staffId = $newStaffId ?: (int) $appt['staff_id'];

        // Check conflict on new time
        $collision = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$apptTable}`
             WHERE staff_id = %d
             AND id != %d
             AND status IN ('confirmed', 'pending')
             AND (start_time < %s AND end_time > %s)",
            $staffId,
            $appointmentId,
            $newUtcEnd,
            $newUtcStart
        ));

        if ($collision > 0) {
            throw new RuntimeException(__('The requested new time slot is not available.', 'appointpro'));
        }

        $wpdb->update($apptTable, [
            'staff_id' => $staffId,
            'start_time' => $newUtcStart,
            'end_time' => $newUtcEnd,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $appointmentId]);

        // Update resource allocations
        $this->resourceRepo->release($appointmentId);
        $service = $this->serviceRepo->find((int) $appt['service_id']);
        if ($service) {
            $res = $this->resourceRepo->getResourceForService((int) $service['id']);
            if ($res) {
                $this->resourceRepo->allocate($appointmentId, (int) $res['id'], $newUtcStart, $newUtcEnd);
            }
        }

        $this->logAudit('appointment_rescheduled', $appointmentId, [
            'old_start' => $appt['start_time'],
            'new_start' => $newUtcStart,
        ]);

        $dispatcher = new NotificationDispatcher();
        $dispatcher->queueEventNotification('appointment_rescheduled', $appointmentId);

        return true;
    }

    /**
     * Cancel an appointment.
     *
     * @param int $appointmentId
     * @param string $reason
     * @return bool
     */
    public function cancel(int $appointmentId, string $reason = ''): bool {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';

        $appt = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$apptTable}` WHERE id = %d LIMIT 1", $appointmentId), ARRAY_A);
        if (!$appt) {
            return false;
        }

        $wpdb->update($apptTable, [
            'status' => 'cancelled',
            'internal_notes' => trim(($appt['internal_notes'] ?? '') . "\nCancelled: " . $reason),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $appointmentId]);

        // Free up resource allocations
        $this->resourceRepo->release($appointmentId);

        $this->logAudit('appointment_cancelled', $appointmentId, ['reason' => $reason]);

        $dispatcher = new NotificationDispatcher();
        $dispatcher->queueEventNotification('appointment_cancelled', $appointmentId);

        return true;
    }

    /**
     * Log action in audit log table.
     *
     * @param string $action
     * @param int $appointmentId
     * @param array<string, mixed> $details
     * @return void
     */
    protected function logAudit(string $action, int $appointmentId, array $details): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_audit_logs';

        $wpdb->insert($table, [
            'user_id' => get_current_user_id() ?: null,
            'action' => $action,
            'entity_type' => 'appointment',
            'entity_id' => $appointmentId,
            'details' => json_encode($details),
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
