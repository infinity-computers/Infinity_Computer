<?php
// Helper Library for OMS Operations

if (!function_exists('logTimelineEvent')) {
    function logTimelineEvent($conn, $service_id, $event_type, $performed_by, $event_data = null) {
        if (empty($service_id) || empty($event_type)) return false;
        
        $data_json = is_array($event_data) || is_object($event_data) ? json_encode($event_data) : $event_data;
        
        $stmt = $conn->prepare("INSERT INTO service_timeline_events (service_id, event_type, performed_by, event_data) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $service_id, $event_type, $performed_by, $data_json);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}

if (!function_exists('encodeServiceValue')) {
    /**
     * Converts rupees into internal Service Value representation (Rupees / 100)
     * Example: ₹1,500 => 15
     */
    function encodeServiceValue($rupees) {
        return (int)round((float)$rupees / 100.0);
    }
}

if (!function_exists('decodeServiceValue')) {
    /**
     * Converts internal Service Value into actual Rupees
     * Example: 15 => ₹1,500
     */
    function decodeServiceValue($internal_val) {
        return ((float)$internal_val) * 100.0;
    }
}

if (!function_exists('encodeSalesValue')) {
    /**
     * Converts rupees into internal Sales Value representation (Rupees / 1000)
     * Example: ₹1,000 => 1
     */
    function encodeSalesValue($rupees) {
        return (int)round((float)$rupees / 1000.0);
    }
}

if (!function_exists('decodeSalesValue')) {
    /**
     * Converts internal Sales Value into actual Rupees
     * Example: 1 => ₹1,000
     */
    function decodeSalesValue($internal_val) {
        return ((float)$internal_val) * 1000.0;
    }
}

if (!function_exists('calculateDurationFormatted')) {
    /**
     * Calculates time difference between two timestamps in human readable format
     */
    function calculateDurationFormatted($start_time, $end_time = null) {
        if (empty($start_time)) return 'N/A';
        
        $start = new DateTime($start_time);
        $end = !empty($end_time) ? new DateTime($end_time) : new DateTime();
        
        $diff = $start->diff($end);
        
        $parts = [];
        if ($diff->d > 0) $parts[] = $diff->d . 'd';
        if ($diff->h > 0) $parts[] = $diff->h . 'h';
        if ($diff->i > 0) $parts[] = $diff->i . 'm';
        if (empty($parts)) $parts[] = $diff->s . 's';
        
        return implode(' ', $parts);
    }
}
?>
