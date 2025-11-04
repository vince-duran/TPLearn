<?php
require_once 'db.php';

/**
 * Validates program input data
 * @param array $data Program data to validate
 * @return array [isValid: bool, errors: array]
 */
function validateProgramData($data) {
    $errors = [];
    
    // Required fields
    $required = [
        'title' => 'Program title',
        'description' => 'Program description',
        'age_group' => 'Age group',
        'fee' => 'Program fee',
        'max_students' => 'Maximum students',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'start_time' => 'Start time',
        'end_time' => 'End time',
        'days' => 'Program days'
    ];

    foreach ($required as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = "$label is required";
        }
    }

    // Numeric validations
    if (!empty($data['fee']) && (!is_numeric($data['fee']) || $data['fee'] < 0)) {
        $errors[] = "Program fee must be a non-negative number";
    }

    if (!empty($data['max_students'])) {
        $max = intval($data['max_students']);
        if ($max <= 0 || $max > 100) {
            $errors[] = "Maximum students must be between 1 and 100";
        }
    }

    // Date validations
    if (!empty($data['start_date']) && !empty($data['end_date'])) {
        try {
            $start = new DateTime($data['start_date']);
            $end = new DateTime($data['end_date']);

            if ($start > $end) {
                $errors[] = "End date cannot be before start date";
            }

            // Program should be at least 1 week and not more than 52 weeks
            $duration = $start->diff($end);
            $weeks = ceil($duration->days / 7);
            
            if ($weeks < 1) {
                $errors[] = "Program duration must be at least 1 week";
            }
            if ($weeks > 52) {
                $errors[] = "Program duration cannot exceed 52 weeks";
            }

            // Start date shouldn't be in the past
            $today = new DateTime();
            $today->setTime(0, 0);
            if ($start < $today) {
                $errors[] = "Start date cannot be in the past";
            }
        } catch (Exception $e) {
            $errors[] = "Invalid date format";
        }
    }

    // Time validations
    if (!empty($data['start_time']) && !empty($data['end_time'])) {
        try {
            $startTime = new DateTime($data['start_time']);
            $endTime = new DateTime($data['end_time']);

            if ($startTime >= $endTime) {
                $errors[] = "End time must be after start time";
            }

            // Sessions should be between 30 mins and 4 hours
            $duration = $startTime->diff($endTime);
            $minutes = $duration->h * 60 + $duration->i;

            if ($minutes < 30) {
                $errors[] = "Session duration must be at least 30 minutes";
            }
            if ($minutes > 240) {
                $errors[] = "Session duration cannot exceed 4 hours";
            }
        } catch (Exception $e) {
            $errors[] = "Invalid time format";
        }
    }

    // Days validation
    if (!empty($data['days'])) {
        $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $days = array_map('trim', explode(',', $data['days']));
        
        foreach ($days as $day) {
            if (!in_array($day, $validDays)) {
                $errors[] = "Invalid day format. Use Mon, Tue, Wed, Thu, Fri, Sat, Sun";
                break;
            }
        }
    }

    // Category validation
    if (!empty($data['category'])) {
        $validCategories = ['General', 'Academic', 'Arts', 'Music', 'Sports', 'Technology', 'Language'];
        if (!in_array($data['category'], $validCategories)) {
            $errors[] = "Invalid program category";
        }
    }

    // Difficulty level validation
    if (!empty($data['difficulty_level'])) {
        $validLevels = ['beginner', 'intermediate', 'advanced'];
        if (!in_array(strtolower($data['difficulty_level']), $validLevels)) {
            $errors[] = "Invalid difficulty level";
        }
    }

    // Session type validation
    if (!empty($data['session_type'])) {
        $validTypes = ['in-person', 'online', 'hybrid'];
        if (!in_array(strtolower($data['session_type']), $validTypes)) {
            $errors[] = "Invalid session type";
        }
    }

    // Tutor validation if provided
    if (!empty($data['tutor_id'])) {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'tutor'");
        $stmt->bind_param('i', $data['tutor_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_row()[0];

        if ($count === 0) {
            $errors[] = "Invalid tutor ID";
        }
    }

    return [
        'isValid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Format program data for database insertion/update
 * @param array $data Raw program data
 * @return array Formatted program data
 */
function formatProgramData($data) {
    // Start with defaults
    $formatted = [
        'title' => $data['title'] ?? '',
        'description' => $data['description'] ?? '',
        'age_group' => $data['age_group'] ?? 'All Ages',
        'fee' => (float)($data['fee'] ?? 0),
        'category' => $data['category'] ?? 'General',
        'difficulty_level' => strtolower($data['difficulty_level'] ?? 'beginner'),
        'max_students' => (int)($data['max_students'] ?? 15),
        'session_type' => strtolower($data['session_type'] ?? 'in-person'),
        'location' => $data['location'] ?? 'TBD',
        'start_date' => null,
        'end_date' => null,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'days' => 'Mon, Wed, Fri',
        'video_call_link' => null,
        'tutor_id' => null
    ];

    // Format dates
    if (!empty($data['start_date'])) {
        $formatted['start_date'] = date('Y-m-d', strtotime($data['start_date']));
    }
    if (!empty($data['end_date'])) {
        $formatted['end_date'] = date('Y-m-d', strtotime($data['end_date']));
    }

    // Format times
    if (!empty($data['start_time'])) {
        $formatted['start_time'] = date('H:i:s', strtotime($data['start_time']));
    }
    if (!empty($data['end_time'])) {
        $formatted['end_time'] = date('H:i:s', strtotime($data['end_time']));
    }

    // Format days
    if (!empty($data['days'])) {
        if (is_array($data['days'])) {
            $formatted['days'] = implode(', ', $data['days']);
        } else {
            $formatted['days'] = trim($data['days']);
        }
    }

    // Calculate duration in weeks
    if (!empty($formatted['start_date']) && !empty($formatted['end_date'])) {
        $start = new DateTime($formatted['start_date']);
        $end = new DateTime($formatted['end_date']);
        $duration = $start->diff($end);
        $formatted['duration_weeks'] = ceil($duration->days / 7);
    }

    // Optional fields
    if (isset($data['video_call_link'])) {
        $formatted['video_call_link'] = filter_var($data['video_call_link'], FILTER_VALIDATE_URL) 
            ? $data['video_call_link'] 
            : null;
    }

    if (isset($data['tutor_id'])) {
        $formatted['tutor_id'] = (int)$data['tutor_id'] ?: null;
    }

    // Handle cover image upload
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        // New file uploaded
        $formatted['cover_image'] = handleCoverImageUpload($_FILES['cover_image']);
    } elseif (isset($data['remove_cover_image']) && $data['remove_cover_image'] === 'true') {
        // User wants to remove the cover image
        $formatted['cover_image'] = null;
    } elseif (isset($data['existing_cover_image']) && !empty($data['existing_cover_image'])) {
        // Preserve existing cover image during edit
        $formatted['cover_image'] = $data['existing_cover_image'];
    } elseif (isset($data['cover_image'])) {
        // Keep existing cover image path for updates (fallback)
        $formatted['cover_image'] = $data['cover_image'];
    }

    return $formatted;
}

/**
 * Handle cover image upload for programs
 * @param array $file $_FILES array element for cover_image
 * @return string|null Relative file path or null on failure
 */
function handleCoverImageUpload($file) {
    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log("Cover image upload error: " . ($file['error'] ?? 'File not provided'));
        return null;
    }

    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mimeType = mime_content_type($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        error_log("Cover image upload error: Invalid file type - " . $mimeType);
        return null;
    }

    // Check file size (5MB limit)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        error_log("Cover image upload error: File too large - " . $file['size'] . " bytes");
        return null;
    }

    // Create upload directory if it doesn't exist  
    $uploadDir = 'uploads/program_covers/';
    $projectRoot = dirname(__DIR__); // This gets us back to /TPLearn/ from /TPLearn/includes/
    $absoluteUploadDir = $projectRoot . '/' . $uploadDir;
    
    if (!is_dir($absoluteUploadDir)) {
        if (!mkdir($absoluteUploadDir, 0755, true)) {
            error_log("Cover image upload error: Could not create upload directory");
            return null;
        }
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cover_' . uniqid() . '_' . time() . '.' . $extension;
    $relativePath = $uploadDir . $filename;
    $absolutePath = $absoluteUploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $absolutePath)) {
        error_log("Cover image uploaded successfully: " . $relativePath);
        return $relativePath;
    } else {
        error_log("Cover image upload error: Could not move uploaded file");
        return null;
    }
}