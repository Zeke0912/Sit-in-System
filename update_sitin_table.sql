-- Check if the columns exist, and add them if they don't
ALTER TABLE sit_in_requests 
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 0 COMMENT 'Flag to indicate if sit-in session is currently active',
ADD COLUMN IF NOT EXISTS start_time DATETIME NULL COMMENT 'Time when the sit-in session started',
ADD COLUMN IF NOT EXISTS end_time DATETIME NULL COMMENT 'Time when the sit-in session ended',
ADD COLUMN IF NOT EXISTS duration VARCHAR(20) NULL COMMENT 'Duration of the sit-in session';

-- Update any existing approved requests to have is_active = 0 as default
UPDATE sit_in_requests SET is_active = 0 WHERE is_active IS NULL; 