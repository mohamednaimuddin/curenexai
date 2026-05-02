<?php
/**
 * API Endpoint: Get All Diseases
 * 
 * Returns all diseases from the database for display
 * Used by disease_database.html
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }


    // Fetch all diseases
    $diseases = DB::query(
        "SELECT 
            id,
            disease_name,
            icd_code,
            category,
            sub_category,
            description,
            primary_symptoms,
            secondary_symptoms,
            warning_signs,
            typical_onset,
            typical_duration,
            age_groups,
            gender_predisposition,
            clinical_findings,
            lab_tests,
            imaging,
            differential_diagnosis,
            conventional_treatment,
            homeopathic_approach,
            lifestyle_modifications,
            severity_level,
            urgency_level,
            contagious,
            reference_source
         FROM diseases 
         ORDER BY category, disease_name"
    );

    if ($diseases === false) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch diseases from database'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'diseases' => $diseases,
        'count' => count($diseases)
    ]);

} catch (Exception $e) {
    error_log('Get All Diseases API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
