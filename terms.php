<?php
/**
 * Terms and Conditions Page - Curenex / CurenexAI
 */
require_once 'includes/init.php';

$pageTitle = 'Terms and Conditions';
$bodyClass = 'legal-page';
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
    
    <!-- SEO Meta Tags - Curenex -->
    <title>Terms and Conditions - CurenexAI - AI Healthcare Platform</title>
    <meta name="title" content="Terms and Conditions - CurenexAI">
    <meta name="description" content="Terms and Conditions for Curenex (CurenexAI) - Read our terms of service for using the AI-powered homeopathic healthcare platform.">
    <meta name="keywords" content="Curenex terms, CurenexAI conditions, terms of service, homeopathy terms, healthcare terms, Curenex AI, curenex, curenexai">
    <meta name="author" content="CurenexAI">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://curenexai.com/terms.php">
    
    <!-- Open Graph -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://curenexai.com/terms.php">
    <meta property="og:title" content="Terms and Conditions - CurenexAI">
    <meta property="og:description" content="Terms of service for Curenex AI healthcare platform.">
    <meta property="og:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta property="og:site_name" content="Curenex">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/image/CURENEXAI ICON.png">
    <link rel="apple-touch-icon" href="assets/image/CURENEXAI ICON.png">
    <meta name="theme-color" content="#14b8a6">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <style>@font-face{font-family:'Font Awesome 6 Brands';font-display:swap}@font-face{font-family:'Font Awesome 6 Free';font-display:swap}@font-face{font-family:'Font Awesome 6 Solid';font-display:swap}</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .legal-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .legal-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .legal-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .legal-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .legal-header .last-updated {
            display: inline-block;
            background: #f3f4f6;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #4b5563;
            margin-top: 15px;
        }
        
        .legal-content {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .legal-content h2 {
            color: #1f2937;
            font-size: 1.4rem;
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .legal-content h2:first-child {
            margin-top: 0;
        }
        
        .legal-content p, .legal-content li {
            color: #4b5563;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        
        .legal-content ul {
            padding-left: 25px;
            margin-bottom: 20px;
        }
        
        .legal-content li {
            margin-bottom: 10px;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin: 25px 0;
        }
        
        .highlight-box p {
            margin: 0;
            color: #92400e;
        }
        
        .warning-box {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin: 25px 0;
        }
        
        .warning-box p {
            margin: 0;
            color: #991b1b;
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
        
        .contact-info {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .contact-info h3 {
            margin: 0 0 15px;
            color: #1f2937;
        }
        
        .contact-info p {
            margin: 5px 0;
        }
        
        @media (max-width: 768px) {
            .legal-content {
                padding: 25px 20px;
            }
            
            .legal-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="legal-container">
        <div class="back-nav">
            <a href="<?php echo APP_URL; ?>"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <div class="legal-header">
            <h1><i class="fas fa-file-contract"></i> Terms and Conditions</h1>
            <p>Please read these terms carefully before using <?php echo APP_NAME; ?></p>
            <span class="last-updated"><i class="fas fa-calendar-alt"></i> Last Updated: <?php echo date('F d, Y'); ?></span>
        </div>
        
        <div class="legal-content">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using <?php echo APP_NAME; ?> ("the Platform"), you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. If you do not agree to these terms, please do not use our services.</p>
            
            <div class="highlight-box">
                <p><strong><i class="fas fa-flask"></i> Beta Version Notice:</strong> <?php echo APP_NAME; ?> is currently available as a <strong>FREE BETA VERSION</strong>. During this beta period, all features are provided at no cost. Please note that this application may transition to a paid subscription model in the future. We will provide advance notice of any pricing changes to all registered users. By using this service, you acknowledge and accept that pricing terms may change.</p>
            </div>
            
            <h2>2. Beta Service & Future Pricing</h2>
            <p>The current beta version is provided free of charge. However:</p>
            <ul>
                <li>This application may become a paid service in the future</li>
                <li>Premium features or subscription plans may be introduced</li>
                <li>Existing users will receive at least 30 days notice before any pricing changes</li>
                <li>Core functionality access during beta does not guarantee free access after the beta period ends</li>
                <li>We reserve the right to introduce different pricing tiers for different feature sets</li>
            </ul>
            
            <h2>3. Description of Service</h2>
            <p><?php echo APP_NAME; ?> is a Clinical Decision Support System designed specifically for homeopathic practitioners. The platform provides:</p>
            <ul>
                <li>Patient management and record keeping</li>
                <li>Consultation tracking and management</li>
                <li>AI-powered remedy suggestions</li>
                <li>RAG-based disease diagnosis using local medical database</li>
                <li>Dermo - AI-powered skin condition analysis with image/camera capture</li>
                <li>Digital repertory and materia medica</li>
                <li>Prescription generation</li>
                <li>Lab report analysis</li>
            </ul>
            
            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle"></i> Medical Disclaimer:</strong> This platform is a decision support tool and is NOT intended to replace professional medical judgment. All treatment decisions must be made by qualified healthcare professionals. The AI suggestions are for reference only and should be verified independently.</p>
            </div>
            
            <h2>4. User Eligibility</h2>
            <p>To use this platform, you must:</p>
            <ul>
                <li>Be a licensed homeopathic practitioner or medical professional</li>
                <li>Provide accurate registration information including valid credentials</li>
                <li>Be at least 18 years of age</li>
                <li>Accept responsibility for all activities under your account</li>
            </ul>
            
            <h2>5. User Account and Security</h2>
            <p>You are responsible for:</p>
            <ul>
                <li>Maintaining the confidentiality of your account credentials</li>
                <li>All activities that occur under your account</li>
                <li>Immediately notifying us of any unauthorized access</li>
                <li>Ensuring your account information remains accurate and up-to-date</li>
            </ul>
            
            <p><strong>Account Protection Features:</strong></p>
            <ul>
                <li>Per-account rate limiting: Failed login attempts only lock your specific account, not other users on the same network</li>
                <li>Single-device login: For security, logging in on a new device will log you out from other devices</li>
                <li>Automatic session expiry: Sessions expire after inactivity to protect your account</li>
            </ul>
            
            <h2>6. Patient Data and Privacy</h2>
            <p>As a healthcare application handling sensitive patient information:</p>
            <ul>
                <li>You must obtain proper consent from patients before storing their data</li>
                <li>Patient data is strictly confidential and only accessible to you</li>
                <li>We employ industry-standard encryption to protect stored data</li>
                <li>You are responsible for complying with local healthcare data regulations</li>
                <li>Patient data is isolated per doctor - no cross-access is permitted</li>
            </ul>
            
            <div class="info-box">
                <p><strong><i class="fas fa-shield-alt"></i> Data Protection:</strong> All patient data is encrypted at rest and in transit. Each doctor's data is completely isolated and inaccessible to other users.</p>
            </div>
            
            <h2>7. AI Suggestions Disclaimer</h2>
            <p>The AI-powered features of this platform:</p>
            <ul>
                <li>Are provided as reference tools only</li>
                <li>Should not be solely relied upon for diagnosis or treatment</li>
                <li>May contain errors or inaccuracies</li>
                <li>Must be verified by qualified practitioners before use</li>
                <li>Do not constitute medical advice</li>
            </ul>
            
            <h2>9. Disease Diagnosis Tool Disclaimer</h2>
            <p>The Disease Diagnosis feature uses RAG-based analysis with our local medical database:</p>
            <ul>
                <li>Provides diagnostic suggestions, NOT definitive diagnoses</li>
                <li>Works entirely with local data - no external AI processing</li>
                <li>Accuracy depends on the quality and completeness of input</li>
                <li>Should be used as one tool among many in your diagnostic process</li>
                <li>Final diagnostic decisions must be made by the qualified practitioner</li>
                <li>We are not liable for any diagnostic decisions made using this tool</li>
            </ul>
            
            <h2>9A. Dermo (Skin Analysis) Disclaimer</h2>
            <p>The Dermo skin analysis feature provides AI-powered skin condition analysis:</p>
            <ul>
                <li>Uses Gemini AI for visual analysis of skin images</li>
                <li>Combines AI analysis with RAG-based remedy suggestions from our database</li>
                <li>Skin images may be sent to Google's AI for analysis (no patient identifying information included)</li>
                <li>Provides suggestions, NOT definitive diagnoses</li>
                <li>Should not replace clinical examination or professional dermatological consultation</li>
                <li>Accuracy depends on image quality, lighting, and visibility of the condition</li>
                <li>Results are for educational and decision support purposes only</li>
                <li>We are not liable for any treatment decisions made using this tool</li>
            </ul>
            
            <p>You agree NOT to:</p>
            <ul>
                <li>Use the platform for any illegal purposes</li>
                <li>Share your account with unauthorized individuals</li>
                <li>Attempt to hack, exploit, or compromise system security</li>
                <li>Upload malicious content or files</li>
                <li>Misuse patient data or violate patient privacy</li>
                <li>Use automated systems to access the platform</li>
            </ul>
            
            <h2>10. Intellectual Property</h2>
            <p>All content, features, and functionality of <?php echo APP_NAME; ?> are owned by us and protected by intellectual property laws. You may not copy, modify, distribute, or create derivative works without explicit permission.</p>
            
            <h2>11. Limitation of Liability</h2>
            <p>To the maximum extent permitted by law:</p>
            <ul>
                <li>We are not liable for any medical decisions made using this platform</li>
                <li>We are not responsible for data loss due to user negligence</li>
                <li>We do not guarantee uninterrupted or error-free service</li>
                <li>Our liability is limited to the amount paid for the service</li>
            </ul>
            
            <div class="highlight-box">
                <p><strong><i class="fas fa-lightbulb"></i> Best Practice:</strong> Always maintain backup records and never rely solely on any digital system for critical patient information.</p>
            </div>
            
            <h2>12. Service Modifications</h2>
            <p>We reserve the right to:</p>
            <ul>
                <li>Modify or discontinue features without prior notice</li>
                <li>Update these terms at any time</li>
                <li>Suspend accounts that violate these terms</li>
                <li>Make changes to improve security and functionality</li>
            </ul>
            
            <h2>13. Termination</h2>
            <p>We may terminate your access if you:</p>
            <ul>
                <li>Violate any of these terms</li>
                <li>Engage in fraudulent activities</li>
                <li>Compromise other users' data or security</li>
            </ul>
            <p>Upon termination, your data may be retained for legal compliance purposes as per applicable regulations.</p>
            
            <h2>14. Feedback and Contributions</h2>
            <p>We encourage users to provide feedback, report bugs, and suggest features. By submitting feedback:</p>
            <ul>
                <li>You grant us the right to use and implement your suggestions</li>
                <li>Valuable contributions may be recognized with gratitude posts</li>
                <li>No compensation is provided for implemented suggestions</li>
            </ul>
            
            <h2>15. Governing Law</h2>
            <p>These terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Kerala, India.</p>
            
            <h2>16. Contact Information</h2>
            <div class="contact-info">
                <h3><i class="fas fa-envelope"></i> Contact Us</h3>
                <p>For questions about these Terms and Conditions:</p>
                <p><strong>Email:</strong> support@homeopathicassistant.com</p>
                <p><strong>Support Page:</strong> <a href="<?php echo APP_URL; ?>/support.php">Visit Support</a></p>
            </div>
            
            <h2>17. Acknowledgment</h2>
            <p>By using <?php echo APP_NAME; ?>, you acknowledge that you have read these Terms and Conditions, understand them, and agree to be bound by them. If you do not agree to these terms, you must not use the platform.</p>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?php echo APP_URL; ?>/register.php" class="btn btn-primary btn-lg">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
            <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-outline btn-lg" style="margin-left: 15px;">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>
    </div>
</body>
</html>
