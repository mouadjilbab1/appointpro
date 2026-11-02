<?php
declare(strict_types=1);

namespace AppointPro\Cart;

use AppointPro\Availability\AvailabilityEngine;
use AppointPro\Booking\BookingEngine;
use AppointPro\Services\ServiceRepository;

/**
 * Booking Cart and Sequential Chained Booking Manager.
 */
class CartManager {
    protected BookingEngine $bookingEngine;
    protected ServiceRepository $serviceRepo;

    public function __construct(
        ?BookingEngine $bookingEngine = null,
        ?ServiceRepository $serviceRepo = null
    ) {
        $this->bookingEngine = $bookingEngine ?: new BookingEngine();
        $this->serviceRepo = $serviceRepo ?: new ServiceRepository();
    }

    /**
     * Compute chained appointment end time and intermediate buffer slots.
     *
     * @param array<int, int> $serviceIds Ordered list of service IDs to chain sequentially
     * @param string $initialStartUtc
     * @return array<int, array{service_id: int, start: string, end: string}>
     */
    public function calculateChainedSequence(array $serviceIds, string $initialStartUtc): array {
        $sequence = [];
        $currentStart = strtotime($initialStartUtc);

        foreach ($serviceIds as $svcId) {
            $service = $this->serviceRepo->find($svcId);
            if (!$service) {
                continue;
            }

            $bufferBefore = (int) ($service['buffer_before'] ?? 0);
            $duration = (int) $service['duration'];
            $bufferAfter = (int) ($service['buffer_after'] ?? 0);

            $apptStartTime = $currentStart + ($bufferBefore * 60);
            $apptEndTime = $apptStartTime + ($duration * 60);

            $sequence[] = [
                'service_id' => $svcId,
                'start' => gmdate('Y-m-d H:i:s', $apptStartTime),
                'end' => gmdate('Y-m-d H:i:s', $apptEndTime),
            ];

            // Next service starts after this service ends + buffer after
            $currentStart = $apptEndTime + ($bufferAfter * 60);
        }

        return $sequence;
    }

    /**
     * Checkout entire cart of multiple appointments atomically.
     *
     * @param array<int, array<string, mixed>> $cartItems
     * @param array<string, mixed> $customerData
     * @param string|null $couponCode
     * @return array{success: bool, appointment_ids: array<int>, total_price: float}
     */
    public function checkoutCart(array $cartItems, array $customerData, ?string $couponCode = null): array {
        $appointmentIds = [];
        $totalPrice = 0.0;

        foreach ($cartItems as $item) {
            $item['customer'] = $customerData;
            $item['coupon_code'] = $couponCode;
            $res = $this->bookingEngine->createAppointment($item);
            $appointmentIds[] = $res['appointment_id'];
            $totalPrice += (float) $res['final_price'];
        }

        return [
            'success' => true,
            'appointment_ids' => $appointmentIds,
            'total_price' => round($totalPrice, 2),
        ];
    }
}
