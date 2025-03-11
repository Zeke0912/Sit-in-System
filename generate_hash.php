<?php
// The password you want to hash
$password = 'admin123';  // Replace this with the password you want for the admin

// Generate the password hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Print the hash
echo "Hashed Password: " . $hashed_password;
?>
