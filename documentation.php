<?php
/**
 * Documentation Page - Curenex / CurenexAI
 */
require_once 'includes/init.php';

$pageTitle = 'Documentation';
$bodyClass = 'docs-page';
?>
<!DOCTYPE html>
<html lang="en" itemscope itemtype="https://schema.org/WebPage">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-WDBLVKCVG1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-WDBLVKCVG1');
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags - CurenexAI Documentation -->
    <title>Documentation - CurenexAI | AI-Powered Homeopathy Software User Guide</title>
    <meta name="title" content="Documentation - CurenexAI | Complete User Guide for Homeopathy Software">
    <meta name="description" content="Complete documentation for CurenexAI (Curenex AI) - the AI-powered homeopathic healthcare SOFTWARE platform. Learn how to use AI diagnosis, digital repertory, patient management & prescription tools. CurenexAI is NOT skin medicine.">
    <meta name="keywords" content="CurenexAI documentation, Curenex AI guide, CurenexAI tutorial, CurenexAI user manual, homeopathy software guide, AI healthcare documentation, CurenexAI help, how to use CurenexAI, CurenexAI features guide, homeopathic practice software">
    <meta name="author" content="CurenexAI">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="googlebot" content="index, follow">
    <link rel="canonical" href="https://curenexai.com/documentation.php">
    
    <!-- Open Graph -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://curenexai.com/documentation.php">
    <meta property="og:title" content="CurenexAI Documentation - Complete Software User Guide">
    <meta property="og:description" content="Learn how to use CurenexAI - the AI-powered homeopathic healthcare SOFTWARE platform. Guides for AI diagnosis, repertory, patient management & more.">
    <meta property="og:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta property="og:site_name" content="CurenexAI">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@curenexai">
    <meta name="twitter:title" content="CurenexAI Documentation - Software User Guide">
    <meta name="twitter:description" content="Complete guide to using CurenexAI homeopathic healthcare software platform.">
    <meta name="twitter:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    
    <!-- Schema.org - TechArticle -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "TechArticle",
        "headline": "CurenexAI Documentation - Complete User Guide",
        "description": "Comprehensive documentation for CurenexAI - AI-powered homeopathic healthcare software. Learn to use AI diagnosis, digital repertory, materia medica, patient management, and prescription generation features.",
        "author": {
            "@type": "Organization",
            "name": "CurenexAI",
            "url": "https://curenexai.com"
        },
        "publisher": {
            "@type": "Organization",
            "name": "CurenexAI",
            "logo": {
                "@type": "ImageObject",
                "url": "https://curenexai.com/assets/image/CURENEXAI PNG.png"
            }
        },
        "datePublished": "2026-01-01T00:00:00+05:30",
        "dateModified": "2026-04-10T00:00:00+05:30",
        "mainEntityOfPage": "https://curenexai.com/documentation.php",
        "image": "https://curenexai.com/assets/image/CURENEXAI PNG.png",
        "about": {
            "@type": "SoftwareApplication",
            "name": "CurenexAI",
            "applicationCategory": "HealthApplication",
            "operatingSystem": "Web Browser (Chrome, Firefox, Safari, Edge)",
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "USD"
            }
        }
    }
    </script>
    
    <!-- Schema.org - BreadcrumbList -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "https://curenexai.com"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "Documentation",
                "item": "https://curenexai.com/documentation.php"
            }
        ]
    }
    </script>
    
    <!-- Schema.org - HowTo for Getting Started -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "HowTo",
        "name": "How to Get Started with CurenexAI Homeopathy Software",
        "description": "Step-by-step guide to start using CurenexAI for your homeopathic practice",
        "step": [
            {
                "@type": "HowToStep",
                "name": "Create Account",
                "text": "Register for a free CurenexAI account at curenexai.com/register.php"
            },
            {
                "@type": "HowToStep",
                "name": "Add Patients",
                "text": "Create patient profiles with medical history and constitutional symptoms"
            },
            {
                "@type": "HowToStep",
                "name": "Use AI Diagnosis",
                "text": "Enter symptoms to get AI-powered remedy suggestions based on homeopathic principles"
            },
            {
                "@type": "HowToStep",
                "name": "Search Repertory",
                "text": "Use the digital repertory to find rubrics and related remedies"
            },
            {
                "@type": "HowToStep",
                "name": "Generate Prescriptions",
                "text": "Create professional prescriptions with dosage and instructions"
            }
        ]
    }
    </script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/image/CURENEXAI ICON.png">
    <link rel="apple-touch-icon" href="assets/image/CURENEXAI ICON.png">
    <meta name="theme-color" content="#6366f1">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <style>@font-face{font-family:'Font Awesome 6 Brands';font-display:swap}@font-face{font-family:'Font Awesome 6 Free';font-display:swap}@font-face{font-family:'Font Awesome 6 Solid';font-display:swap}</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .docs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .docs-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .docs-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .docs-header p {
            color: #6b7280;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .back-nav {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .back-nav a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-nav a:hover {
            text-decoration: underline;
        }
        
        .docs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .docs-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .docs-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        }
        
        .docs-card-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 28px;
        }
        
        .docs-card-icon.patients { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
        .docs-card-icon.consultations { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
        .docs-card-icon.prescriptions { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
        .docs-card-icon.repertory { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); color: #9333ea; }
        .docs-card-icon.ai { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777; }
        .docs-card-icon.settings { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4f46e5; }
        
        .docs-card h3 {
            font-size: 1.3rem;
            margin: 0 0 12px;
            color: #1f2937;
        }
        
        .docs-card p {
            color: #6b7280;
            margin: 0 0 20px;
            line-height: 1.6;
        }
        
        .docs-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .docs-card li {
            padding: 10px 0;
            border-top: 1px solid #f3f4f6;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .docs-card li i {
            color: #10b981;
            font-size: 14px;
        }
        
        .docs-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .docs-section h2 {
            color: #1f2937;
            font-size: 1.5rem;
            margin: 0 0 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .docs-section h2 i {
            color: #6366f1;
        }
        
        .docs-section h3 {
            color: #374151;
            font-size: 1.15rem;
            margin: 25px 0 15px;
        }
        
        .docs-section p, .docs-section li {
            color: #4b5563;
            line-height: 1.8;
        }
        
        .docs-section ul, .docs-section ol {
            padding-left: 25px;
            margin-bottom: 20px;
        }
        
        .docs-section li {
            margin-bottom: 8px;
        }
        
        .step-list {
            counter-reset: steps;
            list-style: none;
            padding: 0;
        }
        
        .step-list li {
            counter-increment: steps;
            padding: 15px 15px 15px 60px;
            position: relative;
            background: #f9fafb;
            border-radius: 12px;
            margin-bottom: 12px;
        }
        
        .step-list li::before {
            content: counter(steps);
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin: 25px 0;
        }
        
        .info-box p {
            margin: 0;
            color: #1e40af;
        }
        
        .tip-box {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-left: 4px solid #10b981;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin: 25px 0;
        }
        
        .tip-box p {
            margin: 0;
            color: #065f46;
        }
        
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin: 25px 0;
        }
        
        .warning-box p {
            margin: 0;
            color: #92400e;
        }
        
        .keyboard-shortcuts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .shortcut-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .shortcut-item kbd {
            background: #1f2937;
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .docs-grid {
                grid-template-columns: 1fr;
            }
            
            .docs-section {
                padding: 25px 20px;
            }
            
            .docs-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="docs-container">
        <div class="back-nav">
            <a href="<?php echo APP_URL; ?>"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <div class="docs-header">
            <h1><i class="fas fa-book"></i> Documentation</h1>
            <p>Learn how to use <?php echo APP_NAME; ?> effectively. Comprehensive guides for all features.</p>
        </div>
        
        <!-- Quick Start Cards -->
        <div class="docs-grid">
            <div class="docs-card">
                <div class="docs-card-icon patients">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Patient Management</h3>
                <p>Manage patient records, medical history, and constitutional symptoms.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Add new patients with complete details</li>
                    <li><i class="fas fa-check"></i> View patient history timeline</li>
                    <li><i class="fas fa-check"></i> Search and filter patients</li>
                    <li><i class="fas fa-check"></i> Print patient records</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon consultations">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <h3>Consultations</h3>
                <p>Record and manage patient consultations with detailed symptom tracking.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Create new consultations</li>
                    <li><i class="fas fa-check"></i> Track symptoms and observations</li>
                    <li><i class="fas fa-check"></i> Schedule follow-ups</li>
                    <li><i class="fas fa-check"></i> View consultation history</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon prescriptions">
                    <i class="fas fa-prescription"></i>
                </div>
                <h3>Prescriptions</h3>
                <p>Generate professional digital prescriptions for your patients.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Create detailed prescriptions</li>
                    <li><i class="fas fa-check"></i> Set potency and dosage</li>
                    <li><i class="fas fa-check"></i> Print or share digitally</li>
                    <li><i class="fas fa-check"></i> Track prescription history</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon repertory">
                    <i class="fas fa-book-medical"></i>
                </div>
                <h3>Repertory Search</h3>
                <p>Search through our comprehensive homeopathic repertory database.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Search 50,000+ rubrics</li>
                    <li><i class="fas fa-check"></i> Find remedy relationships</li>
                    <li><i class="fas fa-check"></i> Access materia medica</li>
                    <li><i class="fas fa-check"></i> Quick remedy lookup</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon ai">
                    <i class="fas fa-robot"></i>
                </div>
                <h3>AI Suggestions</h3>
                <p>Get intelligent remedy suggestions powered by advanced AI.</p>
                <ul>
                    <li><i class="fas fa-check"></i> AI-powered analysis</li>
                    <li><i class="fas fa-check"></i> Symptom-based suggestions</li>
                    <li><i class="fas fa-check"></i> Constitutional matching</li>
                    <li><i class="fas fa-check"></i> Remedy comparisons</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706;">
                    <i class="fas fa-diagnoses"></i>
                </div>
                <h3>Disease Diagnosis</h3>
                <p>Get diagnostic suggestions using our local medical database.</p>
                <ul>
                    <li><i class="fas fa-check"></i> RAG-based analysis</li>
                    <li><i class="fas fa-check"></i> Symptom matching</li>
                    <li><i class="fas fa-check"></i> Lab result interpretation</li>
                    <li><i class="fas fa-check"></i> Differential diagnosis</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon" style="background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777;">
                    <i class="fas fa-hand-holding-medical"></i>
                </div>
                <h3>Dermo - Skin Analysis</h3>
                <p>AI-powered skin condition analysis with homeopathic remedy suggestions.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Upload skin images</li>
                    <li><i class="fas fa-check"></i> Live camera capture</li>
                    <li><i class="fas fa-check"></i> AI visual analysis</li>
                    <li><i class="fas fa-check"></i> RAG remedy matching</li>
                </ul>
            </div>
            
            <div class="docs-card">
                <div class="docs-card-icon settings">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Settings & Profile</h3>
                <p>Customize your experience and manage your account settings.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Update profile information</li>
                    <li><i class="fas fa-check"></i> Change password</li>
                    <li><i class="fas fa-check"></i> Theme preferences</li>
                    <li><i class="fas fa-check"></i> Notification settings</li>
                </ul>
            </div>
        </div>
        
        <!-- Getting Started Section -->
        <div class="docs-section">
            <h2><i class="fas fa-rocket"></i> Getting Started</h2>
            
            <h3>Creating Your Account</h3>
            <ol class="step-list">
                <li>Visit the registration page and fill in your professional details including name, email, and medical credentials.</li>
                <li>Verify your email address by entering the OTP sent to your registered email.</li>
                <li>Complete your profile by adding qualification details and profile picture.</li>
                <li>You're all set! Start by adding your first patient.</li>
            </ol>
            
            <div class="tip-box">
                <p><strong><i class="fas fa-lightbulb"></i> Pro Tip:</strong> Make sure to add your medical registration number and state council for authenticity. This information may be verified.</p>
            </div>
            
            <h3>Dashboard Overview</h3>
            <p>Your dashboard provides a quick overview of:</p>
            <ul>
                <li><strong>Patient Count:</strong> Total number of patients in your records</li>
                <li><strong>Today's Consultations:</strong> Scheduled appointments for the day</li>
                <li><strong>Recent Activity:</strong> Latest consultations and prescriptions</li>
                <li><strong>Quick Actions:</strong> Fast access to common tasks</li>
            </ul>
        </div>
        
        <!-- Patient Management Section -->
        <div class="docs-section">
            <h2><i class="fas fa-users"></i> Patient Management</h2>
            
            <h3>Adding a New Patient</h3>
            <ol class="step-list">
                <li>Click "Add Patient" from the dashboard or navigation menu.</li>
                <li>Fill in basic information: Name, Age, Gender, Contact details.</li>
                <li>Add medical history, family history, and any allergies.</li>
                <li>Record constitutional symptoms if known.</li>
                <li>Save the patient record.</li>
            </ol>
            
            <h3>Patient Information Fields</h3>
            <ul>
                <li><strong>Basic Info:</strong> Name, age, gender, phone, email, address</li>
                <li><strong>Medical History:</strong> Past illnesses, surgeries, current medications</li>
                <li><strong>Family History:</strong> Hereditary conditions, family health patterns</li>
                <li><strong>Constitutional:</strong> Physical makeup, temperament, modalities</li>
            </ul>
            
            <div class="info-box">
                <p><strong><i class="fas fa-shield-alt"></i> Privacy Note:</strong> All patient data is encrypted and only accessible by you. No other user can view your patient records.</p>
            </div>
        </div>
        
        <!-- Consultation Section -->
        <div class="docs-section">
            <h2><i class="fas fa-stethoscope"></i> Managing Consultations</h2>
            
            <h3>Creating a Consultation</h3>
            <ol class="step-list">
                <li>Select a patient from your patient list or create a new one.</li>
                <li>Click "New Consultation" to start recording.</li>
                <li>Document chief complaints and presenting symptoms.</li>
                <li>Record observations: physical, mental, and emotional state.</li>
                <li>Use AI suggestions if needed for remedy selection.</li>
                <li>Create a prescription and schedule follow-up if required.</li>
            </ol>
            
            <h3>Follow-up Management</h3>
            <p>Track patient progress with follow-up consultations:</p>
            <ul>
                <li>View previous consultations for reference</li>
                <li>Compare symptoms before and after treatment</li>
                <li>Adjust prescriptions based on response</li>
                <li>Set reminders for upcoming follow-ups</li>
            </ul>
        </div>
        
        <!-- AI Features Section -->
        <div class="docs-section">
            <h2><i class="fas fa-robot"></i> Using AI Features</h2>
            
            <h3>AI Remedy Suggestions</h3>
            <p>Our AI assistant helps you find suitable remedies based on patient symptoms:</p>
            <ol class="step-list">
                <li>Enter the patient's symptoms in the consultation form.</li>
                <li>Click "Get AI Suggestions" to analyze symptoms.</li>
                <li>Review the suggested remedies with explanations.</li>
                <li>Select the most appropriate remedy for your prescription.</li>
            </ol>
            
            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle"></i> Important:</strong> AI suggestions are for reference only. Always verify suggestions using your clinical judgment and knowledge. The AI is a decision support tool, not a replacement for professional expertise.</p>
            </div>
            
            <h3>AI Lab Report Analysis</h3>
            <p>Upload lab reports for AI-assisted interpretation:</p>
            <ul>
                <li>Upload PDF or image of lab report</li>
                <li>AI extracts and analyzes key values</li>
                <li>Get insights about abnormal findings</li>
                <li>Correlate with homeopathic treatment approach</li>
            </ul>
        </div>
        
        <!-- Disease Diagnosis Section -->
        <div class="docs-section">
            <h2><i class="fas fa-diagnoses"></i> Disease Diagnosis Tool</h2>
            
            <h3>How It Works</h3>
            <p>The Disease Diagnosis feature uses RAG (Retrieval-Augmented Generation) technology with our local medical database to suggest possible diagnoses:</p>
            <ol class="step-list">
                <li>Navigate to the Diagnose section from the dashboard or menu.</li>
                <li>Enter patient symptoms in detail (fever, pain, fatigue, etc.).</li>
                <li>Add chief complaint describing the main issue.</li>
                <li>Include any lab test results (optional but recommended).</li>
                <li>Add physical examination findings (optional).</li>
                <li>Click "Get Diagnosis" to analyze.</li>
            </ol>
            
            <h3>Understanding Results</h3>
            <p>The diagnosis tool provides:</p>
            <ul>
                <li><strong>Possible Diagnoses:</strong> List of conditions matching the symptoms</li>
                <li><strong>Confidence Level:</strong> How well symptoms match each diagnosis</li>
                <li><strong>Matching Symptoms:</strong> Which entered symptoms matched</li>
                <li><strong>Supporting Findings:</strong> Additional clinical indicators</li>
                <li><strong>Notes for Doctor:</strong> Important considerations</li>
            </ul>
            
            <div class="info-box">
                <p><strong><i class="fas fa-database"></i> Local Processing:</strong> Unlike AI suggestions that use external APIs, the Disease Diagnosis tool works entirely with our local database. No patient data is sent to external servers for diagnosis, ensuring complete privacy.</p>
            </div>
            
            <h3>Tips for Best Results</h3>
            <ul>
                <li>Use specific medical terminology when describing symptoms</li>
                <li>Include duration and severity of symptoms</li>
                <li>Add relevant lab values with units</li>
                <li>Mention patient age and gender for age-specific conditions</li>
                <li>Include both positive and negative findings</li>
            </ul>
            
            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle"></i> Disclaimer:</strong> The diagnosis tool provides suggestions only. It is NOT a replacement for clinical judgment. Always verify diagnoses using your expertise, additional tests, and clinical examination.</p>
            </div>
        </div>
        
        <!-- Dermo Skin Analysis Section -->
        <div class="docs-section">
            <h2><i class="fas fa-hand-holding-medical"></i> Dermo - Skin Analysis</h2>
            
            <h3>What is Dermo?</h3>
            <p>Dermo is our AI-powered skin condition analysis tool that combines visual AI analysis with our homeopathic remedy database (RAG) to provide comprehensive skin analysis and remedy suggestions.</p>
            
            <h3>Two Analysis Modes</h3>
            <ul>
                <li><strong>AI + RAG Mode:</strong> Uses Gemini AI for visual analysis + database matching for remedies (most comprehensive)</li>
                <li><strong>RAG Only Mode:</strong> Uses pattern matching with your symptom description (faster, no external API)</li>
            </ul>
            
            <h3>Using Image Upload</h3>
            <ol class="step-list">
                <li>Navigate to Dermo from the dashboard or sidebar menu.</li>
                <li>Select "Upload Image" tab.</li>
                <li>Optionally select a patient to save the analysis to their record.</li>
                <li>Choose the affected body area (face, scalp, hands, etc.).</li>
                <li>Describe symptoms: itching, burning, duration, triggers, what makes it better/worse.</li>
                <li>Choose analysis mode (AI + RAG for comprehensive, RAG Only for faster).</li>
                <li>Drag and drop or click to upload a clear image of the skin condition.</li>
                <li>Click "Analyze Skin" and wait for results.</li>
            </ol>
            
            <h3>Using Live Camera</h3>
            <ol class="step-list">
                <li>Switch to "Live Camera" tab.</li>
                <li>Fill in the same form fields (patient, area, symptoms, mode).</li>
                <li>Click "Start Camera" to activate your device camera.</li>
                <li>Position the skin condition within the focus guide on screen.</li>
                <li>Click "Capture" when the image is clear and well-lit.</li>
                <li>Review the captured image - use "Retake" if needed.</li>
                <li>Click "Analyze Captured Image" to submit for analysis.</li>
            </ol>
            
            <div class="tip-box">
                <p><strong><i class="fas fa-lightbulb"></i> Pro Tip:</strong> For best results, ensure good lighting (natural daylight works best), capture the area up close, and include some surrounding healthy skin for comparison.</p>
            </div>
            
            <h3>Understanding Results</h3>
            <p>Dermo provides three tabs of results:</p>
            <ul>
                <li><strong>AI Analysis:</strong> Visual analysis from AI including detected condition, characteristics, severity, and recommendations</li>
                <li><strong>AI Remedies:</strong> Remedy suggestions directly from the AI based on visual analysis</li>
                <li><strong>RAG Remedies:</strong> Remedies from our homeopathic database matched to the detected condition</li>
            </ul>
            
            <h3>RAG Remedy Information</h3>
            <p>Each RAG remedy suggestion includes:</p>
            <ul>
                <li>Remedy name and common name</li>
                <li>Recommended potency and dosage</li>
                <li>Relevance score (how well it matches)</li>
                <li>Why the remedy is indicated</li>
                <li>Skin-specific indications</li>
                <li>Keynote symptoms</li>
                <li>Modalities (better/worse factors)</li>
            </ul>
            
            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle"></i> Important:</strong> Dermo analysis is for decision support only. Always verify results with clinical examination and your professional expertise. Do not rely solely on AI analysis for diagnosis or treatment.</p>
            </div>
            
            <h3>Common Skin Conditions Supported</h3>
            <p>Dermo can help analyze various skin conditions including:</p>
            <ul>
                <li>Eczema & Dermatitis</li>
                <li>Psoriasis</li>
                <li>Acne & Acne Rosacea</li>
                <li>Urticaria (Hives)</li>
                <li>Fungal infections (Ringworm, etc.)</li>
                <li>Vitiligo</li>
                <li>Herpes</li>
                <li>Scabies</li>
                <li>And many more...</li>
            </ul>
            
            <div class="info-box">
                <p><strong><i class="fas fa-mobile-alt"></i> Mobile Friendly:</strong> Dermo is fully responsive and works great on mobile devices. Use your phone to capture skin conditions directly during patient consultations.</p>
            </div>
        </div>
        
        <!-- Repertory Section -->
        <div class="docs-section">
            <h2><i class="fas fa-book-medical"></i> Repertory & Materia Medica</h2>
            
            <h3>Searching the Repertory</h3>
            <p>Our comprehensive repertory includes over 50,000 rubrics:</p>
            <ul>
                <li><strong>Quick Search:</strong> Type keywords to find rubrics</li>
                <li><strong>Browse by Chapter:</strong> Navigate through sections</li>
                <li><strong>Remedy Grades:</strong> View remedy importance (1, 2, 3 grades)</li>
                <li><strong>Cross References:</strong> Find related rubrics</li>
            </ul>
            
            <h3>Materia Medica</h3>
            <p>Access detailed remedy information:</p>
            <ul>
                <li>Complete remedy profiles</li>
                <li>Key symptoms and characteristics</li>
                <li>Modalities (better/worse factors)</li>
                <li>Remedy relationships (complementary, antidotes)</li>
            </ul>
        </div>
        
        <!-- Prescription Section -->
        <div class="docs-section">
            <h2><i class="fas fa-prescription"></i> Creating Prescriptions</h2>
            
            <h3>Prescription Components</h3>
            <ul>
                <li><strong>Remedy Name:</strong> Selected homeopathic medicine</li>
                <li><strong>Potency:</strong> Strength of the remedy (30C, 200C, 1M, etc.)</li>
                <li><strong>Dosage:</strong> Number of doses and frequency</li>
                <li><strong>Instructions:</strong> How to take the medicine</li>
                <li><strong>Duration:</strong> Treatment period</li>
                <li><strong>Diet & Regimen:</strong> Additional lifestyle advice</li>
            </ul>
            
            <h3>Printing & Sharing</h3>
            <p>Multiple options to share prescriptions with patients:</p>
            <ul>
                <li>Print professional formatted prescription</li>
                <li>Download as PDF</li>
                <li>Share via email or messaging</li>
            </ul>
        </div>
        
        <!-- Keyboard Shortcuts Section -->
        <div class="docs-section">
            <h2><i class="fas fa-keyboard"></i> Keyboard Shortcuts</h2>
            <p>Navigate faster with these keyboard shortcuts:</p>
            <div class="keyboard-shortcuts">
                <div class="shortcut-item">
                    <span>Go to Dashboard</span>
                    <kbd>Alt + D</kbd>
                </div>
                <div class="shortcut-item">
                    <span>New Patient</span>
                    <kbd>Alt + P</kbd>
                </div>
                <div class="shortcut-item">
                    <span>New Consultation</span>
                    <kbd>Alt + C</kbd>
                </div>
                <div class="shortcut-item">
                    <span>Search Repertory</span>
                    <kbd>Alt + R</kbd>
                </div>
                <div class="shortcut-item">
                    <span>Quick Search</span>
                    <kbd>Ctrl + K</kbd>
                </div>
                <div class="shortcut-item">
                    <span>Save Form</span>
                    <kbd>Ctrl + S</kbd>
                </div>
            </div>
        </div>
        
        <!-- Account Security Section -->
        <div class="docs-section">
            <h2><i class="fas fa-shield-alt"></i> Account Security</h2>
            
            <h3>Login Protection</h3>
            <p>Your account is protected with multiple security features:</p>
            <ul>
                <li><strong>Per-Account Rate Limiting:</strong> Failed login attempts only lock your specific account, not other users on the same network. This ensures clinic environments remain functional even if one user has login issues.</li>
                <li><strong>Single-Device Login:</strong> For security, logging in on a new device automatically logs you out from other devices.</li>
                <li><strong>Session Expiry:</strong> Sessions automatically expire after inactivity to protect your account.</li>
                <li><strong>Remember Me:</strong> Use this option on trusted devices for convenient access while maintaining security.</li>
            </ul>
            
            <h3>Password Security</h3>
            <ul>
                <li>Passwords are hashed with bcrypt encryption</li>
                <li>Use a strong, unique password for your account</li>
                <li>Change your password periodically from Settings</li>
                <li>Never share your login credentials</li>
            </ul>
            
            <div class="info-box">
                <p><strong><i class="fas fa-lock"></i> Locked Out?</strong> If you see "Too many login attempts" error, it only affects your account. Wait for the specified time and try again, or use "Forgot Password" to reset your credentials.</p>
            </div>
        </div>
        
        <!-- Need Help Section -->
        <div style="text-align: center; margin-top: 40px; padding: 40px; background: linear-gradient(135deg, #eff6ff, #e0e7ff); border-radius: 16px;">
            <h3 style="margin: 0 0 15px; color: #1f2937;">Need More Help?</h3>
            <p style="color: #6b7280; margin-bottom: 25px;">Can't find what you're looking for? Check our FAQ or contact support.</p>
            <a href="<?php echo APP_URL; ?>/faq.php" class="btn btn-outline btn-lg">
                <i class="fas fa-question-circle"></i> View FAQs
            </a>
            <a href="<?php echo APP_URL; ?>/support.php" class="btn btn-primary btn-lg" style="margin-left: 15px;">
                <i class="fas fa-headset"></i> Contact Support
            </a>
        </div>
    </div>
</body>
</html>
