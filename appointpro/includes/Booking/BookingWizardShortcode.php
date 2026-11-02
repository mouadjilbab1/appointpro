<?php
declare(strict_types=1);

namespace AppointPro\Booking;

/**
 * Frontend Booking Wizard Shortcode Renderer.
 */
class BookingWizardShortcode {
    /**
     * Render the booking wizard HTML container and enqueue assets.
     *
     * @param array<string, mixed> $atts
     * @return string
     */
    public static function render(array $atts = []): string {
        $attributes = shortcode_atts([
            'service_id' => 0,
            'category_id' => 0,
            'staff_id' => 0,
            'location_id' => 0,
            'hide_steps' => '', // e.g. "location,staff"
        ], $atts, 'appointpro_booking');

        wp_enqueue_style('appointpro-frontend-css');
        wp_enqueue_script('appointpro-frontend-bundle');

        $dataset = sprintf(
            'data-service-id="%d" data-category-id="%d" data-staff-id="%d" data-location-id="%d" data-hide-steps="%s"',
            (int) $attributes['service_id'],
            (int) $attributes['category_id'],
            (int) $attributes['staff_id'],
            (int) $attributes['location_id'],
            esc_attr($attributes['hide_steps'])
        );

        return '<div id="appointpro-booking-wizard-root" class="appointpro-wizard-container" ' . $dataset . '>
            <div class="appointpro-wizard-loading">
                <span class="appointpro-spinner"></span>
                <p>' . esc_html__('Loading booking scheduler...', 'appointpro') . '</p>
            </div>
        </div>';
    }
}
