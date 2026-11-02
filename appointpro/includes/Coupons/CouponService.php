<?php
declare(strict_types=1);

namespace AppointPro\Coupons;

/**
 * Coupon and Discount Rule Service.
 */
class CouponService {
    /**
     * Validate coupon code and compute discount amount.
     *
     * @param string $code
     * @param float $subtotal
     * @param int $serviceId
     * @param int|null $customerId
     * @return array{valid: bool, discount: float, message: string}
     */
    public function validateAndCalculateDiscount(
        string $code,
        float $subtotal,
        int $serviceId,
        ?int $customerId = null
    ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_coupons';
        $now = gmdate('Y-m-d H:i:s');

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE code = %s AND status = 'active' LIMIT 1",
            strtoupper(trim($code))
        ), ARRAY_A);

        if (!$coupon) {
            return ['valid' => false, 'discount' => 0.0, 'message' => __('Invalid or expired coupon code.', 'appointpro')];
        }

        // Expiry check
        if (!empty($coupon['expires_at']) && $coupon['expires_at'] < $now) {
            return ['valid' => false, 'discount' => 0.0, 'message' => __('This coupon has expired.', 'appointpro')];
        }

        // Total usage limit check
        if (!empty($coupon['usage_limit_total']) && (int) $coupon['used_count'] >= (int) $coupon['usage_limit_total']) {
            return ['valid' => false, 'discount' => 0.0, 'message' => __('Coupon usage limit has been reached.', 'appointpro')];
        }

        // Minimum order amount
        $minAmount = (float) ($coupon['min_order_amount'] ?? 0.0);
        if ($subtotal < $minAmount) {
            return [
                'valid' => false,
                'discount' => 0.0,
                'message' => sprintf(__('Minimum order amount for this coupon is %s', 'appointpro'), $minAmount)
            ];
        }

        // Per-customer usage limit
        if ($customerId) {
            $usageTable = $wpdb->prefix . 'appointpro_coupon_usage';
            $userUsage = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$usageTable}` WHERE coupon_id = %d AND customer_id = %d",
                (int) $coupon['id'],
                $customerId
            ));
            $limitPerCustomer = (int) ($coupon['usage_limit_per_customer'] ?? 1);
            if ($userUsage >= $limitPerCustomer) {
                return ['valid' => false, 'discount' => 0.0, 'message' => __('You have reached the maximum redemptions for this coupon.', 'appointpro')];
            }
        }

        // Calculate discount
        $discountValue = (float) $coupon['discount_value'];
        $discountAmount = 0.0;

        if ($coupon['discount_type'] === 'percentage') {
            $discountAmount = round(($subtotal * ($discountValue / 100.0)), 2);
        } else {
            $discountAmount = min($subtotal, $discountValue);
        }

        return [
            'valid' => true,
            'discount' => $discountAmount,
            'message' => __('Coupon applied successfully.', 'appointpro'),
        ];
    }

    /**
     * Record coupon redemption.
     *
     * @param string $code
     * @param int $customerId
     * @param int $appointmentId
     * @param float $discountAmount
     * @return void
     */
    public function recordUsage(string $code, int $customerId, int $appointmentId, float $discountAmount): void {
        global $wpdb;
        $couponTable = $wpdb->prefix . 'appointpro_coupons';
        $usageTable = $wpdb->prefix . 'appointpro_coupon_usage';

        $couponId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$couponTable}` WHERE code = %s LIMIT 1",
            strtoupper(trim($code))
        ));

        if ($couponId > 0) {
            $wpdb->insert($usageTable, [
                'coupon_id' => $couponId,
                'customer_id' => $customerId,
                'appointment_id' => $appointmentId,
                'discount_amount' => $discountAmount,
                'used_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $wpdb->query($wpdb->prepare(
                "UPDATE `{$couponTable}` SET used_count = used_count + 1 WHERE id = %d",
                $couponId
            ));
        }
    }
}
