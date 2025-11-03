-- Add test tutors with TPT format usernames
-- This script adds sample tutors to the system with proper TPT format IDs

INSERT INTO users (user_id, username, email, password, role, status) VALUES
('TPT2025-001', 'TPT2025-001', 'literature.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-002', 'TPT2025-002', 'computer.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-003', 'TPT2025-003', 'chemistry.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-004', 'TPT2025-004', 'biology.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-005', 'TPT2025-005', 'math.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-006', 'TPT2025-006', 'physics.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-007', 'TPT2025-007', 'english.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active'),
('TPT2025-008', 'TPT2025-008', 'history.tutor@tplearn.com', '$2y$12$SeOPp.27O/gUqRh4zUZvCOfBEk8t3XwUUG2KOobf5t.6p/VP1zgW6', 'tutor', 'active');

-- Add tutor profiles for the test tutors
INSERT INTO tutor_profiles (user_id, first_name, last_name, specializations, bio, contact_number, address) VALUES
(
  (SELECT id FROM users WHERE username = 'TPT2025-001'), 
  'Ms. Isabella', 'Cruz', 'Literature', 
  'Literature expert with 5 years of teaching experience.', 
  '', 
  'Cebu City'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-002'), 
  'Engr. Michael', 'Tan', 'Computer Science', 
  'Software engineering and programming specialist.', 
  '', 
  'Manila City'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-003'), 
  'Dr. Elena', 'Rodriguez', 'Chemistry', 
  'Chemistry professor with PhD in Organic Chemistry.', 
  '', 
  'Davao City'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-004'), 
  'Prof. Amanda', 'Santos', 'Biology', 
  'Biology and life sciences educator.', 
  '', 
  'Quezon City'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-005'), 
  'Ms. Carlos', 'Reyes', 'Mathematics', 
  'Mathematics and statistics specialist.', 
  '', 
  'Iloilo City'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-006'), 
  'Dr. Maria', 'Garcia', 'Physics', 
  'Physics professor specializing in quantum mechanics.', 
  '', 
  'Baguio City'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-007'), 
  'Prof. John', 'Dela Cruz', 'English', 
  'English literature and language arts teacher.', 
  '', 
  'Cagayan de Oro'
),
(
  (SELECT id FROM users WHERE username = 'TPT2025-008'), 
  'Ms. Sarah', 'Lopez', 'History', 
  'Philippine and world history educator.', 
  '', 
  'Bacolod City'
);