# TPLearn LMS - Student Data Flow Diagram
## Visual ASCII Representation with Data Flow Labels

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
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                    Main System                                           │
│                                                                                         │
│  ┌─────────────┐                 ┌───────────────┐                                     │
│  │     D1      │                 │      2.0      │◄── Student Record ──┐               │
│  │    Users    │◄── User Data ───┤ Profile Mgmt  │                     │               │
│  └─────────────┘                 └───────┬───────┘                     │               │
│                                          │ Profile Summary             │               │
│  ┌─────────────┐                         ▼                             │               │
│  │     D2      │                    STUDENT ────── Program Query ──────┘               │
│  │  Students   │◄── Student Data ──┐      │                                           │
│  └─────────────┘                   │      │                                           │
│                                    │      ▼                                           │
│  ┌─────────────┐                   │ ┌───────────────┐                                │
│  │     D4      │◄── Program Rec ───┼─┤      3.0      │                                │
│  │  Programs   │                   │ │ Program Access│                                │
│  └─────────────┘                   │ └───────┬───────┘                                │
│                                    │         │ Program Summary                        │
│  ┌─────────────┐                   │         ▼                                        │
│  │     D5      │◄── Material Rec ──┘    STUDENT ────── Enrollment Request ───┐       │
│  │  Materials  │                          │                                  │       │
│  └─────────────┘                          │                                  │       │
│                                           ▼                                  │       │
│  ┌─────────────┐                   ┌───────────────┐                         │       │
│  │     D6      │◄── Enrollment ────┤      4.0      │                         │       │
│  │ Enrollments │    Record          │ Enrollment    │                         │       │
│  └─────────────┘                   │ Management    │                         │       │
│                                    └───────┬───────┘                         │       │
│  ┌─────────────┐                          │ Progress Update                  │       │
│  │     D7      │◄── Payment Rec ──────────┼─────────────────────────────────┘       │
│  │  Payments   │                          ▼                                          │
│  └─────────────┘                     STUDENT ────── Payment Query ───┐              │
│                                           │                           │              │
│  ┌─────────────┐                          │                           │              │
│  │     D8      │◄── Meeting Rec ──────────┼───────────────────────────┘              │
│  │  Meetings   │                          ▼                                          │
│  └─────────────┘                   ┌───────────────┐                                 │
│                                    │      5.0      │                                 │
│  ┌─────────────┐                   │ Payment       │                                 │
│  │     D9      │◄── Attendance ────┤ Processing    │                                 │
│  │ Attendance  │    Record          └───────┬───────┘                                 │
│  └─────────────┘                          │ Receipt                                  │
│                                           ▼                                          │
│  ┌─────────────┐                     STUDENT ────── Material Request ──┐            │
│  │    D10      │◄── Assessment ────────────┼─────────────────────────────┘            │
│  │Assessments  │    Record                 ▼                                          │
│  └─────────────┘                   ┌───────────────┐                                 │
│                                    │      6.0      │                                 │
│  ┌─────────────┐                   │ Learning      │                                 │
│  │    D11      │◄── Attempt ───────┤ Materials     │                                 │
│  │  Attempts   │    Record          └───────┬───────┘                                 │
│  └─────────────┘                          │ Material Package                         │
│                                           ▼                                          │
│  ┌─────────────┐                     STUDENT ────── Assessment Query ──┐            │
│  │    D12      │◄── Assignment ────────────┼─────────────────────────────┘            │
│  │Assignments  │    Record                 ▼                                          │
│  └─────────────┘                   ┌───────────────┐                                 │
│                                    │      7.0      │                                 │
│  ┌─────────────┐                   │ Assessment &  │                                 │
│  │    D13      │◄── Submission ────┤ Assignment    │                                 │
│  │Submissions  │    Record          │ System        │                                 │
│  └─────────────┘                   └───────┬───────┘                                 │
│                                           │ Assessment Package                       │
│                                           ▼                                          │
│                                      STUDENT ────── Meeting Request ──┐             │
│                                           │                            │             │
│                                           ▼                            │             │
│                                    ┌───────────────┐                   │             │
│                                    │      8.0      │◄──────────────────┘             │
│                                    │ Live Session  │                                 │
│                                    │ Management    │                                 │
│                                    └───────┬───────┘                                 │
│                                           │ Session Link                             │
│                                           ▼                                          │
│                                      STUDENT                                         │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

## Data Flow Descriptions

### Input Flows to Student:
- **Login Credentials**: Username/password for authentication
- **Authentication Token**: Session validation from access control
- **Profile Summary**: Student profile and account information
- **Program Summary**: Available programs and details  
- **Progress Update**: Enrollment status and learning progress
- **Receipt**: Payment confirmation and transaction details
- **Material Package**: Learning resources and course materials
- **Assessment Package**: Tests, assignments, and submission results
- **Session Link**: Live session access and meeting information

### Output Flows from Student:
- **User Query**: General system interaction requests
- **Student Record**: Profile and account information updates
- **Program Query**: Program search and selection requests
- **Enrollment Request**: Course enrollment applications
- **Payment Query**: Payment processing and billing inquiries
- **Material Request**: Learning resource access requests
- **Assessment Query**: Test and assignment access requests
- **Meeting Request**: Live session participation requests

### Data Store Interactions:
- **D1 Users**: User authentication and profile data
- **D2 Students**: Student-specific information and records
- **D4 Programs**: Available courses and program details
- **D5 Materials**: Learning resources and course content
- **D6 Enrollments**: Student enrollment records and status
- **D7 Payments**: Payment transactions and billing records
- **D8 Meetings**: Live session schedules and recordings
- **D9 Attendance**: Session participation tracking
- **D10 Assessments**: Tests and evaluation materials
- **D11 Attempts**: Assessment submission records
- **D12 Assignments**: Assignment materials and requirements
- **D13 Submissions**: Student work submissions and grades

### Process Descriptions:
1. **1.0 Access Control & Login**: User authentication and session management
2. **2.0 Profile Management**: Student profile and account administration
3. **3.0 Program Access**: Course catalog browsing and selection
4. **4.0 Enrollment Management**: Course registration and enrollment tracking
5. **5.0 Payment Processing**: Financial transactions and billing
6. **6.0 Learning Materials**: Course content delivery and resource access
7. **7.0 Assessment & Assignment System**: Testing and assignment management
8. **8.0 Live Session Management**: Real-time class and meeting coordination