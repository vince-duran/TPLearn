-- Add recording_url column to jitsi_meetings table
ALTER TABLE jitsi_meetings 
ADD COLUMN recording_url VARCHAR(1000) NULL AFTER is_recorded,
ADD COLUMN recording_started_at TIMESTAMP NULL AFTER recording_url,
ADD COLUMN recording_ended_at TIMESTAMP NULL AFTER recording_started_at;
