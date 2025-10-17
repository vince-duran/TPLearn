# TPLearn - Transform Learning 🎓

A comprehensive PHP-based Learning Management System (LMS) designed for educational institutions, featuring student enrollment, tutor management, payment processing, live sessions, and comprehensive administrative controls.

![TPLearn](https://img.shields.io/badge/Version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.4+-purple.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

## Project Structure

```
TPLearn/
├── api/                    # Backend API endpoints
│   ├── auth.php           # Authentication API
│   ├── config.php         # Database configuration
│   ├── enrollments.php    # Enrollment management
│   ├── payments.php       # Payment processing
│   ├── programs.php       # Program management
│   ├── users.php          # User management
│   └── ...
├── dashboards/            # User dashboards
│   ├── admin/             # Administrator interface
│   ├── student/           # Student portal
│   └── tutor/             # Tutor interface
├── includes/              # Shared PHP components
│   ├── auth.php           # Authentication helpers
│   ├── db.php             # Database connection
│   ├── data-helpers.php   # Data processing utilities
│   └── ...
├── assets/                # Static resources
│   ├── *.js               # JavaScript files
│   ├── *.css              # Stylesheets
│   └── *.png              # Images
├── database.sql           # Database schema
├── index.php              # Main landing page
├── login.php              # User authentication
├── register.php           # User registration
└── README.md              # This file
```

## Features

### Payment System

- **Installment Management**: Flexible payment plans with automatic calculation
- **Payment Validation**: Administrative approval workflow
- **Receipt Generation**: Professional receipts with PDF export
- **Status Tracking**: Real-time payment status monitoring

### User Management

- **Role-based Access**: Admin, Student, and Tutor roles
- **Authentication**: Secure login and session management
- **Profile Management**: Complete user profile system

### Academic Programs

- **Program Management**: Create and manage academic programs
- **Enrollment System**: Student enrollment with payment integration
- **Progress Tracking**: Academic progress monitoring

### Administrative Tools

- **Dashboard Analytics**: Comprehensive reporting
- **Payment Management**: Admin payment validation and tracking
- **User Administration**: Complete user management interface

## Technical Requirements

- **PHP**: Version 8.0 or higher
- **MySQL**: Version 5.7 or higher
- **Web Server**: Apache/Nginx with mod_rewrite
- **Extensions**: PDO, mysqli, json

## Installation

1. Clone or download the project files
2. Import `database.sql` into your MySQL database
3. Configure database settings in `api/config.php`
4. Set up web server to point to the project root
5. Access the application via web browser

## Security Features

- Prepared SQL statements for injection prevention
- Session-based authentication
- CSRF protection on forms
- Input validation and sanitization
- Role-based access control

## Development Status

This is a production-ready academic management system with comprehensive payment processing, user management, and administrative features.

## Support

For technical support or feature requests, please contact the development team.
