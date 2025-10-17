<?php
// API Documentation and Endpoint Directory
require_once 'config.php';

header('Content-Type: application/json');
setCORSHeaders();

$endpoints = [
  'api_info' => [
    'version' => APIConfig::API_VERSION,
    'base_url' => APIConfig::API_BASE_URL,
    'documentation' => 'TPLearn API - Academic Management System',
    'last_updated' => '2024-01-01'
  ],

  'authentication' => [
    'description' => 'User authentication and session management',
    'endpoints' => [
      'POST /api/auth.php?action=login' => 'User login',
      'POST /api/auth.php?action=logout' => 'User logout',
      'GET /api/auth.php?action=check_session' => 'Check if user is logged in',
      'POST /api/auth.php?action=change_password' => 'Change user password'
    ]
  ],

  'users' => [
    'description' => 'User management and profile operations',
    'endpoints' => [
      'GET /api/users.php?action=get_users' => 'Get all users (admin only)',
      'GET /api/users.php?action=get_user&id={id}' => 'Get user by ID',
      'POST /api/users.php?action=create_user' => 'Create new user',
      'POST /api/users.php?action=update_user' => 'Update user information',
      'POST /api/users.php?action=delete_user' => 'Delete user (admin only)',
      'GET /api/users.php?action=dashboard_stats' => 'Get dashboard statistics',
      'GET /api/users.php?action=search_users&query={query}' => 'Search users',
      'POST /api/users.php?action=bulk_update' => 'Bulk update users (admin only)'
    ]
  ],

  'programs' => [
    'description' => 'Program management and course operations',
    'endpoints' => [
      'GET /api/programs.php?action=get_programs' => 'Get all programs',
      'GET /api/programs.php?action=get_program&id={id}' => 'Get program by ID',
      'POST /api/programs.php?action=create_program' => 'Create new program (admin only)',
      'POST /api/programs.php?action=update_program' => 'Update program (admin only)',
      'POST /api/programs.php?action=delete_program' => 'Delete program (admin only)',
      'GET /api/programs.php?action=get_program_enrollments&id={id}' => 'Get program enrollments',
      'GET /api/programs.php?action=search_programs&query={query}' => 'Search programs',
      'GET /api/programs.php?action=get_program_analytics&id={id}' => 'Get program analytics (admin only)'
    ]
  ],

  'enrollments' => [
    'description' => 'Student enrollment and assignment management',
    'endpoints' => [
      'GET /api/enrollments.php?action=get_enrollments' => 'Get enrollments',
      'GET /api/enrollments.php?action=get_enrollment&id={id}' => 'Get enrollment by ID',
      'POST /api/enrollments.php?action=create_enrollment' => 'Create new enrollment',
      'POST /api/enrollments.php?action=update_enrollment' => 'Update enrollment',
      'POST /api/enrollments.php?action=assign_tutor' => 'Assign tutor to enrollment',
      'POST /api/enrollments.php?action=update_status' => 'Update enrollment status',
      'GET /api/enrollments.php?action=get_student_enrollments&student_id={id}' => 'Get student enrollments',
      'GET /api/enrollments.php?action=get_tutor_assignments&tutor_id={id}' => 'Get tutor assignments'
    ]
  ],

  'payments' => [
    'description' => 'Payment processing and financial management',
    'endpoints' => [
      'GET /api/payments.php?action=get_payments' => 'Get all payments',
      'GET /api/payments.php?action=get_payment&id={id}' => 'Get payment by ID',
      'POST /api/payments.php?action=record_payment' => 'Record new payment',
      'POST /api/payments.php?action=validate_payment' => 'Validate payment (admin only)',
      'POST /api/payments.php?action=reject_payment' => 'Reject payment (admin only)',
      'GET /api/payments.php?action=get_student_payments&student_id={id}' => 'Get student payments',
      'GET /api/payments.php?action=get_enrollment_balance&enrollment_id={id}' => 'Get enrollment balance',
      'GET /api/payments.php?action=get_financial_summary' => 'Get financial summary (admin only)'
    ]
  ],

  'sessions' => [
    'description' => 'Tutoring session scheduling and management',
    'endpoints' => [
      'GET /api/sessions.php?action=get_sessions' => 'Get sessions',
      'POST /api/sessions.php?action=create_session' => 'Create new session',
      'POST /api/sessions.php?action=update_session_status' => 'Update session status',
      'POST /api/sessions.php?action=reschedule_session' => 'Reschedule session',
      'GET /api/sessions.php?action=get_session_stats' => 'Get session statistics',
      'GET /api/sessions.php?action=get_upcoming_sessions' => 'Get upcoming sessions',
      'GET /api/sessions.php?action=get_session_calendar&month={YYYY-MM}' => 'Get session calendar'
    ]
  ],

  'reports' => [
    'description' => 'Analytics and reporting system',
    'endpoints' => [
      'GET /api/reports.php?action=dashboard_overview' => 'Get dashboard overview (admin only)',
      'GET /api/reports.php?action=enrollment_trends&period={daily|weekly|monthly|yearly}' => 'Get enrollment trends (admin only)',
      'GET /api/reports.php?action=revenue_analysis' => 'Get revenue analysis (admin only)',
      'GET /api/reports.php?action=program_performance' => 'Get program performance (admin only)',
      'GET /api/reports.php?action=student_analytics' => 'Get student analytics (admin only)',
      'GET /api/reports.php?action=tutor_performance' => 'Get tutor performance (admin only)',
      'GET /api/reports.php?action=export_report&type={type}&format={csv|excel}' => 'Export reports (admin only)',
      'GET /api/reports.php?action=get_recent_activities&limit={limit}' => 'Get recent activities'
    ]
  ],

  'common_parameters' => [
    'pagination' => [
      'page' => 'Page number (default: 1)',
      'limit' => 'Items per page (default: 20, max: 100)',
      'sort' => 'Sort field',
      'order' => 'Sort order (asc|desc)'
    ],
    'filtering' => [
      'status' => 'Filter by status',
      'role' => 'Filter by user role',
      'program_id' => 'Filter by program ID',
      'user_id' => 'Filter by user ID',
      'date_from' => 'Filter from date (YYYY-MM-DD)',
      'date_to' => 'Filter to date (YYYY-MM-DD)'
    ]
  ],

  'response_format' => [
    'success_response' => [
      'success' => true,
      'data' => '{}',
      'message' => 'Optional success message',
      'timestamp' => '2024-01-01 12:00:00',
      'api_version' => '1.0'
    ],
    'error_response' => [
      'success' => false,
      'error' => 'Error message',
      'timestamp' => '2024-01-01 12:00:00',
      'api_version' => '1.0'
    ],
    'paginated_response' => [
      'success' => true,
      'data' => [],
      'pagination' => [
        'current_page' => 1,
        'page_size' => 20,
        'total_items' => 100,
        'total_pages' => 5,
        'has_next' => true,
        'has_prev' => false
      ]
    ]
  ],

  'status_codes' => [
    200 => 'OK - Request successful',
    201 => 'Created - Resource created successfully',
    400 => 'Bad Request - Invalid request parameters',
    401 => 'Unauthorized - Authentication required',
    403 => 'Forbidden - Insufficient permissions',
    404 => 'Not Found - Resource not found',
    429 => 'Too Many Requests - Rate limit exceeded',
    500 => 'Internal Server Error - Server error'
  ],

  'authentication_info' => [
    'description' => 'API uses session-based authentication',
    'login_required' => 'Most endpoints require user to be logged in',
    'role_based_access' => 'Some endpoints have role-specific restrictions',
    'session_timeout' => APIConfig::SESSION_TIMEOUT . ' seconds'
  ],

  'data_models' => [
    'user' => [
      'id' => 'integer',
      'username' => 'string',
      'email' => 'string',
      'role' => 'enum(admin,tutor,student)',
      'status' => 'enum(active,inactive,suspended)',
      'created_at' => 'datetime'
    ],
    'program' => [
      'id' => 'integer',
      'name' => 'string',
      'description' => 'text',
      'duration_weeks' => 'integer',
      'fee' => 'decimal',
      'status' => 'enum(active,inactive,draft)'
    ],
    'enrollment' => [
      'id' => 'integer',
      'student_user_id' => 'integer',
      'program_id' => 'integer',
      'tutor_user_id' => 'integer',
      'status' => 'enum(pending,active,completed,cancelled)',
      'enrollment_date' => 'date',
      'start_date' => 'date',
      'end_date' => 'date'
    ],
    'payment' => [
      'id' => 'integer',
      'enrollment_id' => 'integer',
      'amount' => 'decimal',
      'payment_method' => 'string',
      'status' => 'enum(pending,validated,rejected,refunded)',
      'payment_date' => 'datetime'
    ],
    'session' => [
      'id' => 'integer',
      'enrollment_id' => 'integer',
      'session_date' => 'datetime',
      'duration' => 'integer',
      'session_type' => 'enum(regular,makeup,assessment,consultation)',
      'session_mode' => 'enum(virtual,in_person,hybrid)',
      'status' => 'enum(scheduled,in_progress,completed,cancelled,rescheduled)'
    ]
  ]
];

// If specific endpoint documentation is requested
if (isset($_GET['endpoint'])) {
  $endpoint = $_GET['endpoint'];
  if (isset($endpoints[$endpoint])) {
    echo json_encode([
      'success' => true,
      'endpoint' => $endpoint,
      'documentation' => $endpoints[$endpoint]
    ]);
  } else {
    http_response_code(404);
    echo json_encode([
      'success' => false,
      'error' => 'Endpoint documentation not found'
    ]);
  }
} else {
  // Return all documentation
  echo json_encode([
    'success' => true,
    'documentation' => $endpoints
  ]);
}
