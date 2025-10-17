# TPLearn Learning Management System - Complete System Guide

## Table of Contents
1. [System Overview](#system-overview)
2. [Database Architecture](#database-architecture)
3. [Entity Relationship Diagram](#entity-relationship-diagram)
4. [Data Flow Diagrams](#data-flow-diagrams)
5. [System Components](#system-components)
6. [User Roles and Permissions](#user-roles-and-permissions)
7. [Technical Architecture](#technical-architecture)
8. [Implementation Guide](#implementation-guide)
9. [Security Features](#security-features)
10. [System Workflows](#system-workflows)

---

## 1. System Overview

**TPLearn** is a comprehensive Learning Management System (LMS) designed to facilitate online education and training programs. The system supports multiple user roles including administrators, tutors, and students, providing a complete platform for course management, live sessions, assessments, and progress tracking.

### Key Features:
- **Multi-role User Management**: Admin, Tutor, and Student roles with specific permissions
- **Course and Program Management**: Complete curriculum organization
- **Live Session Integration**: Jitsi Meet integration for real-time classes
- **Assessment System**: Comprehensive testing and assignment management
- **Payment Processing**: Integrated billing and payment tracking
- **File Management**: Document and media upload/download capabilities
- **Email Verification**: Security-enhanced user authentication
- **Progress Tracking**: Detailed analytics and reporting

---

## 2. Database Architecture

The TPLearn system uses a MySQL database with **25+ interconnected tables** designed for scalability and data integrity.

### Core Database Tables:

#### User Management
- **`users`**: Core user authentication and profile data
- **`students`**: Student-specific information and records
- **`tutors`**: Tutor profiles and qualifications
- **`email_verifications`**: Email verification tokens and status

#### Academic Structure
- **`programs`**: Course programs and curricula
- **`program_materials`**: Learning resources and content
- **`enrollments`**: Student enrollment records
- **`upcoming_programs`**: Scheduled program offerings

#### Assessment System
- **`assessments`**: Test and quiz definitions
- **`assessment_attempts`**: Student attempt records
- **`assessment_submissions`**: Submitted answers and responses
- **`assignments`**: Assignment definitions and requirements
- **`assignment_submissions`**: Student assignment submissions

#### Financial Management
- **`payments`**: Payment transactions and billing
- **`payment_methods`**: Available payment options

#### Live Sessions
- **`jitsi_meetings`**: Live session management
- **`meeting_participants`**: Session attendance tracking
- **`attendance_records`**: Detailed attendance logs

#### File Management
- **`file_uploads`**: Document and media storage
- **`program_files`**: Course-related file associations

#### System Administration
- **`admin_users`**: Administrative accounts
- **`password_resets`**: Password recovery tokens
- **`user_sessions`**: Active session management

---

## 3. Entity Relationship Diagram

### Complete ERD for dbdiagram.io

The system's ERD is available in `TPLearn_Complete_ERD_dbdiagram_v2.txt` and includes:

```sql
// Core Tables with Relationships
Table users {
  id int [pk, increment]
  username varchar(50) [unique, not null]
  email varchar(100) [unique, not null]
  password_hash varchar(255) [not null]
  role enum('admin', 'tutor', 'student') [not null]
  created_at timestamp [default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [default: `CURRENT_TIMESTAMP`]
  is_active boolean [default: true]
  email_verified boolean [default: false]
}

// [Additional 24 tables with full relationships...]
```

### Key Relationships:
- **Users → Students/Tutors**: One-to-one inheritance relationship
- **Students → Enrollments**: One-to-many enrollment tracking
- **Programs → Materials**: One-to-many content association
- **Assessments → Attempts**: One-to-many submission tracking
- **Meetings → Participants**: Many-to-many attendance tracking

---

## 4. Data Flow Diagrams

### Student-Side Data Flow Diagram

The complete student DFD is documented in `TPLearn_Student_DFD_Corrected.md` and includes:

#### Main Processes:
1. **Access Control & Login** (1.0)
2. **Profile Management** (2.0)
3. **Program Access** (3.0)
4. **Enrollment Management** (4.0)
5. **Payment Processing** (5.0)
6. **Learning Materials** (6.0)
7. **Assessment & Assignment System** (7.0)
8. **Live Session Management** (8.0)

#### Data Flows:
- **Input to Student**: Login credentials, authentication tokens, summaries
- **Output from Student**: Queries, requests, records
- **Data Stores**: 13 data stores (D1-D13) for complete system data

#### Visual Representation:
```
STUDENT ENTITY
     │
     │ Login Credentials
     ▼
┌───────────────┐
│      1.0      │◄─── Authentication Token
│ Access Control│
│   & Login     │
└───────┬───────┘
        │ User Query
        ▼
[Complete system with 8 processes and 13 data stores]
```

---

## 5. System Components

### Frontend Components
- **Student Portal**: Course access, materials, assessments
- **Tutor Dashboard**: Class management, student tracking
- **Admin Panel**: System administration, user management
- **Payment Interface**: Billing and transaction processing

### Backend Services
- **Authentication Service**: User login and session management
- **Course Management**: Program and material organization
- **Assessment Engine**: Testing and grading system
- **File Storage**: Document and media handling
- **Email Service**: Verification and notifications
- **Payment Gateway**: Financial transaction processing

### Integration Services
- **Jitsi Meet API**: Live session management
- **Email SMTP**: Communication services
- **File Upload Handler**: Media and document processing
- **Database Layer**: MySQL data access and ORM

---

## 6. User Roles and Permissions

### Administrator Role
**Capabilities:**
- Complete system access and configuration
- User account management (create, modify, delete)
- Program and curriculum management
- Payment and billing oversight
- System analytics and reporting
- Security and backup management

**Access Level:** Full system administration

### Tutor Role
**Capabilities:**
- Course and material management
- Student progress monitoring
- Assessment creation and grading
- Live session hosting
- Assignment review and feedback
- Class attendance tracking

**Access Level:** Course-specific with student interaction

### Student Role
**Capabilities:**
- Course enrollment and access
- Material viewing and downloading
- Assessment and assignment submission
- Live session participation
- Progress tracking and grades viewing
- Payment and billing management

**Access Level:** Personal data and enrolled courses only

---

## 7. Technical Architecture

### Technology Stack
- **Backend**: PHP (procedural and OOP)
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Live Sessions**: Jitsi Meet API integration
- **File Storage**: Local file system with upload management
- **Email**: SMTP integration for notifications

### System Requirements
- **Web Server**: Apache/Nginx with PHP support
- **Database**: MySQL 5.7 or higher
- **PHP Version**: 7.4 or higher
- **Storage**: Adequate space for file uploads
- **Network**: Stable internet for live sessions

### Security Features
- **Password Hashing**: Secure password storage
- **Email Verification**: Account validation process
- **Session Management**: Secure user sessions
- **File Upload Validation**: Safe file handling
- **SQL Injection Prevention**: Parameterized queries
- **Role-Based Access Control**: Permission management

---

## 8. Implementation Guide

### Database Setup
1. **Create Database**: Set up MySQL database
2. **Import Schema**: Use provided ERD to create tables
3. **Configure Relationships**: Establish foreign key constraints
4. **Initial Data**: Insert default admin users and settings

### Application Configuration
1. **Database Connection**: Configure PHP database credentials
2. **Email Settings**: Set up SMTP for verification emails
3. **File Upload**: Configure upload directories and permissions
4. **Jitsi Integration**: Set up meeting API credentials
5. **Payment Gateway**: Configure payment processing

### Deployment Steps
1. **Environment Setup**: Prepare web server and PHP
2. **File Upload**: Deploy application files
3. **Database Migration**: Import schema and initial data
4. **Configuration**: Set environment-specific settings
5. **Testing**: Verify all system components
6. **Go Live**: Launch production system

---

## 9. Security Features

### Authentication Security
- **Password Hashing**: bcrypt/password_hash implementation
- **Email Verification**: Token-based account validation
- **Session Management**: Secure session handling
- **Login Throttling**: Brute force attack prevention

### Data Protection
- **Input Validation**: Comprehensive data sanitization
- **SQL Injection Prevention**: Prepared statements
- **File Upload Security**: Type and size validation
- **XSS Prevention**: Output encoding and sanitization

### Access Control
- **Role-Based Permissions**: Granular access control
- **Session Validation**: Continuous authentication check
- **Resource Protection**: File and data access restrictions
- **Audit Logging**: System activity tracking

---

## 10. System Workflows

### Student Enrollment Workflow
1. **Account Creation**: User registration with email verification
2. **Profile Setup**: Complete student profile information
3. **Program Selection**: Browse and select desired programs
4. **Payment Processing**: Complete enrollment payment
5. **Access Grant**: Automatic course access activation
6. **Learning Journey**: Access materials, assessments, and sessions

### Assessment Workflow
1. **Assessment Creation**: Tutor creates test/assignment
2. **Student Access**: Enrolled students can view assessments
3. **Submission Process**: Students complete and submit work
4. **Automatic Grading**: System processes objective questions
5. **Manual Review**: Tutor grades subjective responses
6. **Result Publication**: Grades and feedback provided to students

### Live Session Workflow
1. **Session Scheduling**: Tutor schedules class meeting
2. **Notification**: Students receive session notifications
3. **Access Provision**: Meeting links provided to participants
4. **Session Conduct**: Live interaction via Jitsi Meet
5. **Attendance Tracking**: Automatic participation logging
6. **Recording Storage**: Session recordings saved for later access

### Payment Processing Workflow
1. **Service Selection**: Student selects program or service
2. **Payment Gateway**: Secure payment interface
3. **Transaction Processing**: Real-time payment validation
4. **Confirmation**: Payment receipt and confirmation
5. **Service Activation**: Automatic access provisioning
6. **Record Keeping**: Transaction logging and reporting

---

## File References

### Generated Documentation Files:
- **`TPLearn_Complete_ERD_dbdiagram_v2.txt`**: Complete database ERD
- **`TPLearn_Student_DFD.md`**: Comprehensive student DFD documentation
- **`TPLearn_Student_DFD_Corrected.md`**: Visual DFD with data flow labels
- **`TPLearn_Complete_System_Guide.md`**: This comprehensive guide

### Database Analysis Files:
- Various analysis PHP files for database structure verification
- Schema checking and validation scripts
- Data integrity verification tools

---

## Conclusion

The TPLearn Learning Management System represents a comprehensive educational platform designed for scalability, security, and user experience. With its robust database architecture, clear data flow patterns, and comprehensive feature set, it provides a solid foundation for online education delivery.

The system's modular design allows for easy maintenance and future enhancements, while its security features ensure safe handling of user data and educational content. The multi-role architecture supports various educational scenarios from individual tutoring to institutional course delivery.

For implementation support or technical questions, refer to the detailed documentation files and database analysis tools provided in this workspace.

---

*Generated on October 15, 2025*
*TPLearn LMS Documentation v1.0*