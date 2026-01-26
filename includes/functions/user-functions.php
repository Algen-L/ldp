<?php
/**
 * User and Status Related Functions
 */

/**
 * Office Categorization Helper
 */
if (!function_exists('getOfficeCategory')) {
    function getOfficeCategory($office, $osdsOffices = [], $cidOffices = [], $sgodOffices = [])
    {
        // Default categories if not provided
        if (empty($osdsOffices)) {
            $osdsOffices = ['ADMINISTRATIVE (PERSONEL)', 'ADMINISTRATIVE (PROPERTY AND SUPPLY)', 'ADMINISTRATIVE (RECORDS)', 'ADMINISTRATIVE (CASH)', 'ADMINISTRATIVE (GENERAL SERVICES)', 'FINANCE (ACCOUNTING)', 'FINANCE (BUDGET)', 'LEGAL', 'ICT'];
        }
        if (empty($sgodOffices)) {
            $sgodOffices = ['SCHOOL MANAGEMENT MONITORING & EVALUATION', 'HUMAN RESOURCES DEVELOPMENT', 'DISASTER RISK REDUCTION AND MANAGEMENT', 'EDUCATION FACILITIES', 'SCHOOL HEALTH AND NUTRITION', 'SCHOOL HEALTH AND NUTRITION (DENTAL)', 'SCHOOL HEALTH AND NUTRITION (MEDICAL)'];
        }
        if (empty($cidOffices)) {
            $cidOffices = ['CURRICULUM IMPLEMENTATION DIVISION (INSTRUCTIONAL MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (LEARNING RESOURCES MANAGEMENT)', 'CURRICULUM IMPLEMENTATION DIVISION (ALTERNATIVE LEARNING SYSTEM)', 'CURRICULUM IMPLEMENTATION DIVISION (DISTRICT INSTRUCTIONAL SUPERVISION)'];
        }

        $office_upper = strtoupper($office);
        if (in_array($office_upper, $osdsOffices))
            return 'OSDS';
        if (in_array($office_upper, $cidOffices))
            return 'CID';
        if (in_array($office_upper, $sgodOffices))
            return 'SGOD';
        return 'OTHER';
    }
}

/**
 * Relative Time Formatter
 */
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false)
    {
        if (!$datetime)
            return 'Never';
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'min',
            's' => 'sec',
        );
        foreach ($string as $k => &$v) {
            if ($k === 'd') {
                if ($weeks) {
                    $v = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ($days ? ', ' . $days . ' day' . ($days > 1 ? 's' : '') : '');
                } elseif ($diff->d) {
                    $v = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                } else {
                    unset($string[$k]);
                }
            } elseif ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full)
            $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'Just now';
    }
}

/**
 * Status Color Helper
 */
if (!function_exists('getStatusColor')) {
    function getStatusColor($last_action)
    {
        if (!$last_action)
            return 'gray';
        $diff = time() - strtotime($last_action);
        if ($diff < 300)
            return 'green'; // 5 mins
        if ($diff < 3600)
            return 'orange'; // 1 hour
        if ($diff < 86400)
            return 'blue'; // 24 hours
        return 'gray';
    }
}
