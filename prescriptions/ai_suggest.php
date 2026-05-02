<?php
// AI Remedy Suggestion AJAX endpoint for prescription form
require_once __DIR__ . '/../includes/init.php';
requireLogin();
header('Content-Type: application/json');

$doctorId = getLoggedInDoctorId();
$consultationId = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;
if ($consultationId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid consultation ID']);
    exit;
}

// Fetch consultation details
$sql = "SELECT c.*, p.patient_name, p.age, p.gender, p.phone, p.email, p.blood_group, p.allergies FROM consultations c INNER JOIN patients p ON c.patient_id = p.id WHERE c.id = ? AND c.doctor_id = ?";
$consultation = DB::queryOne($sql, [$consultationId, $doctorId]);
if (!$consultation) {
    echo json_encode(['success' => false, 'error' => 'Consultation not found or access denied']);
    exit;
}

// Fetch symptoms
$symptoms = DB::query("SELECT symptom_text as symptom, intensity as severity, duration, CONCAT_WS(', ', location, sensation, modality) as notes FROM symptoms WHERE consultation_id = ? ORDER BY id", [$consultationId]);
$consultation['symptoms'] = $symptoms;

// Get AI suggestions
require_once __DIR__ . '/../includes/gemini_api.php';
$gemini = new GeminiAPI();
$result = $gemini->generateRemedySuggestions($consultation);
if ($result['success']) {
    echo json_encode(['success' => true, 'remedies' => $result['suggestions']['remedies'] ?? [], 'raw' => $result['suggestions']]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
