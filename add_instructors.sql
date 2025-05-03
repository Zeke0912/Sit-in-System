-- Add instructor role to users table (if needed)
-- This first query is a check - it won't run if the role already exists
INSERT INTO users (idno, firstname, lastname, email, course, year, password, gender, role, status)
SELECT '1000', 'Admin', 'User', 'admin@example.com', NULL, NULL, '$2y$10$some_hash', 'Male', 'admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin' LIMIT 1);

-- Insert sample instructors
INSERT INTO users (idno, firstname, lastname, email, course, year, password, gender, role, status) 
VALUES 
('2001', 'John', 'Smith', 'john.smith@example.com', NULL, NULL, '$2y$10$some_hash', 'Male', 'instructor', 'active'),
('2002', 'Maria', 'Garcia', 'maria.garcia@example.com', NULL, NULL, '$2y$10$some_hash', 'Female', 'instructor', 'active'),
('2003', 'David', 'Johnson', 'david.johnson@example.com', NULL, NULL, '$2y$10$some_hash', 'Male', 'instructor', 'active'),
('2004', 'Sarah', 'Williams', 'sarah.williams@example.com', NULL, NULL, '$2y$10$some_hash', 'Female', 'instructor', 'active'),
('2005', 'Michael', 'Brown', 'michael.brown@example.com', NULL, NULL, '$2y$10$some_hash', 'Male', 'instructor', 'active'),
('2006', 'Jennifer', 'Jones', 'jennifer.jones@example.com', NULL, NULL, '$2y$10$some_hash', 'Female', 'instructor', 'active'),
('2007', 'Robert', 'Miller', 'robert.miller@example.com', NULL, NULL, '$2y$10$some_hash', 'Male', 'instructor', 'active'),
('2008', 'Patricia', 'Davis', 'patricia.davis@example.com', NULL, NULL, '$2y$10$some_hash', 'Female', 'instructor', 'active'),
('2009', 'James', 'Martinez', 'james.martinez@example.com', NULL, NULL, '$2y$10$some_hash', 'Male', 'instructor', 'active'),
('2010', 'Linda', 'Hernandez', 'linda.hernandez@example.com', NULL, NULL, '$2y$10$some_hash', 'Female', 'instructor', 'active');

-- Note: You might need to adjust the password hash or other fields to match your database structure
-- If you get duplicate key errors, it means some IDs already exist in your system 