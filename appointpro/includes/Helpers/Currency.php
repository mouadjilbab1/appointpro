<?php
declare(strict_types=1);

namespace AppointPro\Helpers;

/**
 * Currency configuration and formatting.
 */
class Currency {
    /**
     * Format a numerical price into a locale-aware currency string.
     *
     * @param float $amount
     * @return string
     */
    public static function format(float $amount): string {
        $settings = (array) get_option('appointpro_settings', []);
        $symbol = $settings['currency_symbol'] ?? '$';
        $position = $settings['currency_position'] ?? 'before';
        $decimals = 2;
        $decimalSeparator = '.';
        $thousandSeparator = ',';

        $formatted = number_format($amount, $decimals, $decimalSeparator, $thousandSeparator);

        if ($position === 'after') {
            return $formatted . ' ' . $symbol;
        }

        return $symbol . $formatted;
    }

    /**
     * Get list of supported standard world currencies.
     *
     * @return array<string, array{name: string, symbol: string}>
     */
    public static function getCurrencies(): array {
        return [
            'USD' => ['name' => __('US Dollar', 'appointpro'), 'symbol' => '$'],
            'EUR' => ['name' => __('Euro', 'appointpro'), 'symbol' => '€'],
            'GBP' => ['name' => __('British Pound', 'appointpro'), 'symbol' => '£'],
            'CAD' => ['name' => __('Canadian Dollar', 'appointpro'), 'symbol' => 'CA$'],
            'AUD' => ['name' => __('Australian Dollar', 'appointpro'), 'symbol' => 'AU$'],
            'JPY' => ['name' => __('Japanese Yen', 'appointpro'), 'symbol' => '¥'],
            'AED' => ['name' => __('United Arab Emirates Dirham', 'appointpro'), 'symbol' => 'د.إ'],
            'SAR' => ['name' => __('Saudi Riyal', 'appointpro'), 'symbol' => 'ر.س'],
            'CHF' => ['name' => __('Swiss Franc', 'appointpro'), 'symbol' => 'CHF'],
            'INR' => ['name' => __('Indian Rupee', 'appointpro'), 'symbol' => '₹'],
            'BRL' => ['name' => __('Brazilian Real', 'appointpro'), 'symbol' => 'R$'],
            'MXN' => ['name' => __('Mexican Peso', 'appointpro'), 'symbol' => 'MX$'],
        ];
    }
}
