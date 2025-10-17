# Status Tag Consistency - TPLearn

## Overview
The TPLearn system has been updated to use consistent colors and formats for all live session status tags across the tutor and student interfaces.

## Problem Resolved
Previously, session status tags showed inconsistent colors:
- Some "Ended" sessions appeared in red
- Some "Ended" sessions appeared in green  
- Some "Upcoming" sessions appeared in different shades of blue
- Status was based on database `status` field rather than actual time calculations

## Solution Implemented

### 1. Consistent Color Scheme
All status tags now follow this standardized color scheme:

| Status | Color | CSS Classes | Usage |
|--------|-------|-------------|-------|
| **Live Now** | 🟢 Green | `bg-green-100 text-green-800` | Session is currently active |
| **Upcoming** | 🔵 Blue | `bg-blue-100 text-blue-800` | Session hasn't started yet |
| **Ended** | ⚫ Gray | `bg-gray-100 text-gray-800` | Session has finished |

### 2. Consistent Status Text
- **Live sessions**: "Live Now" (instead of just "Live")
- **Upcoming sessions**: "Upcoming"
- **Ended sessions**: "Ended" (instead of "Completed" or database status)

### 3. Logic-Based Status
Status is now determined by actual time calculations using PST timezone:
- **Live**: Current time is between session start and end time
- **Upcoming**: Current time is before session start time
- **Ended**: Current time is after session end time

## Changes Made

### Tutor Interface (`dashboards/tutor/tutor-program-stream.php`)
- Added `getLiveStatusColor(isLive, isUpcoming, isPast)` function
- Added `getStatusText(isLive, isUpcoming, isPast)` function
- Updated `displayLiveClasses()` to use consistent status logic
- Updated meeting details modal to use consistent status display

### Student Interface (`dashboards/student/program-stream.php`)
- Added matching `getLiveStatusColor()` and `getStatusText()` functions
- Updated `displayLiveSessions()` function
- Updated `displayLiveSessionsInAllContent()` function
- Ensured consistent status display across all views

### Status Functions

```javascript
// Get status color based on live/upcoming/ended status
function getLiveStatusColor(isLive, isUpcoming, isPast) {
  if (isLive) {
    return 'bg-green-100 text-green-800'; // Green for live sessions
  } else if (isUpcoming) {
    return 'bg-blue-100 text-blue-800';   // Blue for upcoming sessions
  } else {
    return 'bg-gray-100 text-gray-800';   // Gray for ended sessions
  }
}

// Get consistent status text
function getStatusText(isLive, isUpcoming, isPast) {
  if (isLive) {
    return 'Live Now';
  } else if (isUpcoming) {
    return 'Upcoming';
  } else {
    return 'Ended';
  }
}
```

## Benefits

1. **Visual Consistency**: All status tags use the same colors across the platform
2. **User Experience**: Users can instantly recognize session status by color
3. **Accuracy**: Status reflects actual time-based calculations, not database flags
4. **Maintainability**: Centralized status functions make future updates easier
5. **Accessibility**: Consistent colors improve readability and user understanding

## Status Tag Examples

### Before (Inconsistent)
- Try 3: Blue "Upcoming" (incorrect - should be ended)
- Try 2: Gray "Ended" 
- try 1: Green "Ended" (incorrect color)

### After (Consistent)
- Try 3: Gray "Ended" ✅
- Try 2: Gray "Ended" ✅  
- try 1: Gray "Ended" ✅

## Implementation Details

### HTML Structure
```html
<div class="flex items-center space-x-2 mb-2">
  <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Live Session</span>
  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}">
    ${statusText}
  </span>
</div>
```

### JavaScript Usage
```javascript
const isLive = meeting.is_live;
const isUpcoming = meeting.is_upcoming; 
const isPast = meeting.is_past;

const statusColor = getLiveStatusColor(isLive, isUpcoming, isPast);
const statusText = getStatusText(isLive, isUpcoming, isPast);
```

## Testing
- All existing sessions now show consistent gray "Ended" status
- Status colors properly reflect actual session timing
- Both tutor and student interfaces display identical status formatting
- Status tags update correctly based on PST timezone calculations

The system now provides a professional, consistent user experience with clearly identifiable session statuses across all interfaces.