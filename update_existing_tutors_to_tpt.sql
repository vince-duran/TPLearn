-- Update existing tutors to use TPT format usernames
-- This script updates the current tutors in your database to have proper TPT format IDs

-- Update existing tutors with TPT format usernames and user_ids
UPDATE users SET 
    user_id = 'TPT2025-001',
    username = 'TPT2025-001'
WHERE email = 'literature.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-002',
    username = 'TPT2025-002'
WHERE email = 'computer.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-003',
    username = 'TPT2025-003'
WHERE email = 'chemistry.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-004',
    username = 'TPT2025-004'
WHERE email = 'biology.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-005',
    username = 'TPT2025-005'
WHERE email = 'math.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-006',
    username = 'TPT2025-006'
WHERE email = 'physics.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-007',
    username = 'TPT2025-007'
WHERE email = 'english.tutor@tplearn.com';

UPDATE users SET 
    user_id = 'TPT2025-008',
    username = 'TPT2025-008'
WHERE email = 'history.tutor@tplearn.com';

-- If you have additional tutors, you can add more UPDATE statements here
-- Follow this pattern:
-- UPDATE users SET user_id = 'TPT2025-XXX', username = 'TPT2025-XXX' WHERE email = 'tutor_email@domain.com';