<?php
/**
 * Schedule conflict detection functions for enrollment system
 */

require_once __DIR__ . '/db.php';

/**
 * Check if a student has a schedule conflict with a program
 * @param int $student_id Student's user ID
 * @param int $program_id Program ID to check conflict against
 * @return array Conflict information including bool 'has_conflict' and details
 */
function checkScheduleConflict($student_id, $program_id) {
    global $conn;
    
    try {
        // Get the target program's schedule details
        $target_program_sql = "
            SELECT id, name, start_date, end_date, start_time, end_time, days 
            FROM programs 
            WHERE id = ? AND status = 'active'
        ";
        $stmt = $conn->prepare($target_program_sql);
        $stmt->bind_param("i", $program_id);
        $stmt->execute();
        $target_program = $stmt->get_result()->fetch_assoc();
        
        if (!$target_program) {
            return [
                'has_conflict' => false,
                'message' => 'Target program not found',
                'conflicting_programs' => []
            ];
        }
        
        // Get all active enrollments for the student
        $enrolled_programs_sql = "
            SELECT p.id, p.name, p.start_date, p.end_date, p.start_time, p.end_time, p.days,
                   e.status as enrollment_status
            FROM enrollments e
            JOIN programs p ON e.program_id = p.id
            WHERE e.student_user_id = ? 
            AND e.status IN ('pending', 'active')
            AND p.status = 'active'
        ";
        $stmt = $conn->prepare($enrolled_programs_sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $enrolled_programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $conflicting_programs = [];
        
        foreach ($enrolled_programs as $enrolled_program) {
            $conflict_details = checkProgramScheduleOverlap($target_program, $enrolled_program);
            if ($conflict_details['has_overlap']) {
                $conflicting_programs[] = [
                    'program_id' => $enrolled_program['id'],
                    'program_name' => $enrolled_program['name'],
                    'enrollment_status' => $enrolled_program['enrollment_status'],
                    'conflict_type' => $conflict_details['conflict_type'],
                    'conflict_details' => $conflict_details['details']
                ];
            }
        }
        
        return [
            'has_conflict' => !empty($conflicting_programs),
            'message' => empty($conflicting_programs) ? 
                'No schedule conflicts detected' : 
                'Schedule conflict detected with ' . count($conflicting_programs) . ' enrolled program(s)',
            'conflicting_programs' => $conflicting_programs,
            'target_program' => [
                'id' => $target_program['id'],
                'name' => $target_program['name'],
                'schedule' => formatProgramScheduleForDisplay($target_program)
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error checking schedule conflict: " . $e->getMessage());
        return [
            'has_conflict' => false,
            'message' => 'Error checking schedule conflicts',
            'conflicting_programs' => []
        ];
    }
}

/**
 * Check if two programs have overlapping schedules
 * @param array $program1 First program data
 * @param array $program2 Second program data
 * @return array Overlap information
 */
function checkProgramScheduleOverlap($program1, $program2) {
    // Check date range overlap
    $date_overlap = checkDateRangeOverlap(
        $program1['start_date'], $program1['end_date'],
        $program2['start_date'], $program2['end_date']
    );
    
    if (!$date_overlap['has_overlap']) {
        return [
            'has_overlap' => false,
            'conflict_type' => 'none',
            'details' => 'No date range overlap'
        ];
    }
    
    // Check day overlap
    $day_overlap = checkDayOverlap($program1['days'], $program2['days']);
    
    if (!$day_overlap['has_overlap']) {
        return [
            'has_overlap' => false,
            'conflict_type' => 'none',
            'details' => 'Different days of the week'
        ];
    }
    
    // Check time overlap
    $time_overlap = checkTimeOverlap(
        $program1['start_time'], $program1['end_time'],
        $program2['start_time'], $program2['end_time']
    );
    
    if (!$time_overlap['has_overlap']) {
        return [
            'has_overlap' => false,
            'conflict_type' => 'none',
            'details' => 'Different time slots'
        ];
    }
    
    return [
        'has_overlap' => true,
        'conflict_type' => 'full_overlap',
        'details' => sprintf(
            'Overlapping schedule on %s between %s-%s and %s-%s during %s to %s',
            implode(', ', $day_overlap['common_days']),
            $program1['start_time'], $program1['end_time'],
            $program2['start_time'], $program2['end_time'],
            max($program1['start_date'], $program2['start_date']),
            min($program1['end_date'], $program2['end_date'])
        )
    ];
}

/**
 * Check if two date ranges overlap
 * @param string $start1 Start date of first range (Y-m-d)
 * @param string $end1 End date of first range (Y-m-d)
 * @param string $start2 Start date of second range (Y-m-d)
 * @param string $end2 End date of second range (Y-m-d)
 * @return array Overlap information
 */
function checkDateRangeOverlap($start1, $end1, $start2, $end2) {
    // If either program has no date constraints (both start and end are null/empty),
    // treat them as ongoing programs that can conflict with any other program
    $program1_has_dates = !empty($start1) && !empty($end1);
    $program2_has_dates = !empty($start2) && !empty($end2);
    
    // If neither program has date constraints, they can overlap
    if (!$program1_has_dates && !$program2_has_dates) {
        return [
            'has_overlap' => true,
            'overlap_start' => null,
            'overlap_end' => null
        ];
    }
    
    // If only one program has date constraints, they can still overlap
    if (!$program1_has_dates || !$program2_has_dates) {
        return [
            'has_overlap' => true,
            'overlap_start' => $program1_has_dates ? $start1 : $start2,
            'overlap_end' => $program1_has_dates ? $end1 : $end2
        ];
    }
    
    try {
        $start1_date = new DateTime($start1);
        $end1_date = new DateTime($end1);
        $start2_date = new DateTime($start2);
        $end2_date = new DateTime($end2);
        
        // Check if ranges overlap: start1 <= end2 AND start2 <= end1
        $has_overlap = ($start1_date <= $end2_date) && ($start2_date <= $end1_date);
        
        return [
            'has_overlap' => $has_overlap,
            'overlap_start' => $has_overlap ? max($start1, $start2) : null,
            'overlap_end' => $has_overlap ? min($end1, $end2) : null
        ];
    } catch (Exception $e) {
        error_log("Error checking date range overlap: " . $e->getMessage());
        return [
            'has_overlap' => false,
            'overlap_start' => null,
            'overlap_end' => null
        ];
    }
}

/**
 * Check if two day schedules overlap
 * @param string $days1 First program days (e.g., "Mon, Wed, Fri")
 * @param string $days2 Second program days (e.g., "Mon, Wed, Fri")
 * @return array Overlap information
 */
function checkDayOverlap($days1, $days2) {
    $days1_array = parseProgramDays($days1);
    $days2_array = parseProgramDays($days2);
    
    $common_days = array_intersect($days1_array, $days2_array);
    
    return [
        'has_overlap' => !empty($common_days),
        'common_days' => array_values($common_days)
    ];
}

/**
 * Check if two time ranges overlap
 * @param string $start1 Start time of first range (H:i:s)
 * @param string $end1 End time of first range (H:i:s)
 * @param string $start2 Start time of second range (H:i:s)
 * @param string $end2 End time of second range (H:i:s)
 * @return array Overlap information
 */
function checkTimeOverlap($start1, $end1, $start2, $end2) {
    $start1_time = new DateTime($start1);
    $end1_time = new DateTime($end1);
    $start2_time = new DateTime($start2);
    $end2_time = new DateTime($end2);
    
    // Check if time ranges overlap: start1 < end2 AND start2 < end1
    $has_overlap = ($start1_time < $end2_time) && ($start2_time < $end1_time);
    
    return [
        'has_overlap' => $has_overlap,
        'overlap_start' => $has_overlap ? max($start1, $start2) : null,
        'overlap_end' => $has_overlap ? min($end1, $end2) : null
    ];
}

/**
 * Parse program days string into standardized array
 * @param string $days Days string (e.g., "Mon, Wed, Fri" or "Monday, Wednesday, Friday")
 * @return array Standardized day names
 */
function parseProgramDays($days) {
    if (empty($days)) {
        return [];
    }
    
    $day_mapping = [
        // Full names
        'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 
        'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
        // Abbreviated names
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 
        'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
        // Alternative abbreviations
        'tues' => 'Tue', 'thurs' => 'Thu'
    ];
    
    $days_array = array_map('trim', explode(',', strtolower($days)));
    $standardized_days = [];
    
    foreach ($days_array as $day) {
        $clean_day = preg_replace('/[^a-z]/', '', $day);
        if (isset($day_mapping[$clean_day])) {
            $standardized_days[] = $day_mapping[$clean_day];
        }
    }
    
    return array_unique($standardized_days);
}

/**
 * Format program schedule for display
 * @param array $program Program data
 * @return string Formatted schedule
 */
function formatProgramScheduleForDisplay($program) {
    $days = $program['days'] ?? 'TBD';
    $start_time = $program['start_time'] ? date('g:i A', strtotime($program['start_time'])) : 'TBD';
    $end_time = $program['end_time'] ? date('g:i A', strtotime($program['end_time'])) : 'TBD';
    $start_date = $program['start_date'] ? date('M j, Y', strtotime($program['start_date'])) : 'TBD';
    $end_date = $program['end_date'] ? date('M j, Y', strtotime($program['end_date'])) : 'TBD';
    
    return sprintf(
        '%s, %s - %s (%s to %s)',
        $days, $start_time, $end_time, $start_date, $end_date
    );
}

/**
 * Test function to demonstrate schedule conflict detection
 */
function testScheduleConflict() {
    echo "=== Testing Schedule Conflict Detection ===\n\n";
    
    // Test with student ID 7 (assuming they exist)
    $student_id = 7;
    
    // Get student's current enrollments
    global $conn;
    $sql = "
        SELECT p.id, p.name, p.start_date, p.end_date, p.start_time, p.end_time, p.days
        FROM enrollments e
        JOIN programs p ON e.program_id = p.id
        WHERE e.student_user_id = ? AND e.status IN ('pending', 'active')
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Student $student_id current enrollments:\n";
    foreach ($enrollments as $enrollment) {
        echo "- {$enrollment['name']}: " . formatProgramScheduleForDisplay($enrollment) . "\n";
    }
    echo "\n";
    
    // Test conflict check with a conflicting program
    $test_program_id = 10; // Use program ID 10 which we know has conflicts
    $conflict_result = checkScheduleConflict($student_id, $test_program_id);
    
    echo "Testing conflict with program ID $test_program_id:\n";
    echo "Has conflict: " . ($conflict_result['has_conflict'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . $conflict_result['message'] . "\n";
    
    if ($conflict_result['has_conflict']) {
        echo "\nConflicting programs:\n";
        foreach ($conflict_result['conflicting_programs'] as $conflict) {
            echo "- {$conflict['program_name']} (ID: {$conflict['program_id']})\n";
            echo "  Status: {$conflict['enrollment_status']}\n";
            echo "  Details: {$conflict['conflict_details']}\n\n";
        }
    }
}

// Run test if this file is executed directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    testScheduleConflict();
}
?>