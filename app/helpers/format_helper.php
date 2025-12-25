<?php
/**
 * Format Helper - Standardized formatting functions for consistent data display
 * Used across all pages to ensure uniform formatting
 */

/**
 * Format period labels consistently (month/quarter/year)
 * 
 * @param int $period The period number (1-12 for months, 1-4 for quarters, YYYY for years)
 * @param string $filter The filter type: 'month', 'quarter', or 'year'
 * @return string Formatted period label in Vietnamese
 * 
 * @example
 * formatPeriodLabel(1, 'month')     // Returns: "Tháng 1"
 * formatPeriodLabel(2, 'quarter')   // Returns: "Quý 2"
 * formatPeriodLabel(2025, 'year')   // Returns: "Năm 2025"
 */
function formatPeriodLabel($period, $filter = 'month') {
    if (!is_numeric($period)) {
        return strval($period);
    }
    
    $period = intval($period);
    
    switch ($filter) {
        case 'month':
            return sprintf('Tháng %d', $period);
        case 'quarter':
            return sprintf('Quý %d', $period);
        case 'year':
            return sprintf('Năm %d', $period);
        default:
            return strval($period);
    }
}

/**
 * Format currency value with VND symbol and proper thousands separator
 * 
 * @param float|int $amount The amount to format
 * @param string $locale The locale code (default: 'vi_VN')
 * @return string Formatted currency string
 * 
 * @example
 * formatCurrency(1000000)  // Returns: "1.000.000 ₫" (or similar based on locale)
 */
function formatCurrency($amount) {
    $amount = floatval($amount);
    
    // Format with thousands separator
    $formatted = number_format($amount, 0, ',', '.');
    
    // Add VND symbol
    return $formatted . ' ₫';
}

/**
 * Calculate percentage change between two values safely
 * Handles edge cases like division by zero
 * 
 * @param float|int $current Current period value
 * @param float|int $previous Previous period value
 * @return array Array with 'value' (float) and 'status' (string) keys
 * 
 * @example
 * calculatePercentageChange(100, 50) 
 * // Returns: ['value' => 100.0, 'status' => 'normal']
 * 
 * calculatePercentageChange(100, 0)
 * // Returns: ['value' => 100.0, 'status' => 'growth_from_zero']
 */
function calculatePercentageChange($current, $previous) {
    $current = floatval($current);
    $previous = floatval($previous);
    
    // Case 1: Both zero (no change)
    if ($previous == 0 && $current == 0) {
        return [
            'value' => 0,
            'status' => 'flat'
        ];
    }
    
    // Case 2: Previous was zero (new growth)
    if ($previous == 0) {
        return [
            'value' => 100.0,
            'status' => 'growth_from_zero'
        ];
    }
    
    // Case 3: Normal calculation
    $growth = (($current - $previous) / abs($previous)) * 100;
    
    // Validate result
    if (!is_finite($growth)) {
        return [
            'value' => 0,
            'status' => 'error'
        ];
    }
    
    return [
        'value' => round($growth, 2),
        'status' => 'normal'
    ];
}

/**
 * Format date in Vietnamese format
 * 
 * @param string $date Date string or timestamp
 * @param string $format Format pattern (default: 'd/m/Y')
 * @return string Formatted date string
 * 
 * @example
 * formatDate('2025-12-05')        // Returns: "05/12/2025"
 * formatDate('2025-12-05', 'D, d M Y')  // Returns: "Fri, 05 Dec 2025"
 */
function formatDate($date, $format = 'd/m/Y') {
    if (is_string($date)) {
        $timestamp = strtotime($date);
    } else {
        $timestamp = $date;
    }
    
    if ($timestamp === false) {
        return '—';
    }
    
    return date($format, $timestamp);
}

/**
 * Format number with proper decimal places
 * 
 * @param float|int $number The number to format
 * @param int $decimals Number of decimal places (default: 2)
 * @return string Formatted number
 * 
 * @example
 * formatNumber(1234.5678, 2)  // Returns: "1.234,57"
 */
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, ',', '.');
}

/**
 * Get CSS class for trend indicator based on growth status
 * 
 * @param array $growthResult Result from calculatePercentageChange()
 * @return string CSS class name for styling
 * 
 * @example
 * getTrendClass(['value' => 50, 'status' => 'normal'])
 * // Returns: "text-green-600" (positive growth)
 */
function getTrendClass($growthResult) {
    if (!is_array($growthResult)) {
        return 'text-gray-500';
    }
    
    $status = $growthResult['status'] ?? 'normal';
    $value = $growthResult['value'] ?? 0;
    
    switch ($status) {
        case 'growth_from_zero':
            return 'text-green-600';
        case 'flat':
            return 'text-gray-500';
        case 'error':
            return 'text-red-600';
        case 'normal':
            return $value >= 0 ? 'text-green-600' : 'text-red-600';
        default:
            return 'text-gray-500';
    }
}

/**
 * Get text representation of trend status for display
 * 
 * @param array $growthResult Result from calculatePercentageChange()
 * @return string Display text
 * 
 * @example
 * getTrendText(['value' => 50, 'status' => 'normal'])
 * // Returns: "+50.00%"
 */
function getTrendText($growthResult) {
    if (!is_array($growthResult)) {
        return '—';
    }
    
    $status = $growthResult['status'] ?? 'normal';
    $value = $growthResult['value'] ?? 0;
    
    switch ($status) {
        case 'insufficient':
            return 'N/A';
        case 'flat':
            return '0%';
        case 'growth_from_zero':
            return 'Mới';
        case 'error':
            return 'Lỗi';
        case 'normal':
            $prefix = $value >= 0 ? '+' : '';
            return sprintf('%s%.1f%%', $prefix, $value);
        default:
            return '—';
    }
}
