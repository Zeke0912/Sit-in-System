<?php
// The password you want to hash
$password = 'admin123';

// Generate the hash using bcrypt
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Display the hash
echo $hashedPassword;
?>
