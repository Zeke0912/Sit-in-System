-- Add instructor_id to subjects table if it doesn't exist
ALTER TABLE subjects
ADD COLUMN IF NOT EXISTS instructor_id INT DEFAULT NULL COMMENT 'ID of the instructor from users table';

-- Update the status of available subjects
UPDATE subjects SET status = 'available' WHERE status IS NULL;

-- Clear all existing subject records (optional - comment out if you want to keep existing data)
-- TRUNCATE TABLE subjects;

-- Create some sample lab rooms without schedules
INSERT INTO subjects (subject_name, lab_number, date, start_time, end_time, instructor_id, sessions, status)
VALUES 
('Available', '528', CURDATE(), '00:00:00', '00:00:00', 0, 0, 'available'),
('Available', '524', CURDATE(), '00:00:00', '00:00:00', 0, 0, 'available'),
('Available', '526', CURDATE(), '00:00:00', '00:00:00', 0, 0, 'available'),
('Available', '523', CURDATE(), '00:00:00', '00:00:00', 0, 0, 'available'),
('Available', '527', CURDATE(), '00:00:00', '00:00:00', 0, 0, 'available');

-- Add a foreign key constraint to reference users table (optional)
-- ALTER TABLE subjects
-- ADD CONSTRAINT fk_subjects_instructor 
-- FOREIGN KEY (instructor_id) REFERENCES users(idno) ON DELETE SET NULL; 