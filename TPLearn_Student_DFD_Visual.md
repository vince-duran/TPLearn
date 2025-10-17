# TPLearn System - Student Side Data Flow Diagram (DFD)
## Level 1 DFD - Student Processes and Data Flows

---

## Student Data Flow Diagram - Level 1

```
                                           ┌─────────────────────────────────────────────────────────────────────────────────────────────────────────┐
                                           │                                                                                                             │
                          Login Credential │                                        STUDENT                                                             │
                     ┌────────────────────►│                                                                                                             │
                     │                     │                                                                                                             │
                     │  Authentication     │                                                                                                             │
                     │  Result             │                                                                                                             │
                     │                     │                                                                                                             │
                     │                     │                                                                                                             │
                ┌────┴────┐               │                                                                                                             │
                │         │               │                                                                                                             │
                │   1.0   │◄─Session Token─┤                                                                                                             │
                │         │    ┌─────────►│                                          Profile Catalog                                                   │
                │ Access  │    │          │      ┌────────────────────────────────────────────────────────────────────────────────────────────────►│
                │ Control │    │User Data │      │                                                                                                      │
                │         │    │          │      │                     Profile Summary                                                                 │
                │         │    │          │      │               ┌──────────────────────────────────────────────────────────────────────────────────┤
                └─────────┘    │          │      │               │                                                                                      │
                     │         │          │      │               │                                                                                      │
        ┌────────────┴─────────┴─────────►│      │               │                                                                                      │
        │         User Query              │      │               │                                                                                      │
        │ D1                              │      │               │                                                                                      │
        │ User ──────────────────────────►│      │               │                                                                                      │
        │                                 │      │               │                                                                                      │
        │                                 │      │               │                                                                                      │
        │                                 │      │               │                                                                                      │
        │                                 │      │               │                         Program Catalog                                             │
        │                                 │      │               │      ┌────────────────────────────────────────────────────────────────────────►│
        │                                 │      │               │      │                                                                              │
        │                                 │      │               │      │                     Program Summary                                          │
        │                                 │      │               │      │               ┌──────────────────────────────────────────────────────────┤
        │                                 │      │               │      │               │                                                              │
        │                                 │      │               │      │               │                                                              │
        │                                 │      │               │      │               │                                                              │
        │                                 │      │               │      │               │                                                              │
        │                                 │      │               │      │               │                                                              │
        │         ┌───────────────────────┤      │               │      │               │                        Enrollment Catalog                  │
        │         │        Student Record │      │               │      │               │      ┌─────────────────────────────────────────────────────►│
        │         │                       │      │               │      │               │      │                                                       │
        │         │                       │      │               │      │               │      │                     Enrollment Summary              │
        │         │                       │      │               │      │               │      │               ┌───────────────────────────────────────┤
        │         │  ┌────────────────────┤      │               │      │               │      │               │                                       │
        │         │  │                    │      │               │      │               │      │               │                                       │
        │         │  │ D2                 │      │               │      │               │      │               │                                       │
        │         │  │ Student ───────────┤      │               │      │               │      │               │                                       │
        │         │  │                    │      │               │      │               │      │               │                                       │
        │         │  │                    │      │               │      │               │      │               │                Payment Catalog       │
        │         │  │                    │      │               │      │               │      │               │      ┌─────────────────────────────────►│
        │         │  │                    │      │               │      │               │      │               │      │                               │
        │         │  │                    │      │               │      │               │      │               │      │                Payment Summary │
        │         │  │                    │      │               │      │               │      │               │      │             ┌─────────────────────┤
        │         │  │                    │      │               │      │               │      │               │      │             │                     │
        │         │  │                    │      │      ┌────────┴──────┐               │      │               │      │             │                     │
        │         │  │                    │      │      │               │               │      │               │      │             │                     │
        │         │  │                    │      │      │     2.0       │               │      │               │      │             │                     │
        │         │  │                    │      │      │               │               │      │               │      │             │                     │
        │         │  │                    │      │      │ Account and   │               │      │               │      │             │                     │
        │         │  │                    │      │      │   Profile     │               │      │               │      │             │                     │
        │         │  │                    │      │      │               │               │      │               │      │             │         Material   │
        │         │  │                    │      │      │               │               │      │               │      │             │         Request   │
        │         │  │                    │      │      └───────────────┘               │      │               │      │             │      ┌──────────────►│
        │         │  │                    │      │                                      │      │               │      │             │      │              │
        │         │  │                    │      │                                      │      │               │      │             │      │      Material│
        │         │  │                    │      │                                      │      │               │      │             │      │      Package │
        │         │  │                    │      │           ┌──────────────────────────┴──────┐               │      │             │      │             ┌┤
        │         │  │                    │      │           │                                 │               │      │             │      │             ││
        │         │  │                    │      │           │           3.0                   │               │      │             │      │             ││
        │         │  │                    │      │           │                                 │               │      │             │      │             ││
        │         │  │                    │      │           │     Program Access              │ ◄─────────────┴──────┘             │      │             ││
        │         │  │                    │      │           │                                 │ Program Record                     │      │             ││
        │         │  │                    │      │           │                                 │                                   │      │             ││
        │         │  │                    │      │           └─────────────────────────────────┘                                   │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │                 D4 ──────────────────────────────────────────────────────────┤      │             ││
        │         │  │                    │      │                 Program                                                         │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │                 D5 ──────────────────────────────────────────────────────────┤      │             ││
        │         │  │                    │      │                 Material                                                       │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │                                                                                 │      │             ││
        │         │  │                    │      │           ┌─────────────────────────────────┐                                   │      │             ││
        │         │  │                    │      │           │                                 │                                   │      │             ││
        │         │  │                    │      │           │           4.0                   │ ◄─────────────────────────────────┘      │             ││
        │         │  │                    │      │           │                                 │ Enrollment Record                        │             ││
        │         │  │                    │      │           │     Enrollment                  │                                         │             ││
        │         │  │                    │      │           │     Management                  │                                         │             ││
        │         │  │                    │      │           │                                 │ Progress Update                         │             ││
        │         │  │                    │      │           │                                 │ ───────────────────────────────────────►│             ││
        │         │  │                    │      │           └─────────────────────────────────┘                                         │             ││
        │         │  │                    │      │                                                                                       │             ││
        │         │  │                    │      │                 D6 ──────────────────────────────────────────────────────────────────┤             ││
        │         │  │                    │      │                 Enrollment                                                            │             ││
        │         │  │                    │      │                                                                                       │             ││
        │         │  │                    │      │                                                                                       │             ││
        │         │  │                    │      │                                                                                       │             ││
        │         │  │                    │      │           ┌─────────────────────────────────┐                                         │             ││
        │         │  │                    │      │           │                                 │                                         │             ││
        │         │  │                    │      │           │           5.0                   │ ◄───────────────────────────────────────┘             ││
        │         │  │                    │      │           │                                 │ Payment Record                                        ││
        │         │  │                    │      │           │       Payment                   │                                                       ││
        │         │  │                    │      │           │                                 │                                                       ││
        │         │  │                    │      │           │                                 │ Receipt                                               ││
        │         │  │                    │      │           │                                 │ ──────────────────────────────────────────────────────►││
        │         │  │                    │      │           └─────────────────────────────────┘                                                       ││
        │         │  │                    │      │                                                                                                     ││
        │         │  │                    │      │                 D7 ────────────────────────────────────────────────────────────────────────────────┤│
        │         │  │                    │      │                 Payment                                                                             ││
        │         │  │                    │      │                                                                                                     ││
        │         │  │                    │      │                                                                                                     ││
        │         │  │                    │      │                                                                                                     ││
        │         │  │                    │      │           ┌─────────────────────────────────┐                                                       ││
        │         │  │                    │      │           │                                 │                                                       ││
        │         │  │                    │      │           │           6.0                   │ ◄─────────────────────────────────────────────────────┘│
        │         │  │                    │      │           │                                 │ Material Record                                        │
        │         │  │                    │      │           │    Learning Materials           │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           └─────────────────────────────────┘                                                        │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │                 D8 ──────────────────────────────────────────────────────────────────────────────────┤
        │         │  │                    │      │                 Meeting                                                                              │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │                 D9 ──────────────────────────────────────────────────────────────────────────────────┤
        │         │  │                    │      │                 Attendance                                                                           │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │                                                                                                      │
        │         │  │                    │      │           ┌─────────────────────────────────┐                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │           7.0                   │ ◄─Assessment Record─────────────────────────────────────┤
        │         │  │                    │      │           │                                 │ ◄─Attempt Record─────────────────────────────────────────┤
        │         │  │                    │      │           │   Assessment and                │ ◄─Assignment Record─────────────────────────────────────┤
        │         │  │                    │      │           │     Assignment                  │ ◄─Submission Record─────────────────────────────────────┤
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           └─────────────────────────────────┘                                                        │
        │         │  │                    │      │                       Assessment and Assignment Catalog                                              │
        │         │  │                    │      │                  ┌──────────────────────────────────────────────────────────────────────────────────┤
        │         │  │                    │      │                  │                                                                                  │
        │         │  │                    │      │                  │       Assessment and Assignment Package                                          │
        │         │  │                    │      │                  │                 ┌────────────────────────────────────────────────────────────────┤
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                    D10                                         │
        │         │  │                    │      │                  │                 │                    Assessment                                  │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                    D11                                         │
        │         │  │                    │      │                  │                 │                    Attempt                                     │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                    D12                                         │
        │         │  │                    │      │                  │                 │                    Assignment                                  │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                    D13                                         │
        │         │  │                    │      │                  │                 │                    Submission                                  │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │           ┌─────────────────────────────────┐                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │           8.0                   │ ◄─Attendance Record─────────────────────────────────────┤
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │      Live Session               │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           │                                 │                                                        │
        │         │  │                    │      │           └─────────────────────────────────┘                                                        │
        │         │  │                    │      │                       Meeting Catalog                                                                │
        │         │  │                    │      │                  ┌──────────────────────────────────────────────────────────────────────────────────┤
        │         │  │                    │      │                  │                                                                                  │
        │         │  │                    │      │                  │       Session Link                                                               │
        │         │  │                    │      │                  │                 ┌────────────────────────────────────────────────────────────────┤
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                                                                │
        │         │  │                    │      │                  │                 │                    D9                                          │
        │         │  │                    │      │                  │                 │                    Attendance                                  │
        │         │  │                    │      │                  │                 │                                                                │
        └─────────┴──┴────────────────────┴──────┴──────────────────┴─────────────────┴────────────────────────────────────────────────────────────────┘
```

---

## Data Store Descriptions

| **Data Store** | **Name** | **Description** |
|----------------|----------|-----------------|
| **D1** | User | Core user authentication and account information |
| **D2** | Student | Extended student profile data and personal information |
| **D3** | Program | Available educational programs and course details |
| **D4** | Material | Learning materials, documents, and educational content |
| **D5** | Enrollment | Student enrollment records and program registration |
| **D6** | Payment | Payment transactions, fees, and financial records |
| **D7** | Meeting | Live session schedules and Jitsi meeting information |
| **D8** | Attendance | Student attendance records for sessions |
| **D9** | Assessment | Assessment definitions and evaluation criteria |
| **D10** | Attempt | Student assessment attempts and responses |
| **D11** | Assignment | Assignment definitions and requirements |
| **D12** | Submission | Student assignment submissions and files |

---

## Process Descriptions

### **1.0 Access Control**
- **Purpose**: Authenticate student login credentials and manage user sessions
- **Inputs**: Login credentials from student
- **Outputs**: Authentication result, session token
- **Data Stores**: D1 (User)

### **2.0 Account and Profile**
- **Purpose**: Manage student profile information and account settings
- **Inputs**: Profile catalog requests from student
- **Outputs**: Profile summary and student record updates
- **Data Stores**: D2 (Student)

### **3.0 Program Access**
- **Purpose**: Provide access to available programs and course information
- **Inputs**: Program catalog requests from student
- **Outputs**: Program summary and program records
- **Data Stores**: D4 (Program), D5 (Material)

### **4.0 Enrollment Management**
- **Purpose**: Handle student enrollment in programs and track progress
- **Inputs**: Enrollment catalog requests from student
- **Outputs**: Enrollment summary, progress updates, enrollment records
- **Data Stores**: D6 (Enrollment)

### **5.0 Payment**
- **Purpose**: Process student payments and manage financial transactions
- **Inputs**: Payment catalog requests from student
- **Outputs**: Payment summary, receipts, payment records
- **Data Stores**: D7 (Payment)

### **6.0 Learning Materials**
- **Purpose**: Provide access to course materials and learning resources
- **Inputs**: Material requests from student
- **Outputs**: Material packages and material records
- **Data Stores**: D8 (Meeting), D9 (Attendance)

### **7.0 Assessment and Assignment**
- **Purpose**: Manage assessments, assignments, and student submissions
- **Inputs**: Assessment and assignment catalog requests from student
- **Outputs**: Assessment and assignment packages, various records
- **Data Stores**: D10 (Assessment), D11 (Attempt), D12 (Assignment), D13 (Submission)

### **8.0 Live Session**
- **Purpose**: Manage live online sessions and track student attendance
- **Inputs**: Meeting catalog requests from student
- **Outputs**: Session links, attendance records
- **Data Stores**: D9 (Attendance)

---

## Data Flow Legend

| **Symbol** | **Meaning** |
|------------|-------------|
| **STUDENT** | External entity (student user) |
| **Numbered Circles** | System processes (1.0, 2.0, etc.) |
| **D1, D2, etc.** | Data stores (database tables) |
| **Arrows** | Data flows between entities, processes, and stores |
| **Labels** | Description of data being transferred |

---

## Key Student Workflows

### **Authentication Flow**
1. Student provides login credentials
2. Access Control (1.0) validates against User data store (D1)
3. System returns authentication result and session token
4. Student gains access to system functions

### **Program Enrollment Flow**
1. Student requests program catalog
2. Program Access (3.0) retrieves data from Program (D4) and Material (D5) stores
3. Student selects program for enrollment
4. Enrollment Management (4.0) creates enrollment record in Enrollment store (D6)
5. Payment (5.0) processes fees and updates Payment store (D7)

### **Learning Flow**
1. Student requests learning materials
2. Learning Materials (6.0) provides content from Meeting (D8) and Attendance (D9) stores
3. Student accesses assignments through Assessment and Assignment (7.0)
4. System tracks submissions in Assignment (D12) and Submission (D13) stores

### **Live Session Flow**
1. Student requests meeting information
2. Live Session (8.0) provides session links from available meetings
3. Student joins live session
4. System records attendance in Attendance store (D9)

---

*TPLearn Learning Management System - Student Side Data Flow Diagram*  
*Generated: October 15, 2025*