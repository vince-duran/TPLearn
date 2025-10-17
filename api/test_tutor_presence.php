<?php
require_once '../includes/auth.php';

// Login as a student
$_SESSION['user_id'] = 9; // teststudent  
$_SESSION['role'] = 'student';
$_SESSION['username'] = 'teststudent';

// Test the tutor presence check
$meeting_id = 1;
$program_id = 3;

echo "Testing tutor presence check for meeting $meeting_id in program $program_id...\n";

$url = "http://localhost/tplearn/api/check-tutor-presence.php?meeting_id=$meeting_id&program_id=$program_id";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id() . "\r\n"
    ]
]);

$response = file_get_contents($url, false, $context);
echo "Response: $response\n";

$data = json_decode($response, true);
if ($data) {
    echo "\nParsed response:\n";
    echo "Can Join: " . ($data['canJoin'] ? 'YES' : 'NO') . "\n";
    echo "Reason: " . ($data['reason'] ?? 'N/A') . "\n";
    echo "Message: " . ($data['message'] ?? 'N/A') . "\n";
    if (isset($data['tutorName'])) {
        echo "Tutor Name: " . $data['tutorName'] . "\n";
    }
}
?>