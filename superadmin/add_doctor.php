<?php
/**
 * Super Admin - Add Doctor
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: doctors.php');
    exit;
}

$fullName = sanitize($_POST['full_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$registrationNumber = sanitize($_POST['registration_number'] ?? '');
$qualification = sanitize($_POST['qualification'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$status = sanitize($_POST['status'] ?? 'active');

// Validate
if (empty($fullName) || empty($email) || empty($password)) {
    setFlash('error', 'Full name, email, and password are required.');
    header('Location: doctors.php');
    exit;
}

if (!isValidEmail($email)) {
    setFlash('error', 'Invalid email address.');
    header('Location: doctors.php');
    exit;
}

if (strlen($password) < 8) {
    setFlash('error', 'Password must be at least 8 characters.');
    header('Location: doctors.php');
    exit;
}

// Check if email exists
$existing = DB::queryOne("SELECT id FROM doctors WHERE email = ?", [$email]);
if ($existing) {
    setFlash('error', 'A doctor with this email already exists.');
    header('Location: doctors.php');
    exit;
}

// Create doctor
try {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    DB::insert('doctors', [
        'full_name' => $fullName,
        'email' => $email,
        'password' => $hashedPassword,
        'registration_number' => $registrationNumber ?: null,
        'qualification' => $qualification ?: null,
        'phone' => $phone ?: null,
        'status' => $status
    ]);
    
    $doctorId = DB::lastInsertId();
    
    logAdminActivity($_SESSION['admin_id'], 'create_doctor', "Created doctor: $fullName ($email)", 'doctor', $doctorId);
    
    setFlash('success', 'Doctor created successfully.');
} catch (Exception $e) {
    setFlash('error', 'Failed to create doctor: ' . $e->getMessage());
}

header('Location: doctors.php');
exit;
