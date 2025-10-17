# Fix: Error Message Context for Attached Assessments

## 🎯 Problem
When clicking "Submissions" button on an **Attached Assessment**, the error modal (if any error occurs) was showing:
- **"Submissions - Error loading assignment"**
- **"Error loading assignment details"**

This is incorrect because the user clicked on an **Assessment**, not an Assignment.

## ✅ Solution

### Changes Made:

**1. Added Context Parameter to `viewSubmissions()` Function**
- Now accepts second parameter: `viewSubmissions(id, context)`
- Context can be: `'assessment'`, `'assignment'`, or `null`
- Stores context in `window.currentSubmissionContext`

**2. Updated Attached Assessment Button**
- Changed from: `onclick="viewSubmissions('${assessment.assessment_id}')"`
- Changed to: `onclick="viewSubmissions('${assessment.assessment_id}', 'assessment')"`
- This explicitly marks it as an assessment submission request

**3. Enhanced Error Display Function**
- `displaySubmissionsError(errorMessage, isAssessment)`
- Now accepts `isAssessment` parameter to determine the item type
- Dynamically shows correct error message:
  - If `isAssessment === true`: "Error loading **assessment**"
  - If `isAssessment === false`: "Error loading **assignment**"
  - If `isAssessment === null`: "Error loading assessment/assignment"

**4. Updated Error Handling in `loadAssignmentSubmissions()`**
- Uses `window.currentSubmissionContext` to determine type
- When both APIs fail, uses context to show appropriate error message
- Passes `isAssessment` flag to `displaySubmissionsError()`

## 📊 How It Works

### Flow:
1. User clicks "Submissions" on attached assessment
2. `viewSubmissions(assessment_id, 'assessment')` is called
3. Context `'assessment'` is stored in `window.currentSubmissionContext`
4. `loadAssignmentSubmissions()` tries to fetch data
5. If error occurs:
   - Checks `window.currentSubmissionContext`
   - Determines it's an assessment
   - Shows "Error loading **assessment**" instead of "Error loading assignment"

### Error Messages Now Show:

| Context | Modal Title | Error Title |
|---------|-------------|-------------|
| Assessment | "Submissions - Error loading **assessment**" | "Error loading **assessment** details" |
| Assignment | "Submissions - Error loading **assignment**" | "Error loading **assignment** details" |
| Unknown | "Submissions - Error loading assessment/assignment" | "Error loading assessment/assignment details" |

## 🧪 Testing

1. Log in as tutor
2. Go to program stream with attached assessments
3. Click "Submissions" button on an attached assessment
4. If error occurs (e.g., no data), verify it says:
   - ✅ "Error loading **assessment**"
   - ❌ NOT "Error loading assignment"

## 📁 Files Modified

- `dashboards/tutor/tutor-program-stream.php`:
  - Modified `viewSubmissions()` function (added context parameter)
  - Modified `loadAttachedAssessmentsForTutor()` (updated button onclick)
  - Modified `loadAssignmentSubmissions()` (context-aware error handling)
  - Modified `displaySubmissionsError()` (dynamic error message based on type)

## ✨ Benefits

✅ **Accurate Error Messages**: Shows correct item type (assessment vs assignment)
✅ **Better UX**: Users know exactly what failed to load
✅ **Context-Aware**: System understands where the request came from
✅ **Flexible**: Works for both assessments and assignments
✅ **Future-Proof**: Easy to extend for other material types

## 🔍 Edge Cases Handled

1. **Unknown Context**: If context is not provided, shows generic "assessment/assignment"
2. **API Failures**: Works even when both APIs fail
3. **Mixed Types**: Handles programs with both assessments and assignments
4. **Backward Compatible**: Regular assignment submissions still work normally

## 📝 Code Examples

### Before:
```javascript
onclick="viewSubmissions('${assessment.assessment_id}')"
// Always showed "Error loading assignment"
```

### After:
```javascript
onclick="viewSubmissions('${assessment.assessment_id}', 'assessment')"
// Shows "Error loading assessment" when appropriate
```

---

**Status:** ✅ Fixed
**Date:** October 8, 2025
**Impact:** Error messages now correctly reflect the type of material (assessment vs assignment)
