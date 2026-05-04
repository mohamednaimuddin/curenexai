<?php
/**
 * Support Page
 */
require_once 'includes/init.php';
require_once 'includes/email_otp.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pageTitle = 'Support';
$bodyClass = 'support-page';

$supportSuccess = '';
$supportError = '';

/**
 * Send support ticket email
 */
function sendSupportEmail($data) {
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
        
        // Recipients
        $adminEmail = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'admin@example.com';
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : $adminEmail;
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($adminEmail);
        $mail->addReplyTo($data['email'], $data['name']);
        
        // Priority badge color
        $priorityColor = match($data['priority']) {
            'high' => '#dc2626',
            'medium' => '#f59e0b',
            default => '#10b981'
        };
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = APP_NAME . ' - Support Ticket #' . $data['id'] . ': ' . $data['subject'];
        $mail->Body    = getSupportEmailTemplate($data, $priorityColor);
        $mail->AltBody = "Support Ticket #{$data['id']}\n\nFrom: {$data['name']}\nEmail: {$data['email']}\nPriority: {$data['priority']}\nCategory: {$data['category']}\n\nMessage:\n{$data['message']}";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log('Support email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get HTML email template for support ticket
 * Uses inline CSS for email client compatibility
 */
function getSupportEmailTemplate($data, $priorityColor) {
    $appName = APP_NAME;
    $date = date('F j, Y \a\t g:i A');
    $priority = ucfirst($data['priority']);
    $category = ucfirst($data['category']);
    
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 20px; background-color: #f3f4f6;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px;">
        <tr>
            <td style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff; padding: 25px; text-align: center; border-radius: 12px 12px 0 0;">
                <h1 style="margin: 0; font-size: 22px;">Support Ticket #' . $data['id'] . '</h1>
                <span style="display: inline-block; padding: 4px 12px; background-color: ' . $priorityColor . '; color: #ffffff; border-radius: 15px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 10px;">' . $priority . ' Priority</span>
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
                        <td style="font-weight: bold; color: #6b7280; vertical-align: top;">Category:</td>
                        <td style="color: #1f2937;">' . htmlspecialchars($category) . '</td>
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
                    <a href="mailto:' . htmlspecialchars($data['email']) . '?subject=Re: Support Ticket %23' . $data['id'] . ' - ' . rawurlencode($data['subject']) . '" style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Reply to ' . htmlspecialchars($data['name']) . '</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #1f2937; color: #9ca3af; padding: 15px; text-align: center; font-size: 11px; border-radius: 0 0 12px 12px;">
                <p style="margin: 0;">Received on ' . $date . '</p>
                <p style="margin: 5px 0 0;">This notification was sent from ' . $appName . '</p>
            </td>
        </tr>
    </table>
</body>
</html>';
}

// Handle support form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['support_form'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $category = sanitize($_POST['category'] ?? 'general');
    $priority = sanitize($_POST['priority'] ?? 'low');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $supportError = 'Please fill in all required fields';
    } elseif (!isValidEmail($email)) {
        $supportError = 'Please enter a valid email address';
    } else {
        try {
            // Create support_tickets table if not exists
            DB::execute("CREATE TABLE IF NOT EXISTS support_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                category VARCHAR(50) DEFAULT 'general',
                priority ENUM('low', 'medium', 'high') DEFAULT 'low',
                status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            
            // Modify category column if it's still ENUM (for existing tables)
            try {
                DB::execute("ALTER TABLE support_tickets MODIFY COLUMN category VARCHAR(50) DEFAULT 'general'");
            } catch (Exception $e) {
                // Column already correct type, ignore
            }
            
            // Insert ticket
            $ticketId = DB::insert('support_tickets', [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
                'category' => $category,
                'priority' => $priority
            ]);
            
            // Send email notification
            $emailData = [
                'id' => $ticketId,
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
                'category' => $category,
                'priority' => $priority
            ];
            
            $emailSent = sendSupportEmail($emailData);
            
            if ($emailSent) {
                $_SESSION['support_success'] = "Your support ticket #$ticketId has been submitted successfully! We'll get back to you within 24 hours.";
            } else {
                $_SESSION['support_success'] = "Your support ticket #$ticketId has been received. Our team will review it shortly.";
            }
            
            header('Location: ' . APP_URL . '/support.php');
            exit;
        } catch (Exception $e) {
            error_log('Support ticket error: ' . $e->getMessage());
            $_SESSION['support_error'] = 'An error occurred. Please try again later.';
            header('Location: ' . APP_URL . '/support.php');
            exit;
        }
    }
}

// Get flash messages
if (isset($_SESSION['support_success'])) {
    $supportSuccess = $_SESSION['support_success'];
    unset($_SESSION['support_success']);
}
if (isset($_SESSION['support_error'])) {
    $supportError = $_SESSION['support_error'];
    unset($_SESSION['support_error']);
}
?>
<!DOCTYPE html>
<html lang="en" itemscope itemtype="https://schema.org/ContactPage">
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
    <title>Support - CurenexAI - Get Help with AI Homeopathic Healthcare</title>
    <meta name="title" content="Support - CurenexAI - Contact Us">
    <meta name="description" content="Get support for Curenex (CurenexAI) - Contact our team for help with the AI-powered homeopathic healthcare platform. We're here to assist you.">
    <meta name="keywords" content="Curenex support, CurenexAI help, Curenex contact, homeopathy support, AI healthcare help, Curenex AI customer service, curenex, curenexai">
    <meta name="author" content="CurenexAI">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://curenexai.com/support.php">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://curenexai.com/support.php">
    <meta property="og:title" content="Support - CurenexAI">
    <meta property="og:description" content="Get help with Curenex AI-powered homeopathic healthcare platform.">
    <meta property="og:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta property="og:site_name" content="Curenex">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:site" content="@curenexai">
    <meta name="twitter:title" content="Support - CurenexAI">
    
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
        .support-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .support-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .support-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .support-header p {
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
        
        .support-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .support-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        }
        
        .support-card-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        
        .support-card-icon.email { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
        .support-card-icon.docs { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
        .support-card-icon.faq { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
        
        .support-card h3 {
            margin: 0 0 12px;
            font-size: 1.2rem;
            color: #1f2937;
        }
        
        .support-card p {
            color: #6b7280;
            margin: 0 0 20px;
            line-height: 1.6;
        }
        
        .support-card .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .support-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        
        @media (max-width: 900px) {
            .support-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .support-info {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .support-info h2 {
            margin: 0 0 25px;
            font-size: 1.4rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .support-info h2 i {
            color: #6366f1;
        }
        
        .contact-method {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .contact-method:last-child {
            border-bottom: none;
        }
        
        .contact-method-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6366f1;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .contact-method h4 {
            margin: 0 0 5px;
            color: #1f2937;
            font-size: 1rem;
        }
        
        .contact-method p {
            margin: 0;
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .contact-method a {
            color: #6366f1;
            text-decoration: none;
        }
        
        .contact-method a:hover {
            text-decoration: underline;
        }
        
        .support-form-wrapper {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .support-form-wrapper h2 {
            margin: 0 0 25px;
            font-size: 1.4rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .support-form-wrapper h2 i {
            color: #6366f1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group label i {
            color: #6366f1;
            margin-right: 6px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        .btn-outline {
            background: white;
            color: #6366f1;
            border: 2px solid #6366f1;
        }
        
        .btn-outline:hover {
            background: #6366f1;
            color: white;
        }
        
        .btn-block {
            display: flex;
            width: 100%;
        }
        
        .response-time {
            background: linear-gradient(135deg, #eff6ff, #e0e7ff);
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .response-time i {
            font-size: 24px;
            color: #6366f1;
        }
        
        .response-time div h4 {
            margin: 0 0 5px;
            color: #1f2937;
            font-size: 1rem;
        }
        
        .response-time div p {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .priority-selector {
            display: flex;
            gap: 10px;
        }
        
        .priority-option {
            flex: 1;
            position: relative;
        }
        
        .priority-option input {
            position: absolute;
            opacity: 0;
        }
        
        .priority-option label {
            display: block;
            padding: 12px;
            text-align: center;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .priority-option input:checked + label {
            border-color: #6366f1;
            background: #eff6ff;
            color: #6366f1;
        }
        
        .priority-option.low label { color: #10b981; }
        .priority-option.low input:checked + label { background: #ecfdf5; border-color: #10b981; }
        
        .priority-option.medium label { color: #f59e0b; }
        .priority-option.medium input:checked + label { background: #fffbeb; border-color: #f59e0b; }
        
        .priority-option.high label { color: #dc2626; }
        .priority-option.high input:checked + label { background: #fef2f2; border-color: #dc2626; }
        
        @media (max-width: 768px) {
            .support-header h1 {
                font-size: 1.8rem;
            }
            
            .support-form-wrapper,
            .support-info {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="support-container">
        <div class="back-nav">
            <a href="<?php echo APP_URL; ?>"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <div class="support-header">
            <h1><i class="fas fa-headset"></i> Support Center</h1>
            <p>Need help? We're here for you. Choose how you'd like to get assistance.</p>
        </div>
        
        <!-- Quick Support Options -->
        <div class="support-options">
            <div class="support-card">
                <div class="support-card-icon docs">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Documentation</h3>
                <p>Browse our comprehensive guides and tutorials to learn how to use all features.</p>
                <a href="<?php echo APP_URL; ?>/documentation.php" class="btn btn-outline">
                    <i class="fas fa-external-link-alt"></i> View Docs
                </a>
            </div>
            
            <div class="support-card">
                <div class="support-card-icon faq">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h3>FAQs</h3>
                <p>Find quick answers to commonly asked questions about our platform.</p>
                <a href="<?php echo APP_URL; ?>/faq.php" class="btn btn-outline">
                    <i class="fas fa-external-link-alt"></i> View FAQs
                </a>
            </div>
            
            <div class="support-card">
                <div class="support-card-icon email">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Email Support</h3>
                <p>Can't find your answer? Submit a ticket and we'll respond within 24 hours.</p>
                <a href="#submit-ticket" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Ticket
                </a>
            </div>
        </div>
        
        <!-- Support Form and Contact Info -->
        <div class="support-grid" id="submit-ticket">
            <div class="support-info">
                <h2><i class="fas fa-info-circle"></i> Contact Information</h2>
                
                <div class="contact-method">
                    <div class="contact-method-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h4>Email Support</h4>
                        <p><a href="mailto:support@curenexai.com">support@curenexai.com</a></p>
                    </div>
                </div>
                
                <div class="contact-method">
                    <div class="contact-method-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h4>Support Hours</h4>
                        <p>Monday - Saturday<br>9:00 AM - 6:00 PM IST</p>
                    </div>
                </div>
                
                <div class="contact-method">
                    <div class="contact-method-icon">
                        <i class="fas fa-reply"></i>
                    </div>
                    <div>
                        <h4>Response Time</h4>
                        <p>We aim to respond within 24 hours</p>
                    </div>
                </div>
                
                <div class="contact-method">
                    <div class="contact-method-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h4>Location</h4>
                        <p>Kerala, India</p>
                    </div>
                </div>
                
                <div class="response-time">
                    <i class="fas fa-bolt"></i>
                    <div>
                        <h4>Priority Support</h4>
                        <p>High priority tickets are addressed first. Use high priority only for critical issues.</p>
                    </div>
                </div>
            </div>
            
            <div class="support-form-wrapper">
                <h2><i class="fas fa-ticket-alt"></i> Submit Support Ticket</h2>
                
                <?php if ($supportSuccess): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $supportSuccess; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($supportError): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $supportError; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo APP_URL; ?>/support.php#submit-ticket">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="support_form" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Your Name *</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   placeholder="Dr. John Doe" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   placeholder="doctor@example.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category"><i class="fas fa-tag"></i> Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="general">General Question</option>
                                <option value="technical">Technical Issue</option>
                                <option value="account">Account Related</option>
                                <option value="billing">Billing Question</option>
                                <option value="feature">Feature Request</option>
                                <option value="bug">Bug Report</option>
                                <option value="diagnose">Diagnose Feature</option>
                                <option value="dermo">Dermo (Skin Analysis)</option>
                                <option value="repertory">Repertory</option>
                                <option value="materia-medica">Materia Medica</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject"><i class="fas fa-heading"></i> Subject *</label>
                            <input type="text" id="subject" name="subject" class="form-control" 
                                   placeholder="Brief description of your issue" 
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Priority</label>
                        <div class="priority-selector">
                            <div class="priority-option low">
                                <input type="radio" id="priority-low" name="priority" value="low" checked>
                                <label for="priority-low">🟢 Low</label>
                            </div>
                            <div class="priority-option medium">
                                <input type="radio" id="priority-medium" name="priority" value="medium">
                                <label for="priority-medium">🟡 Medium</label>
                            </div>
                            <div class="priority-option high">
                                <input type="radio" id="priority-high" name="priority" value="high">
                                <label for="priority-high">🔴 High</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message"><i class="fas fa-comment-alt"></i> Describe Your Issue *</label>
                        <textarea id="message" name="message" class="form-control" rows="5" 
                                  placeholder="Please provide as much detail as possible about your issue..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Submit Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
