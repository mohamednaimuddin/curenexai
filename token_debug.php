<?php
require_once 'includes/init.php';

echo "<h1>Token Debug</h1><pre>";

// Get token from URL
$urlToken = $_GET['token'] ?? '';
echo "Token from URL: " . ($urlToken ?: 'NONE') . "\n";
echo "Token length: " . strlen($urlToken) . "\n\n";

// Check all tokens in DB
echo "=== All password_reset tokens in DB ===\n";
$rows = DB::query("SELECT * FROM email_otps WHERE purpose = 'password_reset' ORDER BY id DESC LIMIT 5");

if (empty($rows)) {
    echo "No tokens found!\n";
} else {
    foreach ($rows as $r) {
        echo "ID: {$r['id']}\n";
        echo "Email: {$r['email']}\n";
        echo "Token in DB: {$r['otp']}\n";
        echo "DB Token Length: " . strlen($r['otp']) . "\n";
        echo "Expires: {$r['expires_at']}\n";
        echo "Verified: {$r['verified']}\n";
        
        // Compare
        if ($urlToken) {
            echo "Tokens Match: " . ($urlToken === $r['otp'] ? "YES ✅" : "NO ❌") . "\n";
        }
        
        $isExpired = strtotime($r['expires_at']) < time();
        echo "Status: " . ($isExpired ? "EXPIRED ❌" : "VALID ✅") . "\n";
        echo "---\n";
    }
}

// Try direct query with URL token
if ($urlToken) {
    echo "\n=== Direct query for URL token ===\n";
    $record = DB::queryOne(
        "SELECT * FROM email_otps WHERE otp = ? AND purpose = 'password_reset'",
        [$urlToken]
    );
    
    if ($record) {
        echo "Found! Email: {$record['email']}, Verified: {$record['verified']}\n";
    } else {
        echo "NOT FOUND in database!\n";
        
        // Check if token exists but with different purpose
        $any = DB::queryOne("SELECT * FROM email_otps WHERE otp = ?", [$urlToken]);
        if ($any) {
            echo "Token exists but with purpose: {$any['purpose']}\n";
        }
    }
}

echo "</pre>";
echo "<p><a href='forgot_password.php'>Request new reset link</a></p>";
