<?php

/**
 * Program Status Definitions
 */
const PROGRAM_STATUS = [
    'UPCOMING' => 'upcoming',   // Program hasn't started yet
    'ACTIVE' => 'active',       // Program is currently running
    'COMPLETED' => 'completed', // Program has ended
    'CANCELLED' => 'cancelled', // Program was cancelled
    'FULL' => 'full',          // Program has reached max_students
    'DRAFT' => 'draft'         // Program is not yet published
];

/**
 * Calculate program status based on dates and enrollment
 * @param array $program Program data with start_date, end_date, max_students, current_students
 * @return string Status code from PROGRAM_STATUS
 */
function calculateProgramStatus($program) {
    // Check if program is in draft mode
    if (isset($program['status']) && $program['status'] === PROGRAM_STATUS['DRAFT']) {
        return PROGRAM_STATUS['DRAFT'];
    }

    // Check if program is cancelled
    if (isset($program['status']) && $program['status'] === PROGRAM_STATUS['CANCELLED']) {
        return PROGRAM_STATUS['CANCELLED'];
    }

    // Get dates
    $start = new DateTime($program['start_date']);
    $end = new DateTime($program['end_date']);
    $now = new DateTime();

    // Check enrollment capacity
    $maxStudents = (int)($program['max_students'] ?? 0);
    $currentStudents = (int)($program['current_students'] ?? 0);

    if ($maxStudents > 0 && $currentStudents >= $maxStudents && $now < $end) {
        return PROGRAM_STATUS['FULL'];
    }

    // Check program timeline
    if ($now < $start) {
        return PROGRAM_STATUS['UPCOMING'];
    } elseif ($now > $end) {
        return PROGRAM_STATUS['COMPLETED'];
    } else {
        return PROGRAM_STATUS['ACTIVE'];
    }
}

/**
 * Format program duration in a human-readable format
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @return string Formatted duration (e.g., "8 weeks", "3 months")
 */
function formatProgramDuration($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);

    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
    } else {
        $weeks = ceil($interval->days / 7);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '');
    }
}

/**
 * Format program schedule in human-readable format
 * @param string $days Comma-separated days (e.g., "Mon, Wed, Fri")
 * @param string $startTime Start time in HH:mm:ss format
 * @param string $endTime End time in HH:mm:ss format
 * @return string Formatted schedule (e.g., "Mon/Wed/Fri 9:00 AM - 10:00 AM")
 */
function formatProgramSchedule($days, $startTime, $endTime) {
    // Format days
    $dayList = array_map('trim', explode(',', $days));
    $formattedDays = implode('/', $dayList);

    // Format times
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    $timeFormat = 'g:i A'; // 12-hour format with AM/PM

    return sprintf(
        '%s %s - %s',
        $formattedDays,
        $start->format($timeFormat),
        $end->format($timeFormat)
    );
}

/**
 * Calculate program statistics
 * @param string $programId Program ID
 * @return array Program statistics including enrollment, attendance, etc.
 */
function getProgramStats($programId) {
    global $conn;
    $stats = [
        'total_enrolled' => 0,
        'active_students' => 0,
        'completed_students' => 0,
        'dropout_students' => 0,
        'attendance_rate' => 0,
        'avg_grade' => 0,
        'materials_count' => 0,
        'assessments_count' => 0
    ];

    try {
        // Get enrollment stats
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped
            FROM enrollments 
            WHERE program_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $enrollment = $result->fetch_assoc();

        $stats['total_enrolled'] = (int)$enrollment['total'];
        $stats['active_students'] = (int)$enrollment['active'];
        $stats['completed_students'] = (int)$enrollment['completed'];
        $stats['dropout_students'] = (int)$enrollment['dropped'];

        // Get attendance rate
        $sql = "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_sessions
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            WHERE s.program_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = $result->fetch_assoc();

        if ($attendance['total_sessions'] > 0) {
            $stats['attendance_rate'] = round(
                ($attendance['attended_sessions'] / $attendance['total_sessions']) * 100,
                1
            );
        }

        // Get average grade from assessments
        $sql = "SELECT AVG(grade) as avg_grade
                FROM assessment_submissions
                WHERE program_id = ? AND grade IS NOT NULL";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $grades = $result->fetch_assoc();

        $stats['avg_grade'] = round($grades['avg_grade'] ?? 0, 1);

        // Get materials and assessments count
        $sql = "SELECT 
                COUNT(DISTINCT CASE WHEN type = 'material' THEN id END) as materials,
                COUNT(DISTINCT CASE WHEN type = 'assessment' THEN id END) as assessments
            FROM program_materials
            WHERE program_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts = $result->fetch_assoc();

        $stats['materials_count'] = (int)$counts['materials'];
        $stats['assessments_count'] = (int)$counts['assessments'];

        return $stats;
    } catch (Exception $e) {
        error_log("Error getting program stats: " . $e->getMessage());
        return $stats; // Return default stats on error
    }
}

/**
 * Get program progress data for a specific student
 * @param int $programId Program ID
 * @param int $studentId Student user ID
 * @return array Progress data including materials, assessments completion
 */
function getStudentProgramProgress($programId, $studentId) {
    global $conn;
    $progress = [
        'materials_completed' => 0,
        'materials_total' => 0,
        'assessments_completed' => 0,
        'assessments_total' => 0,
        'attendance_rate' => 0,
        'avg_grade' => 0,
        'completion_rate' => 0
    ];

    try {
        // Get materials progress
        $sql = "SELECT
                COUNT(DISTINCT m.id) as total_materials,
                COUNT(DISTINCT CASE WHEN mc.completed_at IS NOT NULL THEN m.id END) as completed_materials
            FROM program_materials m
            LEFT JOIN material_completions mc ON m.id = mc.material_id AND mc.student_user_id = ?
            WHERE m.program_id = ? AND m.type = 'material'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentId, $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $materials = $result->fetch_assoc();

        $progress['materials_completed'] = (int)$materials['completed_materials'];
        $progress['materials_total'] = (int)$materials['total_materials'];

        // Get assessment progress
        $sql = "SELECT
                COUNT(DISTINCT a.id) as total_assessments,
                COUNT(DISTINCT CASE WHEN s.submitted_at IS NOT NULL THEN a.id END) as completed_assessments,
                AVG(s.grade) as avg_grade
            FROM program_materials a
            LEFT JOIN assessment_submissions s ON a.id = s.assessment_id AND s.student_user_id = ?
            WHERE a.program_id = ? AND a.type = 'assessment'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $studentId, $programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assessments = $result->fetch_assoc();

        $progress['assessments_completed'] = (int)$assessments['completed_assessments'];
        $progress['assessments_total'] = (int)$assessments['total_assessments'];
        $progress['avg_grade'] = round($assessments['avg_grade'] ?? 0, 1);

        // Get attendance rate
        $sql = "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_sessions
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            WHERE s.program_id = ? AND a.student_user_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $programId, $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = $result->fetch_assoc();

        if ($attendance['total_sessions'] > 0) {
            $progress['attendance_rate'] = round(
                ($attendance['attended_sessions'] / $attendance['total_sessions']) * 100,
                1
            );
        }

        // Calculate overall completion rate
        $totalItems = $progress['materials_total'] + $progress['assessments_total'];
        $completedItems = $progress['materials_completed'] + $progress['assessments_completed'];

        if ($totalItems > 0) {
            $progress['completion_rate'] = round(
                ($completedItems / $totalItems) * 100,
                1
            );
        }

        return $progress;
    } catch (Exception $e) {
        error_log("Error getting student program progress: " . $e->getMessage());
        return $progress; // Return default progress on error
    }
}