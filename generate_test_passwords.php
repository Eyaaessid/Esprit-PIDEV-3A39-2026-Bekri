<?php
/**
 * Password Hash Generator for Test Users
 * Run this file to generate bcrypt hashes for your test users
 * 
 * Usage: php generate_test_passwords.php
 */

echo "=== Bekri Wellbeing - Password Hash Generator ===\n\n";

$passwords = [
    'admin123' => 'Admin user password',
    'coach123' => 'Coach user password',
    'user123'  => 'Regular user password',
];

foreach ($passwords as $password => $description) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    echo "{$description}:\n";
    echo "  Plain: {$password}\n";
    echo "  Hash:  {$hash}\n\n";
}

echo "Copy these hashes into your SQL INSERT statements.\n";
echo "Replace '\$2y\$13\$YourHashedPasswordHere' with the generated hash.\n";
