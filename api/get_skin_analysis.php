<?php
/**
 * API Endpoint: Get Skin Analysis
 * 
 * Accepts either:
 * - Base64 encoded image data
 * - Symptoms description text
 * 
 * Returns AI analysis + RAG-based homeopathic remedy suggestions
 */

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json');

// Global exception handler
set_exception_handler(function($e) {
    error_log("Skin Analysis API Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/dermo_ai.php';

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    // Check authentication
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in']);
        exit;
    }

    $doctor_id = $_SESSION['doctor_id'];

    // Get input data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Also accept form data
    if (empty($input)) {
        $input = [
            'image_data' => $_POST['image_data'] ?? '',
            'skin_area' => $_POST['skin_area'] ?? 'general',
            'symptoms' => $_POST['symptoms'] ?? '',
            'patient_id' => $_POST['patient_id'] ?? null
        ];
    }

    // Extract inputs
    $imageData = $input['image_data'] ?? '';
    $skinArea = $input['skin_area'] ?? 'general';
    $symptoms = $input['symptoms'] ?? '';
    $patientId = $input['patient_id'] ?? null;
    $skipAI = isset($input['skip_ai']) ? (bool)$input['skip_ai'] : false;

    // Get patient info if provided
    $patient = null;
    if ($patientId) {
        $patient = DB::queryOne(
            "SELECT * FROM patients WHERE id = ? AND doctor_id = ?",
            [$patientId, $doctor_id]
        );
    }

    // Validate input - need either image or symptoms
    if (empty($imageData) && empty($symptoms)) {
        echo json_encode([
            'success' => false,
            'error' => 'Please provide either an image or symptoms description'
        ]);
        exit;
    }

    $result = null;

    // Process based on input type
    if (!empty($imageData)) {
        // Save base64 image temporarily
        $tempDir = __DIR__ . '/../uploads/skin_images/temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        // Extract base64 data
        $imageParts = explode(";base64,", $imageData);
        if (count($imageParts) == 2) {
            $imageDecoded = base64_decode($imageParts[1]);
            $tempFile = $tempDir . uniqid('temp_skin_') . '.png';
            file_put_contents($tempFile, $imageDecoded);
            
            // Analyze image
            $result = analyzeSkinImage($tempFile, $skinArea, $symptoms, $patient, $skipAI);
            
            // Clean up temp file
            unlink($tempFile);
        } else {
            // Try direct base64
            $imageDecoded = base64_decode($imageData);
            if ($imageDecoded) {
                $tempFile = $tempDir . uniqid('temp_skin_') . '.png';
                file_put_contents($tempFile, $imageDecoded);
                
                $result = analyzeSkinImage($tempFile, $skinArea, $symptoms, $patient, $skipAI);
                
                unlink($tempFile);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid image data format'
                ]);
                exit;
            }
        }
    } else {
        // Text-only analysis
        $result = analyzeSkinsymptomsOnly($symptoms, $skinArea, $patient);
    }

    // Save to database if patient selected
    if ($patientId && $result && $result['success']) {
        try {
            DB::insert('skin_analyses', [
                'patient_id' => $patientId,
                'doctor_id' => $doctor_id,
                'image_path' => null, // API doesn't save images permanently
                'skin_area' => $skinArea,
                'symptoms_description' => $symptoms,
                'ai_analysis' => json_encode($result['ai_analysis'] ?? []),
                'rag_remedies' => json_encode($result['rag_remedies'] ?? []),
                'analysis_date' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log but don't fail the request
            error_log('Failed to save skin analysis: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    error_log("Skin Analysis API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred during analysis: ' . $e->getMessage()
    ]);
}
