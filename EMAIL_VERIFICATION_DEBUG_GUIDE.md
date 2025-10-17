# EMAIL VERIFICATION DEBUGGING GUIDE

## Current Status
Added debugging console logs to help identify where the email verification system is failing.

## How to Test:

1. **Open the student profile page**
2. **Open browser Developer Tools (F12)**
3. **Go to the Console tab**
4. **Click "Edit Profile"**
5. **Change the email address**
6. **Watch the console for debug messages**

## Expected Console Output:

When changing email:
```
Email changed from: [old_email] to: [new_email]
Initiating email verification for: [new_email]
initiateEmailVerification called with: [new_email]
Sending API request to initiate email change
API response status: 200
API response data: {success: true, message: "..."}
```

When clicking Resend:
```
resendVerificationCode called with email: [new_email]
originalEmail: [old_email]
Sending resend API request
Resend API response status: 200
Resend API response data: {success: true, message: "..."}
```

## Troubleshooting:

### If no console messages appear:
- JavaScript functions not being called
- Check if email field change event is working

### If API request fails:
- Check Network tab in Developer Tools
- Look for HTTP error responses
- Check if user is authenticated

### If API returns success but no email:
- Check email configuration in config/email.php
- Verify Gmail SMTP settings
- Check server PHP error logs

### If "Error" dialog appears:
- Check console for actual error message
- Look at Network tab for API response details

## Quick Fixes to Try:

1. **Test with a real email address** (not example.com)
2. **Make sure user is logged in**
3. **Check if email field actually triggers change event**
4. **Try clicking in/out of email field after changing it**

## Files to Check:
- api/email-change.php (API endpoint)
- includes/email-verification.php (email sending functions)
- config/email.php (email configuration)
- dashboards/student/student-profile.php (frontend code)

The console debugging will help identify exactly where the issue is occurring.