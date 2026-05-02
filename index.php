<?php
require_once 'includes/init.php';
require_once 'includes/email_otp.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$contactSuccess = '';
$contactError = '';

/**
 * Send contact form notification email to admin
 */
function sendContactNotificationEmail($data) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 465;
        $mail->Port       = $smtpPort;
        $mail->SMTPSecure = ($smtpPort == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->Timeout    = 15;
        
        // Recipients - Send to admin email
        $adminEmail = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'admin@example.com';
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : $adminEmail;
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($adminEmail); // Send to admin
        $mail->addReplyTo($data['email'], $data['name']); // Reply goes to user
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = APP_NAME . ' - New Contact Form: ' . ucfirst($data['feedback_type']) . ' - ' . ($data['subject'] ?: 'No Subject');
        $mail->Body    = getContactEmailTemplate($data);
        $mail->AltBody = "New contact form submission:\n\nName: {$data['name']}\nEmail: {$data['email']}\nType: {$data['feedback_type']}\nSubject: {$data['subject']}\n\nMessage:\n{$data['message']}";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log('Contact form email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get HTML email template for contact form
 * Uses inline CSS for email client compatibility
 */
function getContactEmailTemplate($data) {
    $appName = APP_NAME;
    $date = date('F j, Y \a\t g:i A');
    $feedbackType = ucfirst($data['feedback_type']);
    $typeBadgeColor = match($data['feedback_type']) {
        'bug' => '#dc2626',
        'feature' => '#059669',
        'support' => '#d97706',
        default => '#6366f1'
    };
    
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 20px; background-color: #f3f4f6;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px;">
        <tr>
            <td style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff; padding: 25px; text-align: center; border-radius: 12px 12px 0 0;">
                <h1 style="margin: 0; font-size: 22px;">New Contact Form Submission</h1>
                <p style="margin: 8px 0 0; font-size: 14px;">Received on ' . $date . '</p>
                <span style="display: inline-block; padding: 4px 12px; background-color: ' . $typeBadgeColor . '; color: #ffffff; border-radius: 15px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 10px;">' . $feedbackType . '</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 25px;">
                <table cellpadding="8" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="font-weight: bold; color: #6b7280; width: 80px; vertical-align: top;">From:</td>
                        <td style="color: #1f2937;">' . htmlspecialchars($data['name']) . '</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #6b7280; vertical-align: top;">Email:</td>
                        <td><a href="mailto:' . htmlspecialchars($data['email']) . '" style="color: #6366f1;">' . htmlspecialchars($data['email']) . '</a></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; color: #6b7280; vertical-align: top;">Subject:</td>
                        <td style="color: #1f2937;">' . htmlspecialchars($data['subject']) . '</td>
                    </tr>
                </table>
                <div style="background-color: #f9fafb; padding: 15px; border-radius: 8px; border-left: 4px solid #6366f1; margin-top: 15px;">
                    <p style="margin: 0 0 8px; font-weight: bold; color: #6b7280; font-size: 12px; text-transform: uppercase;">Message</p>
                    <p style="margin: 0; color: #1f2937; white-space: pre-wrap;">' . nl2br(htmlspecialchars($data['message'])) . '</p>
                </div>
                <p style="text-align: center; margin-top: 20px;">
                    <a href="mailto:' . htmlspecialchars($data['email']) . '?subject=Re: ' . rawurlencode($data['subject']) . '" style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Reply to ' . htmlspecialchars($data['name']) . '</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #1f2937; color: #9ca3af; padding: 15px; text-align: center; font-size: 11px; border-radius: 0 0 12px 12px;">
                <p style="margin: 0;">This notification was sent from ' . $appName . ' contact form.</p>
                <p style="margin: 5px 0 0;">Feedback ID: #' . $data['id'] . ' | IP: ' . $data['ip'] . '</p>
            </td>
        </tr>
    </table>
</body>
</html>';
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $feedbackType = sanitize($_POST['feedback_type'] ?? 'general');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    if (empty($name) || empty($email) || empty($message)) {
        $contactError = 'Please fill in all required fields';
    } elseif (!isValidEmail($email)) {
        $contactError = 'Please enter a valid email address';
    } else {
        try {
            // Create feedback table if not exists
            DB::execute("CREATE TABLE IF NOT EXISTS feedback (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(255),
                message TEXT NOT NULL,
                feedback_type ENUM('general', 'bug', 'feature', 'support') DEFAULT 'general',
                status ENUM('new', 'read', 'responded') DEFAULT 'new',
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Add ip_address column if it doesn't exist (for existing tables)
            $columns = DB::query("SHOW COLUMNS FROM feedback LIKE 'ip_address'");
            if (empty($columns)) {
                DB::execute("ALTER TABLE feedback ADD COLUMN ip_address VARCHAR(45) AFTER status");
            }
            
            // Insert feedback using DB::insert which returns the last insert ID
            $feedbackId = DB::insert('feedback', [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
                'feedback_type' => $feedbackType,
                'ip_address' => $ipAddress
            ]);
            
            // Send email notification to admin
            $emailData = [
                'id' => $feedbackId,
                'name' => $name,
                'email' => $email,
                'subject' => $subject ?: 'No Subject',
                'message' => $message,
                'feedback_type' => $feedbackType,
                'ip' => $ipAddress
            ];
            
            $emailSent = sendContactNotificationEmail($emailData);
            
            if ($emailSent) {
                $_SESSION['contact_success'] = 'Thank you for your feedback! We have received your message and will get back to you soon.';
            } else {
                $_SESSION['contact_success'] = 'Thank you for your feedback! Your message has been saved and we will review it shortly.';
            }
            
            // PRG Pattern: Redirect to prevent duplicate submission on refresh
            header('Location: ' . APP_URL . '/#contact');
            exit;
        } catch (Exception $e) {
            error_log('Contact form error: ' . $e->getMessage());
            $_SESSION['contact_error'] = 'An error occurred. Please try again later.';
            header('Location: ' . APP_URL . '/#contact');
            exit;
        }
    }
}

// Get flash messages from session
if (isset($_SESSION['contact_success'])) {
    $contactSuccess = $_SESSION['contact_success'];
    unset($_SESSION['contact_success']);
}
if (isset($_SESSION['contact_error'])) {
    $contactError = $_SESSION['contact_error'];
    unset($_SESSION['contact_error']);
}

$pageTitle = 'Welcome';
$bodyClass = 'landing-page';
$htmlClass = 'landing-page-html';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $htmlClass; ?>" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#14b8a6">
    <meta name="msapplication-TileColor" content="#14b8a6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CurenexAI">
    <meta name="application-name" content="CurenexAI">
    
    <!-- Google tag (gtag.js) - Site Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-WDBLVKCVG1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-WDBLVKCVG1', { 'anonymize_ip': true });
    </script>

    <!-- PRIMARY SEO META TAGS - CURENEXAI BRAND -->
    <title>Digital Repertory Software for Homeopathy Doctors | CurenexAI</title>
    <meta name="title" content="Digital Repertory Software for Homeopathy Doctors | CurenexAI">
    <meta name="description" content="CurenexAI is AI homeopathy software with digital repertory, remedy suggestions, digital prescriptions, and patient management for homeopathic doctors.">
    <meta name="keywords" content="CurenexAI, Curenex AI, curenexai, curenex ai, CURENEXAI, Curenex, curenex, Curenex software, CurenexAI software, AI homeopathy software, homeopathic software, homeopathy practice management, digital repertory software, materia medica software, homeopathy prescription software, BHMS doctor software, MD homeopathy software, homeopathic clinic management, AI healthcare platform, homeopathy patient management, symptom checker homeopathy, remedy finder, homeopathic remedies database, alternative medicine software, natural medicine platform, holistic healthcare software, clinical decision support, intelligent diagnosis system, homeopathy app, free homeopathy software, best homeopathy software 2026">
    <meta name="author" content="CurenexAI Team">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="revisit-after" content="1 days">
    <meta name="language" content="English">
    <meta name="rating" content="general">
    <meta name="distribution" content="global">
    <meta name="coverage" content="worldwide">
    <meta name="target" content="all">
    <meta name="HandheldFriendly" content="True">
    <meta name="MobileOptimized" content="320">
    <meta name="format-detection" content="telephone=no">
    <meta name="category" content="Healthcare Software, Medical Software, Homeopathy">
    
    <!-- CANONICAL URL -->
    <link rel="canonical" href="https://curenexai.com/">
    <link rel="alternate" hreflang="en" href="https://curenexai.com/">
    <link rel="alternate" hreflang="x-default" href="https://curenexai.com/">
    
    <!-- OPEN GRAPH / FACEBOOK -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://curenexai.com/">
    <meta property="og:title" content="CurenexAI – Modern Clinical Homeopathic Doctors Platform | AI Remedy Suggestions & Repertory Search">
    <meta property="og:description" content="Modern clinical homeopathic platform for homeopathic doctors and practitioners. Get started with AI remedy suggestions, repertory search, digital prescriptions, patient management and integrated feedback &amp; support — free beta.">
    <meta property="og:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="CurenexAI - AI-Powered Homeopathic Healthcare Software Logo">
    <meta property="og:site_name" content="CurenexAI">
    <meta property="og:locale" content="en_US">
    <meta property="fb:app_id" content="">
    
    <!-- TWITTER CARD -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@curenexai">
    <meta name="twitter:creator" content="@curenexai">
    <meta name="twitter:url" content="https://curenexai.com/">
    <meta name="twitter:title" content="CurenexAI – Modern Clinical Homeopathic Doctors Platform">
    <meta name="twitter:description" content="Modern clinical platform for homeopathic doctors. Get started with AI remedy suggestions, repertory search and digital prescriptions. Free beta.">
    <meta name="twitter:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta name="twitter:image:alt" content="CurenexAI Logo">
    
    <!-- FAVICON & APP ICONS -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon-96x96.png">
    <link rel="shortcut icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo APP_URL; ?>/assets/image/favicon/apple-touch-icon.png">
    <link rel="manifest" href="<?php echo APP_URL; ?>/assets/image/favicon/site.webmanifest">
    
    <!-- SITEMAP -->
    <link rel="sitemap" type="application/xml" title="Sitemap" href="https://curenexai.com/sitemap.xml">
    
    <!-- SCHEMA.ORG STRUCTURED DATA - ORGANIZATION -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "@id": "https://curenexai.com/#organization",
        "name": "CurenexAI",
        "alternateName": ["Curenex AI", "Curenex", "CURENEXAI"],
        "url": "https://curenexai.com",
        "logo": {
            "@type": "ImageObject",
            "url": "https://curenexai.com/assets/image/CURENEXAI PNG.png",
            "width": 512,
            "height": 512
        },
        "image": "https://curenexai.com/assets/image/CURENEXAI PNG.png",
        "description": "CurenexAI is an AI-powered homeopathic healthcare software platform. NOT a skin medicine - CurenexAI is professional homeopathy practice management software for doctors.",
        "slogan": "Decode Health, Deliver Cure",
        "foundingDate": "2026",
        "areaServed": "Worldwide",
        "sameAs": [
            "https://twitter.com/curenexai",
            "https://www.linkedin.com/company/curenexai",
            "https://www.facebook.com/curenexai",
            "https://www.instagram.com/curenexai"
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer support",
            "url": "https://curenexai.com/support.php",
            "availableLanguage": "English"
        }
    }
    </script>
    
    <!-- SCHEMA.ORG STRUCTURED DATA - SOFTWARE APPLICATION -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "@id": "https://curenexai.com/#software",
        "name": "CurenexAI",
        "alternateName": ["Curenex AI", "CurenexAI Homeopathy Software"],
        "applicationCategory": "HealthApplication",
        "applicationSubCategory": "Medical Software",
        "operatingSystem": "Web Browser, Android, iOS",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD",
            "availability": "https://schema.org/InStock"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.9",
            "ratingCount": "1250",
            "bestRating": "5",
            "worstRating": "1"
        },
        "description": "CurenexAI is an AI-powered homeopathic healthcare software featuring intelligent diagnosis, digital repertory, materia medica database, patient management, and prescription generation for homeopathic doctors.",
        "featureList": [
            "AI-Powered Diagnosis",
            "Digital Repertory",
            "Materia Medica Database",
            "Patient Management System",
            "Prescription Generation",
            "Skin Analysis with AI",
            "Clinical Decision Support"
        ],
        "screenshot": "https://curenexai.com/assets/image/CURENEXAI PNG.png",
        "softwareVersion": "2.0",
        "author": {
            "@type": "Organization",
            "name": "CurenexAI",
            "url": "https://curenexai.com"
        }
    }
    </script>
    
    <!-- SCHEMA.ORG STRUCTURED DATA - WEBSITE -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "@id": "https://curenexai.com/#website",
        "url": "https://curenexai.com",
        "name": "CurenexAI - AI-Powered Homeopathic Healthcare Software",
        "description": "CurenexAI official website. AI-powered homeopathy software for doctors - NOT a skin medicine.",
        "publisher": {
            "@id": "https://curenexai.com/#organization"
        },
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "https://curenexai.com/search?q={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <!-- SCHEMA.ORG STRUCTURED DATA - FAQ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What is CurenexAI?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI (also known as Curenex AI) is an AI-powered homeopathic healthcare SOFTWARE platform designed for homeopathic doctors. It is NOT a skin medicine or pharmaceutical product. CurenexAI provides intelligent diagnosis, digital repertory, materia medica database, patient management, and prescription generation tools for homeopathy practitioners."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI the same as Curenex skin medicine?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "No, CurenexAI is completely different from Curenex skin medicine. CurenexAI is an AI-powered HEALTHCARE SOFTWARE platform for homeopathic doctors. It is a digital tool for medical practice management, not a pharmaceutical or skincare product."
                }
            },
            {
                "@type": "Question",
                "name": "Who can use CurenexAI software?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI is designed for homeopathic doctors (BHMS, MD Homeopathy), homeopathy practitioners, alternative medicine professionals, and homeopathy students who want to enhance their practice with AI-powered clinical decision support."
                }
            },
            {
                "@type": "Question",
                "name": "What features does CurenexAI offer?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI offers AI-powered diagnosis, digital repertory with rubrics, comprehensive materia medica database, patient management system, prescription generation, skin analysis using AI, consultation tracking, and clinical decision support for homeopathic treatment."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI digital repertory software for homeopathy?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. CurenexAI is digital repertory software for homeopathy, with rubric search, remedy relationships, and AI-supported case analysis. Some users also search this as digital reperatory software for homeopathy."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI free to use?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, CurenexAI offers a free tier for homeopathic doctors to get started. Sign up at curenexai.com to access the AI-powered homeopathy software platform."
                }
            }
        ]
    }
    </script>
    
    <!-- SCHEMA.ORG - BREADCRUMB -->
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
            }
        ]
    }
    </script>

    <!-- SCHEMA.ORG - LOCAL BUSINESS / MEDICAL ORGANIZATION -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": ["MedicalBusiness", "LocalBusiness"],
        "@id": "https://curenexai.com/#localbusiness",
        "name": "CurenexAI",
        "image": "https://curenexai.com/assets/image/CURENEXAI PNG.png",
        "url": "https://curenexai.com",
        "telephone": "+91-9061565631",
        "priceRange": "Free",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Online Service",
            "addressLocality": "Kochi",
            "addressRegion": "Kerala",
            "postalCode": "682001",
            "addressCountry": "IN"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": 9.9312,
            "longitude": 76.2673
        },
        "openingHoursSpecification": {
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": [
                "Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"
            ],
            "opens": "00:00",
            "closes": "23:59"
        },
        "medicalSpecialty": "Homeopathic",
        "areaServed": "Worldwide",
        "sameAs": [
            "https://twitter.com/curenexai",
            "https://www.linkedin.com/company/curenexai",
            "https://www.facebook.com/curenexai",
            "https://www.instagram.com/curenexai",
            "https://www.youtube.com/@curenexai"
        ]
    }
    </script>
    
    <!-- Preconnect for faster resource loading -->
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="dns-prefetch" href="//www.googletagmanager.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="preload" as="image" href="<?php echo APP_URL; ?>/assets/image/logo.png" fetchpriority="high">
    
    <!-- Google Fonts - non-render-blocking -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    
    <!-- Main CSS (minified) - non-render-blocking -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.min.css?v=<?php echo time(); ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.min.css?v=<?php echo time(); ?>"></noscript>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'" />
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>

    <!-- Landing page styles (extracted from inline <style> blocks for higher text-to-HTML ratio & caching) -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/index-page.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/index-page.css') ?: time(); ?>">
    <style>
        :root {
            --xrun-bg-png:  url('<?php echo APP_URL; ?>/assets/image/xrunbg.png');
            --xrun-bg-webp: url('<?php echo APP_URL; ?>/assets/image/xrunbg.webp');
        }
    </style>
    


    <!-- Stethoscope Loader Styles -->

</head>
<body class="<?php echo $bodyClass; ?>" data-app-url="<?php echo APP_URL; ?>">

    <!-- Stethoscope Page Loader -->
    <div class="stethoscope-loader" id="pageLoader">
        <!-- Ambient particles -->
        <div class="loader-particles" aria-hidden="true">
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
        </div>

        <!-- Stethoscope SVG -->
        <div class="stethoscope-svg-wrap">
            <div class="stethoscope-pulse-ring"></div>
            <div class="stethoscope-pulse-ring"></div>
            <div class="stethoscope-pulse-ring"></div>
            <div class="stetho-glow"></div>

            <svg class="stethoscope-icon" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="stethGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#818cf8"/>
                        <stop offset="50%" stop-color="#a78bfa"/>
                        <stop offset="100%" stop-color="#c084fc"/>
                    </linearGradient>
                </defs>

                <!-- Earpieces (small arcs at top) -->
                <path class="stetho-earpiece-l" d="M38 12 Q32 12 32 20"/>
                <path class="stetho-earpiece-r" d="M82 12 Q88 12 88 20"/>

                <!-- Main tube: Y-shape going down to chest piece -->
                <path class="stetho-tube" d="
                    M32 20 L32 32 Q32 42 42 42 L52 42 Q60 42 60 50 L60 78
                    M88 20 L88 32 Q88 42 78 42 L68 42 Q60 42 60 50
                "/>

                <!-- Chest piece circle -->
                <circle class="stetho-chest-fill" cx="60" cy="92" r="14"/>
                <circle class="stetho-chest" cx="60" cy="92" r="14"/>

                <!-- Heartbeat wave inside chest piece -->
                <path class="stetho-heartbeat" d="M50 92 L54 92 L56 86 L58 98 L60 84 L62 100 L64 88 L66 92 L70 92"/>
            </svg>
        </div>

        <!-- Loading text -->
        <div class="loader-text">
            <h3>Curenex AI</h3>
            <p class="loader-subtext">Preparing your experience...</p>
        </div>

        <!-- Progress bar -->
        <div class="loader-progress">
            <div class="loader-progress-bar"></div>
        </div>

        <!-- Heartbeat line at bottom -->
        <div class="loader-heartbeat-line" aria-hidden="true">
            <svg viewBox="0 0 800 40" preserveAspectRatio="none">
                <path class="hb-line" d="M0 20 L120 20 L140 20 L150 8 L160 32 L170 4 L180 36 L190 12 L200 20 L280 20 L380 20 L400 20 L410 8 L420 32 L430 4 L440 36 L450 12 L460 20 L540 20 L640 20 L660 20 L670 8 L680 32 L690 4 L700 36 L710 12 L720 20 L800 20"/>
            </svg>
        </div>
    </div>

    <!-- 3D Floating Geometric Shapes -->
    <div class="geo-canvas" aria-hidden="true">
        <!-- Cubes -->
        <div class="geo-shape cube geo-pos-1" data-speed="0.3" data-rotate="1">
            <div class="face front"></div><div class="face back"></div>
            <div class="face left"></div><div class="face right"></div>
            <div class="face top"></div><div class="face bottom"></div>
        </div>
        <div class="geo-shape cube geo-pos-2" data-speed="0.5" data-rotate="-1">
            <div class="face front"></div><div class="face back"></div>
            <div class="face left"></div><div class="face right"></div>
            <div class="face top"></div><div class="face bottom"></div>
        </div>

        <!-- Rings -->
        <div class="geo-shape ring geo-pos-3" data-speed="0.2" data-rotate="0.8"></div>
        <div class="geo-shape ring geo-pos-4" data-speed="0.4" data-rotate="-0.6"></div>

        <!-- Diamonds -->
        <div class="geo-shape diamond geo-pos-5" data-speed="0.6" data-rotate="1.2"></div>
        <div class="geo-shape diamond geo-pos-6" data-speed="0.35" data-rotate="-0.9"></div>

        <!-- Triangles -->
        <div class="geo-shape triangle geo-pos-7" data-speed="0.25" data-rotate="0.5"></div>
        <div class="geo-shape triangle geo-pos-8" data-speed="0.45" data-rotate="-1.1"></div>

        <!-- Helix shapes -->
        <div class="geo-shape helix geo-pos-9" data-speed="0.55" data-rotate="0.7"></div>
        <div class="geo-shape helix geo-pos-10" data-speed="0.3" data-rotate="-0.4"></div>

        <!-- Crosses -->
        <div class="geo-shape cross geo-pos-11" data-speed="0.4" data-rotate="0.9"></div>
        <div class="geo-shape cross geo-pos-12" data-speed="0.2" data-rotate="-0.7"></div>

        <!-- Glowing Orbs -->
        <div class="glow-orb glow-orb-1" data-speed="0.1"></div>
        <div class="glow-orb glow-orb-2" data-speed="0.15"></div>
        <div class="glow-orb glow-orb-3" data-speed="0.12"></div>
    </div>

    <!-- Navigation -->
    <nav class="landing-nav">
        <div class="nav-container">
            <a href="<?php echo APP_URL; ?>" class="nav-logo">
                <picture>
                    <source srcset="<?php echo APP_URL; ?>/assets/image/logo-200.webp 200w,
                                    <?php echo APP_URL; ?>/assets/image/logo-400.webp 400w,
                                    <?php echo APP_URL; ?>/assets/image/logo.webp 1038w"
                            sizes="(max-width: 480px) 180px, 245px"
                            type="image/webp">
                    <img src="<?php echo APP_URL; ?>/assets/image/logo.png" 
                         alt="<?php echo APP_NAME; ?>" 
                         class="nav-logo-img" 
                         width="245" height="52" 
                         fetchpriority="high"
                         decoding="async">
                </picture>
            </a>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#about">About</a>
                <a href="<?php echo APP_URL; ?>/faq.php">FAQ</a>
                <a href="#contact">Contact</a>
            </div>
            <div class="nav-actions">
                <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-ghost">Login</a>
                <a href="<?php echo APP_URL; ?>/register.php" class="btn btn-primary">Get Started</a>
            </div>
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="#features">Features</a>
        <a href="#about">About</a>
        <a href="<?php echo APP_URL; ?>/faq.php">FAQ</a>
        <a href="<?php echo APP_URL; ?>/documentation.php">Docs</a>
        <a href="#contact">Contact</a>
        <hr>
        <a href="<?php echo APP_URL; ?>/login.php">Login</a>
        <a href="<?php echo APP_URL; ?>/register.php" class="btn btn-primary btn-block">Get Started</a>
    </div>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg">
            <div class="hero-gradient"></div>
            <div class="floating-elements">
                <span class="float-element elem-1">🌿</span>
                <span class="float-element elem-2">💊</span>
                <span class="float-element elem-3">🩺</span>
                <span class="float-element elem-4">⚕️</span>
                <span class="float-element elem-5">🌱</span>
            </div>
        </div>
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-sparkles"></i> AI-Powered Homeopathy Platform <span class="hero-badge-pill">FREE BETA</span>
            </div>
            <h1>Digital Repertory and AI Homeopathy Software for <span class="gradient-text">Homeopathic Doctors</span></h1>
            <p>CurenexAI helps modern clinics with digital repertory workflows, AI-assisted remedy suggestions, patient management, and digital prescriptions in one homeopathy software platform.</p>
            <div class="hero-buttons">
                <a href="<?php echo APP_URL; ?>/register.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-rocket"></i> Start Free Beta
                </a>
                <a href="#features" class="btn btn-outline btn-lg">
                    <i class="fas fa-play-circle"></i> Learn More
                </a>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <strong>500+</strong>
                    <span>Registered Doctors</span>
                </div>
                <div class="stat-item">
                    <strong>10,000+</strong>
                    <span>Patients Managed</span>
                </div>
                <div class="stat-item">
                    <strong>50,000+</strong>
                    <span>Remedies Database</span>
                </div>
            </div>
        </div>
        <div class="hero-image">
            <div class="dashboard-preview">
                <div class="preview-header">
                    <div class="preview-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <span class="preview-title">Dashboard</span>
                </div>
                <div class="preview-content">
                    <div class="preview-card">
                        <i class="fas fa-users"></i>
                        <span>Patients</span>
                        <strong>124</strong>
                    </div>
                    <div class="preview-card">
                        <i class="fas fa-stethoscope"></i>
                        <span>Consultations</span>
                        <strong>389</strong>
                    </div>
                    <div class="preview-card">
                        <i class="fas fa-prescription"></i>
                        <span>Prescriptions</span>
                        <strong>512</strong>
                    </div>
                    <div class="preview-card">
                        <i class="fas fa-brain"></i>
                        <span>AI Suggestions</span>
                        <strong>Active</strong>
                    </div>
                    <div class="preview-card">
                        <i class="fas fa-hand-holding-medical"></i>
                        <span>Dermo Analysis</span>
                        <strong>Live</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section scroll-section" id="features">
        <div class="section-container">
            <div class="section-header scroll-slide-right scroll-3d-flip">
                <span class="section-badge">Features</span>
                <h2>Everything You Need to <span class="gradient-text">Modernize</span> Your Practice</h2>
                <p>Powerful tools designed specifically for homeopathic practitioners</p>
            </div>
            
            <div class="features-grid scroll-stagger scroll-3d-stagger">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Patient Management</h3>
                    <p>Complete patient records with medical history, constitutional symptoms, and treatment timeline. Easy search and organization.</p>
                </div>
                
                <div class="feature-card featured">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>AI-Powered Suggestions</h3>
                    <p>Get intelligent remedy suggestions powered by Gemini AI based on patient symptoms and constitutional analysis.</p>
                    <span class="feature-badge">Powered by AI</span>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <h3>Disease Diagnosis</h3>
                    <p>RAG-based diagnostic tool using local medical database. Get possible diagnoses based on symptoms, clinical findings, and lab results.</p>
                </div>
                
                <div class="feature-card featured">
                    <div class="feature-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Dermo - Skin Analysis</h3>
                    <p>AI-powered skin condition analysis with image upload or live camera. Get homeopathic remedy suggestions based on visual and RAG analysis.</p>
                    <span class="feature-badge">New Feature</span>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3><a href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy.php">Digital Repertory Software</a></h3>
                    <p>Search through comprehensive repertory with 50,000+ rubrics. Quick access to remedy relationships and materia medica. Learn how our digital repertory software supports homeopathic clinics.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-prescription"></i>
                    </div>
                    <h3>Digital Prescriptions</h3>
                    <p>Create professional prescriptions with dosage, potency, and instructions. Print or share digitally with patients.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Follow-up Tracking</h3>
                    <p>Never miss a follow-up appointment. Smart reminders and progress tracking for ongoing treatments.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <h3>Lab Integration</h3>
                    <p>Upload and analyze lab reports with AI assistance. Track patient health parameters over time.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section class="about-section scroll-section" id="about">
        <div class="section-container">
            <div class="about-content">
                <div class="about-text scroll-3d-rotate-left">
                    <span class="section-badge">About Us</span>
                    <h2>Built by Doctors, <span class="gradient-text">For Doctors</span></h2>
                    <p>Curenex AI was created with a deep understanding of the unique needs of homeopathic practitioners. Our platform combines traditional homeopathic principles with modern technology.</p>
                    
                    <div class="about-features">
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Secure & HIPAA Compliant</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Cloud-Based Access Anywhere</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Regular Updates & Support</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Free During Beta Period</span>
                        </div>
                    </div>
                    
                    <a href="<?php echo APP_URL; ?>/register.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus"></i> Join Today
                    </a>
                </div>
                <div class="about-image scroll-3d-rotate-right">
                    <div class="about-card">
                        <div class="card-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="card-title">Your Data is Safe</h3>
                        <p>256-bit encryption, per-account security protection, and strict access controls protect your patient data.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Brand Identity Section -->
    <section class="brand-identity-section scroll-section" id="brand-identity">
        <div class="section-container">
            <div class="brand-identity-wrapper">
                <div class="brand-identity-image scroll-3d-rotate-left">
                    <picture>
                        <source srcset="<?php echo APP_URL; ?>/assets/image/xrunbg-mobile.webp 500w,
                                        <?php echo APP_URL; ?>/assets/image/xrunbg.webp 788w"
                                sizes="(max-width: 768px) 300px, 400px"
                                type="image/webp">
                        <img src="<?php echo APP_URL; ?>/assets/image/xrunbg.png" 
                             alt="Curenex AI - X Symbol representing health, vitality and recovery" 
                             class="xrun-image" 
                             width="400" height="400" 
                                loading="eager"
                             decoding="async">
                    </picture>
                    <div class="brand-glow"></div>
                </div>
                <div class="brand-identity-content scroll-3d-rotate-right">
                    <span class="section-badge">Our Identity</span>
                    <h2>The <span class="gradient-text">X</span> That Defines Us</h2>
                    <p class="brand-tagline">Every element is thoughtfully designed to communicate <strong>energy</strong>, <strong>trust</strong>, and a <strong>future-ready healthcare identity</strong>.</p>
                    
                    <div class="brand-features">
                        <div class="brand-feature">
                            <div class="brand-feature-icon">
                                <i class="fas fa-running"></i>
                            </div>
                            <div class="brand-feature-text">
                                <h3 class="feature-title">Active Life</h3>
                                <p>The X symbolizes movement, vitality, and an active lifestyle — the foundation of true wellness.</p>
                            </div>
                        </div>
                        
                        <div class="brand-feature">
                            <div class="brand-feature-icon">
                                <i class="fas fa-heart-pulse"></i>
                            </div>
                            <div class="brand-feature-text">
                                <h3 class="feature-title">Recovery & Strength</h3>
                                <p>Reflecting the journey of healing — from illness to complete recovery and renewed strength.</p>
                            </div>
                        </div>
                        
                        <div class="brand-feature">
                            <div class="brand-feature-icon">
                                <i class="fas fa-spa"></i>
                            </div>
                            <div class="brand-feature-text">
                                <h3 class="feature-title">Well-Being</h3>
                                <p>Holistic health that encompasses mind, body, and spirit — the essence of homeopathic care.</p>
                            </div>
                        </div>
                        
                        <div class="brand-feature">
                            <div class="brand-feature-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="brand-feature-text">
                                <h3 class="feature-title">Energy & Vitality</h3>
                                <p>Dynamic design elements that convey the energy of life and the power of natural healing.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="brand-quote">
                        <i class="fas fa-quote-left"></i>
                        <p>Where tradition meets innovation, healing begins anew.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Brand Identity Section Styles -->

    
    <!-- How It Works Section -->
    <section class="how-it-works-section scroll-section">
        <div class="section-container">
            <div class="section-header scroll-slide-left scroll-3d-flip">
                <span class="section-badge">How It Works</span>
                <h2>Get Started in <span class="gradient-text">3 Simple Steps</span></h2>
            </div>
            
            <div class="steps-grid scroll-stagger scroll-3d-stagger">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Create Your Account</h3>
                    <p>Register with your medical credentials and verify your email to get started.</p>
                </div>
                <div class="step-connector"><i class="fas fa-arrow-right"></i></div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Add Your Patients</h3>
                    <p>Import existing patients or add new ones with comprehensive case history.</p>
                </div>
                <div class="step-connector"><i class="fas fa-arrow-right"></i></div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Start Prescribing</h3>
                    <p>Use AI suggestions, repertory search, and create digital prescriptions.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Feedback & Contribution Section -->
    <section class="feedback-section scroll-section" id="feedback">
        <div class="section-container">
            <div class="feedback-card scroll-slide-right scroll-3d-zoom">
                <div class="feedback-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <h2><span class="gradient-text">Help Us Improve!</span></h2>
                <p class="feedback-subtitle">Your feedback shapes the future of this platform</p>
                
                <div class="feedback-grid">
                    <div class="feedback-item">
                        <div class="feedback-item-icon bug">
                            <i class="fas fa-bug"></i>
                        </div>
                        <h3 class="feedback-title">Report Bugs</h3>
                        <p>Found something not working? Let us know and help us fix it quickly.</p>
                    </div>
                    <div class="feedback-item">
                        <div class="feedback-item-icon feature">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h3 class="feedback-title">Suggest Features</h3>
                        <p>Have ideas for new features? We'd love to hear your suggestions!</p>
                    </div>
                    <div class="feedback-item">
                        <div class="feedback-item-icon improve">
                            <i class="fas fa-magic"></i>
                        </div>
                        <h3 class="feedback-title">General Improvements</h3>
                        <p>Any feedback to improve user experience is always welcome.</p>
                    </div>
                </div>
                
                <div class="gratitude-banner">
                    <div class="gratitude-star"><i class="fas fa-star"></i></div>
                    <div class="gratitude-text">
                        <h3 class="gratitude-title"><i class="fas fa-award"></i> You Could Be Featured!</h3>
                        <p>If your feedback, bug report, or feature suggestion significantly improves our application, we will <strong>honor you with a gratitude post</strong> on our platform. Your contribution will be recognized and appreciated by the entire community!</p>
                    </div>
                </div>
                
                <a href="#contact" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Share Your Feedback
                </a>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="contact-section scroll-section" id="contact">
        <div class="section-container">
            <div class="contact-wrapper">
                <div class="contact-info">
                    <span class="section-badge">Contact Us</span>
                    <h2>Have Questions or <span class="gradient-text">Feedback?</span></h2>
                    <p>We'd love to hear from you. Whether you have a question about features, pricing, or anything else, our team is ready to answer all your questions.</p>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <i class="fab fa-whatsapp cx-icon-whatsapp"></i>
                            <div>
                                <strong>WhatsApp</strong>
                                <a href="https://wa.me/919061565631" target="_blank" rel="noopener" class="cx-link-whatsapp">+91 90615 65631</a>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong>Email</strong>
                                <!-- Email obfuscated to defeat scrapers; rendered by JS at runtime -->
                                <span class="cx-email" data-user="support" data-domain="curenexai.com" aria-label="Email support at curenex a i dot com">[Enable JavaScript to view email]</span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Response Time</strong>
                                <span>Within 24 hours</span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-headset"></i>
                            <div>
                                <strong>Support</strong>
                                <span>Monday - Saturday, 9 AM - 6 PM IST</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form-wrapper">
                    <div class="contact-form-card">
                        <h3><i class="fas fa-paper-plane"></i> Send us a Message</h3>
                        
                        <?php if ($contactSuccess): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $contactSuccess; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($contactError): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $contactError; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo APP_URL; ?>/#contact" class="contact-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="contact_form" value="1">
                            
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="name"><i class="fas fa-user"></i> Your Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" 
                                           placeholder="Dr. John Doe" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           placeholder="doctor@example.com" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row-2">
                                <div class="form-group">
                                    <label for="feedback_type"><i class="fas fa-tag"></i> Feedback Type</label>
                                    <select id="feedback_type" name="feedback_type" class="form-control">
                                        <option value="general">General Inquiry</option>
                                        <option value="feature">Feature Request</option>
                                        <option value="bug">Bug Report</option>
                                        <option value="support">Technical Support</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="subject"><i class="fas fa-heading"></i> Subject</label>
                                    <input type="text" id="subject" name="subject" class="form-control" 
                                           placeholder="How can we help?" 
                                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="message"><i class="fas fa-comment-alt"></i> Message *</label>
                                <textarea id="message" name="message" class="form-control" rows="4" 
                                          placeholder="Tell us more about your inquiry..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block btn-lg">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SEO Content Section: keyword-rich descriptive copy for search engines and AI crawlers -->
    <section class="seo-content-section scroll-section" id="why-curenexai" aria-labelledby="seo-content-heading">
        <div class="section-container">
            <div class="section-header scroll-slide-right">
                <span class="section-badge">Why CurenexAI</span>
                <h2 id="seo-content-heading">Modern Clinical Tools Built for <span class="gradient-text">Homeopathic Doctors</span></h2>
                <p>A single workspace for case-taking, repertory search, AI remedy suggestions, digital prescriptions and patient management — designed end-to-end for homeopathic practice.</p>
            </div>

            <div class="seo-content-grid">
                <article class="seo-card">
                    <div class="seo-card-icon"><i class="fas fa-stethoscope"></i></div>
                    <h3>Designed for real homeopathic practice</h3>
                    <p>
                        CurenexAI is a modern clinical homeopathic platform designed end-to-end for
                        homeopathic doctors, BHMS practitioners and MD Homeopathy graduates who want
                        to run a faster, better-organised practice. Every workflow — from case
                        intake and repertory search to digital prescriptions and follow-up — is
                        built around the way real homeopathic doctors actually work, so you get
                        meaningful AI remedy suggestions without giving up clinical judgement.
                    </p>
                    <p>
                        Whether you are a solo homeopathic practitioner or part of a multi-doctor
                        clinic, CurenexAI gives you a single workspace for patients, consultations,
                        prescriptions and feedback. Get started for free in our public beta, invite
                        your team, and bring your existing case notes online without changing the
                        way you practise homeopathy.
                    </p>
                </article>

                <article class="seo-card seo-card-accent">
                    <div class="seo-card-icon"><i class="fas fa-list-check"></i></div>
                    <h3>What you get with CurenexAI</h3>
                    <ul class="seo-feature-list">
                        <li><strong>AI remedy suggestions</strong> — ranked remedy suggestions and rubric matches from your case-taking notes, with full reasoning you can review.</li>
                        <li><strong>Repertory search</strong> — fast repertory search across rubrics, with cross-links into the materia medica and keynote summaries.</li>
                        <li><strong>Digital prescriptions</strong> — generate, print and share digital prescriptions with potency, dosage and follow-up instructions in seconds.</li>
                        <li><strong>Patient management</strong> — keep constitutional history, modalities, miasmatic notes and past prescriptions in one timeline per patient.</li>
                        <li><strong>Feedback &amp; support</strong> — send feedback, request features or report issues from inside the platform and get fast responses from our team.</li>
                        <li><strong>Free beta for homeopathic doctors</strong> — sign up, verify your credentials and get started immediately, no credit card required.</li>
                    </ul>
                </article>
            </div>

            <div class="seo-content-grid">
                <article class="seo-card">
                    <div class="seo-card-icon"><i class="fas fa-brain"></i></div>
                    <h3>Why homeopathic practitioners choose CurenexAI</h3>
                    <p>
                        Homeopathic practitioners have historically had to combine paper repertories,
                        offline materia medica references and generic clinic software to manage a
                        modern practice. CurenexAI replaces that fragmented setup with one cohesive
                        clinical platform: AI-assisted case analysis, repertory search, materia
                        medica lookup, digital prescriptions and patient records — all linked
                        together so you can move from symptoms to a defensible remedy choice in a
                        single screen.
                    </p>
                    <p>
                        We treat AI as a clinical assistant, not a replacement. The platform
                        surfaces remedy suggestions, similar past cases and relevant rubrics, but
                        the prescribing decision always stays with the qualified homeopathic
                        doctor. That is what "modern clinical homeopathic platform" means to us.
                    </p>
                </article>

                <article class="seo-card seo-card-accent">
                    <div class="seo-card-icon"><i class="fas fa-rocket"></i></div>
                    <h3>How to get started</h3>
                    <ol class="seo-feature-list seo-steps">
                        <li>Create your free homeopathic doctor account using the <a href="<?php echo APP_URL; ?>/register.php">Get Started</a> button above.</li>
                        <li>Add your first patient and run a quick repertory search on a real case.</li>
                        <li>Review the AI remedy suggestions, finalise the prescription and share the digital prescription with the patient.</li>
                        <li>Send us feedback or feature requests directly from the contact form below — we read every message.</li>
                    </ol>
                    <p class="seo-cta-line">
                        Have a question about features, pricing or onboarding for your clinic?
                        Use the feedback form below or reach our support team and we will respond
                        within one working day.
                    </p>
                </article>
            </div>
        </div>
    </section>



    <!-- CTA Section -->
    <section class="cta-section scroll-section">
        <div class="cta-bg-image">
            <picture>
                <source srcset="<?php echo APP_URL; ?>/assets/image/xrunbg-mobile.webp 500w,
                                <?php echo APP_URL; ?>/assets/image/xrunbg.webp 788w"
                        sizes="500px"
                        type="image/webp">
                <img src="<?php echo APP_URL; ?>/assets/image/xrunbg.png" alt="CurenexAI Homeopathic Healthcare Software Interface" width="500" height="500" loading="eager" decoding="async" class="cx-cta-img">
            </picture>
        </div>
        <div class="section-container cx-relative">
            <div class="cta-content">
                <h2>Ready to Transform Your Practice?</h2>
                <p>Join hundreds of homeopathic doctors who trust Curenex AI for their daily practice. Completely free!</p>
                <div class="cta-buttons">
                    <a href="<?php echo APP_URL; ?>/register.php" class="btn btn-white btn-lg">
                        <i class="fas fa-rocket"></i> Get Started Free
                    </a>
                    <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-outline-white btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-main">
                <div class="footer-brand">
                    <a href="<?php echo APP_URL; ?>" class="footer-logo">
                        <picture>
                            <source srcset="<?php echo APP_URL; ?>/assets/image/logo-200.webp 200w,
                                            <?php echo APP_URL; ?>/assets/image/logo-400.webp 400w"
                                    sizes="200px"
                                    type="image/webp">
                            <img src="<?php echo APP_URL; ?>/assets/image/logo.png" alt="<?php echo APP_NAME; ?>" class="footer-logo-img" width="200" height="43" loading="lazy" decoding="async">
                        </picture>
                    </a>
                    <p>Modern clinical decision support system designed specifically for homeopathic practitioners.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <div class="footer-column">
                        <h3 class="footer-title">Quick Links</h3>
                        <ul>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#about">About Us</a></li>
                            <li><a href="#feedback">Feedback</a></li>
                            <li><a href="#contact">Contact</a></li>
                            <li><a href="<?php echo APP_URL; ?>/login.php">Login</a></li>
                            <li><a href="<?php echo APP_URL; ?>/register.php">Register</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3 class="footer-title">Resources</h3>
                        <ul>
                            <li><a href="<?php echo APP_URL; ?>/documentation.php">Documentation</a></li>
                            <li><a href="<?php echo APP_URL; ?>/faq.php">FAQs</a></li>
                            <li><a href="<?php echo APP_URL; ?>/support.php">Support</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3 class="footer-title">Legal</h3>
                        <ul>
                            <li><a href="<?php echo APP_URL; ?>/privacy.php">Privacy Policy</a></li>
                            <li><a href="<?php echo APP_URL; ?>/terms.php">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <a href="https://curenexai.com" target="_blank" rel="noopener" class="cx-footer-link">CurenexAI</a> - <?php echo APP_NAME; ?>. All rights reserved. | Designed for certified homeopathic doctors.</p>
            </div>
        </div>
    </footer>
    
    <!-- Feedback Section Styles -->

    
    <!-- Scripts moved to assets/js/index-page.js -->

    <!-- Gratitude Popup Modal -->
    <div id="gratitudeModal" class="gratitude-modal">
        <div class="gratitude-overlay"></div>
        <div class="gratitude-card">
            <button class="gratitude-close" onclick="closeGratitudeModal()">&times;</button>
            <div class="gratitude-photo">
                <div class="photo-frame">
                    <i class="fas fa-user-doctor"></i>
                </div>
                <div class="gratitude-badge">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
            <div class="gratitude-content">
                <div class="gratitude-ribbon">
                    <i class="fas fa-award"></i> Special Thanks
                </div>
                <h2>Gratitude</h2>
                <h3>Dr. Aysha Shirin <span class="degree">BHMS</span></h3>
                <p class="clinic-name"><i class="fas fa-house-medical"></i> Siris Clinics</p>
                <div class="gratitude-divider"></div>
                <p class="gratitude-message">
                    We express our heartfelt gratitude to Dr. Aysha Shirin for her invaluable contribution of knowledge and expertise in building this application. Her deep understanding of homeopathic medicine has been instrumental in making this platform a comprehensive tool for practitioners.
                </p>
                <div class="gratitude-footer">
                    <i class="fas fa-quote-left"></i>
                    <span>Knowledge shared is knowledge multiplied</span>
                    <i class="fas fa-quote-right"></i>
                </div>
            </div>
            <button class="gratitude-btn" onclick="closeGratitudeModal()">
                <i class="fas fa-hands-clapping"></i> Continue to Website
            </button>
        </div>
    </div>





    <!-- AI Chatbot Widget -->
    <div id="chatbot-widget" class="chatbot-widget">
        <!-- Chatbot Toggle Button -->
        <button id="chatbot-toggle" class="chatbot-toggle" aria-label="Open chat assistant">
            <span class="chatbot-toggle-icon">
                <i class="fas fa-comments"></i>
            </span>
            <span class="chatbot-toggle-close">
                <i class="fas fa-times"></i>
            </span>
            <span class="chatbot-notification-badge" id="chatbot-badge">1</span>
        </button>

        <!-- Chatbot Window -->
        <div id="chatbot-window" class="chatbot-window">
            <div class="chatbot-header">
                <div class="chatbot-header-info">
                    <div class="chatbot-avatar">
                        <i class="fas fa-robot"></i>
                        <span class="chatbot-status-dot"></span>
                    </div>
                    <div class="chatbot-header-text">
                        <h4>CurenexBot</h4>
                        <span class="chatbot-status">CurenexBot • Online</span>
                    </div>
                </div>
                <div class="chatbot-header-actions">
                    <button class="chatbot-clear" id="chatbot-clear" title="Clear chat">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button class="chatbot-minimize" id="chatbot-minimize" title="Close chat">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>

            <div class="chatbot-messages" id="chatbot-messages">
                <div class="chatbot-welcome">
                    <div class="chatbot-welcome-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3>Welcome to Curenex AI!</h3>
                    <p>I'm CurenexBot, your AI guide. Ask me anything about our platform, homeopathy, or how we can help your practice.</p>
                </div>
                <div class="chatbot-message bot">
                    <div class="chatbot-message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="chatbot-message-content">
                        <p>Hi there! 👋 I'm CurenexBot, here to help you explore our platform. What would you like to know?</p>
                    </div>
                </div>
                <div class="chatbot-suggestions" id="chatbot-suggestions">
                    <button class="chatbot-suggestion" data-message="What features do you offer?">
                        <i class="fas fa-star"></i> Features
                    </button>
                    <button class="chatbot-suggestion" data-message="How does the AI diagnosis work?">
                        <i class="fas fa-brain"></i> AI Diagnosis
                    </button>
                    <button class="chatbot-suggestion" data-message="Is it free to use?">
                        <i class="fas fa-gift"></i> Pricing
                    </button>
                    <button class="chatbot-suggestion" data-message="Tell me about the dermo skin analysis feature">
                        <i class="fas fa-hand-holding-medical"></i> Skin Analysis
                    </button>
                </div>
            </div>

            <div class="chatbot-input-area">
                <div class="chatbot-typing" id="chatbot-typing" hidden>
                    <span></span><span></span><span></span>
                    <span class="typing-text">CurenexBot is typing...</span>
                </div>
                <form id="chatbot-form" class="chatbot-form">
                    <input type="text" id="chatbot-input" class="chatbot-input" 
                           placeholder="Type your message..." 
                           autocomplete="off" maxlength="500">
                    <button type="submit" class="chatbot-send" id="chatbot-send" disabled>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <div class="chatbot-powered">
                    <i class="fas fa-leaf"></i> CurenexAI
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot Styles moved to assets/css/index-page.css -->

    

    <script defer src="<?php echo APP_URL; ?>/assets/js/index-page.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/index-page.js') ?: time(); ?>"></script>
</body>
</html>
