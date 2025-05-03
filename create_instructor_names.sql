-- Create instructor_names table
CREATE TABLE IF NOT EXISTS instructor_names (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL
);

-- Insert sample instructors
INSERT INTO instructor_names (firstname, lastname) 
VALUES 
('John', 'Smith'),
('Maria', 'Garcia'),
('David', 'Johnson'),
('Sarah', 'Williams'),
('Michael', 'Brown'),
('Jennifer', 'Jones'),
('Robert', 'Miller'),
('Patricia', 'Davis'),
('James', 'Martinez'),
('Linda', 'Hernandez'); 