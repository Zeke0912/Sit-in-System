-- Check if pc_status table exists and drop it if it does
DROP TABLE IF EXISTS pc_status;

-- Create the pc_status table with proper structure
CREATE TABLE pc_status (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    lab_id INT(11) NOT NULL,
    pc_number INT(11) NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY lab_pc (lab_id, pc_number)
);

-- Create index for better performance
CREATE INDEX idx_pc_status_lab_status ON pc_status(lab_id, status);

-- Insert maintenance records for lab 1 (change to your lab ID)
-- Uncomment and modify the below lines for your specific lab
-- INSERT INTO pc_status (lab_id, pc_number, status) VALUES (1, 1, 'maintenance');
-- INSERT INTO pc_status (lab_id, pc_number, status) VALUES (1, 2, 'maintenance');
-- INSERT INTO pc_status (lab_id, pc_number, status) VALUES (1, 3, 'maintenance');
-- Add more as needed

-- To mark all PCs in a lab as maintenance (uncomment and use below)
-- Replace LAB_ID with your lab ID and adjust the LIMIT to match your PC count
/*
SET @lab_id = 1;  -- Change to your lab ID
SET @pc_count = 50;  -- Change to your PC count

-- First delete any existing records for this lab
DELETE FROM pc_status WHERE lab_id = @lab_id;

-- Insert maintenance status for all PCs in the lab
DROP PROCEDURE IF EXISTS mark_all_maintenance;
DELIMITER //
CREATE PROCEDURE mark_all_maintenance()
BEGIN
    DECLARE i INT DEFAULT 1;
    
    WHILE i <= @pc_count DO
        INSERT INTO pc_status (lab_id, pc_number, status) 
        VALUES (@lab_id, i, 'maintenance');
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL mark_all_maintenance();
DROP PROCEDURE mark_all_maintenance;
*/

-- Quick check query (uncomment to verify)
-- SELECT * FROM pc_status ORDER BY lab_id, pc_number; 