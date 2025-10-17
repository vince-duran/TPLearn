# TPLearn WebRTC Video Conferencing System

## Overview

TPLearn now includes a complete **WebRTC-based video conferencing system** that enables real-time video and audio communication between tutors and students. This implementation provides:

- **Peer-to-peer video/audio communication**
- **Screen sharing capabilities**
- **Session management and signaling**
- **Cross-browser compatibility**
- **No plugins required**

## Architecture

### 1. Client-Side WebRTC (`assets/webrtc-video-conference.js`)
- **VideoConference class**: Main WebRTC management class
- **Media handling**: Camera, microphone, and screen sharing
- **Peer connections**: RTCPeerConnection management
- **Signaling**: HTTP-based signaling protocol

### 2. Signaling Server (`signaling/api.php`)
- **HTTP-based signaling**: RESTful API for peer coordination
- **Session management**: Room-based session handling
- **Message relay**: Offer/answer/ICE candidate exchange
- **Participant tracking**: User join/leave management

### 3. User Interfaces
- **Tutor Interface**: `dashboards/tutor/tutor-programs.php` - Join Online Session modal
- **Student Interface**: `video-conference/student-join.php` - Dedicated joining page
- **Test Interface**: `test/webrtc-test.html` - Development testing page

## Features

### ✅ **Core WebRTC Features**
- **Video Streaming**: HD video communication (720p ideal)
- **Audio Streaming**: High-quality audio with echo cancellation
- **Screen Sharing**: Desktop/application sharing capability
- **Camera/Mic Controls**: Toggle video and audio on/off

### ✅ **Session Management**
- **Room-based Sessions**: Each class session has unique ID
- **Multiple Participants**: Support for tutor + multiple students
- **Join/Leave Handling**: Graceful connection management
- **Connection Status**: Real-time connection monitoring

### ✅ **Cross-Platform Support**
- **Browser Compatibility**: Chrome, Firefox, Safari, Edge
- **Mobile Support**: Works on mobile browsers
- **No Downloads**: Pure web-based solution

## Installation & Setup

### 1. **Files Already Created**
```
TPLearn/
├── assets/
│   └── webrtc-video-conference.js    # Main WebRTC client
├── signaling/
│   ├── api.php                       # HTTP signaling server
│   └── server.php                    # WebSocket server (alternative)
├── video-conference/
│   └── student-join.php              # Student interface
└── test/
    └── webrtc-test.html              # Testing interface
```

### 2. **Browser Permissions Required**
Users need to grant browser permissions for:
- **Camera access**
- **Microphone access**
- **Screen sharing** (when used)

### 3. **HTTPS Requirement**
WebRTC requires HTTPS in production. For local testing:
- Use `localhost` (works with HTTP)
- Or set up HTTPS with SSL certificates

## Usage Guide

### For Tutors

1. **Start a Session**:
   ```javascript
   // In tutor-programs.php, click "Join Online Session"
   // Then click "Start Session" button
   ```

2. **Session Controls**:
   - **Camera Toggle**: Turn video on/off
   - **Microphone Toggle**: Mute/unmute audio
   - **Screen Share**: Share desktop/application
   - **End Session**: Stop the session

### For Students

1. **Join a Session**:
   ```
   Navigate to: /video-conference/student-join.php?session=SESSION_ID
   ```

2. **Auto-Connection**:
   - Page automatically requests camera/mic permissions
   - Joins the session immediately
   - Shows tutor's video in main area
   - Shows own video in corner

### For Developers

1. **Test WebRTC Support**:
   ```
   Open: /test/webrtc-test.html
   Click "Test Connection" to verify browser capabilities
   ```

2. **Integration Example**:
   ```javascript
   // Initialize video conference
   const videoConf = new VideoConference();
   
   // Join session
   const result = await videoConf.initializeSession(
       'session_123',           // Session ID
       'user_456',             // User ID
       'tutor'                 // Role: 'tutor' or 'student'
   );
   
   if (result.success) {
       console.log('Joined successfully!');
   }
   ```

## Technical Details

### WebRTC Configuration
```javascript
rtcConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
    ]
}
```

### Signaling Protocol
- **HTTP-based**: Uses fetch() for signaling messages
- **Polling**: 1-second polling for real-time updates
- **Session Storage**: PHP sessions for temporary storage

### Media Constraints
```javascript
constraints = {
    video: {
        width: { ideal: 1280 },
        height: { ideal: 720 },
        facingMode: 'user'
    },
    audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true
    }
}
```

## Troubleshooting

### Common Issues

1. **"Camera/Mic Not Working"**
   - Check browser permissions
   - Ensure HTTPS (or localhost for testing)
   - Verify camera/mic not used by other apps

2. **"Connection Failed"**
   - Check internet connectivity
   - Verify signaling server accessible
   - Try refreshing the page

3. **"No Video/Audio"**
   - Check device permissions
   - Verify WebRTC browser support
   - Test with webrtc-test.html page

### Browser Support
- ✅ **Chrome 56+**
- ✅ **Firefox 44+** 
- ✅ **Safari 11+**
- ✅ **Edge 79+**
- ❌ **Internet Explorer** (not supported)

### Network Requirements
- **STUN servers**: For NAT traversal (using free Google STUN)
- **TURN servers**: May be needed for restrictive firewalls (not included)
- **Bandwidth**: ~1-2 Mbps per participant for HD video

## Security Features

- **Encrypted by Default**: WebRTC uses DTLS/SRTP encryption
- **Session-based**: Access requires valid session ID
- **Role-based**: Tutor vs student permissions
- **Browser Security**: Relies on browser's security model

## Future Enhancements

### Planned Features
- [ ] **Recording**: Session recording capability
- [ ] **Chat**: Text chat during sessions
- [ ] **Whiteboard**: Shared drawing/annotation
- [ ] **File Sharing**: Document sharing during sessions
- [ ] **Breakout Rooms**: Split into smaller groups

### Scalability
- [ ] **TURN Servers**: For enterprise firewalls
- [ ] **Media Server**: For large group sessions
- [ ] **Load Balancing**: Multiple signaling servers
- [ ] **Database Storage**: Replace session storage

## Support

For technical support or questions:
1. Check browser console for errors
2. Test with `/test/webrtc-test.html`
3. Verify all files are properly uploaded
4. Ensure proper PHP session configuration

---

**✅ WebRTC Video Conferencing is now fully implemented and ready to use!**