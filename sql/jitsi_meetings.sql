-- Create Jitsi Live Classes table
CREATE TABLE IF NOT EXISTS jitsi_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    tutor_id INT NOT NULL,
    meeting_id VARCHAR(255) UNIQUE NOT NULL,
    meeting_url VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    duration_minutes INT DEFAULT 60,
    status ENUM('scheduled', 'active', 'completed', 'cancelled') DEFAULT 'scheduled',
    max_participants INT DEFAULT 50,
    is_recorded BOOLEAN DEFAULT FALSE,
    meeting_password VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_program_date (program_id, scheduled_date),
    INDEX idx_tutor (tutor_id),
    INDEX idx_status (status)
);

-- Create Jitsi Meeting Participants table to track attendance
CREATE TABLE IF NOT EXISTS jitsi_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    duration_minutes INT DEFAULT 0,
    status ENUM('joined', 'left', 'kicked', 'no_show') DEFAULT 'no_show',
    FOREIGN KEY (meeting_id) REFERENCES jitsi_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_meeting (meeting_id),
    INDEX idx_user (user_id),
    UNIQUE KEY unique_meeting_user (meeting_id, user_id)
);