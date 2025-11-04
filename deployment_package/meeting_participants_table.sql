-- Meeting Participants Tracking Table
-- This table tracks when users join and leave live sessions

CREATE TABLE IF NOT EXISTS meeting_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP NULL,
    left_at TIMESTAMP NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_meeting_user (meeting_id, user_id),
    INDEX idx_meeting_active (meeting_id, joined_at, left_at),
    INDEX idx_last_seen (last_seen),
    
    -- Unique constraint to prevent duplicate active sessions
    UNIQUE KEY unique_active_participation (meeting_id, user_id, left_at)
);

-- Add some sample data structure comments
-- joined_at: When user joined the session
-- left_at: When user left the session (NULL means still in session)
-- last_seen: Last activity timestamp (updated via heartbeat)
-- This allows tracking of active participants and session history