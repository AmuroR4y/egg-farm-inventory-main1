<?php
/**
 * Egg Age Eligibility Configuration
 * 
 * This file defines age thresholds for egg inventory eligibility.
 * Adjust these values based on your farm's storage policies.
 */

// Age thresholds in days
$age_eligibility_config = array(
    'eligible_days' => 21,    // Eggs <= 21 days are eligible for reservation
    'review_days' => 28,      // Eggs 22-28 days are due for review
    'excluded_days' => 35     // Eggs > 35 days are excluded from available inventory
);

// Eligibility status labels
$eligibility_labels = array(
    'eligible' => 'Eligible',
    'review' => 'Due for Review',
    'excluded' => 'Excluded'
);

// Eligibility status colors (for UI display)
$eligibility_colors = array(
    'eligible' => '#28a745',  // Green
    'review' => '#ffc107',    // Amber/Yellow
    'excluded' => '#dc3545'   // Red
);

/**
 * Calculate eligibility status based on harvest date
 * 
 * @param string $harvest_date The harvest date (Y-m-d format)
 * @return string 'eligible', 'review', or 'excluded'
 */
function get_eligibility_status($harvest_date) {
    global $age_eligibility_config;
    
    if (empty($harvest_date)) {
        return 'excluded'; // Unknown harvest dates are excluded
    }
    
    $harvest_timestamp = strtotime($harvest_date);
    $current_timestamp = time();
    $days_old = floor(($current_timestamp - $harvest_timestamp) / (60 * 60 * 24));
    
    if ($days_old <= $age_eligibility_config['eligible_days']) {
        return 'eligible';
    } elseif ($days_old <= $age_eligibility_config['review_days']) {
        return 'review';
    } else {
        return 'excluded';
    }
}

/**
 * Calculate age in days from harvest date
 * 
 * @param string $harvest_date The harvest date (Y-m-d format)
 * @return int Age in days
 */
function get_age_in_days($harvest_date) {
    if (empty($harvest_date)) {
        return null;
    }
    
    $harvest_timestamp = strtotime($harvest_date);
    $current_timestamp = time();
    return floor(($current_timestamp - $harvest_timestamp) / (60 * 60 * 24));
}

/**
 * Get the minimum harvest date for eligible eggs
 * 
 * @return string Date string (Y-m-d)
 */
function get_eligible_min_date() {
    global $age_eligibility_config;
    return date('Y-m-d', strtotime("-{$age_eligibility_config['excluded_days']} days"));
}
