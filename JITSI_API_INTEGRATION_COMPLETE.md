# Jitsi Meet API Integration - TPLearn LMS

## Overview
Successfully integrated the official Jitsi Meet API (https://jitsi.org/api) into the TPLearn Learning Management System, replacing the basic meet.jit.si redirect approach with a comprehensive External API implementation.

## Key Features Implemented

### 1. Official Jitsi Meet External API Integration
- **External API Script**: Added `https://meet.jit.si/external_api.js` to load the official Jitsi Meet JavaScript API
- **JitsiMeetExternalAPI**: Using the proper `new JitsiMeetExternalAPI(domain, options)` constructor
- **Embedded Meetings**: Meetings now embed directly in the TPLearn interface via modal overlay

### 2. Enhanced Meeting Experience
- **Modal Interface**: Meetings open in a full-screen modal overlay within TPLearn
- **Dual Join Options**: Users can join meetings in:
  - **Embedded Mode**: Full-screen modal with Jitsi interface
  - **New Tab Mode**: Traditional browser tab opening
- **Keyboard Shortcuts**: Press `Escape` to leave meetings
- **Responsive Design**: Meetings adapt to all screen sizes

### 3. Advanced Configuration Options
```javascript
configOverwrite: {
  startWithAudioMuted: false,
  startWithVideoMuted: false,
  enableWelcomePage: false,
  prejoinPageEnabled: false,
  disableModeratorIndicator: false,
  startScreenSharing: false,
  enableEmailInStats: false
}
```

### 4. Customized Interface
```javascript
interfaceConfigOverwrite: {
  JITSI_WATERMARK_LINK: 'https://tplearn.com',
  PROVIDER_NAME: 'TPLearn',
  SHOW_JITSI_WATERMARK: false,
  SUPPORT_URL: 'https://tplearn.com/support',
  TOOLBAR_BUTTONS: [
    'microphone', 'camera', 'closedcaptions', 'desktop', 'embedmeeting',
    'fullscreen', 'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
    'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
    'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
    'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone',
    'security'
  ]
}
```

### 5. Event Handling & Monitoring
```javascript
// Real-time event listeners
jitsiAPI.addEventListener('videoConferenceJoined', callback);
jitsiAPI.addEventListener('videoConferenceLeft', callback);
jitsiAPI.addEventListener('participantJoined', callback);
jitsiAPI.addEventListener('participantLeft', callback);
jitsiAPI.addEventListener('readyToClose', callback);
```

### 6. User Information Integration
```javascript
userInfo: {
  displayName: currentUserName,  // From PHP session
  email: currentUserEmail        // From PHP session
}
```

### 7. Improved Room Name Generation
```php
// More readable meeting IDs
$meeting_id = 'TPLearn-' . $program_id . '-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
// Example: TPLearn-15-20251014-143052-A7B3
```

## File Changes Made

### 1. `dashboards/tutor/tutor-program-stream.php`
- Added Jitsi Meet External API script inclusion
- Added global JavaScript variables for user information
- Completely rewrote `joinLiveClass()` function for API integration
- Added `openJitsiMeeting()` function for embedded meetings
- Added `closeJitsiMeeting()` function with proper cleanup
- Added keyboard shortcuts (`handleJitsiEscape()`)
- Enhanced meeting cards with dual join options (embedded + new tab)
- Added comprehensive event handling and logging

### 2. `api/jitsi_meetings.php`
- Improved meeting ID generation format
- Enhanced room name readability
- Maintained all existing API functionality

### 3. `test_jitsi.html` (New Test Page)
- Standalone test page for Jitsi API integration
- Real-time event logging and status monitoring
- Configuration testing interface
- Helps debug and verify API functionality

## API Usage Examples

### Starting a Meeting
```javascript
const options = {
  roomName: 'TPLearn-15-20251014-143052-A7B3',
  width: '100%',
  height: '100%',
  parentNode: document.querySelector('#jitsi-container'),
  userInfo: {
    displayName: 'John Doe',
    email: 'john@tplearn.com'
  }
};

const api = new JitsiMeetExternalAPI('meet.jit.si', options);
```

### Event Handling
```javascript
api.addEventListener('videoConferenceJoined', () => {
  console.log('Successfully joined meeting');
});

api.addEventListener('participantJoined', (participant) => {
  console.log('New participant:', participant.displayName);
});
```

### Cleanup
```javascript
api.dispose(); // Properly dispose of the API instance
```

## Security & Production Notes

### Current Configuration
- Using `meet.jit.si` for development and testing
- No authentication tokens (JWT) required for basic functionality

### Production Recommendations
1. **Jitsi as a Service (JaaS)**: Consider upgrading to https://jaas.8x8.vc/ for production
2. **Self-Hosted Jitsi**: Deploy your own Jitsi Meet server for full control
3. **JWT Authentication**: Implement JWT tokens for secure meetings
4. **Custom Domain**: Use your own domain instead of meet.jit.si

## Testing

### Test Page Available
- Access: `http://localhost/TPLearn/test_jitsi.html`
- Features: Room creation, event monitoring, API testing
- Real-time status logging and debugging

### Integration Testing
1. Navigate to any Program Stream as a tutor
2. Click "Live Classes" tab
3. Create a new live class
4. Test both join options:
   - Main button: Embedded modal interface
   - Arrow button: New tab/window

## Troubleshooting

### Common Issues
1. **API Not Loading**: Check internet connection and firewall settings
2. **Modal Not Opening**: Verify JavaScript console for errors
3. **Audio/Video Issues**: Check browser permissions and device access

### Debug Information
- All events are logged to browser console
- Success/error notifications appear in UI
- Check network tab for API call responses

## Next Steps
1. **End-to-End Testing**: Test complete meeting workflow
2. **Mobile Testing**: Verify functionality on mobile devices
3. **Performance Optimization**: Monitor meeting performance and resource usage
4. **Production Setup**: Consider upgrading to JaaS or self-hosted solution
5. **Additional Features**: Screen sharing controls, recording management, breakout rooms

## Benefits of This Implementation
- ✅ **Professional Integration**: Seamless embedding within TPLearn interface
- ✅ **Enhanced User Experience**: No need to leave the platform
- ✅ **Full API Access**: Complete control over meeting functionality
- ✅ **Customizable Interface**: Branded to match TPLearn design
- ✅ **Event Monitoring**: Real-time tracking of meeting activities
- ✅ **Flexible Options**: Both embedded and new tab joining methods
- ✅ **Production Ready**: Easy upgrade path to enterprise solutions

The Jitsi Meet API integration is now complete and provides a robust, professional video conferencing solution for the TPLearn platform.