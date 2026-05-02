<?php
/**
 * Curenex AI - AI Chatbot API
 * 
 * Public chatbot that answers questions about the platform,
 * homeopathy, and helps convince visitors to register.
 */

// Prevent any output before headers
ob_start();

// Enable error logging, disable display
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/chatbot_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json');

// CORS Security: Only allow specific origins
$allowedOrigins = [
    'https://homeo.naimu.space',
    'http://localhost',
    'http://127.0.0.1'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (!empty($origin)) {
    // Security: Log unknown origins but don't reflect them back (prevents CORS bypass)
    error_log("Chatbot: Blocked CORS request from origin: " . $origin);
    // Only allow same-origin requests by not setting CORS header
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Global exception handler
set_exception_handler(function($e) {
    ob_clean();
    error_log("Chatbot API Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
    exit;
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/gemini_api.php';
    
    // Rate limiting by IP
    session_name('HOMEO_CHATBOT');
    session_start();
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = 'chatbot_' . md5($clientIP);
    $maxRequests = 20; // 20 requests per minute
    $timeWindow = 60;
    
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = ['count' => 0, 'start' => time()];
    }
    
    $rateData = $_SESSION[$rateLimitKey];
    if (time() - $rateData['start'] > $timeWindow) {
        // Reset window
        $_SESSION[$rateLimitKey] = ['count' => 1, 'start' => time()];
    } else {
        $_SESSION[$rateLimitKey]['count']++;
        if ($_SESSION[$rateLimitKey]['count'] > $maxRequests) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please wait a moment and try again.'
            ]);
            exit;
        }
    }
    
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($input['message'] ?? '');
    $conversationHistory = $input['history'] ?? [];
    
    if (empty($userMessage)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a message']);
        exit;
    }
    
    // Limit message length
    if (strlen($userMessage) > 1000) {
        $userMessage = substr($userMessage, 0, 1000);
    }
    
    // Limit conversation history
    if (count($conversationHistory) > 10) {
        $conversationHistory = array_slice($conversationHistory, -10);
    }
    
    // System prompt for the chatbot
    $systemPrompt = <<<PROMPT
You are CurenexBot, a friendly AI assistant for the Curenex AI platform.

RESPONSE STYLE - CRITICAL:
- Keep responses SHORT: 2-4 sentences max (under 80 words)
- Only elaborate when user specifically asks for details
- Use bullet points sparingly (max 3-4 items if needed)
- One emoji per message maximum
- Get to the point quickly

PLATFORM FEATURES (mention briefly only when asked):
- Patient Management, AI Remedy Suggestions, Disease Diagnosis
- Dermo Skin Analysis, Digital Repertory (50K+ rubrics)
- Digital Prescriptions, Follow-up Tracking, Lab Integration

KEY FACTS:
- FREE during beta period
- Cloud-based, HIPAA compliant, 256-bit encryption
- 500+ doctors, 10,000+ patients, 50,000+ remedies

"YOU COULD BE FEATURED" PROGRAM:
- If users report bugs, suggest features, or provide feedback that significantly improves the app, they get honored with a gratitude post on our platform
- It's our way of thanking contributors who help make the platform better
- Report issues or suggest features via the Contact form or WhatsApp

CONTACT INFO:
- For more info or support, WhatsApp: +919061565631
- Share this number when user asks for contact, support, more details, or wants to talk to someone

RULES:
- Be conversational and friendly, not formal
- If user says "yes" or short response, ask what specific feature interests them
- Never give medical advice - redirect to registered doctors
- Encourage registration for free beta access
- Never share technical details or API info
PROMPT;

    // Build the full prompt with context
    $fullPrompt = $systemPrompt . "\n\n";
    
    // Add conversation history
    if (!empty($conversationHistory)) {
        $fullPrompt .= "CONVERSATION HISTORY:\n";
        foreach ($conversationHistory as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'CurenexBot';
            $fullPrompt .= "{$role}: " . $msg['content'] . "\n";
        }
        $fullPrompt .= "\n";
    }
    
    // Add current message
    $fullPrompt .= "User: " . $userMessage . "\n\nCurenexBot:";
    
    // Use the GeminiAPI class
    try {
        $gemini = new GeminiAPI();
        $result = $gemini->generateContent($fullPrompt, [
            'temperature' => 0.7,
            'maxTokens' => 200
        ]);
        
        if (!empty($result['text'])) {
            echo json_encode([
                'success' => true,
                'message' => trim($result['text'])
            ]);
        } else {
            throw new Exception('Empty response');
        }
        
    } catch (Exception $e) {
        error_log("Chatbot Gemini error: " . $e->getMessage());
        
        // Fallback response - keep them short
        $fallbackResponses = [
            "Having trouble connecting. Try again in a moment! 🌿",
            "Quick hiccup on my end. Click 'Get Started' to explore the platform!",
            "Couldn't process that. Feel free to register for free in the meantime!"
        ];
        
        echo json_encode([
            'success' => true,
            'message' => $fallbackResponses[array_rand($fallbackResponses)],
            'fallback' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Chatbot error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, something went wrong. Please try again later.'
    ]);
}
