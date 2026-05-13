<?php
/**
 * Privacy Policy Page - Curenex / CurenexAI
 */
require_once 'includes/init.php';

$pageTitle = 'Privacy Policy';
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
    <title>Privacy Policy - CurenexAI - Data Protection & Security</title>
    <meta name="title" content="Privacy Policy - CurenexAI">
    <meta name="description" content="Privacy Policy for Curenex (CurenexAI) - Learn how we protect your data on our AI-powered homeopathic healthcare platform. Your privacy is our priority.">
    <meta name="keywords" content="Curenex privacy, CurenexAI privacy policy, data protection, homeopathy privacy, healthcare data security, Curenex AI, curenex, curenexai">
    <meta name="author" content="CurenexAI">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://curenexai.com/privacy.php">
    
    <!-- Open Graph -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://curenexai.com/privacy.php">
    <meta property="og:title" content="Privacy Policy - CurenexAI">
    <meta property="og:description" content="How Curenex protects your data and privacy.">
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
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    
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
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-left: 4px solid #10b981;
            padding: 20px;
            border-radius: 0 12px 12px 0;
            margin: 25px 0;
        }
        
        .highlight-box p {
            margin: 0;
            color: #065f46;
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
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
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

    <!-- Organization sameAs (social profile signals) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "@id": "https://curenexai.com/#organization",
        "name": "CurenexAI",
        "url": "https://curenexai.com/",
        "logo": "https://curenexai.com/assets/image/CURENEXAI PNG.png",
        "sameAs": [
            "https://x.com/curenexai",
            "https://www.linkedin.com/company/curenexai",
            "https://www.instagram.com/curenexai"
        ]
    }
    </script>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="legal-container">
        <div class="back-nav">
            <a href="<?php echo APP_URL; ?>"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <div class="legal-header">
            <h1><i class="fas fa-user-shield"></i> Privacy Policy</h1>
            <p>Your privacy is important to us. This policy explains how we handle your data.</p>
            <span class="last-updated"><i class="fas fa-calendar-alt"></i> Last Updated: <?php echo date('F d, Y'); ?></span>
        </div>
        
        <div class="legal-content">
            <h2>1. Introduction</h2>
            <p><?php echo APP_NAME; ?> ("we", "our", "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our platform.</p>
            
            <div class="info-box">
                <p><strong><i class="fas fa-flask"></i> Beta Version:</strong> <?php echo APP_NAME; ?> is currently offered as a <strong>FREE BETA VERSION</strong>. This application may transition to a paid subscription model in the future. Your data handling and privacy protections will remain consistent regardless of any future pricing changes.</p>
            </div>
            
            <h2>2. Beta Service & Future Changes</h2>
            <p>As a beta service that may become paid in the future, please note:</p>
            <ul>
                <li>Your personal and patient data will be handled with the same care regardless of pricing model</li>
                <li>We may collect additional billing information if/when paid plans are introduced</li>
                <li>You will be notified of any changes to data collection practices related to payment processing</li>
                <li>Your data will not be sold or used differently based on your subscription status</li>
            </ul>
            
            <h2>3. Information We Collect</h2>
            <h3 style="color: #4b5563; margin: 20px 0 10px; font-size: 1.1rem;">3.1 Doctor Information</h3>
            <ul>
                <li>Full name and professional credentials</li>
                <li>Email address and phone number</li>
                <li>Medical registration number and state council</li>
                <li>Qualification details</li>
                <li>Profile picture (optional)</li>
            </ul>
            
            <h3 style="color: #4b5563; margin: 20px 0 10px; font-size: 1.1rem;">3.2 Patient Information (Collected by You)</h3>
            <ul>
                <li>Patient demographics (name, age, gender, contact)</li>
                <li>Medical history and symptoms</li>
                <li>Consultation records</li>
                <li>Prescriptions and treatment plans</li>
                <li>Lab reports and test results</li>
                <li>Skin images uploaded for Dermo analysis</li>
            </ul>
            
            <h3 style="color: #4b5563; margin: 20px 0 10px; font-size: 1.1rem;">3.3 Usage Information</h3>
            <ul>
                <li>Login timestamps and IP addresses</li>
                <li>Browser and device information</li>
                <li>Feature usage patterns</li>
            </ul>
            
            <div class="highlight-box">
                <p><strong><i class="fas fa-lock"></i> Data Isolation:</strong> Each doctor's patient data is completely isolated. You can only access data for patients you have created. No other user can view your patient records.</p>
            </div>
            
            <h2>4. How We Use Your Information</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Data Used</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Account Management</td>
                        <td>Email, name, credentials</td>
                    </tr>
                    <tr>
                        <td>Service Delivery</td>
                        <td>All platform features</td>
                    </tr>
                    <tr>
                        <td>AI Suggestions</td>
                        <td>Consultation data (anonymized)</td>
                    </tr>
                    <tr>
                        <td>Disease Diagnosis</td>
                        <td>Symptoms, clinical findings (local processing only)</td>
                    </tr>
                    <tr>
                        <td>Dermo Skin Analysis</td>
                        <td>Skin images, symptoms (AI + RAG processing)</td>
                    </tr>
                    <tr>
                        <td>Security</td>
                        <td>IP addresses, login data</td>
                    </tr>
                    <tr>
                        <td>Communication</td>
                        <td>Email for notifications</td>
                    </tr>
                </tbody>
            </table>
            
            <h2>5. Data Security</h2>
            <p>We implement industry-standard security measures:</p>
            <ul>
                <li><strong>Encryption:</strong> All data encrypted in transit (HTTPS/TLS) and at rest</li>
                <li><strong>Password Security:</strong> Passwords are hashed using bcrypt with high cost factor</li>
                <li><strong>Session Security:</strong> Secure session handling with regeneration and fingerprinting</li>
                <li><strong>Access Control:</strong> Strict role-based access with doctor isolation</li>
                <li><strong>Per-Account Rate Limiting:</strong> Protection against brute force attacks on individual accounts without affecting other users on the same network</li>
                <li><strong>CSRF Protection:</strong> Token-based protection against cross-site attacks</li>
            </ul>
            
            <h2>6. Data Sharing</h2>
            <p>We do NOT sell or share your data with third parties except:</p>
            <ul>
                <li>When required by law or legal process</li>
                <li>To protect our rights or safety of users</li>
                <li>With service providers essential to platform operation (under strict agreements)</li>
            </ul>
            
            <div class="info-box">
                <p><strong><i class="fas fa-robot"></i> AI Processing:</strong> When using AI features (remedy suggestions and Dermo skin analysis), relevant consultation data or skin images are sent to Google's Gemini AI for processing. This data is not stored by Google and is used solely for generating suggestions. No patient identifying information is included in AI requests.</p>
            </div>
            
            <h2>7. Data Retention</h2>
            <p>We retain your data:</p>
            <ul>
                <li><strong>Account Data:</strong> As long as your account is active</li>
                <li><strong>Patient Records:</strong> Until you delete them or close your account</li>
                <li><strong>Activity Logs:</strong> 90 days for security purposes</li>
                <li><strong>Backup Data:</strong> Up to 30 days after deletion</li>
            </ul>
            
            <h2>8. Your Rights</h2>
            <p>You have the right to:</p>
            <ul>
                <li><strong>Access:</strong> View all data we have about you</li>
                <li><strong>Correction:</strong> Update or correct your information</li>
                <li><strong>Deletion:</strong> Request deletion of your account and data</li>
                <li><strong>Data Portability:</strong> Request your data (contact support for export assistance)</li>
                <li><strong>Restrict:</strong> Limit how we process your data</li>
            </ul>
            
            <h2>9. Cookies and Tracking</h2>
            <p>We use minimal cookies for:</p>
            <ul>
                <li>Session management (essential for login)</li>
                <li>Security tokens (CSRF protection)</li>
                <li>User preferences (theme, settings)</li>
            </ul>

            <h3>Google Analytics</h3>
            <p>We use <strong>Google Analytics 4 (GA4)</strong> to understand how visitors interact with our public website (such as pages visited, time on page, device type, approximate geographic region, and referral source). This helps us improve the platform and content.</p>
            <ul>
                <li><strong>What is collected:</strong> Anonymous usage data such as page views, clicks, session duration, browser/device information, and IP-derived approximate location. IP addresses are anonymized by Google before storage.</li>
                <li><strong>What is NOT collected:</strong> We do <strong>not</strong> send any patient data, consultation details, prescriptions, or personally identifiable information (PII) to Google Analytics. Analytics is only loaded on public marketing pages and never within the clinical/patient management areas of the platform.</li>
                <li><strong>Cookies used:</strong> Google Analytics sets cookies (such as <code>_ga</code>, <code>_ga_*</code>) to distinguish unique users. These are first-party cookies under our domain.</li>
                <li><strong>Data sharing with Google:</strong> Data is processed by Google LLC under their <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy Policy</a> and <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">how Google uses information from sites that use their services</a>.</li>
                <li><strong>Opt-out:</strong> You can opt out of Google Analytics by installing the official <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener">Google Analytics Opt-out Browser Add-on</a>, or by enabling "Do Not Track" / blocking analytics cookies in your browser.</li>
            </ul>
            <p>By continuing to use this website, you acknowledge our use of Google Analytics as described above.</p>
            
            <h2>10. Children's Privacy</h2>
            <p>Our platform is intended for adult medical professionals only. We do not knowingly collect data from individuals under 18 years of age.</p>
            
            <h2>11. Changes to This Policy</h2>
            <p>We may update this Privacy Policy periodically. We will notify you of significant changes via email or platform notification. Continued use after changes constitutes acceptance of the updated policy.</p>
            
            <h2>12. Contact Us</h2>
            <p>For privacy-related inquiries:</p>
            <ul>
                <li><strong>Email:</strong> support@curenexai.com</li>
                <li><strong>Support Page:</strong> <a href="<?php echo APP_URL; ?>/support.php">Visit Support</a></li>
            </ul>
            
            <h2>13. Compliance</h2>
            <p>We strive to comply with applicable data protection laws including:</p>
            <ul>
                <li>Information Technology Act, 2000 (India)</li>
                <li>Personal Data Protection principles</li>
                <li>Healthcare data handling best practices</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?php echo APP_URL; ?>/terms.php" class="btn btn-outline btn-lg">
                <i class="fas fa-file-contract"></i> Terms & Conditions
            </a>
            <a href="<?php echo APP_URL; ?>" class="btn btn-primary btn-lg" style="margin-left: 15px;">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>
