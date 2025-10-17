# TPLearn Payment Resubmission Testing Guide

## Setup Instructions

1. **Database Setup:**
   - Run the SQL script in `update_payment_attachments.sql` in phpMyAdmin
   - Make sure the `tplearn` database exists
   - Ensure the `uploads/payment_receipts/` directory exists and is writable

2. **Testing the Resubmission Flow:**

### Step 1: Create a Rejected Payment (Admin Side)
1. Login as admin
2. Go to Admin Payments page
3. Find a payment with status "pending_validation"
4. Click "Validate" button
5. In the validation modal, enter a rejection reason (e.g., "Receipt was blurry")
6. Click "Reject Payment"
7. The payment status should change to "rejected"

### Step 2: Test Resubmission (Student Side)
1. Login as the student whose payment was rejected
2. Go to Student Payments page
3. You should see the rejected payment in the "Make Payment" tab
4. Click "Resubmit" button on the rejected payment
5. The resubmit modal should show:
   - Real rejection reason from admin ("Receipt was blurry")
   - Correct payment ID (PAY-YYYYMMDD-XXXXXX format)
   - Correct balance calculations
   - Payment method selection
   - File upload area

### Step 3: Submit Resubmission
1. Select a payment method (GCash, BPI, SeaBank, or Cash)
2. Enter a reference number (e.g., "135790")
3. Upload a receipt file (PNG, JPG, JPEG - max 10MB)
4. Click "Submit Payment"
5. Should see processing animation
6. Should show success modal with payment details
7. Page should reload showing updated payment status

### Step 4: Verify in Admin Dashboard
1. Login as admin
2. Go to Admin Payments page
3. The resubmitted payment should now show status "pending" or "pending_validation"
4. Reference number should be updated
5. Admin can validate the new submission

## Expected Behavior

### API Endpoints:
- `GET /api/payments.php?action=get_rejection_reason&payment_id=X` - Returns real rejection reason and payment details
- `POST /api/submit-payment.php` - Handles payment resubmissions with file uploads

### Database Changes:
- Payment status changes from "rejected" to "pending"
- Reference number and payment method updated
- Notes field cleared (removing rejection reason)
- File uploaded to `uploads/payment_receipts/`
- Payment attachment record created in `payment_attachments` table

### UI Updates:
- Student payments table reflects new status
- Admin payments table shows resubmitted payment as pending
- Real rejection reasons displayed instead of hardcoded messages
- Proper balance calculations shown

## Troubleshooting

1. **File Upload Issues:**
   - Check if `uploads/payment_receipts/` directory exists and is writable
   - Verify file size is under 10MB
   - Ensure file is PNG, JPG, or JPEG format

2. **Database Errors:**
   - Run the `update_payment_attachments.sql` script
   - Check if all tables exist
   - Verify foreign key constraints

3. **API Errors:**
   - Check browser console for JavaScript errors
   - Verify user is logged in
   - Check network tab for API response errors

4. **Payment Not Updating:**
   - Refresh both admin and student pages
   - Check database directly for payment status
   - Verify payment ID format and extraction