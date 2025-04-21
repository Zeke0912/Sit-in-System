-- Add points column to users table if it doesn't exist
ALTER TABLE IF NOT EXISTS users ADD COLUMN IF NOT EXISTS points INT DEFAULT 0;

-- Create session_points table if it doesn't exist
CREATE TABLE IF NOT EXISTS session_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    session_id INT NOT NULL,
    points INT NOT NULL,
    awarded_at DATETIME NOT NULL,
    UNIQUE KEY unique_session_student (session_id, student_id)
);

-- Create bonus_logs table if it doesn't exist
CREATE TABLE IF NOT EXISTS bonus_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    points_used INT NOT NULL,
    sessions_added INT NOT NULL,
    awarded_at DATETIME NOT NULL
);

-- Optional: Add some initial sample data
-- This assumes you have users and sit_in_requests tables with data
-- Uncomment the lines below if you want to add sample data

/*
-- Add sample points to some students
INSERT INTO session_points (student_id, session_id, points, awarded_at)
SELECT 
    s.student_id, 
    s.id as session_id, 
    FLOOR(1 + RAND() * 5) as points, -- Random points between 1 and 5
    s.end_time as awarded_at
FROM 
    sit_in_requests s
WHERE 
    s.status = 'approved' 
    AND s.end_time IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM session_points sp WHERE sp.session_id = s.id)
LIMIT 20; -- Only add for 20 random sessions

-- Update the user's total points based on the session_points
UPDATE users u
SET u.points = (
    SELECT COALESCE(SUM(sp.points), 0)
    FROM session_points sp
    WHERE sp.student_id = u.idno
);

-- Add a few sample bonus records (points being used for extra sessions)
INSERT INTO bonus_logs (student_id, points_used, sessions_added, awarded_at)
SELECT 
    u.idno,
    3 as points_used, -- 3 points per bonus session
    1 as sessions_added,
    NOW() as awarded_at
FROM 
    users u
WHERE 
    u.role = 'student'
    AND u.points >= 3 -- Has enough points for a bonus
LIMIT 5; -- Only for 5 students
*/

-- Change the MAX_SESSION_COUNT if needed for BSIT/BSCS students
-- UPDATE site_settings SET value = '30' WHERE setting = 'BSIT_MAX_SESSIONS';
-- UPDATE site_settings SET value = '15' WHERE setting = 'OTHER_MAX_SESSIONS'; 