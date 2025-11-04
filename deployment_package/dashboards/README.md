# TPLearn Dashboard File Organization

This document outlines the organized file structure for the TPLearn dashboard system, categorized by user roles.

## 📁 Directory Structure

```
dashboards/
├── admin/          # Administrator files
├── student/        # Student files
├── tutor/          # Tutor files
└── README.md       # This documentation
```

## 👑 Admin Files (`/admin/`)

**Role Access**: `requireRole('admin')`

- `admin.php` - Main admin dashboard
- `admin-tools.php` - Administrative tools and user management
- `students.php` - Student management interface
- `tutors.php` - Tutor management interface
- `reports.php` - System reports and analytics
- `payments.php` - Payment management interface
- `programs.php` - Program management interface

## 👨‍🎓 Student Files (`/student/`)

**Role Access**: `requireRole('student')`

### Core Dashboard

- `student.php` - Main student dashboard

### Academic Management

- `student-academics.php` - Academic records and grades
- `student-enrollment.php` - Course enrollment interface
- `student-payments.php` - Payment history and management
- `student-profile.php` - Profile settings and information

### Program Access

- `program-details.php` - Detailed program information
- `program-stream.php` - Live program streaming interface

### Enrollment Process

- `enrollment-process.php` - Step-by-step enrollment
- `enrollment-confirmation.php` - Enrollment confirmation page
- `payment-method.php` - Payment method selection

## 👨‍🏫 Tutor Files (`/tutor/`)

**Role Access**: `requireRole('tutor')`

### Core Dashboard

- `tutor.php` - Main tutor dashboard

### Program Management

- `tutor-programs.php` - Program management interface
- `tutor-students.php` - Student management and monitoring

### Teaching Tools

- `tutor-program-stream.php` - Live streaming interface
- `tutor-program-stream-backup.php` - Backup streaming system

## 🔗 Navigation Updates Required

After this reorganization, the following navigation links need to be updated:

### Admin Navigation

```php
// Update paths in admin sidebar/navigation
'students' => '/dashboards/admin/students.php'
'tutors' => '/dashboards/admin/tutors.php'
'programs' => '/dashboards/admin/programs.php'
'payments' => '/dashboards/admin/payments.php'
```

### Student Navigation

```php
// Update paths in student sidebar/navigation
'dashboard' => '/dashboards/student/student.php'
'academics' => '/dashboards/student/student-academics.php'
'enrollment' => '/dashboards/student/student-enrollment.php'
'payments' => '/dashboards/student/student-payments.php'
'profile' => '/dashboards/student/student-profile.php'
```

### Tutor Navigation

```php
// Update paths in tutor sidebar/navigation
'dashboard' => '/dashboards/tutor/tutor.php'
'programs' => '/dashboards/tutor/tutor-programs.php'
'students' => '/dashboards/tutor/tutor-students.php'
'stream' => '/dashboards/tutor/tutor-program-stream.php'
```

## 📋 Include Files to Update

The following include files may need path updates:

- `/includes/admin-sidebar.php`
- `/includes/student-sidebar.php`
- `/includes/tutor-sidebar.php`
- Any authentication redirects in `/includes/auth.php`

## 🚀 Benefits of This Organization

1. **Clear Role Separation**: Easy to identify which files belong to which user role
2. **Security**: Easier to implement role-based access control at directory level
3. **Maintenance**: Simpler to maintain and update role-specific features
4. **Scalability**: Easy to add new features to specific user roles
5. **Development**: Multiple developers can work on different roles without conflicts

## ⚠️ Next Steps

1. Update all navigation links in sidebar includes
2. Update authentication redirects
3. Test all links and ensure proper functionality
4. Update any hardcoded paths in JavaScript files
5. Update routing/URL structures if using a router system

---

_Last updated: September 22, 2025_
_Organization completed by: GitHub Copilot_
