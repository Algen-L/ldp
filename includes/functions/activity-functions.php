<?php
/**
 * Activity Related Functions
 */

/**
 * Helper to determine current stage and progress percentage
 * Used in submissions_progress.php
 */
if (!function_exists('getProgressInfo')) {
    function getProgressInfo($act)
    {
        $stages = [
            ['label' => 'Submitted', 'completed' => true, 'date' => $act['created_at'], 'icon' => 'bi-send'],
            ['label' => 'Reviewed', 'completed' => (bool) $act['reviewed_by_supervisor'], 'date' => $act['reviewed_at'], 'icon' => 'bi-eye'],
            ['label' => 'Recommended', 'completed' => (bool) $act['recommending_asds'], 'date' => $act['recommended_at'], 'icon' => 'bi-check2-circle'],
            ['label' => 'Approved', 'completed' => (bool) $act['approved_sds'], 'date' => $act['approved_at'], 'icon' => 'bi-trophy']
        ];

        $completedCount = 0;
        foreach ($stages as $stage) {
            if ($stage['completed'])
                $completedCount++;
        }

        $percentage = ($completedCount / count($stages)) * 100;

        return [
            'stages' => $stages,
            'percentage' => $percentage
        ];
    }
}

/**
 * Helper for checkboxes/radio state
 */
if (!function_exists('isChecked')) {
    function isChecked($value, $storedValue)
    {
        if (empty($storedValue))
            return '';
        $arr = explode(', ', $storedValue);
        return in_array($value, $arr) ? 'checked' : '';
    }
}
