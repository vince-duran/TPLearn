# 🔧 Assessment Submissions Debug & Fix - Complete Guide

## 🎯 Problem
When clicking "Submissions" button on attached assessments in tutor stream, you see:
**"Error loading submissions. Please try again."**

## 📊 Root Cause Analysis Tools Created

### 1. **FIX_ASSESSMENT_SUBMISSIONS_GUIDE.html** 
   - **Purpose:** Step-by-step diagnostic and testing guide
   - **What it does:**
     - Links to all diagnostic tools
     - Explains what to check and why
     - Shows expected vs actual results
   - **Use when:** Starting the debugging process

### 2. **LIVE_DEBUGGER.html**
   - **Purpose:** Real-time monitoring of API calls and context changes
   - **What it does:**
     - Intercepts all fetch calls to assessment/assignment APIs
     - Logs every parameter, response, and error
     - Shows exact data flow
   - **Use when:** You want to see EXACTLY what's happening when you click the button

### 3. **debug_assessment_submission_issue.php**
   - **Purpose:** Backend diagnostic - checks database state
   - **What it does:**
     - Verifies material exists
     - Shows all attached assessments
     - Checks for submissions for each assessment
     - Displays what assessment IDs have submissions
   - **Use when:** You need to verify database structure and relationships

### 4. **create_test_assessment_submissions.php**
   - **Purpose:** Generate sample data for testing
   - **What it does:**
     - Creates 3 test submissions for the first attached assessment
     - Uses actual enrolled students from the program
     - Randomizes grades and status (submitted/graded)
   - **Use when:** No submissions exist and you want to test with real data

### 5. **api/check_all_assessment_submissions.php**
   - **Purpose:** Quick API endpoint to see ALL submissions
   - **What it does:**
     - Returns JSON of all assessment submissions in database
     - Shows last 20 submissions with student names and assessment titles
   - **Use when:** You want to verify submissions exist anywhere in the system

## 🔍 Diagnostic Steps

### Step 1: Open FIX_ASSESSMENT_SUBMISSIONS_GUIDE.html
This is your main hub. It provides:
- Links to all tools
- Expected outcomes for each scenario
- Common error messages and what they mean

### Step 2: Run the Diagnostic
Click **"Run Diagnostic"** button to see:
- ✅/❌ Is the material found?
- ✅/❌ Are there attached assessments?
- ✅/❌ Are there submissions?
- What is the assessment ID?

### Step 3: Interpret Results

#### Scenario A: No Submissions Exist ✅
```
Material found: ✅ Laban (Type: document)
Attached assessments: ✅ 1 assessment found
  - Assessment ID: 242
  - Title: "DURAN EI!filiibusterismo"
  - Max Score: 100 pts
Submissions: ❌ NO submissions found
```
**Solution:** This is NORMAL! Click "Create Test Submissions" to generate sample data.

#### Scenario B: Wrong ID Being Passed ❌
```
Assessment ID being passed: 241 (the document)
Should be: 242 (the assessment)
```
**Solution:** The onclick is passing wrong ID - need to fix the button's assessment_id parameter

#### Scenario C: API Error ❌
```
HTTP 500 Error
or
Database connection error
```
**Solution:** Check PHP error logs, verify database connection, check API file for syntax errors

### Step 4: Test with LIVE_DEBUGGER.html
1. Open LIVE_DEBUGGER.html
2. Copy the JavaScript code
3. Open tutor stream in another tab
4. Press F12, paste code in console
5. Click "Submissions" button
6. Watch the real-time logs appear

**What you'll see:**
```
🎯 viewSubmissions() CALLED
  ID: 242
  Context: assessment

📡 INTERCEPTED API CALL
  URL: /TPLearn/api/get-assessment-submissions.php?material_id=242
  Context: assessment

✅ RESPONSE RECEIVED
  Status: 200 OK

📦 PARSED JSON:
  Success: true
  Assessment ID: 242
  Submissions Count: 3
```

## 🎯 Expected Outcomes

### ✅ SUCCESS - Submissions Exist
- Modal opens
- Shows assessment title and details
- Displays table with student names, grades, submission dates
- Statistics show: "3 submissions / 5 students (60% submission rate)"

### ✅ SUCCESS - No Submissions Yet
- Modal opens
- Shows assessment title and details
- Displays: "No submissions found for this **assessment**" (note: says "assessment" not "assignment")
- Statistics show: "0 submissions / 5 students (0% submission rate)"

### ❌ ERROR - Context Wrong
- Modal shows: "Error loading **assignment**" ← WRONG! Should say "assessment"
- This means context is not being set correctly
- Check that viewSubmissions() is being called with context='assessment'

### ❌ ERROR - API Failed
- Modal shows: "Error loading assessment"
- Console shows specific error message
- Use diagnostic tools to identify why API failed

## 🔧 Code Flow Verification

### 1. Button Click
```javascript
onclick="viewSubmissions('${assessment.assessment_id}', 'assessment')"
```
✅ Verify assessment_id is correct (should be from material_assessments table)
✅ Verify context='assessment' is passed

### 2. Context Storage
```javascript
window.currentSubmissionContext = 'assessment';
```
✅ This should be set before API call

### 3. API Call
```javascript
fetch(`/TPLearn/api/get-assessment-submissions.php?material_id=${materialId}`)
```
✅ URL should include correct assessment ID
✅ Should use assessment API, not assignment API

### 4. Response Handling
```javascript
if (data.success && data.assessment) {
  displaySubmissionsData(...);
} else {
  displaySubmissionsError(data.error, true); // isAssessment=true
}
```
✅ Success path displays data
✅ Error path shows "assessment" not "assignment"

### 5. Error Handling
```javascript
if (context === 'assessment') {
  isAssessment = true; // Always use context
}
displaySubmissionsError(errorMessage, isAssessment);
```
✅ Context takes priority over error flags
✅ Ensures error messages say "assessment" correctly

## 📝 Quick Reference

| Tool | URL | Purpose |
|------|-----|---------|
| Main Guide | `/TPLearn/FIX_ASSESSMENT_SUBMISSIONS_GUIDE.html` | Start here |
| Live Debugger | `/TPLearn/LIVE_DEBUGGER.html` | Real-time monitoring |
| Diagnostic | `/TPLearn/debug_assessment_submission_issue.php` | Check database |
| Create Data | `/TPLearn/create_test_assessment_submissions.php` | Generate submissions |
| View All | `/TPLearn/api/check_all_assessment_submissions.php` | See all submissions |
| Tutor Stream | `/TPLearn/dashboards/tutor/tutor-program-stream.php?program_id=32` | Test here |

## 🎬 Testing Checklist

- [ ] Run diagnostic - verify assessment ID and structure
- [ ] Check if submissions exist in database
- [ ] If no submissions, create test data
- [ ] Open LIVE_DEBUGGER.html and copy code
- [ ] Open tutor stream, open console (F12), paste debug code
- [ ] Click "Submissions" on attached assessment
- [ ] Verify console shows correct context='assessment'
- [ ] Verify API call uses correct ID
- [ ] Verify modal opens with correct data OR correct error message
- [ ] If error, verify it says "assessment" not "assignment"

## 💡 Common Issues & Solutions

### Issue: "Assessment not found"
**Cause:** Wrong ID being passed (document ID instead of assessment ID)
**Solution:** Check that onclick passes assessment.assessment_id, not material.id

### Issue: "Error loading assignment" (says "assignment")
**Cause:** Context not set or lost
**Solution:** Verify viewSubmissions() is called with context='assessment'

### Issue: "No submissions found" but submissions exist
**Cause:** API querying wrong assessment_id
**Solution:** Check that assessment_submissions.assessment_id matches the ID being queried

### Issue: Network error / 500 error
**Cause:** API file has error or database connection failed
**Solution:** Check PHP error logs, verify includes/auth.php and includes/db.php work

## 🎯 Next Steps

1. **Start with FIX_ASSESSMENT_SUBMISSIONS_GUIDE.html** - this is your main hub
2. **Run the diagnostic** - this tells you exactly what the problem is
3. **Use LIVE_DEBUGGER.html** - this shows you the data flow in real-time
4. **Create test data if needed** - this lets you test with actual submissions
5. **Report back** - once you see the console logs and diagnostic results, we can fix the exact issue

---

**All context-awareness fixes are already in place:**
✅ Error handler prioritizes context
✅ Error messages say "assessment" or "assignment" correctly
✅ "No submissions" message is context-aware
✅ Console logging shows every step

**Now we need to verify the DATA is flowing correctly through the system!**
