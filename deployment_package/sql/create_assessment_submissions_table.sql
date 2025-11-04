-- Create assessment_submissions table (separate from assignment_submissions)
-- This table stores student submissions for assessments

CREATE TABLE IF NOT EXISTS `assessment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL COMMENT 'FK to program_materials where material_type=assessment',
  `student_id` int(11) NOT NULL COMMENT 'FK to users table',
  `file_upload_id` int(11) DEFAULT NULL COMMENT 'FK to file_uploads table',
  `submission_text` text DEFAULT NULL COMMENT 'Optional text submission or comments',
  `is_late` tinyint(1) DEFAULT 0 COMMENT '1 if submitted after due date',
  `status` enum('submitted','graded','returned') DEFAULT 'submitted',
  `grade` decimal(5,2) DEFAULT NULL COMMENT 'Grade given by tutor',
  `feedback` text DEFAULT NULL COMMENT 'Tutor feedback',
  `graded_by` int(11) DEFAULT NULL COMMENT 'FK to users (tutor who graded)',
  `graded_at` datetime DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assessment_student` (`assessment_id`,`student_id`),
  KEY `idx_assessment_id` (`assessment_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `fk_assessment_submissions_file` (`file_upload_id`),
  KEY `fk_assessment_submissions_grader` (`graded_by`),
  CONSTRAINT `fk_assessment_submissions_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `program_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assessment_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assessment_submissions_file` FOREIGN KEY (`file_upload_id`) REFERENCES `file_uploads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assessment_submissions_grader` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores student submissions for assessments (separate from assignments)';
