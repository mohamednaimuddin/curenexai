<?php
/**
 * CurenexBot - Custom AI-Free Chatbot
 * 
 * A rule-based, pattern-matching chatbot that provides intelligent responses
 * without requiring any external API or AI service. Completely free to use.
 */

class HomeoBot {
    
    private $conversationHistory = [];
    private $lastMatchedIntent = null;
    
    /**
     * Platform knowledge base
     */
    private $platformInfo = [
        'name' => 'Curenex AI',
        'company' => 'CurenexAI',
        'domain' => 'curenexai.com',
        'features' => [
            'patient_management' => 'Complete patient records with medical history, symptoms, and treatments',
            'ai_diagnosis' => 'Smart symptom analysis with remedy suggestions from our database',
            'repertory' => 'Digital repertory with 50,000+ rubrics for accurate remedy matching',
            'prescriptions' => 'Digital prescriptions with dosage tracking',
            'dermo_analysis' => 'Skin condition analysis with dermoscopic image support',
            'lab_integration' => 'Lab test management and result tracking',
            'followups' => 'Follow-up scheduling and progress monitoring',
            'consultations' => 'Complete consultation management and history'
        ],
        'stats' => [
            'doctors' => '500+',
            'patients' => '10,000+',
            'remedies' => '50,000+',
            'rubrics' => '50,000+'
        ],
        'pricing' => 'FREE during beta period',
        'security' => 'Account-based patient data access with security controls and privacy-minded workflows',
        'contact' => '+919061565631 (WhatsApp)'
    ];
    
    /**
     * Response patterns with keywords and responses
     */
    private $patterns = [
        // Greetings
        'greeting' => [
            'keywords' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'greetings', 'howdy', 'hola', 'namaste'],
            'responses' => [
                "Hello! 👋 How can I help you explore our homeopathic platform today?",
                "Hi there! Ready to discover how we can help your practice? Ask me anything!",
                "Hey! 🌿 Welcome! What would you like to know about our platform?"
            ]
        ],
        
        // Features inquiry
        'features' => [
            'keywords' => ['features', 'what can', 'what do', 'capabilities', 'tools', 'functions', 'offer', 'provide', 'have'],
            'responses' => [
                "We offer powerful tools for homeopathic practitioners:\n• Patient Management\n• AI-Powered Remedy Suggestions\n• Digital Repertory (50K+ rubrics)\n• Skin Analysis\n• Digital Prescriptions\n\nWhich feature interests you most? 🌿"
            ]
        ],
        
        // AI Diagnosis
        'ai_diagnosis' => [
            'keywords' => ['ai diagnosis', 'ai suggest', 'remedy suggest', 'symptom analysis', 'how does ai', 'diagnosis work', 'smart diagnosis', 'ai work'],
            'responses' => [
                "Our diagnosis system analyzes patient symptoms and matches them against our extensive remedy database. It considers:\n• Symptom patterns\n• Constitutional factors\n• Remedy affinities\n\nGreat opportunity to start - it's completely free! 🎯"
            ]
        ],
        
        // Pricing
        'pricing' => [
            'keywords' => ['price', 'cost', 'free', 'pay', 'subscription', 'premium', 'charge', 'pricing', 'expensive', 'affordable', 'money'],
            'responses' => [
                "Great news! It's a wonderful opportunity to start - the platform is completely FREE! Just register and explore all features! 🎉",
                "It's completely free! Register now and get full access to all features. Great time to get started! 🌟"
            ]
        ],
        
        // Skin Analysis / Dermo
        'dermo' => [
            'keywords' => ['skin', 'dermo', 'dermoscopy', 'dermatology', 'rash', 'skin condition', 'skin analysis', 'derma'],
            'responses' => [
                "Our Dermo feature helps analyze skin conditions using dermoscopic images. Upload patient images and get condition suggestions with matching homeopathic remedies. Perfect for skin-related cases! 🔬"
            ]
        ],
        
        // Repertory
        'repertory' => [
            'keywords' => ['repertory', 'rubric', 'rubrics', 'kent', 'boericke', 'materia medica'],
            'responses' => [
                "Our digital repertory contains 50,000+ rubrics for precise remedy selection. Search symptoms, browse categories, and find matching remedies instantly. Much faster than flipping through books! 📚"
            ]
        ],
        
        // Patients
        'patients' => [
            'keywords' => ['patient', 'patients', 'patient management', 'records', 'case', 'cases'],
            'responses' => [
                "Our patient management system lets you:\n• Store complete medical histories\n• Track symptoms and treatments\n• Manage follow-ups\n• Generate reports\n\nAll your patient data in one secure place! 📋"
            ]
        ],
        
        // Prescriptions
        'prescriptions' => [
            'keywords' => ['prescription', 'prescribe', 'medicine', 'dosage', 'remedy'],
            'responses' => [
                "Create digital prescriptions easily! Add remedies, set dosages, and track patient medications. You can also print or share prescriptions digitally. 💊"
            ]
        ],
        
        // Security
        'security' => [
            'keywords' => ['secure', 'security', 'safe', 'privacy', 'hipaa', 'data protection', 'encrypted'],
            'responses' => [
                "Your data is protected with account-based access controls, secure session handling, restricted patient record visibility, and privacy-minded workflows. Patient privacy is a core design priority."
            ]
        ],
        
        // Registration
        'register' => [
            'keywords' => ['register', 'sign up', 'signup', 'create account', 'join', 'start', 'get started', 'how to start'],
            'responses' => [
                "Getting started is easy! Click 'Get Started Free' at the top of the page, fill in your details, and you're ready to go. Takes less than a minute! 🚀",
                "Ready to join? Click the registration button, enter your email and details, and start using the platform immediately. It's free! ✨"
            ]
        ],
        
        // Contact / Support
        'contact' => [
            'keywords' => ['contact', 'support', 'help', 'talk', 'reach', 'whatsapp', 'phone', 'email', 'assistance', 'more info', 'details'],
            'responses' => [
                "Need personal assistance? Contact us on WhatsApp: +919061565631\n\nWe're happy to help with any questions or provide a demo! 📱"
            ]
        ],
        
        // Feature Request / Bug Report
        'feedback' => [
            'keywords' => ['bug', 'issue', 'problem', 'feedback', 'suggest', 'feature request', 'improve', 'improvement', 'report'],
            'responses' => [
                "We love feedback! Report bugs or suggest features through our Contact form. If your contribution significantly improves the app, you could be featured in our 'Gratitude' section! 🌟"
            ]
        ],
        
        // Mobile / App
        'mobile' => [
            'keywords' => ['mobile', 'app', 'android', 'ios', 'phone', 'tablet', 'smartphone'],
            'responses' => [
                "Our platform is fully responsive and works great on mobile browsers. Access your practice from anywhere - phone, tablet, or desktop! 📱"
            ]
        ],
        
        // Lab Tests
        'lab' => [
            'keywords' => ['lab', 'laboratory', 'test', 'tests', 'blood test', 'reports'],
            'responses' => [
                "Our Lab module helps you manage patient test results. Track blood work, imaging, and other diagnostics alongside homeopathic treatments. 🔬"
            ]
        ],
        
        // Consultations
        'consultations' => [
            'keywords' => ['consultation', 'consult', 'appointment', 'visit', 'session'],
            'responses' => [
                "Track every consultation! Record symptoms, prescriptions, and notes. View complete patient journey and treatment history at a glance. 📝"
            ]
        ],
        
        // How it works
        'how_works' => [
            'keywords' => ['how it works', 'how does it', 'explain', 'tell me about', 'what is this', 'about'],
            'responses' => [
                "Here's how it works:\n1️⃣ Register free\n2️⃣ Add your patients\n3️⃣ Record symptoms & consultations\n4️⃣ Get remedy suggestions\n5️⃣ Create prescriptions\n\nSimple and powerful! Want to know more about any feature?"
            ]
        ],
        
        // Thanks
        'thanks' => [
            'keywords' => ['thank', 'thanks', 'thank you', 'appreciate', 'helpful', 'great', 'awesome', 'wonderful'],
            'responses' => [
                "You're welcome! 😊 Happy to help. Let me know if you have more questions!",
                "Glad I could help! Feel free to ask anything else about the platform. 🌿"
            ]
        ],
        
        // Goodbye
        'goodbye' => [
            'keywords' => ['bye', 'goodbye', 'see you', 'later', 'gotta go', 'leaving'],
            'responses' => [
                "Goodbye! 👋 Hope to see you registered soon. Take care!",
                "See you! 🌿 Remember, registration is free. Come back anytime!"
            ]
        ],
        
        // Yes / Affirmative
        'affirmative' => [
            'keywords' => ['yes', 'yeah', 'yep', 'sure', 'okay', 'ok', 'correct', 'right'],
            'responses' => [
                "Great! What specific feature would you like to know more about? Features, pricing, or how to get started?",
                "Awesome! Would you like to know about our features, how the diagnosis works, or how to register?"
            ]
        ],
        
        // Homeopathy general
        'homeopathy' => [
            'keywords' => ['homeopathy', 'homeopathic', 'hahnemann', 'potency', 'dilution', 'natural', 'alternative'],
            'responses' => [
                "We're built for homeopathic practitioners! Our platform includes comprehensive remedy databases, repertory tools, and practice management features specifically designed for homeopathy. 🌿"
            ]
        ],
        
        // CurenexAI Company
        'curenexai' => [
            'keywords' => ['curenexai', 'curenex', 'what is curenexai', 'tell about curenexai', 'about curenexai', 'who is curenexai', 'company'],
            'responses' => [
                "CurenexAI is a healthcare technology company focused on building AI-powered tools for medical practitioners. Our flagship product is the Homeopathic Assistant - a complete practice management platform with digital repertory, AI remedy suggestions, patient management, and skin analysis. Visit curenexai.com to learn more! 🌿",
                "CurenexAI develops intelligent healthcare solutions. We created the Homeopathic Assistant to help practitioners manage their practice efficiently with modern AI-powered tools. Currently free to use - a great opportunity to get started! 🚀"
            ]
        ],
        
        // Who are you
        'identity' => [
            'keywords' => ['who are you', 'what are you', 'your name', 'are you ai', 'are you real', 'are you bot', 'are you human'],
            'responses' => [
                "I'm CurenexBot! 🤖 Your assistant for the Curenex AI platform. I can answer questions about features, pricing, and how to get started!"
            ]
        ],
        
        // Doctors / Practitioners
        'doctors' => [
            'keywords' => ['doctor', 'doctors', 'practitioner', 'homeopath', 'clinic', 'practice'],
            'responses' => [
                "We serve 500+ homeopathic practitioners! Whether you run a solo practice or a clinic, our platform helps streamline your workflow and improve patient care. 👨‍⚕️"
            ]
        ],
        
        // Database / Remedies
        'database' => [
            'keywords' => ['database', 'remedies', 'how many', 'remedy count', 'medicines'],
            'responses' => [
                "Our database includes 50,000+ homeopathic remedies and rubrics! Constantly growing with contributions from the community. 📊"
            ]
        ]
    ];
    
    /**
     * Process user message and generate response
     */
    public function processMessage($message, $history = []) {
        $this->conversationHistory = $history;
        $normalizedMessage = $this->normalizeText($message);
        
        // Try to match a pattern
        $intent = $this->detectIntent($normalizedMessage);
        
        if ($intent) {
            $this->lastMatchedIntent = $intent;
            return $this->getRandomResponse($intent);
        }
        
        // Check for follow-up questions based on context
        if ($this->lastMatchedIntent) {
            $contextResponse = $this->handleContextualResponse($normalizedMessage);
            if ($contextResponse) {
                return $contextResponse;
            }
        }
        
        // Default response
        return $this->getDefaultResponse($normalizedMessage);
    }
    
    /**
     * Normalize text for matching
     */
    private function normalizeText($text) {
        // Convert to lowercase
        $text = strtolower($text);
        // Remove special characters but keep spaces
        $text = preg_replace('/[^\w\s]/', '', $text);
        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    /**
     * Detect user intent based on keywords
     */
    private function detectIntent($message) {
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($this->patterns as $intent => $data) {
            $score = $this->calculateMatchScore($message, $data['keywords']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $intent;
            }
        }
        
        // Require minimum confidence
        return $bestScore > 0 ? $bestMatch : null;
    }
    
    /**
     * Calculate match score for keywords
     */
    private function calculateMatchScore($message, $keywords) {
        $score = 0;
        $words = explode(' ', $message);
        
        foreach ($keywords as $keyword) {
            // Exact phrase match (highest score)
            if (strpos($message, $keyword) !== false) {
                $score += 3;
            }
            // Word-level match
            foreach ($words as $word) {
                if ($word === $keyword || 
                    levenshtein($word, $keyword) <= 1 ||
                    strpos($word, $keyword) !== false) {
                    $score += 1;
                }
            }
        }
        
        return $score;
    }
    
    /**
     * Get random response from intent
     */
    private function getRandomResponse($intent) {
        $responses = $this->patterns[$intent]['responses'];
        return $responses[array_rand($responses)];
    }
    
    /**
     * Handle contextual follow-up responses
     */
    private function handleContextualResponse($message) {
        // If user asks for "more" or "details"
        if (preg_match('/(more|detail|explain|tell me|elaborate)/', $message)) {
            switch ($this->lastMatchedIntent) {
                case 'features':
                    return "Let me break down each feature:\n\n📋 **Patient Management** - Complete records with history\n🎯 **Remedy Suggestions** - Smart symptom matching\n📚 **Repertory** - 50K+ searchable rubrics\n🔬 **Skin Analysis** - Dermoscopic support\n💊 **Prescriptions** - Digital Rx with tracking\n\nWant details on any specific one?";
                case 'ai_diagnosis':
                    return "Our diagnosis system works by:\n1. Analyzing patient symptoms\n2. Matching against remedy profiles\n3. Considering constitutional factors\n4. Suggesting ranked remedies\n\nIt's a great opportunity to try it - completely free!";
                case 'pricing':
                    return "It's completely free! You get:\n• All features unlocked\n• Unlimited patients\n• Full repertory access\n• Priority support\n\nGreat time to get started!";
            }
        }
        
        return null;
    }
    
    /**
     * Get default response when no pattern matches
     */
    private function getDefaultResponse($message) {
        $defaults = [
            "I'm not sure I understood that. Could you ask about our features, pricing, or how to get started? 🤔",
            "Let me help! You can ask me about:\n• Platform features\n• Pricing (it's free!)\n• How to register\n• Contact information\n\nWhat interests you?",
            "I didn't quite catch that. Try asking about our AI diagnosis, repertory, skin analysis, or how to sign up! 🌿"
        ];
        
        // If message is very short, encourage more specific question
        if (strlen($message) < 5) {
            return "Could you tell me more? I can help with info about features, pricing, getting started, or contact details! 💬";
        }
        
        return $defaults[array_rand($defaults)];
    }
    
    /**
     * Get suggested follow-up questions based on context
     */
    public function getSuggestions($intent = null) {
        $defaultSuggestions = [
            'What features do you offer?',
            'How does the diagnosis work?',
            'Is it free to use?',
            'How do I contact support?'
        ];
        
        if (!$intent) {
            return $defaultSuggestions;
        }
        
        $contextualSuggestions = [
            'features' => ['Tell me about AI diagnosis', 'How does skin analysis work?', 'Is it free?'],
            'pricing' => ['How do I register?', 'What features are included?', 'Contact support'],
            'ai_diagnosis' => ['Tell me about the repertory', 'How to register?', 'Is it secure?'],
            'dermo' => ['Other features?', 'How to get started?', 'Pricing info'],
            'register' => ['What features do I get?', 'Is my data secure?', 'Contact for help']
        ];
        
        return $contextualSuggestions[$intent] ?? $defaultSuggestions;
    }
}
