-- Add pc_number field to sit_in_requests table
ALTER TABLE `sit_in_requests` 
ADD COLUMN `pc_number` INT NULL AFTER `subject_id`,
ADD INDEX `idx_pc_number` (`pc_number`);

-- Update existing records to have NULL pc_number
UPDATE `sit_in_requests` SET `pc_number` = NULL;

-- Update the subjects table to include PC count field
ALTER TABLE `subjects`
ADD COLUMN `pc_count` INT NOT NULL DEFAULT 50 COMMENT 'Number of PCs available in the lab' AFTER `sessions`; 