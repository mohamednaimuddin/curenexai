<?php
/**
 * Frequently Asked Questions Page - Curenex / CurenexAI
 */
require_once 'includes/init.php';

$pageTitle = 'Frequently Asked Questions';
$bodyClass = 'faq-page';
?>
<!DOCTYPE html>
<html lang="en">
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
    
    <!-- SEO Meta Tags - Curenex -->
    <title>Homeopathy AI FAQ, Kent Repertory & RadarOpus Alternative | CurenexAI</title>
    <meta name="title" content="Homeopathy AI FAQ, Kent Repertory & RadarOpus Alternative | CurenexAI">
    <meta name="description" content="Frequently asked questions about CurenexAI homeopathy AI software, digital repertory, Kent repertory workflows, rubrics AI, and RadarOpus alternative searches.">
    <meta name="keywords" content="Curenex FAQ, CurenexAI questions, Curenex help, homeopathy FAQ, Homeopathy AI FAQ, Kent repertory software, digital reperatory, repertory FAQ, rubics AI, rubic automatic AI, RadarOpus alternative, better than RadarOpus, Curenex AI support, curenex, curenexai">
    <meta name="author" content="CurenexAI">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://curenexai.com/faq.php">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://curenexai.com/faq.php">
    <meta property="og:title" content="CurenexAI FAQ - Homeopathy AI, Kent Repertory & Rubrics AI">
    <meta property="og:description" content="Find answers about CurenexAI homeopathy AI software, digital repertory, Kent repertory workflows, and RadarOpus alternative comparisons.">
    <meta property="og:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta property="og:site_name" content="Curenex">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:site" content="@curenexai">
    <meta name="twitter:title" content="CurenexAI FAQ - Homeopathy AI & Digital Repertory">
    <meta name="twitter:description" content="Questions about CurenexAI, Kent repertory workflows, rubrics AI, and web-based alternatives to RadarOpus.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/image/CURENEXAI ICON.png">
    <link rel="apple-touch-icon" href="assets/image/CURENEXAI ICON.png">
    <meta name="theme-color" content="#14b8a6">
    
    <!-- FAQ Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "How do I create an account?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Creating an account is simple: Click 'Get Started' or 'Register' on the homepage, fill in your details including name, email, and medical credentials, enter your medical registration number and state council, verify your email with the OTP sent to you, and you're ready to start using the platform!"
                }
            },
            {
                "@type": "Question",
                "name": "How do I reset my password?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "To reset your password: Click 'Forgot Password' on the login page, enter your registered email address, you'll receive an OTP on your email, enter the OTP and create a new password."
                }
            },
            {
                "@type": "Question",
                "name": "How many patients can I add?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "There is no limit to the number of patients you can add. You can manage as many patients as you need for your practice."
                }
            },
            {
                "@type": "Question",
                "name": "Can other doctors see my patient records?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "No, absolutely not. Your patient data is completely isolated and private. Each doctor can only see patients they have created. We implement strict data isolation to ensure patient privacy and data security."
                }
            },
            {
                "@type": "Question",
                "name": "How does the AI suggestion feature work?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Our AI system analyzes the symptoms and patient information you provide. It uses advanced language models (Gemini AI) to suggest potential remedies based on homeopathic principles. The AI considers chief complaints and symptoms, constitutional factors, modalities (what makes symptoms better/worse), and mental and emotional symptoms. AI suggestions are for reference only and should always be verified by the practitioner."
                }
            },
            {
                "@type": "Question",
                "name": "Does CurenexAI support Kent repertory and rubrics AI workflows?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. CurenexAI supports digital repertory workflows with rubric exploration, Kent repertory style navigation, and AI-assisted analysis. Users who search for digital reperatory, rubics AI, or rubic automatic AI are generally looking for this kind of workflow."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI a web-based alternative to RadarOpus?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI is a web-based homeopathy AI platform for doctors who want digital repertory, patient management, prescriptions, and AI-assisted case analysis in one system. Clinics comparing CurenexAI with RadarOpus should choose based on workflow needs, but CurenexAI is built as a modern browser-based alternative for practices that want integrated AI and online access."
                }
            },
            {
                "@type": "Question",
                "name": "Is my patient data shared with the AI?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "When using AI features, only relevant symptoms and clinical information are sent for analysis. No patient identifying information (name, contact details, etc.) is included in AI requests. The data is processed in real-time and not stored by the AI provider."
                }
            },
            {
                "@type": "Question",
                "name": "What is the Diagnosis feature?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "The Diagnosis feature helps practitioners analyze symptoms and find matching remedies using AI-powered analysis of homeopathic repertories and materia medica."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI free to use?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI offers both free and premium features. Basic features are free for all registered practitioners. Advanced AI features and premium tools may require a subscription."
                }
            },
            {
                "@type": "Question",
                "name": "Is my data secure on CurenexAI?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, we take data security very seriously. All data is encrypted in transit and at rest. We use industry-standard security practices and comply with healthcare data protection regulations."
                }
            },
            {
                "@type": "Question",
                "name": "How do I contact support?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "You can reach our support team through the Support page on our website, or by emailing support@curenexai.com. We typically respond within 24-48 hours."
                }
            }
        ]
    }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <style>@font-face{font-family:'Font Awesome 6 Brands';font-display:swap}@font-face{font-family:'Font Awesome 6 Free';font-display:swap}@font-face{font-family:'Font Awesome 6 Solid';font-display:swap}</style>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    
    <style>
        .faq-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .faq-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .faq-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .faq-header p {
            color: #6b7280;
            font-size: 1.1rem;
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
        
        .faq-search {
            max-width: 500px;
            margin: 0 auto 40px;
            position: relative;
        }
        
        .faq-search input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .faq-search input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        .faq-search i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
        }
        
        .faq-categories {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 40px;
        }
        
        .faq-category {
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 30px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #4b5563;
        }
        
        .faq-category:hover,
        .faq-category.active {
            border-color: #6366f1;
            background: #6366f1;
            color: white;
        }
        
        .faq-section {
            margin-bottom: 40px;
        }
        
        .faq-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .faq-section-title i {
            color: #6366f1;
        }
        
        .faq-item {
            background: white;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #f3f4f6;
        }
        
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .faq-question:hover {
            background: #f9fafb;
        }
        
        .faq-question h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            flex: 1;
            padding-right: 15px;
        }
        
        .faq-toggle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        .faq-item.active .faq-toggle {
            background: #6366f1;
            color: white;
            transform: rotate(180deg);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .faq-item.active .faq-answer {
            max-height: 500px;
        }
        
        .faq-answer-content {
            padding: 0 25px 25px;
            color: #4b5563;
            line-height: 1.8;
        }
        
        .faq-answer-content p {
            margin: 0 0 15px;
        }
        
        .faq-answer-content p:last-child {
            margin-bottom: 0;
        }
        
        .faq-answer-content ul {
            margin: 10px 0;
            padding-left: 25px;
        }
        
        .faq-answer-content li {
            margin-bottom: 8px;
        }
        
        .faq-answer-content a {
            color: #6366f1;
            text-decoration: none;
        }
        
        .faq-answer-content a:hover {
            text-decoration: underline;
        }
        
        .contact-banner {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            color: white;
            margin-top: 50px;
        }
        
        .contact-banner h3 {
            margin: 0 0 10px;
            font-size: 1.5rem;
        }
        
        .contact-banner p {
            margin: 0 0 25px;
            opacity: 0.9;
        }
        
        .contact-banner .btn {
            background: white;
            color: #6366f1;
        }
        
        .contact-banner .btn:hover {
            background: #f3f4f6;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            display: none;
        }
        
        .no-results i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .faq-header h1 {
                font-size: 1.8rem;
            }
            
            .faq-question {
                padding: 15px 20px;
            }
            
            .faq-answer-content {
                padding: 0 20px 20px;
            }
            
            .contact-banner {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="faq-container">
        <div class="back-nav">
            <a href="<?php echo APP_URL; ?>"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <div class="faq-header">
            <h1><i class="fas fa-question-circle"></i> Frequently Asked Questions</h1>
            <p>Find answers to common questions about <?php echo APP_NAME; ?></p>
        </div>
        
        <div class="faq-search">
            <i class="fas fa-search"></i>
            <input type="text" id="faqSearch" placeholder="Search for answers...">
        </div>
        
        <div class="faq-categories">
            <button class="faq-category active" data-category="all">All</button>
            <button class="faq-category" data-category="account">Account</button>
            <button class="faq-category" data-category="patients">Patients</button>
            <button class="faq-category" data-category="prescriptions">Prescriptions</button>
            <button class="faq-category" data-category="ai">AI Features</button>
            <button class="faq-category" data-category="diagnosis">Diagnosis</button>
            <button class="faq-category" data-category="dermo">Dermo</button>
            <button class="faq-category" data-category="security">Security</button>
            <button class="faq-category" data-category="billing">Billing</button>
        </div>
        
        <div class="no-results" id="noResults">
            <i class="fas fa-search"></i>
            <h3>No results found</h3>
            <p>Try a different search term or browse by category</p>
        </div>
        
        <!-- Account & Registration FAQs -->
        <div class="faq-section" data-category="account">
            <h2 class="faq-section-title"><i class="fas fa-user-circle"></i> Account & Registration</h2>
            
            <div class="faq-item" data-category="account">
                <div class="faq-question">
                    <h3>How do I create an account?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Creating an account is simple:</p>
                        <ul>
                            <li>Click "Get Started" or "Register" on the homepage</li>
                            <li>Fill in your details including name, email, and medical credentials</li>
                            <li>Enter your medical registration number and state council</li>
                            <li>Verify your email with the OTP sent to you</li>
                            <li>You're ready to start using the platform!</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="account">
                <div class="faq-question">
                    <h3>I didn't receive the verification email. What should I do?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>If you haven't received the verification email:</p>
                        <ul>
                            <li>Check your spam/junk folder</li>
                            <li>Make sure you entered the correct email address</li>
                            <li>Wait a few minutes and try again using "Resend OTP"</li>
                            <li>If the issue persists, contact our <a href="<?php echo APP_URL; ?>/support.php">support team</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="account">
                <div class="faq-question">
                    <h3>How do I reset my password?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>To reset your password:</p>
                        <ul>
                            <li>Click "Forgot Password" on the login page</li>
                            <li>Enter your registered email address</li>
                            <li>You'll receive an OTP on your email</li>
                            <li>Enter the OTP and create a new password</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="account">
                <div class="faq-question">
                    <h3>Can I change my email address?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Yes, you can change your email address from your profile settings. For security reasons, you'll need to verify the new email address before the change takes effect. Go to Settings > Profile > Update Email.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="account">
                <div class="faq-question">
                    <h3>How do I delete my account?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>To delete your account, please contact our support team via the <a href="<?php echo APP_URL; ?>/support.php">Support Page</a>. Please note that:</p>
                        <ul>
                            <li>All your data including patient records will be permanently deleted</li>
                            <li>This action cannot be undone</li>
                            <li>We recommend saving any important records before requesting deletion</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Patient Management FAQs -->
        <div class="faq-section" data-category="patients">
            <h2 class="faq-section-title"><i class="fas fa-users"></i> Patient Management</h2>
            
            <div class="faq-item" data-category="patients">
                <div class="faq-question">
                    <h3>How many patients can I add?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>There is no limit to the number of patients you can add. You can manage as many patients as you need for your practice.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="patients">
                <div class="faq-question">
                    <h3>Can other doctors see my patient records?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>No, absolutely not.</strong> Your patient data is completely isolated and private. Each doctor can only see patients they have created. We implement strict data isolation to ensure patient privacy and data security.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="patients">
                <div class="faq-question">
                    <h3>Can I export my patient data?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Data export features are being developed. Currently, you can print prescriptions and consultation records. For bulk data export requests, please contact our support team who can assist you with exporting your data.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="patients">
                <div class="faq-question">
                    <h3>How do I search for a specific patient?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>You can search for patients using the search bar on the patient list page. Search by:</p>
                        <ul>
                            <li>Patient name</li>
                            <li>Phone number</li>
                            <li>Email address</li>
                            <li>Patient ID</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Prescriptions FAQs -->
        <div class="faq-section" data-category="prescriptions">
            <h2 class="faq-section-title"><i class="fas fa-prescription"></i> Prescriptions</h2>
            
            <div class="faq-item" data-category="prescriptions">
                <div class="faq-question">
                    <h3>How do I create a prescription?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>To create a prescription:</p>
                        <ul>
                            <li>Select a patient or create a consultation first</li>
                            <li>Click "New Prescription"</li>
                            <li>Add remedies with potency and dosage</li>
                            <li>Include any dietary or lifestyle instructions</li>
                            <li>Save and print or share the prescription</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="prescriptions">
                <div class="faq-question">
                    <h3>Can I edit a prescription after saving?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Yes, you can edit prescriptions. However, we recommend creating a new prescription if significant changes are needed to maintain proper medical records. Edited prescriptions will show a modification timestamp.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="prescriptions">
                <div class="faq-question">
                    <h3>How do I print a prescription?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>From the prescription view page, click the "Print" button. This will generate a professionally formatted prescription that you can print directly or save as PDF.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI Features FAQs -->
        <div class="faq-section" data-category="ai">
            <h2 class="faq-section-title"><i class="fas fa-robot"></i> AI Features</h2>
            
            <div class="faq-item" data-category="ai">
                <div class="faq-question">
                    <h3>How does the AI suggestion feature work?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Our AI system analyzes the symptoms and patient information you provide. It uses advanced language models (Gemini AI) to suggest potential remedies based on homeopathic principles. The AI considers:</p>
                        <ul>
                            <li>Chief complaints and symptoms</li>
                            <li>Constitutional factors</li>
                            <li>Modalities (what makes symptoms better/worse)</li>
                            <li>Mental and emotional symptoms</li>
                        </ul>
                        <p><strong>Important:</strong> AI suggestions are for reference only and should always be verified by the practitioner.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="ai">
                <div class="faq-question">
                    <h3>Is my patient data shared with the AI?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>When using AI features, only relevant symptoms and clinical information are sent for analysis. <strong>No patient identifying information</strong> (name, contact details, etc.) is included in AI requests. The data is processed in real-time and not stored by the AI provider.</p>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-category="ai">
                <div class="faq-question">
                    <h3>Does CurenexAI support Kent repertory and rubrics AI workflows?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>Yes.</strong> CurenexAI is built for digital repertory work with fast rubric exploration, AI-assisted remedy reasoning, and Kent repertory style navigation for modern clinics.</p>
                        <p>Users who search for <strong>digital reperatory</strong>, <strong>rubics AI</strong>, <strong>rubric AI</strong>, or <strong>rubic automatic AI</strong> are usually looking for this combination of repertory search plus AI-assisted case analysis.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="ai">
                <div class="faq-question">
                    <h3>Can I rely solely on AI suggestions for treatment?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>No.</strong> AI suggestions are meant to assist and not replace your professional judgment. The AI is a decision support tool. Always:</p>
                        <ul>
                            <li>Verify suggestions using your knowledge and experience</li>
                            <li>Cross-reference with repertory and materia medica</li>
                            <li>Consider the complete case before prescribing</li>
                            <li>Use your clinical expertise for final decisions</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="ai">
                <div class="faq-question">
                    <h3>The AI gave incorrect suggestions. What should I do?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>AI suggestions may not always be accurate. If you receive incorrect suggestions:</p>
                        <ul>
                            <li>Try providing more detailed or specific symptoms</li>
                            <li>Report the issue through our feedback system</li>
                            <li>Use the repertory and your expertise for verification</li>
                            <li>Your feedback helps us improve the AI system</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Disease Diagnosis FAQs -->
        <div class="faq-section" data-category="diagnosis">
            <h2 class="faq-section-title"><i class="fas fa-diagnoses"></i> Disease Diagnosis</h2>
            
            <div class="faq-item" data-category="diagnosis">
                <div class="faq-question">
                    <h3>What is the Disease Diagnosis feature?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>The Disease Diagnosis feature is a RAG (Retrieval-Augmented Generation) based diagnostic tool that uses our local medical database to suggest possible diagnoses based on:</p>
                        <ul>
                            <li>Patient symptoms</li>
                            <li>Chief complaints</li>
                            <li>Physical examination findings</li>
                            <li>Lab test results</li>
                        </ul>
                        <p>It provides a list of possible diagnoses ranked by confidence level to assist in your clinical decision-making.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="diagnosis">
                <div class="faq-question">
                    <h3>How accurate is the Disease Diagnosis tool?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>Important:</strong> The Disease Diagnosis tool is a <strong>decision support tool</strong>, not a definitive diagnostic system. It provides suggestions based on pattern matching with our medical database.</p>
                        <ul>
                            <li>Accuracy depends on the completeness of symptoms entered</li>
                            <li>More detailed input leads to better suggestions</li>
                            <li>Always verify diagnoses with your clinical expertise</li>
                            <li>Use it as one of many tools in your diagnostic process</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-category="diagnosis">
                <div class="faq-question">
                    <h3>Is CurenexAI a web-based alternative to RadarOpus?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>CurenexAI and RadarOpus are different products, but many doctors compare them when choosing repertory software.</p>
                        <p><strong>CurenexAI is designed for clinics that want a web-based workflow</strong> with digital repertory, AI-assisted case analysis, patient management, and digital prescriptions in one platform. If you are searching for something better than RadarOpus for a browser-first, AI-enabled workflow, CurenexAI is the relevant option to evaluate.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="diagnosis">
                <div class="faq-question">
                    <h3>Does the Diagnosis tool use external AI?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>No.</strong> Unlike the AI Remedy Suggestions which use Gemini AI, the Disease Diagnosis feature works entirely with our <strong>local medical database</strong>. This means:</p>
                        <ul>
                            <li>No patient data is sent to external servers for diagnosis</li>
                            <li>Works offline once the database is loaded</li>
                            <li>Faster response times</li>
                            <li>Complete data privacy for diagnostic queries</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="diagnosis">
                <div class="faq-question">
                    <h3>How do I get the best results from the Diagnosis tool?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>For best diagnostic suggestions:</p>
                        <ul>
                            <li><strong>Be specific:</strong> Include detailed symptom descriptions</li>
                            <li><strong>Add duration:</strong> Mention how long symptoms have been present</li>
                            <li><strong>Include lab results:</strong> Add any relevant test results</li>
                            <li><strong>Physical findings:</strong> Document examination observations</li>
                            <li><strong>Use medical terminology:</strong> The system recognizes standard medical terms</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dermo Skin Analysis FAQs -->
        <div class="faq-section" data-category="dermo">
            <h2 class="faq-section-title"><i class="fas fa-hand-holding-medical"></i> Dermo - Skin Analysis</h2>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>What is Dermo?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Dermo is our AI-powered skin condition analysis tool. It allows you to:</p>
                        <ul>
                            <li><strong>Upload Images:</strong> Upload skin images for analysis</li>
                            <li><strong>Live Camera:</strong> Capture skin conditions directly using your device camera</li>
                            <li><strong>AI Analysis:</strong> Get visual analysis powered by Gemini AI</li>
                            <li><strong>RAG Remedies:</strong> Receive homeopathic remedy suggestions from our database</li>
                        </ul>
                        <p>Dermo combines AI visual analysis with our comprehensive homeopathic remedy database to suggest suitable remedies for skin conditions.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>How do I use Dermo?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Using Dermo is simple:</p>
                        <ul>
                            <li>Navigate to Dermo from the dashboard or sidebar menu</li>
                            <li>Choose either "Upload Image" or "Live Camera" mode</li>
                            <li>Select the affected body area (face, hands, back, etc.)</li>
                            <li>Describe the symptoms (itching, burning, color changes, etc.)</li>
                            <li>Optionally select a patient to save the analysis</li>
                            <li>Choose analysis mode: AI + RAG or RAG Only (faster)</li>
                            <li>Submit for analysis</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>What's the difference between AI + RAG and RAG Only modes?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>AI + RAG Mode:</strong></p>
                        <ul>
                            <li>Uses Gemini AI to analyze the skin image visually</li>
                            <li>AI identifies the condition, characteristics, and severity</li>
                            <li>Combines AI analysis with RAG database matching</li>
                            <li>Provides comprehensive analysis but takes longer</li>
                        </ul>
                        <p><strong>RAG Only Mode:</strong></p>
                        <ul>
                            <li>Uses pattern matching with symptoms you describe</li>
                            <li>Matches against our local homeopathic database</li>
                            <li>Faster processing (no external API calls)</li>
                            <li>Complete privacy - no image sent externally</li>
                            <li>Best when you already know the condition</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>Are skin images stored or shared?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>Storage:</strong> Skin images are stored on our secure servers if you have selected a patient. They are linked to the patient's record for your future reference.</p>
                        <p><strong>AI Processing:</strong> When using AI + RAG mode, the image is sent to Google's Gemini AI for visual analysis. However:</p>
                        <ul>
                            <li>No patient identifying information is included</li>
                            <li>Google does not store the images</li>
                            <li>Images are processed in real-time and discarded</li>
                        </ul>
                        <p><strong>RAG Only Mode:</strong> In this mode, images are only stored locally and not sent to any external service.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>How do I get best results from Dermo?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>For accurate skin analysis:</p>
                        <ul>
                            <li><strong>Good Lighting:</strong> Use natural daylight or bright, even lighting</li>
                            <li><strong>Clear Focus:</strong> Ensure the affected area is in sharp focus</li>
                            <li><strong>Close-up:</strong> Capture the condition clearly, not too far away</li>
                            <li><strong>Context:</strong> Include some surrounding healthy skin for comparison</li>
                            <li><strong>Describe Symptoms:</strong> Add details like itching, burning, duration, triggers</li>
                            <li><strong>Select Correct Area:</strong> Choose the appropriate body part</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>Can I use Dermo on mobile devices?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>Yes!</strong> Dermo is fully responsive and works on mobile devices:</p>
                        <ul>
                            <li>Upload images from your phone's gallery</li>
                            <li>Use the live camera feature to capture directly</li>
                            <li>Switch between front and back cameras</li>
                            <li>Touch-friendly interface optimized for mobile</li>
                        </ul>
                        <p>Mobile devices are particularly useful for capturing skin conditions in real-time during consultations.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="dermo">
                <div class="faq-question">
                    <h3>Is Dermo analysis accurate?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>Important:</strong> Dermo is a <strong>decision support tool</strong>, not a diagnostic system. The analysis:</p>
                        <ul>
                            <li>Provides suggestions based on visual patterns and symptom matching</li>
                            <li>Should be verified with your clinical expertise</li>
                            <li>Does not replace professional dermatological examination</li>
                            <li>Accuracy depends on image quality and symptom description</li>
                            <li>Use as one of many tools in your diagnostic process</li>
                        </ul>
                        <p>Always apply your clinical judgment before prescribing remedies.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security FAQs -->
        <div class="faq-section" data-category="security">
            <h2 class="faq-section-title"><i class="fas fa-shield-alt"></i> Security & Privacy</h2>
            
            <div class="faq-item" data-category="security">
                <div class="faq-question">
                    <h3>Why am I seeing "Too many login attempts" error?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>This error appears when there have been too many failed login attempts <strong>for your specific account</strong>. This is a security feature to protect your account from unauthorized access attempts.</p>
                        <p><strong>Important:</strong> This lockout only affects your account. Other users can still log in normally even if you're locked out.</p>
                        <p>To resolve this:</p>
                        <ul>
                            <li>Wait for the specified time (usually a few minutes) before trying again</li>
                            <li>Make sure you're entering the correct email and password</li>
                            <li>Use the "Forgot Password" option to reset your password if needed</li>
                            <li>Contact support if you continue to have issues</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="security">
                <div class="faq-question">
                    <h3>If someone tries to hack my account, will it affect other users?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>No.</strong> Our rate limiting is implemented per account, not per IP address. This means:</p>
                        <ul>
                            <li>If someone enters wrong passwords for your account, only your account gets temporarily locked</li>
                            <li>Other doctors using the same network (like in a clinic) can still log in to their own accounts</li>
                            <li>Each user's login attempts are tracked separately</li>
                        </ul>
                        <p>This approach protects your account while ensuring other users aren't affected by someone else's failed login attempts.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="security">
                <div class="faq-question">
                    <h3>How is my data protected?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>We implement multiple layers of security to protect your data:</p>
                        <ul>
                            <li><strong>Encryption:</strong> All data is encrypted in transit (HTTPS/TLS) and at rest</li>
                            <li><strong>Password Security:</strong> Passwords are hashed using bcrypt with high cost factor</li>
                            <li><strong>Account Protection:</strong> Per-account rate limiting prevents brute force attacks</li>
                            <li><strong>Session Security:</strong> Secure session handling with automatic expiry</li>
                            <li><strong>Data Isolation:</strong> Each doctor's patient data is completely isolated</li>
                            <li><strong>CSRF Protection:</strong> Token-based protection against cross-site attacks</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="security">
                <div class="faq-question">
                    <h3>Can I be logged in on multiple devices?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>For security reasons, when you log in on a new device, your previous sessions on other devices will be automatically logged out. This ensures:</p>
                        <ul>
                            <li>Only one active session at a time for better security</li>
                            <li>Protection against unauthorized access if you forget to log out</li>
                            <li>Clear visibility of who is accessing your account</li>
                        </ul>
                        <p>Use the "Remember Me" option for convenience on your trusted devices.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Billing FAQs -->
        <div class="faq-section" data-category="billing">
            <h2 class="faq-section-title"><i class="fas fa-credit-card"></i> Billing & Pricing</h2>
            
            <div class="faq-item" data-category="billing">
                <div class="faq-question">
                    <h3>Is <?php echo APP_NAME; ?> free to use?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong><?php echo APP_NAME; ?> is currently available as a FREE BETA VERSION.</strong> During this beta period, all features are provided at no cost to help homeopathic practitioners manage their practice efficiently.</p>
                        <p><strong>Important:</strong> This application may transition to a paid subscription model in the future. We will provide advance notice (at least 30 days) to all registered users before implementing any pricing changes.</p>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="billing">
                <div class="faq-question">
                    <h3>Will there be paid plans in the future?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p><strong>Yes, this application may become a paid service in the future.</strong> The current beta version is free, but we plan to introduce subscription plans as the platform matures and more features are added.</p>
                        <p>What to expect:</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>Different pricing tiers may be introduced for various feature sets</li>
                            <li>Current beta users will be notified at least 30 days before any pricing changes</li>
                            <li>We may offer special pricing or benefits to early beta users</li>
                            <li>All pricing details will be transparently communicated before implementation</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="faq-item" data-category="billing">
                <div class="faq-question">
                    <h3>Is there a clinic or multi-user plan?</h3>
                    <div class="faq-toggle"><i class="fas fa-chevron-down"></i></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>We're working on clinic and multi-user plans for larger practices. If you're interested in such features, please <a href="<?php echo APP_URL; ?>/support.php">contact us</a> and we'll notify you when these options become available.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Banner -->
        <div class="contact-banner">
            <h3>Still have questions?</h3>
            <p>Can't find what you're looking for? Our support team is here to help!</p>
            <a href="<?php echo APP_URL; ?>/support.php" class="btn btn-lg">
                <i class="fas fa-headset"></i> Contact Support
            </a>
        </div>
    </div>
    
    <script>
        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const item = question.parentElement;
                const wasActive = item.classList.contains('active');
                
                // Close all items
                document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
                
                // Open clicked item if it wasn't active
                if (!wasActive) {
                    item.classList.add('active');
                }
            });
        });
        
        // Category Filter
        document.querySelectorAll('.faq-category').forEach(btn => {
            btn.addEventListener('click', () => {
                const category = btn.dataset.category;
                
                // Update active button
                document.querySelectorAll('.faq-category').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Filter sections
                if (category === 'all') {
                    document.querySelectorAll('.faq-section').forEach(s => s.style.display = 'block');
                    document.querySelectorAll('.faq-item').forEach(i => i.style.display = 'block');
                } else {
                    document.querySelectorAll('.faq-section').forEach(s => {
                        s.style.display = s.dataset.category === category ? 'block' : 'none';
                    });
                }
                
                document.getElementById('noResults').style.display = 'none';
            });
        });
        
        // Search
        document.getElementById('faqSearch').addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let hasResults = false;
            
            // Reset category filter
            document.querySelectorAll('.faq-category').forEach(b => b.classList.remove('active'));
            document.querySelector('.faq-category[data-category="all"]').classList.add('active');
            document.querySelectorAll('.faq-section').forEach(s => s.style.display = 'block');
            
            document.querySelectorAll('.faq-item').forEach(item => {
                const question = item.querySelector('h3').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer-content').textContent.toLowerCase();
                
                if (query === '' || question.includes(query) || answer.includes(query)) {
                    item.style.display = 'block';
                    hasResults = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide sections based on visible items
            document.querySelectorAll('.faq-section').forEach(section => {
                const visibleItems = section.querySelectorAll('.faq-item[style="display: block;"]').length;
                section.style.display = visibleItems > 0 ? 'block' : 'none';
            });
            
            document.getElementById('noResults').style.display = hasResults ? 'none' : 'block';
        });
    </script>
</body>
</html>
