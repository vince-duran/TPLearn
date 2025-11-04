-- Add new fields to tutor_profiles table for enhanced registration
ALTER TABLE tutor_profiles 
ADD COLUMN middle_name VARCHAR(50) DEFAULT NULL AFTER last_name,
ADD COLUMN bachelor_degree VARCHAR(100) NOT NULL AFTER middle_name,
ADD COLUMN cv_document_path VARCHAR(255) DEFAULT NULL AFTER address,
ADD COLUMN diploma_document_path VARCHAR(255) DEFAULT NULL AFTER cv_document_path,
ADD COLUMN tor_document_path VARCHAR(255) DEFAULT NULL AFTER diploma_document_path,
ADD COLUMN lpt_csc_document_path VARCHAR(255) DEFAULT NULL AFTER tor_document_path,
ADD COLUMN other_documents_paths TEXT DEFAULT NULL AFTER lpt_csc_document_path;

-- Remove hourly_rate column as it's no longer needed
ALTER TABLE tutor_profiles DROP COLUMN hourly_rate;