<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add proper error handling
try {
    require_once dirname(__DIR__) . '/includes/db.php';
    require_once dirname(__DIR__) . '/includes/data-helpers.php';
    
    // Get available programs for display on landing page
    $availablePrograms = getProgramsWithCalculatedStatus();
    
    // Filter for active programs only
    $activePrograms = array_filter($availablePrograms, function($program) {
        return isset($program['calculated_status']) && 
               ($program['calculated_status'] === 'ongoing' || $program['calculated_status'] === 'upcoming');
    });
    
    // Limit to 6 programs for landing page display
    $featuredPrograms = array_slice($activePrograms, 0, 6);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'programs' => $featuredPrograms,
        'total' => count($activePrograms)
    ]);

} catch (Exception $e) {
    error_log("Error in get-featured-programs.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load programs: ' . $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'message' => $e->getMessage()
        ]
    ]);
}
?>