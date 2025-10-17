# TPLearn System - Student Side Data Flow Diagram (DFD)

## Overview
This document presents the Data Flow Diagram for the Student side of the TPLearn Learning Management System, showing how data flows between the student user, system processes, and data stores.

---

## Context Diagram (Level 0)
```
External Entity: STUDENT
                    |
                    | Login Credentials, Registration Data,
                    | Assignment Submissions, Payment Info,
                    | Communication Messages
                    |
                    v
        +-----------------------+
        |                       |
        |   TPLEARN SYSTEM      |
        |   (Student Portal)    |
        |                       |
        +-----------------------+
                    |
                    | Dashboard Data, Grades, 
                    | Program Materials, Session Info,
                    | Notifications, Assessment Results
                    |
                    v
External Entity: STUDENT
```

---

## Level 1 DFD - Main Student Processes

```
STUDENT
   |
   | 1. Login/Registration Data
   |
   v
+------------------+     User Data     +----------------+
|                  |  -------------->  |                |
| 1.0 AUTHENTICATE |                   | D1: Users      |
|     STUDENT      |  <--------------  |                |
|                  |   Verification    +----------------+
+------------------+
   |
   | Authentication Token
   |
   v
+------------------+     Profile Data  +----------------+
|                  |  -------------->  |                |
| 2.0 MANAGE       |                   | D2: Student    |
|     PROFILE      |  <--------------  |    Profiles    |
|                  |   Profile Info    +----------------+
+------------------+
   |
   | Updated Profile
   |
   v
+------------------+     Enrollment    +----------------+
|                  |     Requests      |                |
| 3.0 BROWSE &     |  -------------->  | D3: Programs   |
|     ENROLL IN    |                   |                |
|     PROGRAMS     |  <--------------  | D4: Enrollments|
|                  |   Program Data    |                |
+------------------+                   +----------------+
   |
   | Enrollment Confirmation
   |
   v
+------------------+     Payment Data  +----------------+
|                  |  -------------->  |                |
| 4.0 PROCESS      |                   | D5: Payments   |
|     PAYMENTS     |  <--------------  |                |
|                  |   Payment Status  | D6: Payment    |
+------------------+                   |    Attachments |
   |                                   +----------------+
   | Payment Confirmation
   |
   v
+------------------+     Material      +----------------+
|                  |     Access        |                |
| 5.0 ACCESS       |  -------------->  | D7: Program    |
|     LEARNING     |                   |     Materials  |
|     MATERIALS    |  <--------------  |                |
|                  |   Content Data    | D8: File       |
+------------------+                   |     Uploads    |
   |                                   +----------------+
   | Material Progress
   |
   v
+------------------+     Submissions   +----------------+
|                  |  -------------->  |                |
| 6.0 COMPLETE     |                   | D9: Assignment |
|     ASSIGNMENTS  |  <--------------  |     Submissions|
|     & ASSESS.    |   Feedback/Grades |                |
|                  |                   | D10: Assessment|
+------------------+                   |      Submissions|
   |                                   +----------------+
   | Completion Status
   |
   v
+------------------+     Attendance    +----------------+
|                  |     Data          |                |
| 7.0 ATTEND       |  -------------->  | D11: Sessions  |
|     LIVE         |                   |                |
|     SESSIONS     |  <--------------  | D12: Jitsi     |
|                  |   Session Info    |      Meetings  |
+------------------+                   |                |
   |                                   | D13: Attendance|
   | Session Participation             +----------------+
   |
   v
+------------------+     Messages      +----------------+
|                  |  -------------->  |                |
| 8.0 COMMUNICATE  |                   | D14: Communication|
|     WITH TUTORS  |  <--------------  |      History   |
|     & ADMIN      |   Responses       |                |
|                  |                   | D15: Notifications|
+------------------+                   +----------------+
   |
   | Communication Data
   |
   v
+------------------+     Progress Data +----------------+
|                  |  -------------->  |                |
| 9.0 TRACK        |                   | D16: Grades    |
|     PROGRESS     |  <--------------  |                |
|     & GRADES     |   Report Data     | D17: Material  |
|                  |                   |     Assessments|
+------------------+                   +----------------+
```

---

## Level 2 DFD - Detailed Student Processes

### 2.1 Authentication Process (Process 1.0)

```
STUDENT
   |
   | Login Credentials
   |
   v
+------------------+     Username/     +----------------+
|                  |     Email Query   |                |
| 1.1 VALIDATE     |  -------------->  | D1: Users      |
|     CREDENTIALS  |                   |                |
|                  |  <--------------  +----------------+
+------------------+   User Record
   |
   | Validation Result
   |
   v
+------------------+     Email Token   +----------------+
|                  |  -------------->  |                |
| 1.2 VERIFY       |                   | D18: Email     |
|     EMAIL        |  <--------------  |     Verifications|
|                  |   Verification    |                |
+------------------+                   +----------------+
   |
   | Session Token
   |
   v
+------------------+     Login Log     +----------------+
|                  |  -------------->  |                |
| 1.3 CREATE       |                   | D19: Activity  |
|     SESSION      |                   |     Logs       |
|                  |                   |                |
+------------------+                   +----------------+
```

### 2.2 Program Enrollment Process (Process 3.0)

```
STUDENT
   |
   | Browse Request
   |
   v
+------------------+     Program Query +----------------+
|                  |  -------------->  |                |
| 3.1 BROWSE       |                   | D3: Programs   |
|     AVAILABLE    |  <--------------  |                |
|     PROGRAMS     |   Program List    +----------------+
+------------------+
   |
   | Program Selection
   |
   v
+------------------+     Enrollment    +----------------+
|                  |     Check         |                |
| 3.2 CHECK        |  -------------->  | D4: Enrollments|
|     ELIGIBILITY  |                   |                |
|                  |  <--------------  +----------------+
+------------------+   Eligibility
   |
   | Enrollment Request
   |
   v
+------------------+     New           +----------------+
|                  |     Enrollment    |                |
| 3.3 CREATE       |  -------------->  | D4: Enrollments|
|     ENROLLMENT   |                   |                |
|                  |                   +----------------+
+------------------+
   |
   | Enrollment ID
   |
   v
+------------------+     Notification  +----------------+
|                  |  -------------->  |                |
| 3.4 SEND         |                   | D15: Notifications|
|     CONFIRMATION |                   |                |
|                  |                   +----------------+
+------------------+
```

### 2.3 Assignment Completion Process (Process 6.0)

```
STUDENT
   |
   | Assignment Access Request
   |
   v
+------------------+     Assignment    +----------------+
|                  |     Query         |                |
| 6.1 RETRIEVE     |  -------------->  | D7: Program    |
|     ASSIGNMENT   |                   |    Materials   |
|     DETAILS      |  <--------------  |                |
|                  |   Assignment Info | D20: Assignments|
+------------------+                   +----------------+
   |
   | Assignment Content
   |
   v
+------------------+     Submission    +----------------+
|                  |     Data          |                |
| 6.2 SUBMIT       |  -------------->  | D9: Assignment |
|     ASSIGNMENT   |                   |    Submissions |
|                  |                   |                |
+------------------+                   +----------------+
   |
   | Submission ID
   |
   v
+------------------+     File Upload   +----------------+
|                  |  -------------->  |                |
| 6.3 UPLOAD       |                   | D8: File       |
|     ATTACHMENTS  |                   |    Uploads     |
|                  |                   |                |
+------------------+                   +----------------+
   |
   | Upload Confirmation
   |
   v
+------------------+     Progress      +----------------+
|                  |     Update        |                |
| 6.4 UPDATE       |  -------------->  | D17: Material  |
|     PROGRESS     |                   |     Assessments|
|                  |                   |                |
+------------------+                   +----------------+
```

### 2.4 Live Session Attendance Process (Process 7.0)

```
STUDENT
   |
   | Session Join Request
   |
   v
+------------------+     Session Query +----------------+
|                  |  -------------->  |                |
| 7.1 CHECK        |                   | D12: Jitsi     |
|     AVAILABLE    |  <--------------  |     Meetings   |
|     SESSIONS     |   Session List    |                |
+------------------+                   +----------------+
   |
   | Session Selection
   |
   v
+------------------+     Join Record   +----------------+
|                  |  -------------->  |                |
| 7.2 JOIN         |                   | D21: Jitsi     |
|     SESSION      |                   |     Participants|
|                  |                   |                |
+------------------+                   +----------------+
   |
   | Session URL
   |
   v
+------------------+     Participation +----------------+
|                  |     Data          |                |
| 7.3 TRACK        |  -------------->  | D21: Jitsi     |
|     PARTICIPATION|                   |     Participants|
|                  |                   |                |
+------------------+                   +----------------+
   |
   | Leave Event
   |
   v
+------------------+     Attendance    +----------------+
|                  |     Record        |                |
| 7.4 RECORD       |  -------------->  | D13: Attendance|
|     ATTENDANCE   |                   |                |
|                  |                   +----------------+
+------------------+
```

---

## Data Stores Description

| Data Store | Name | Description |
|------------|------|-------------|
| D1 | Users | Core user authentication and profile data |
| D2 | Student Profiles | Extended student information and preferences |
| D3 | Programs | Available educational programs and details |
| D4 | Enrollments | Student enrollment records and status |
| D5 | Payments | Payment transactions and installment tracking |
| D6 | Payment Attachments | Receipt and proof of payment files |
| D7 | Program Materials | Learning content, documents, and resources |
| D8 | File Uploads | Student-uploaded files and documents |
| D9 | Assignment Submissions | Student assignment submissions and status |
| D10 | Assessment Submissions | Assessment responses and submissions |
| D11 | Sessions | Scheduled learning sessions |
| D12 | Jitsi Meetings | Live online session configurations |
| D13 | Attendance | Student attendance records |
| D14 | Communication History | Messages between students and tutors |
| D15 | Notifications | System alerts and messages |
| D16 | Grades | Student grades and performance records |
| D17 | Material Assessments | Progress tracking for learning materials |
| D18 | Email Verifications | Email verification tokens and status |
| D19 | Activity Logs | System usage and security audit trail |
| D20 | Assignments | Assignment definitions and requirements |
| D21 | Jitsi Participants | Live session participation tracking |

---

## Key Student Workflows

### 1. Registration & Onboarding Flow
```
Student Registration → Email Verification → Profile Creation → Program Browsing
```

### 2. Enrollment & Payment Flow
```
Program Selection → Enrollment Request → Payment Processing → Enrollment Confirmation
```

### 3. Learning Flow
```
Material Access → Assignment Completion → Submission → Grading → Progress Update
```

### 4. Live Session Flow
```
Session Notification → Session Join → Participation Tracking → Attendance Recording
```

### 5. Communication Flow
```
Message Composition → Message Sending → Response Notification → Message Reading
```

### 6. Progress Tracking Flow
```
Activity Completion → Grade Recording → Progress Calculation → Report Generation
```

---

## Data Flow Rules

### Input Data Validation
- All student inputs are validated before processing
- File uploads are scanned for security and size limits
- Payment information is encrypted and verified

### Data Storage Rules
- Personal information is encrypted at rest
- Session data is temporarily stored and cleaned regularly
- Audit logs are maintained for all critical operations

### Data Access Controls
- Students can only access their own data
- Program materials are available only to enrolled students
- Grades are visible only after tutor approval

### Data Synchronization
- Real-time updates for live sessions and notifications
- Batch processing for grade calculations and reports
- Regular backup of critical student data

---

## Security Considerations

### Authentication Flow
1. Username/email validation
2. Password verification
3. Email verification check
4. Session token generation
5. Activity logging

### Data Protection
- Encryption of sensitive data (passwords, payment info)
- Secure file upload handling
- XSS and SQL injection prevention
- Rate limiting for API calls

### Session Management
- Automatic session timeout
- Secure session token handling
- Login attempt monitoring
- Multi-device session control

---

## Performance Optimization

### Caching Strategy
- Program materials cached for quick access
- User session data cached
- Frequently accessed data optimized

### Database Optimization
- Indexed queries for student data retrieval
- Optimized joins for enrollment and grade queries
- Efficient pagination for large datasets

### File Handling
- Compressed file uploads
- CDN integration for static content
- Efficient file streaming for video materials

---

*Generated: October 15, 2025*
*TPLearn Learning Management System - Student DFD Documentation*