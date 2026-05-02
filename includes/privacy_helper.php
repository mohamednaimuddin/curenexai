<?php
/**
 * Privacy Helper Functions
 * Handles data anonymization and AI consent checking
 */

/**
 * Check if a doctor has consented to external AI usage
 * 
 * @param int $doctor_id The doctor's ID
 * @return bool True if AI consent is enabled, false otherwise
 */
function hasAIConsent($doctor_id) {
    try {
        // First check if the ai_consent column exists
        $columns = DB::query("SHOW COLUMNS FROM doctors LIKE 'ai_consent'");
        if (empty($columns)) {
            // Column doesn't exist yet - default to enabled for backward compatibility
            return true;
        }
        
        $doctor = DB::queryOne("SELECT ai_consent FROM doctors WHERE id = ?", [$doctor_id]);
        // Default to true for backward compatibility (existing users who haven't set preference)
        return $doctor ? ($doctor['ai_consent'] ?? 1) == 1 : true;
    } catch (Exception $e) {
        error_log('Error checking AI consent: ' . $e->getMessage());
        return true; // Default to enabled on error for backward compatibility
    }
}

/**
 * Anonymize patient data before sending to external AI
 * Removes all personally identifiable information (PII)
 * 
 * @param array $data The data array containing patient/consultation info
 * @return array Anonymized data safe for external AI
 */
function anonymizeForAI($data) {
    if (!is_array($data)) {
        return $data;
    }
    
    $anonymized = [];
    
    // Fields to completely remove (PII)
    $removeFields = [
        'patient_name', 'name', 'full_name', 'doctor_name',
        'email', 'phone', 'mobile', 'contact', 'telephone',
        'address', 'street', 'city', 'state', 'zip', 'postal_code', 'pincode',
        'patient_id', 'doctor_id', 'id', 'user_id',
        'registration_number', 'license_number', 'aadhar', 'pan',
        'emergency_contact', 'guardian_name', 'father_name', 'mother_name',
        'spouse_name', 'next_of_kin',
        'insurance_id', 'policy_number',
        'created_at', 'updated_at', 'created_by'
    ];
    
    // Fields to keep (medical data)
    $keepFields = [
        'age', 'gender', 'blood_group',
        'chief_complaint', 'present_illness', 'past_history', 'family_history',
        'general_symptoms', 'particular_symptoms', 'mental_state',
        'physical_examination', 'thermal_state', 'thirst', 'appetite',
        'sleep', 'stool', 'urine', 'sweat', 'menses',
        'modalities', 'aggravation', 'amelioration',
        'diagnosis', 'differential_diagnosis',
        'causation', 'exciting_cause', 'maintaining_cause',
        'notes', 'clinical_notes', 'observations',
        'lab_report', 'lab_values', 'test_results',
        'symptoms', 'rubrics', 'repertory_symptoms',
        'allergies', 'sensitivities', 'intolerances',
        'weight', 'height', 'bmi', 'vital_signs',
        'blood_pressure', 'pulse', 'temperature', 'respiratory_rate'
    ];
    
    foreach ($data as $key => $value) {
        $lowerKey = strtolower($key);
        
        // Skip PII fields
        $shouldRemove = false;
        foreach ($removeFields as $removeField) {
            if (strpos($lowerKey, strtolower($removeField)) !== false) {
                $shouldRemove = true;
                break;
            }
        }
        
        if ($shouldRemove) {
            continue;
        }
        
        // Handle nested arrays (like symptoms array)
        if (is_array($value)) {
            $anonymized[$key] = anonymizeForAI($value);
        } else {
            $anonymized[$key] = $value;
        }
    }
    
    return $anonymized;
}

/**
 * Anonymize text content by removing common PII patterns
 * Used for lab reports and free-text fields
 * 
 * @param string $text The text to anonymize
 * @return string Anonymized text
 */
function anonymizeText($text) {
    if (!is_string($text) || empty($text)) {
        return $text;
    }
    
    // Remove common name patterns (Mr./Mrs./Dr. followed by names)
    $text = preg_replace('/\b(Mr\.|Mrs\.|Ms\.|Dr\.|Shri|Smt\.|Sri)\s+[A-Z][a-z]+(\s+[A-Z][a-z]+)*/u', '[PATIENT]', $text);
    
    // Remove "Patient Name:" or "Name:" followed by text
    $text = preg_replace('/\b(Patient\s*Name|Name|Patient)\s*[:\-]?\s*[A-Za-z\s\.]+(?=\n|,|$)/i', 'Patient: [ANONYMIZED]', $text);
    
    // Remove phone numbers (Indian and international formats)
    $text = preg_replace('/(\+?\d{1,3}[\s\-]?)?(\(?\d{2,5}\)?[\s\-]?)?\d{3,5}[\s\-]?\d{4,6}/u', '[PHONE]', $text);
    
    // Remove email addresses
    $text = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u', '[EMAIL]', $text);
    
    // Remove Aadhar numbers (12 digits with optional spaces)
    $text = preg_replace('/\b\d{4}\s?\d{4}\s?\d{4}\b/', '[AADHAR]', $text);
    
    // Remove PAN numbers (Indian)
    $text = preg_replace('/\b[A-Z]{5}\d{4}[A-Z]\b/', '[PAN]', $text);
    
    // Remove patient ID patterns
    $text = preg_replace('/\b(Patient\s*ID|PID|MRN|UHID|Reg\.?\s*No\.?)\s*[:\-]?\s*[A-Z0-9\-]+/i', '[ID]', $text);
    
    // Remove address-like patterns (with PIN codes)
    $text = preg_replace('/\b\d{6}\b/', '[PINCODE]', $text); // Indian PIN codes
    
    // Remove dates of birth in common formats (keep test dates)
    $text = preg_replace('/\b(DOB|Date\s*of\s*Birth|Birth\s*Date)\s*[:\-]?\s*\d{1,2}[\-\/\.]\d{1,2}[\-\/\.]\d{2,4}/i', 'DOB: [ANONYMIZED]', $text);
    
    return $text;
}

/**
 * Prepare consultation data for AI with anonymization
 * 
 * @param array $consultation Consultation data
 * @param array $patient Patient data (optional)
 * @param string $labText Lab report text (optional)
 * @return array Anonymized data ready for AI
 */
function prepareDataForAI($consultation, $patient = null, $labText = null) {
    $data = [];
    
    // Add patient demographics (non-PII only)
    if ($patient) {
        $data['age'] = $patient['age'] ?? 'Unknown';
        $data['gender'] = $patient['gender'] ?? 'Unknown';
        $data['blood_group'] = $patient['blood_group'] ?? null;
    }
    
    // Add consultation data (anonymized)
    if ($consultation) {
        $anonymizedConsultation = anonymizeForAI($consultation);
        $data = array_merge($data, $anonymizedConsultation);
    }
    
    // Add lab report (anonymized)
    if ($labText) {
        $data['lab_report'] = anonymizeText($labText);
    }
    
    return $data;
}
