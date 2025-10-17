# SweetAlert2 Integration - TPLearn System

## Overview
SweetAlert2 has been integrated throughout the TPLearn system to provide beautiful, customizable popup notifications that replace standard JavaScript alerts.

## Setup

### Including SweetAlert2 in Your Page
```php
<?php include 'includes/common-scripts.php'; ?>
```

This include provides:
- SweetAlert2 library (CDN)
- TPAlert helper functions
- Custom TPLearn styling

## Available Functions

### Basic Alerts

#### Success Alert
```javascript
TPAlert.success('Title', 'Optional message');
// Example: TPAlert.success('Saved!', 'Student profile updated successfully');
```

#### Error Alert
```javascript
TPAlert.error('Title', 'Optional message');
// Example: TPAlert.error('Error!', 'Failed to save student data');
```

#### Warning Alert
```javascript
TPAlert.warning('Title', 'Optional message');
// Example: TPAlert.warning('Warning!', 'Please fill all required fields');
```

#### Info Alert
```javascript
TPAlert.info('Title', 'Optional message');
// Example: TPAlert.info('Notice', 'New features are available');
```

### Interactive Alerts

#### Confirmation Dialog
```javascript
const result = await TPAlert.confirm('Title', 'Message', 'Yes', 'Cancel');
if (result.isConfirmed) {
    // User clicked Yes
    console.log('User confirmed');
} else {
    // User clicked Cancel or dismissed
    console.log('User cancelled');
}
```

#### Delete Confirmation (Pre-styled)
```javascript
const result = await TPAlert.deleteConfirm('student profile');
if (result.isConfirmed) {
    // Proceed with deletion
}
```

### Loading States

#### Loading Dialog
```javascript
TPAlert.loading('Processing...', 'Please wait');
// Later, close it:
TPAlert.close();
```

#### Loading with Auto-close
```javascript
TPAlert.loading('Saving Data', 'Updating student profile...');
setTimeout(() => {
    TPAlert.success('Saved!', 'Profile updated successfully');
}, 2000);
```

### Toast Notifications

#### Basic Toast
```javascript
TPAlert.toast('Message', 'success'); // success, error, warning, info
```

#### Examples
```javascript
TPAlert.toast('Data saved successfully!', 'success');
TPAlert.toast('Connection lost', 'error');
TPAlert.toast('Form validation failed', 'warning');
TPAlert.toast('New notification received', 'info');
```

### Specialized TPLearn Functions

#### Save Success
```javascript
TPAlert.saveSuccess('Student Profile');
// Displays: "Student Profile Saved! Your student profile has been saved successfully."
```

#### Network Error
```javascript
TPAlert.networkError();
// Pre-configured network error message
```

## Usage Examples

### Form Validation
```javascript
function validateForm() {
    if (!document.getElementById('name').value) {
        TPAlert.warning('Missing Information', 'Please enter a student name');
        return false;
    }
    return true;
}
```

### AJAX Success/Error Handling
```javascript
fetch('/api/save-student')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            TPAlert.saveSuccess('Student');
        } else {
            TPAlert.error('Save Failed', data.message);
        }
    })
    .catch(() => {
        TPAlert.networkError();
    });
```

### Delete Confirmation Flow
```javascript
async function deleteStudent(studentId) {
    const result = await TPAlert.deleteConfirm('this student');
    
    if (result.isConfirmed) {
        TPAlert.loading('Deleting Student', 'Removing student from system...');
        
        try {
            const response = await fetch(`/api/students/${studentId}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                TPAlert.success('Deleted!', 'Student has been removed successfully');
                // Refresh page or remove element
            } else {
                TPAlert.error('Delete Failed', 'Could not remove student');
            }
        } catch (error) {
            TPAlert.networkError();
        }
    }
}
```

### Export Process with Loading
```javascript
function exportData() {
    TPAlert.loading('Generating Report', 'Creating your export file...');
    
    // Trigger download
    window.location.href = '/export/students.csv';
    
    // Show success after delay
    setTimeout(() => {
        TPAlert.success('Export Complete!', 'Your file has been downloaded');
    }, 2000);
}
```

## Styling

The system includes custom TPLearn styling that:
- Uses consistent border radius (12px for popups, 8px for buttons)
- Applies TPLearn color scheme (#10b981 for success, etc.)
- Maintains consistent typography
- Provides responsive design

## Migration from Standard Alerts

### Replace Standard JavaScript Alerts
```javascript
// OLD:
alert('Success!');
confirm('Are you sure?');

// NEW:
TPAlert.success('Success!');
await TPAlert.confirm('Are you sure?');
```

### Replace Form Validation Messages
```javascript
// OLD:
if (!email) {
    alert('Please enter email');
    return;
}

// NEW:
if (!email) {
    TPAlert.warning('Missing Email', 'Please enter a valid email address');
    return;
}
```

## Best Practices

1. **Use appropriate alert types**: Success for positive actions, error for failures, warning for validation, info for neutral information

2. **Provide clear titles and messages**: Make it obvious what happened and what the user should do

3. **Use loading states for async operations**: Show users that something is happening

4. **Handle confirmations properly**: Always check `result.isConfirmed` for confirmation dialogs

5. **Use toasts for non-critical notifications**: For things like "Settings saved" that don't need user interaction

6. **Be consistent**: Use the same patterns throughout your application

## Demo Page

Visit `/dashboards/admin/sweetalert-demo.php` to see all functions in action and test their behavior.