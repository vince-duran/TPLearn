# Live Session Moderator System - TPLearn

## Overview
The TPLearn platform now implements a role-based moderation system for live sessions using Jitsi Meet, where tutors have full moderator privileges and students join as participants with limited controls.

## Implementation Details

### Tutor (Moderator) Privileges
**File:** `dashboards/tutor/tutor-program-stream.php`

#### Features Enabled for Tutors:
- **Full Toolbar Access**: All Jitsi controls including moderation tools
- **Moderator Controls**:
  - `mute-everyone`: Can mute all participants
  - `mute-video-everyone`: Can turn off video for all participants
  - `security`: Access to security settings
  - `lobby-mode`: Can control lobby/waiting room
  - `participants-pane`: View and manage all participants
- **Advanced Features**:
  - Screen sharing controls
  - Recording capabilities
  - Live streaming options
  - Room security management
- **Enhanced Notifications**: Receives alerts when students join/leave
- **Room Control**: Creates and controls the session room

#### Tutor Configuration:
```javascript
configOverwrite: {
  startWithAudioMuted: false,
  startWithVideoMuted: false,
  disableModeratorIndicator: false,
  // Moderator-specific features enabled
}

userInfo: {
  displayName: `${currentUserName} (Tutor)`,
  role: 'moderator'
}
```

### Student (Participant) Restrictions
**File:** `dashboards/student/program-stream.php`

#### Limited Features for Students:
- **Restricted Toolbar**: Only essential controls available
  - `microphone`: Can control own microphone
  - `camera`: Can control own camera
  - `hangup`: Can leave the session
  - `chat`: Can participate in chat
  - `raisehand`: Can raise hand to get attention
  - `videoquality`: Can adjust their video quality
  - `filmstrip`: Can view other participants
  - `settings`: Basic audio/video settings only
  - `tileview`: Can change view layout
  - `fullscreen`: Can go fullscreen
  - `fodeviceselection`: Can select audio/video devices

#### Student Configuration:
```javascript
configOverwrite: {
  startWithAudioMuted: true, // Students start muted
  startWithVideoMuted: false,
  disableModeratorIndicator: true, // Hide moderator indicators
  // Advanced features disabled for students
}

userInfo: {
  displayName: `${currentUserName} (Student)`,
  role: 'participant'
}
```

### Room Management System

#### Consistent Room Naming:
Both tutors and students use a consistent room naming convention to ensure they join the same session:

**Format:** `tplearn-{program_id}-{meeting_id}-{base_room_name}`

#### Functions:
- `generateModeratorRoomName()`: Creates room name for tutor
- `generateStudentRoomName()`: Creates matching room name for students

### Security Features

1. **Role-Based Access**: Tutors automatically get moderator rights, students are participants
2. **Toolbar Restrictions**: Students cannot access moderation controls
3. **Room Control**: Only tutors can control room settings and manage participants
4. **Automatic Muting**: Students start with audio muted by default
5. **Limited Invitations**: Students cannot invite others to the session

### Event Handling

#### Tutor Events:
- Enhanced participant tracking with student join/leave notifications
- Moderator privilege activation upon joining
- Advanced room control event handling

#### Student Events:
- Basic participation notifications
- Alerts when tutor joins/leaves
- Notifications when muted/unmuted by moderator
- Limited event access for security

### User Experience

#### For Tutors:
- Join as "Tutor (Moderator)" with full control
- Can manage all aspects of the live session
- Receive detailed participant information
- Access to all Jitsi features and controls

#### For Students:
- Join as "Student (Participant)" with appropriate restrictions
- Focus on learning rather than technical controls
- Clear notifications about session status
- Essential controls only for better UX

## Tutor Presence Requirement

### Core Feature: Students Cannot Join Without Tutor
The system now enforces a strict rule that students cannot join live sessions unless a tutor (moderator) is actively present.

#### Implementation Details:

1. **Presence Verification API**: `api/check-tutor-presence.php`
   - Checks if tutor is currently active in the session
   - Verifies tutor joined within the last 2 minutes
   - Returns detailed status information

2. **Session Participation Tracking**: `api/track-session-participation.php`
   - Tracks when users join/leave sessions
   - Maintains heartbeat to confirm active presence
   - Stores participation history in `meeting_participants` table

3. **Student Join Process**:
   ```javascript
   // Before joining, check tutor presence
   const tutorCheck = await fetch('check-tutor-presence.php');
   if (!tutorCheck.canJoin) {
     showWaitingForTutorModal();
     return;
   }
   // Only proceed if tutor is present
   ```

4. **Automatic Monitoring**:
   - Tutors send heartbeat every 60 seconds
   - Students check tutor presence before joining
   - Real-time monitoring of session status

#### Student Experience When Tutor Not Present:

- **Initial Check**: "Verifying tutor presence..." message
- **Wait Modal**: Interactive waiting screen with:
  - Auto-refresh every 30 seconds
  - Manual "Check Again" button
  - Clear explanation of the wait
- **Status Messages**:
  - "Waiting for tutor to start session"
  - "Tutor has left the session"
  - "Session hasn't started yet"
  - "Session has already ended"

#### Tutor Responsibilities:
- Must join session first to enable student access
- Heartbeat automatically maintains their presence
- Clear warning when leaving: "Students can no longer join until you return"

## Benefits

1. **Educational Focus**: Students can focus on learning without distracting controls
2. **Classroom Control**: Tutors have full authority over the session
3. **Security**: Prevents students from disrupting sessions
4. **Professional Experience**: Mimics real classroom dynamics
5. **Scalability**: Works well with multiple students in one session
6. ****Session Integrity**: Ensures supervised learning environment
7. ****Attendance Control**: Prevents unsupervised student sessions

## Database Schema

### Meeting Participants Table
```sql
CREATE TABLE meeting_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP NULL,
    left_at TIMESTAMP NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_meeting_user (meeting_id, user_id),
    INDEX idx_meeting_active (meeting_id, joined_at, left_at),
    INDEX idx_last_seen (last_seen)
);
```

#### Key Fields:
- **joined_at**: When user entered the session (NULL if never joined)
- **left_at**: When user left the session (NULL if still in session)  
- **last_seen**: Updated by heartbeat every 60 seconds
- **ip_address**: User's IP for security tracking
- **user_agent**: Browser information for debugging

#### Usage Patterns:
- **Active Participants**: `left_at IS NULL AND last_seen > (NOW() - INTERVAL 2 MINUTE)`
- **Tutor Present**: `role = 'tutor' AND active participant conditions`
- **Session History**: All join/leave events for analytics

## Technical Implementation

### Key Configuration Differences:

| Feature | Tutor | Student |
|---------|-------|---------|
| Toolbar Buttons | 15+ controls | 10 essential controls |
| Start Audio Muted | No | Yes |
| Moderator Indicator | Visible | Hidden |
| Lobby Control | Yes | No |
| Mute Others | Yes | No |
| Screen Sharing | Full control | Limited |
| Room Security | Full access | No access |
| Participant Management | Full view | Basic view |

### Event Listeners:
- Both roles have role-specific event handling
- Tutors get enhanced participant tracking
- Students get basic session notifications
- Automatic privilege assignment on join

## Future Enhancements

1. **Breakout Rooms**: Tutor-controlled student grouping
2. **Attendance Tracking**: Automatic session attendance recording
3. **Session Recording**: Tutor-controlled session recording
4. **Hand Raise Queue**: Organized student question management
5. **Screen Annotation**: Tutor ability to annotate shared screens
6. **Session Analytics**: Detailed participation metrics

## Testing

### Core Moderator System:

1. **As Tutor**:
   - Navigate to tutor program stream
   - Create and join a live session
   - Verify moderator controls are available
   - Test muting/unmuting capabilities

2. **As Student**:
   - Navigate to student program stream
   - Join the same live session
   - Verify limited toolbar
   - Test that moderation controls are not accessible

### Tutor Presence Enforcement:

#### Test Scenario 1: Student tries to join before tutor
1. **Setup**: Ensure no tutor in session
2. **Action**: Student attempts to join live session
3. **Expected**: 
   - "Verifying tutor presence..." message appears
   - "Waiting for Tutor" modal is shown
   - Student cannot access Jitsi interface
   - Auto-check happens every 30 seconds

#### Test Scenario 2: Student joins after tutor present
1. **Setup**: Tutor joins session first
2. **Action**: Student attempts to join
3. **Expected**:
   - "Tutor Present" success message
   - Student immediately joins session
   - Jitsi interface loads normally

#### Test Scenario 3: Tutor leaves during session
1. **Setup**: Both tutor and student in session
2. **Action**: Tutor leaves session
3. **Expected**:
   - Tutor sees warning: "Students can no longer join until you return"
   - New students cannot join until tutor returns
   - Existing students remain in session

#### Test Scenario 4: Tutor returns to session
1. **Setup**: Tutor left, student waiting to join
2. **Action**: Tutor rejoins session
3. **Expected**:
   - Waiting student gets notification: "Tutor Joined"
   - Student can now join automatically
   - Session continues normally

### API Endpoint Testing:

#### Check Tutor Presence API:
```bash
# Test API directly
curl "http://localhost/TPLearn/api/check-tutor-presence.php?meeting_id=1&program_id=1"
```

**Expected Responses**:
- Tutor present: `{"canJoin": true, "reason": "tutor_present", "tutorName": "John Doe"}`
- Tutor absent: `{"canJoin": false, "reason": "tutor_not_present", "message": "..."}`

#### Session Tracking API:
```bash
# Test participation tracking
curl -X POST "http://localhost/TPLearn/api/track-session-participation.php" \
  -H "Content-Type: application/json" \
  -d '{"meeting_id": 1, "action": "join"}'
```

## Support

For technical support or questions about the live session moderator system, contact the TPLearn development team.