<?php
require_once 'includes/init.php';
require_once 'includes/email_otp.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';

// Function to validate phone number format based on country
function validatePhoneNumber($phone, $countryCode) {
    // Remove spaces, dashes, dots from phone number
    $phone = preg_replace('/[\s\-\.]/', '', $phone);
    
    // Country-specific validation rules
    $phonePatterns = [
        '+91' => '/^[6-9][0-9]{9}$/',      // India: 10 digits, starts with 6-9
        '+1' => '/^[2-9][0-9]{9}$/',        // USA/Canada: 10 digits, area code starts 2-9
        '+44' => '/^[1-9][0-9]{9,10}$/',    // UK: 10-11 digits
        '+61' => '/^[4][0-9]{8}$/',         // Australia: 9 digits, starts with 4 for mobile
        '+971' => '/^[5][0-9]{8}$/',        // UAE: 9 digits, starts with 5 for mobile
        '+966' => '/^[5][0-9]{8}$/',        // Saudi Arabia: 9 digits
        '+974' => '/^[3567][0-9]{7}$/',     // Qatar: 8 digits
        '+973' => '/^[3][0-9]{7}$/',        // Bahrain: 8 digits
        '+968' => '/^[79][0-9]{7}$/',       // Oman: 8 digits
        '+965' => '/^[569][0-9]{7}$/',      // Kuwait: 8 digits
        '+977' => '/^[9][0-9]{9}$/',        // Nepal: 10 digits, starts with 9
        '+94' => '/^[7][0-9]{8}$/',         // Sri Lanka: 9 digits
        '+880' => '/^[1][0-9]{9}$/',        // Bangladesh: 10 digits, starts with 1
        '+92' => '/^[3][0-9]{9}$/',         // Pakistan: 10 digits, starts with 3
        '+60' => '/^[1][0-9]{8,9}$/',       // Malaysia: 9-10 digits
        '+65' => '/^[89][0-9]{7}$/',        // Singapore: 8 digits
        '+49' => '/^[1][5-7][0-9]{8,9}$/',  // Germany: 10-11 digits
        '+33' => '/^[67][0-9]{8}$/',        // France: 9 digits
        '+39' => '/^[3][0-9]{9}$/',         // Italy: 10 digits
        '+34' => '/^[67][0-9]{8}$/',        // Spain: 9 digits
        '+27' => '/^[6-8][0-9]{8}$/',       // South Africa: 9 digits
        '+254' => '/^[7][0-9]{8}$/',        // Kenya: 9 digits
        '+234' => '/^[789][0-9]{9}$/',      // Nigeria: 10 digits
        '+55' => '/^[1-9][0-9]{10}$/',      // Brazil: 11 digits
        '+52' => '/^[1-9][0-9]{9}$/',       // Mexico: 10 digits
        '+86' => '/^[1][3-9][0-9]{9}$/',    // China: 11 digits
        '+81' => '/^[789][0-9]{8,9}$/',     // Japan: 9-10 digits
        '+82' => '/^[1][0-9]{9,10}$/',      // South Korea: 10-11 digits
        '+7' => '/^[9][0-9]{9}$/',          // Russia: 10 digits
        '+62' => '/^[8][0-9]{9,11}$/',      // Indonesia: 10-12 digits
        '+66' => '/^[689][0-9]{8}$/',       // Thailand: 9 digits
        '+84' => '/^[3589][0-9]{8}$/',      // Vietnam: 9 digits
        '+63' => '/^[9][0-9]{9}$/',         // Philippines: 10 digits
        'DEFAULT' => '/^[0-9]{7,15}$/'      // Default: 7-15 digits
    ];
    
    $pattern = $phonePatterns[$countryCode] ?? $phonePatterns['DEFAULT'];
    return preg_match($pattern, $phone);
}

// Function to validate registration number format
function validateRegistrationNumber($regNo, $stateCode) {
    // Remove spaces and convert to uppercase
    $regNo = strtoupper(trim(str_replace(' ', '', $regNo)));
    
    // Basic validation - must have at least 3 characters
    if (strlen($regNo) < 3) {
        return ['valid' => false, 'message' => 'Registration number is too short'];
    }
    
    // Check for alphanumeric with allowed special characters (hyphen, slash)
    if (!preg_match('/^[A-Z0-9\-\/]+$/', $regNo)) {
        return ['valid' => false, 'message' => 'Registration number contains invalid characters'];
    }
    
    // State-specific format validation (basic patterns)
    $patterns = [
        'CCH' => '/^[A-Z]{0,3}[0-9]{3,10}$/', // Central Council
        'MH' => '/^(MH)?[0-9]{4,8}$/', // Maharashtra
        'KA' => '/^(KA|RH)?[0-9]{3,8}$/', // Karnataka  
        'DL' => '/^(DL)?[0-9]{4,8}$/', // Delhi
        'UP' => '/^(UP)?[0-9]{4,8}$/', // Uttar Pradesh
        'WB' => '/^(WB)?[0-9]{4,8}$/', // West Bengal
        'TN' => '/^(TN)?[0-9]{4,8}$/', // Tamil Nadu
        'GJ' => '/^(GJ)?[0-9]{4,8}$/', // Gujarat
        'RJ' => '/^(RJ)?[0-9]{4,8}$/', // Rajasthan
        'KL' => '/^(KL)?[0-9]{4,8}$/', // Kerala
        'DEFAULT' => '/^[A-Z]{0,3}[0-9]{3,10}$/' // Default pattern
    ];
    
    $pattern = $patterns[$stateCode] ?? $patterns['DEFAULT'];
    
    if (!preg_match($pattern, $regNo)) {
        return ['valid' => false, 'message' => 'Registration number format appears invalid for ' . $stateCode];
    }
    
    return ['valid' => true, 'formatted' => $regNo];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $stateCouncil = sanitize($_POST['state_council'] ?? '');
    $registrationNumber = sanitize($_POST['registration_number'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $countryCode = sanitize($_POST['country_code'] ?? '+91');
    $phoneNumber = sanitize($_POST['phone_number'] ?? '');
    $phone = $countryCode . ' ' . $phoneNumber; // Combined phone with country code
    
    $termsAccepted = isset($_POST['terms']) && $_POST['terms'] === 'on';
    
    // Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (empty($stateCouncil)) {
        $error = 'Please select your State Council';
    } elseif (empty($registrationNumber)) {
        $error = 'Please enter your registration number';
    } elseif (empty($qualification)) {
        $error = 'Please select your qualification';
    } elseif (empty($phoneNumber)) {
        $error = 'Please enter your phone number';
    } elseif (!validatePhoneNumber($phoneNumber, $countryCode)) {
        $error = 'Please enter a valid phone number for the selected country';
    } elseif (!$termsAccepted) {
        $error = 'You must agree to the Terms and Conditions and Privacy Policy to create an account';
    } else {
        // Validate registration number format
        $regValidation = validateRegistrationNumber($registrationNumber, $stateCouncil);
        
        if (!$regValidation['valid']) {
            $error = $regValidation['message'];
        } else {
            // Format registration number with state code
            $formattedRegNo = $stateCouncil . '-' . $regValidation['formatted'];
            
            // Check if registration number already exists
            $existingReg = DB::queryOne("SELECT id FROM doctors WHERE registration_number = ?", [$formattedRegNo]);
            if ($existingReg) {
                $error = 'This registration number is already registered';
            } else {
                // Check if email already exists in doctors table
                $existingDoctor = DB::queryOne("SELECT id FROM doctors WHERE email = ?", [$email]);
                
                if ($existingDoctor) {
                    $error = 'Email address already registered';
                } else {
                    // Clean up expired OTPs and pending registrations
                    cleanupExpiredOTPs();
                    
                    // Hash password
                    $hashedPassword = hashPassword($password);
                    
                    // Generate OTP
                    $otp = generateOTP();
                    $otpId = storeOTP($email, $otp, 'registration');
                    
                    // Store pending registration data
                    $pendingData = [
                        'full_name' => $fullName,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'registration_number' => $formattedRegNo,
                        'state_council' => $stateCouncil,
                        'qualification' => $qualification,
                        'phone' => $phone
                    ];
                    
                    storePendingRegistration($pendingData, $otpId);
                    
                    // Store email in session for verification page
                    $_SESSION['verify_email'] = $email;
                    $_SESSION['otp_resend_time'] = time();
                    
                    // Send OTP email via PHPMailer
                    $emailSent = sendOTPEmail($email, $otp, $fullName);
                    
                    if (!$emailSent) {
                        $error = 'Failed to send verification email. Please try again.';
                    } else {
                        // Redirect to OTP verification page
                        redirect('/verify_email.php');
                    }
                }
            }
        }
    }
}

// SEO Variables
$pageTitle = 'Create Free Account | AI Homeopathy Software';
$pageDescription = 'Create your CurenexAI account - Join the leading AI-powered homeopathic healthcare platform for doctors.';
$pageKeywords = 'Curenex register, CurenexAI signup, create account, homeopathy platform, doctor registration, healthcare signup, Curenex AI';
$pageRobots = 'index, follow';
$pageCanonical = 'https://curenexai.com/register.php';
$bodyClass = 'auth-page';
$htmlClass = 'auth-page-html';
?>
<?php require_once 'includes/header.php'; ?>

<style>
    .auth-container::before {
        content: '';
        position: fixed;
        right: -15%;
        bottom: -10%;
        width: 600px;
        height: 600px;
        background: url('assets/image/xrunbg.png') center/contain no-repeat;
        opacity: 0.04;
        pointer-events: none;
        z-index: 0;
    }
</style>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-back-btn">
            <a href="<?php echo APP_URL; ?>/" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
        <div class="auth-header">
            <div class="auth-logo">
                <img src="<?php echo APP_URL; ?>/assets/image/CURENEXAI PNG.png" alt="Curenex AI" class="auth-logo-img">
            </div>
            <h1>Create Doctor Account</h1>
            <p>Join the modern homeopathic practice</p>
        </div>
        
        <div class="auth-body">
            <h2>Register</h2>
            <p class="text-muted">For certified homeopathic doctors only</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">
                            <i class="fas fa-user"></i> Full Name *
                        </label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-control" 
                            placeholder="Dr. Full Name"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            required 
                            autofocus
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="doctor@example.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Minimum 6 characters"
                            required
                        >
                    </div>
                    
                    <div class="form-group half">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm Password *
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-control" 
                            placeholder="Confirm password"
                            required
                        >
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="state_council">
                            <i class="fas fa-landmark"></i> State Council *
                        </label>
                        <select 
                            id="state_council" 
                            name="state_council" 
                            class="form-control"
                            required
                        >
                            <option value="">Select Council</option>
                            <option value="CCH" <?php echo ($_POST['state_council'] ?? '') === 'CCH' ? 'selected' : ''; ?>>Central Council of Homoeopathy (CCH)</option>
                            <option value="AP" <?php echo ($_POST['state_council'] ?? '') === 'AP' ? 'selected' : ''; ?>>Andhra Pradesh</option>
                            <option value="AR" <?php echo ($_POST['state_council'] ?? '') === 'AR' ? 'selected' : ''; ?>>Arunachal Pradesh</option>
                            <option value="AS" <?php echo ($_POST['state_council'] ?? '') === 'AS' ? 'selected' : ''; ?>>Assam</option>
                            <option value="BR" <?php echo ($_POST['state_council'] ?? '') === 'BR' ? 'selected' : ''; ?>>Bihar</option>
                            <option value="CG" <?php echo ($_POST['state_council'] ?? '') === 'CG' ? 'selected' : ''; ?>>Chhattisgarh</option>
                            <option value="DL" <?php echo ($_POST['state_council'] ?? '') === 'DL' ? 'selected' : ''; ?>>Delhi</option>
                            <option value="GA" <?php echo ($_POST['state_council'] ?? '') === 'GA' ? 'selected' : ''; ?>>Goa</option>
                            <option value="GJ" <?php echo ($_POST['state_council'] ?? '') === 'GJ' ? 'selected' : ''; ?>>Gujarat</option>
                            <option value="HR" <?php echo ($_POST['state_council'] ?? '') === 'HR' ? 'selected' : ''; ?>>Haryana</option>
                            <option value="HP" <?php echo ($_POST['state_council'] ?? '') === 'HP' ? 'selected' : ''; ?>>Himachal Pradesh</option>
                            <option value="JK" <?php echo ($_POST['state_council'] ?? '') === 'JK' ? 'selected' : ''; ?>>Jammu & Kashmir</option>
                            <option value="JH" <?php echo ($_POST['state_council'] ?? '') === 'JH' ? 'selected' : ''; ?>>Jharkhand</option>
                            <option value="KA" <?php echo ($_POST['state_council'] ?? '') === 'KA' ? 'selected' : ''; ?>>Karnataka</option>
                            <option value="KL" <?php echo ($_POST['state_council'] ?? '') === 'KL' ? 'selected' : ''; ?>>Kerala</option>
                            <option value="MP" <?php echo ($_POST['state_council'] ?? '') === 'MP' ? 'selected' : ''; ?>>Madhya Pradesh</option>
                            <option value="MH" <?php echo ($_POST['state_council'] ?? '') === 'MH' ? 'selected' : ''; ?>>Maharashtra</option>
                            <option value="MN" <?php echo ($_POST['state_council'] ?? '') === 'MN' ? 'selected' : ''; ?>>Manipur</option>
                            <option value="ML" <?php echo ($_POST['state_council'] ?? '') === 'ML' ? 'selected' : ''; ?>>Meghalaya</option>
                            <option value="MZ" <?php echo ($_POST['state_council'] ?? '') === 'MZ' ? 'selected' : ''; ?>>Mizoram</option>
                            <option value="NL" <?php echo ($_POST['state_council'] ?? '') === 'NL' ? 'selected' : ''; ?>>Nagaland</option>
                            <option value="OD" <?php echo ($_POST['state_council'] ?? '') === 'OD' ? 'selected' : ''; ?>>Odisha</option>
                            <option value="PB" <?php echo ($_POST['state_council'] ?? '') === 'PB' ? 'selected' : ''; ?>>Punjab</option>
                            <option value="RJ" <?php echo ($_POST['state_council'] ?? '') === 'RJ' ? 'selected' : ''; ?>>Rajasthan</option>
                            <option value="SK" <?php echo ($_POST['state_council'] ?? '') === 'SK' ? 'selected' : ''; ?>>Sikkim</option>
                            <option value="TN" <?php echo ($_POST['state_council'] ?? '') === 'TN' ? 'selected' : ''; ?>>Tamil Nadu</option>
                            <option value="TS" <?php echo ($_POST['state_council'] ?? '') === 'TS' ? 'selected' : ''; ?>>Telangana</option>
                            <option value="TR" <?php echo ($_POST['state_council'] ?? '') === 'TR' ? 'selected' : ''; ?>>Tripura</option>
                            <option value="UP" <?php echo ($_POST['state_council'] ?? '') === 'UP' ? 'selected' : ''; ?>>Uttar Pradesh</option>
                            <option value="UK" <?php echo ($_POST['state_council'] ?? '') === 'UK' ? 'selected' : ''; ?>>Uttarakhand</option>
                            <option value="WB" <?php echo ($_POST['state_council'] ?? '') === 'WB' ? 'selected' : ''; ?>>West Bengal</option>
                            <option value="OTHER" <?php echo ($_POST['state_council'] ?? '') === 'OTHER' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group half">
                        <label for="registration_number">
                            <i class="fas fa-id-card"></i> Registration No. *
                        </label>
                        <input 
                            type="text" 
                            id="registration_number" 
                            name="registration_number" 
                            class="form-control" 
                            placeholder="e.g., 12345 or MH-12345"
                            value="<?php echo htmlspecialchars($_POST['registration_number'] ?? ''); ?>"
                            required
                        >
                        <small class="form-hint" id="regHint">Enter your BHMS/MD registration number</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="qualification">
                            <i class="fas fa-graduation-cap"></i> Qualification *
                        </label>
                        <select 
                            id="qualification" 
                            name="qualification" 
                            class="form-control"
                            required
                        >
                            <option value="">Select Qualification</option>
                            <option value="BHMS" <?php echo ($_POST['qualification'] ?? '') === 'BHMS' ? 'selected' : ''; ?>>BHMS</option>
                            <option value="MD(Hom)" <?php echo ($_POST['qualification'] ?? '') === 'MD(Hom)' ? 'selected' : ''; ?>>MD (Homoeopathy)</option>
                            <option value="BHMS, MD(Hom)" <?php echo ($_POST['qualification'] ?? '') === 'BHMS, MD(Hom)' ? 'selected' : ''; ?>>BHMS + MD (Hom)</option>
                            <option value="DHMS" <?php echo ($_POST['qualification'] ?? '') === 'DHMS' ? 'selected' : ''; ?>>DHMS</option>
                            <option value="Other" <?php echo ($_POST['qualification'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group half">
                        <label for="phone_number">
                            <i class="fas fa-phone"></i> Phone Number *
                        </label>
                        <div class="phone-input-group">
                            <select id="country_code" name="country_code" class="country-code-select" required>
                                <option value="+91" data-flag="🇮🇳" data-length="10" <?php echo ($_POST['country_code'] ?? '+91') === '+91' ? 'selected' : ''; ?>>🇮🇳 +91</option>
                                <option value="+1" data-flag="🇺🇸" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+1' ? 'selected' : ''; ?>>🇺🇸 +1</option>
                                <option value="+44" data-flag="🇬🇧" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+44' ? 'selected' : ''; ?>>🇬🇧 +44</option>
                                <option value="+61" data-flag="🇦🇺" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+61' ? 'selected' : ''; ?>>🇦🇺 +61</option>
                                <option value="+971" data-flag="🇦🇪" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+971' ? 'selected' : ''; ?>>🇦🇪 +971</option>
                                <option value="+966" data-flag="🇸🇦" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+966' ? 'selected' : ''; ?>>🇸🇦 +966</option>
                                <option value="+974" data-flag="🇶🇦" data-length="8" <?php echo ($_POST['country_code'] ?? '') === '+974' ? 'selected' : ''; ?>>🇶🇦 +974</option>
                                <option value="+973" data-flag="🇧🇭" data-length="8" <?php echo ($_POST['country_code'] ?? '') === '+973' ? 'selected' : ''; ?>>🇧🇭 +973</option>
                                <option value="+968" data-flag="🇴🇲" data-length="8" <?php echo ($_POST['country_code'] ?? '') === '+968' ? 'selected' : ''; ?>>🇴🇲 +968</option>
                                <option value="+965" data-flag="🇰🇼" data-length="8" <?php echo ($_POST['country_code'] ?? '') === '+965' ? 'selected' : ''; ?>>🇰🇼 +965</option>
                                <option value="+977" data-flag="🇳🇵" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+977' ? 'selected' : ''; ?>>🇳🇵 +977</option>
                                <option value="+94" data-flag="🇱🇰" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+94' ? 'selected' : ''; ?>>🇱🇰 +94</option>
                                <option value="+880" data-flag="🇧🇩" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+880' ? 'selected' : ''; ?>>🇧🇩 +880</option>
                                <option value="+92" data-flag="🇵🇰" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+92' ? 'selected' : ''; ?>>🇵🇰 +92</option>
                                <option value="+60" data-flag="🇲🇾" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+60' ? 'selected' : ''; ?>>🇲🇾 +60</option>
                                <option value="+65" data-flag="🇸🇬" data-length="8" <?php echo ($_POST['country_code'] ?? '') === '+65' ? 'selected' : ''; ?>>🇸🇬 +65</option>
                                <option value="+49" data-flag="🇩🇪" data-length="11" <?php echo ($_POST['country_code'] ?? '') === '+49' ? 'selected' : ''; ?>>🇩🇪 +49</option>
                                <option value="+33" data-flag="🇫🇷" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+33' ? 'selected' : ''; ?>>🇫🇷 +33</option>
                                <option value="+39" data-flag="🇮🇹" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+39' ? 'selected' : ''; ?>>🇮🇹 +39</option>
                                <option value="+34" data-flag="🇪🇸" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+34' ? 'selected' : ''; ?>>🇪🇸 +34</option>
                                <option value="+27" data-flag="🇿🇦" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+27' ? 'selected' : ''; ?>>🇿🇦 +27</option>
                                <option value="+254" data-flag="🇰🇪" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+254' ? 'selected' : ''; ?>>🇰🇪 +254</option>
                                <option value="+234" data-flag="🇳🇬" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+234' ? 'selected' : ''; ?>>🇳🇬 +234</option>
                                <option value="+55" data-flag="🇧🇷" data-length="11" <?php echo ($_POST['country_code'] ?? '') === '+55' ? 'selected' : ''; ?>>🇧🇷 +55</option>
                                <option value="+52" data-flag="🇲🇽" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+52' ? 'selected' : ''; ?>>🇲🇽 +52</option>
                                <option value="+86" data-flag="🇨🇳" data-length="11" <?php echo ($_POST['country_code'] ?? '') === '+86' ? 'selected' : ''; ?>>🇨🇳 +86</option>
                                <option value="+81" data-flag="🇯🇵" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+81' ? 'selected' : ''; ?>>🇯🇵 +81</option>
                                <option value="+82" data-flag="🇰🇷" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+82' ? 'selected' : ''; ?>>🇰🇷 +82</option>
                                <option value="+7" data-flag="🇷🇺" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+7' ? 'selected' : ''; ?>>🇷🇺 +7</option>
                                <option value="+62" data-flag="🇮🇩" data-length="11" <?php echo ($_POST['country_code'] ?? '') === '+62' ? 'selected' : ''; ?>>🇮🇩 +62</option>
                                <option value="+66" data-flag="🇹🇭" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+66' ? 'selected' : ''; ?>>🇹🇭 +66</option>
                                <option value="+84" data-flag="🇻🇳" data-length="9" <?php echo ($_POST['country_code'] ?? '') === '+84' ? 'selected' : ''; ?>>🇻🇳 +84</option>
                                <option value="+63" data-flag="🇵🇭" data-length="10" <?php echo ($_POST['country_code'] ?? '') === '+63' ? 'selected' : ''; ?>>🇵🇭 +63</option>
                            </select>
                            <input 
                                type="tel" 
                                id="phone_number" 
                                name="phone_number" 
                                class="form-control phone-number-input" 
                                placeholder="9876543210"
                                value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                                maxlength="15"
                                required
                            >
                        </div>
                        <small class="form-hint phone-hint" id="phoneHint">Enter 10-digit mobile number</small>
                    </div>
                </div>
                
                <div class="verification-notice">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Registration Verification</strong>
                        <p>Your registration number will be verified against official records. Providing false information may result in account termination.</p>
                    </div>
                </div>
                
                <div class="form-group form-check">
                    <input type="checkbox" id="terms" name="terms" class="form-check-input" required disabled>
                    <label for="terms" class="form-check-label terms-label-disabled">
                        <span class="terms-text">I agree to the <a href="#" onclick="openTermsModal(); return false;" class="terms-link">Terms and Conditions</a> and <a href="#" onclick="openPrivacyModal(); return false;" class="terms-link">Privacy Policy</a>, and confirm I am a certified homeopathic practitioner</span>
                        <span class="terms-read-notice"><i class="fas fa-info-circle"></i> Please read both Terms and Privacy Policy</span>
                        <span class="read-status">
                            <span id="termsStatus" class="status-item not-read"><i class="fas fa-times-circle"></i> Terms</span>
                            <span id="privacyStatus" class="status-item not-read"><i class="fas fa-times-circle"></i> Privacy</span>
                        </span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
        </div>
        
        <div class="auth-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div id="termsModal" class="terms-modal">
    <div class="terms-modal-content">
        <div class="terms-modal-header">
            <h2><i class="fas fa-file-contract"></i> Terms and Conditions</h2>
            <button class="terms-close-btn" onclick="closeTermsModal()">&times;</button>
        </div>
        <div class="terms-modal-body">
            <p class="terms-effective-date"><strong>Effective Date:</strong> December 2024</p>
            
            <div class="terms-highlight" style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #065f46;"><strong><i class="fas fa-flask"></i> BETA VERSION NOTICE:</strong> <?php echo APP_NAME; ?> is currently available as a <strong>FREE BETA VERSION</strong>. Please note that this application may transition to a paid subscription model in the future. By creating an account, you acknowledge and accept that pricing terms may change with advance notice.</p>
            </div>
            
            <h3>1. Acceptance of Terms</h3>
            <p>By creating an account and using the <?php echo APP_NAME; ?> application, you agree to be bound by these Terms and Conditions. If you do not agree, please do not register or use this service.</p>
            
            <h3>2. Eligibility</h3>
            <p>This application is intended exclusively for:</p>
            <ul>
                <li>Licensed homeopathic doctors (BHMS, MD Homeopathy, or equivalent)</li>
                <li>Registered practitioners with valid medical registration numbers</li>
                <li>Healthcare professionals authorized to practice homeopathy in their jurisdiction</li>
            </ul>
            <p>By registering, you certify that you hold valid credentials and are legally permitted to practice homeopathy.</p>
            
            <h3>3. Account Responsibilities</h3>
            <ul>
                <li>You are responsible for maintaining the confidentiality of your account credentials</li>
                <li>You must provide accurate and truthful information during registration</li>
                <li>You are solely responsible for all activities under your account</li>
                <li>You must notify us immediately of any unauthorized access to your account</li>
            </ul>
            
            <h3>4. Patient Data & Privacy</h3>
            <ul>
                <li>You are responsible for obtaining proper consent from patients before storing their data</li>
                <li>Patient information must be handled in compliance with applicable healthcare privacy laws (HIPAA, GDPR, or local regulations)</li>
                <li>We provide the platform; you are responsible for data accuracy and lawful processing</li>
                <li>Patient data remains under your custody and control</li>
            </ul>
            
            <h3>5. Application Features</h3>
            <p>This application provides the following key features:</p>
            
            <h4 style="color: #059669; margin-top: 1rem;"><i class="fas fa-stethoscope"></i> Diagnose</h4>
            <p>AI-powered disease diagnosis tool that analyzes symptoms and suggests potential diagnoses with recommended homeopathic remedies based on our comprehensive database.</p>
            
            <h4 style="color: #8b5cf6; margin-top: 1rem;"><i class="fas fa-camera-retro"></i> Dermo (Skin Analysis)</h4>
            <p>Advanced skin condition analysis using AI and image recognition. Upload photos or use live camera capture to identify skin conditions and receive homeopathic remedy suggestions from our RAG database.</p>
            
            <h4 style="color: #3b82f6; margin-top: 1rem;"><i class="fas fa-book-medical"></i> Repertory</h4>
            <p>Comprehensive homeopathic repertory with rubric search, symptom matching, and remedy suggestions based on Kent's Repertory and other authoritative sources.</p>
            
            <h4 style="color: #f59e0b; margin-top: 1rem;"><i class="fas fa-pills"></i> Materia Medica</h4>
            <p>Detailed materia medica database including Boericke's Materia Medica and other standard references for remedy information and indications.</p>
            
            <h4 style="color: #10b981; margin-top: 1rem;"><i class="fas fa-users"></i> Patient Management</h4>
            <p>Comprehensive patient records management, consultation history, follow-ups, and prescription tracking.</p>
            
            <h3>6. AI-Powered Suggestions Disclaimer</h3>
            <div class="terms-highlight">
                <p><strong>IMPORTANT:</strong> This application includes AI-powered features including:</p>
                <ul>
                    <li><strong>Diagnose:</strong> AI symptom analysis and disease prediction</li>
                    <li><strong>Dermo:</strong> AI skin condition analysis from uploaded images</li>
                    <li><strong>RAG Suggestions:</strong> Retrieval-Augmented Generation for remedy recommendations</li>
                    <li><strong>Gemini AI:</strong> Advanced AI analysis and suggestions</li>
                </ul>
                <p>All AI-powered suggestions:</p>
                <ul>
                    <li>Are for <strong>educational and reference purposes only</strong></li>
                    <li>Should <strong>NOT replace</strong> professional medical judgment or clinical expertise</li>
                    <li>Must be verified against authoritative homeopathic texts (Kent, Boericke, Allen, etc.)</li>
                    <li>Require consideration of individual patient characteristics before use</li>
                    <li>May not be 100% accurate and should be used as a starting point for analysis</li>
                </ul>
                <p><strong>The practitioner bears full responsibility for all treatment decisions.</strong> The AI suggestions are tools to assist, not replace, clinical judgment.</p>
            </div>
            
            <h3>7. Beta Service & Future Pricing</h3>
            <p>This application is currently offered as a FREE BETA VERSION:</p>
            <ul>
                <li>All features are currently available at no cost during the beta period</li>
                <li><strong>This application may become a paid subscription service in the future</strong></li>
                <li>Different pricing tiers may be introduced for different feature sets</li>
                <li>Existing users will receive at least 30 days advance notice before any pricing changes</li>
                <li>Access during beta does not guarantee free access after the beta period ends</li>
            </ul>
            
            <h3>8. Limitation of Liability</h3>
            <p>To the maximum extent permitted by law:</p>
            <ul>
                <li>We are not liable for any clinical decisions made using this application</li>
                <li>We do not guarantee the accuracy of AI suggestions or database content</li>
                <li>We are not responsible for patient outcomes resulting from treatment decisions</li>
                <li>The application is provided "as is" without warranties of any kind</li>
            </ul>
            
            <h3>9. Acceptable Use</h3>
            <p>You agree NOT to:</p>
            <ul>
                <li>Use the application for any unlawful purpose</li>
                <li>Share your account credentials with unauthorized persons</li>
                <li>Attempt to hack, reverse engineer, or compromise the application</li>
                <li>Use the application to provide care you are not qualified to give</li>
                <li>Store false or misleading patient information</li>
            </ul>
            
            <h3>10. Intellectual Property</h3>
            <p>The application, including its design, features, and content (excluding patient data you input), is our intellectual property. The homeopathic materia medica and repertory data is compiled from public domain sources.</p>
            
            <h3>11. Data Security</h3>
            <p>We implement reasonable security measures to protect data. However:</p>
            <ul>
                <li>No system is 100% secure</li>
                <li>You are responsible for maintaining secure access to your devices</li>
                <li>Regular password changes are recommended</li>
            </ul>
            
            <h3>12. Termination</h3>
            <p>We reserve the right to suspend or terminate accounts that violate these terms. Upon termination, you should export any necessary patient data.</p>
            
            <h3>13. Changes to Terms</h3>
            <p>We may update these terms periodically. Continued use of the application after changes constitutes acceptance of new terms.</p>
            
            <h3>14. Governing Law</h3>
            <p>These terms are governed by applicable laws in your jurisdiction. Any disputes will be resolved in appropriate courts.</p>
            
            <h3>15. Contact</h3>
            <p>For questions about these terms, please visit our <a href="<?php echo APP_URL; ?>/support.php" target="_blank">Support Page</a> or email support@homeopathicassistant.com.</p>
            
            <div class="terms-agreement-note">
                <i class="fas fa-info-circle"></i>
                <p>By checking the "I agree" checkbox and creating an account, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</p>
            </div>
        </div>
        <div class="terms-modal-footer">
            <button class="btn btn-secondary" onclick="closeTermsModal()">Close</button>
            <button class="btn btn-primary" onclick="acceptTerms()">
                <i class="fas fa-check"></i> I Accept
            </button>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div id="privacyModal" class="terms-modal">
    <div class="terms-modal-content">
        <div class="terms-modal-header" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
            <h2><i class="fas fa-shield-alt"></i> Privacy Policy</h2>
            <button class="terms-close-btn" onclick="closePrivacyModal()">&times;</button>
        </div>
        <div class="terms-modal-body">
            <p class="terms-effective-date"><strong>Effective Date:</strong> December 2024</p>
            
            <h3>1. Introduction</h3>
            <p><?php echo APP_NAME; ?> ("we", "us", or "our") is committed to protecting your privacy and the privacy of your patients. This Privacy Policy explains how we collect, use, store, and protect information when you use our application.</p>
            
            <h3>2. Information We Collect</h3>
            <h4 style="color: #059669; margin-top: 0.75rem;">Doctor Information</h4>
            <ul>
                <li>Full name and professional credentials</li>
                <li>Email address and phone number</li>
                <li>State Council registration number</li>
                <li>Professional qualifications (BHMS, MD, etc.)</li>
                <li>Login credentials (password stored encrypted)</li>
            </ul>
            
            <h4 style="color: #059669; margin-top: 0.75rem;">Patient Information (Entered by You)</h4>
            <ul>
                <li>Patient name, age, gender, contact details</li>
                <li>Medical history and symptoms</li>
                <li>Consultation notes and prescriptions</li>
                <li>Follow-up records</li>
            </ul>
            
            <h4 style="color: #8b5cf6; margin-top: 0.75rem;"><i class="fas fa-camera-retro"></i> Dermo (Skin Analysis) Data</h4>
            <ul>
                <li>Skin images uploaded for AI analysis</li>
                <li>Camera captures used for skin condition detection</li>
                <li>Analysis results and AI-generated suggestions</li>
                <li>RAG database matches and remedy recommendations</li>
            </ul>
            
            <h4 style="color: #3b82f6; margin-top: 0.75rem;"><i class="fas fa-stethoscope"></i> Diagnose Data</h4>
            <ul>
                <li>Symptoms entered for disease diagnosis</li>
                <li>AI analysis results and disease predictions</li>
                <li>Recommended remedies from analysis</li>
            </ul>
            
            <h4 style="color: #f59e0b; margin-top: 0.75rem;"><i class="fas fa-book-medical"></i> Repertory & Materia Medica Usage</h4>
            <ul>
                <li>Search queries and rubric selections</li>
                <li>Remedy lookups and favorites</li>
                <li>AI suggestion requests</li>
            </ul>
            
            <h3>3. How We Use Your Information</h3>
            <table style="width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.9rem;">
                <tr style="background: var(--bg-light, #f8f9fa);">
                    <th style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6); text-align: left;">Data Type</th>
                    <th style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6); text-align: left;">Purpose</th>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Doctor credentials</td>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Account authentication and verification</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Patient records</td>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">To provide patient management services</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Dermo skin images</td>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">AI skin analysis and remedy matching</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Diagnose symptoms</td>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">AI disease diagnosis and suggestions</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Repertory searches</td>
                    <td style="padding: 0.75rem; border: 1px solid var(--border-color, #dee2e6);">Remedy suggestions and AI analysis</td>
                </tr>
            </table>
            
            <h3>4. AI Processing & Third-Party Services</h3>
            <div class="terms-highlight" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-color: #10b981;">
                <p style="color: #065f46;"><strong><i class="fas fa-robot"></i> AI Features Notice:</strong></p>
                <ul style="color: #065f46;">
                    <li><strong>Dermo Analysis:</strong> Skin images may be processed by Google Gemini AI for condition identification</li>
                    <li><strong>Diagnose:</strong> Symptoms may be sent to AI services for analysis</li>
                    <li><strong>RAG System:</strong> Local database matching for remedy suggestions</li>
                    <li>AI providers have their own privacy policies governing data handling</li>
                    <li>We do not permanently store images sent to external AI services</li>
                </ul>
            </div>
            
            <h3>5. Data Storage & Security</h3>
            <ul>
                <li>Data is stored on secure servers with encryption</li>
                <li>Passwords are hashed using industry-standard algorithms</li>
                <li>Access is restricted to authenticated users only</li>
                <li>Regular security audits and updates are performed</li>
                <li>Dermo images are stored temporarily and can be deleted</li>
            </ul>
            
            <h3>6. Data Retention</h3>
            <ul>
                <li>Account data: Retained while account is active</li>
                <li>Patient records: Retained as per medical record regulations</li>
                <li>Dermo images: Can be deleted by user; temporary processing copies are not retained</li>
                <li>Analysis logs: Retained for service improvement (anonymized)</li>
            </ul>
            
            <h3>7. Your Rights</h3>
            <p>You have the right to:</p>
            <ul>
                <li>Access your personal data</li>
                <li>Correct inaccurate information</li>
                <li>Request deletion of your account</li>
                <li>Export your patient data</li>
                <li>Opt-out of non-essential communications</li>
            </ul>
            
            <h3>8. Patient Data Responsibility</h3>
            <div class="terms-highlight">
                <p style="color: #856404;"><strong>Important:</strong> As the practitioner, you are responsible for:</p>
                <ul style="color: #856404;">
                    <li>Obtaining proper consent from patients before storing their data</li>
                    <li>Complying with healthcare privacy laws (HIPAA, GDPR, local regulations)</li>
                    <li>Informing patients about how their data is used, including AI analysis</li>
                    <li>Securing access to your account and devices</li>
                </ul>
            </div>
            
            <h3>9. Cookies & Tracking</h3>
            <p>We use essential cookies for:</p>
            <ul>
                <li>Session management and authentication</li>
                <li>User preferences and settings</li>
                <li>Security (CSRF protection)</li>
            </ul>
            
            <h3>10. Changes to Privacy Policy</h3>
            <p>We may update this policy periodically. Significant changes will be communicated via email or in-app notification. Continued use after changes constitutes acceptance.</p>
            
            <h3>11. Contact Us</h3>
            <p>For privacy-related questions or data requests, contact us through our <a href="<?php echo APP_URL; ?>/support.php" target="_blank">Support Page</a> or email privacy@homeopathicassistant.com.</p>
            
            <div class="terms-agreement-note" style="background: #ecfdf5; border-color: #10b981;">
                <i class="fas fa-shield-alt" style="color: #059669;"></i>
                <p style="color: #065f46;">By checking the "I agree" checkbox, you acknowledge that you have read and understood this Privacy Policy and consent to the collection and processing of data as described.</p>
            </div>
        </div>
        <div class="terms-modal-footer">
            <button class="btn btn-secondary" onclick="closePrivacyModal()">Close</button>
            <button class="btn btn-primary" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);" onclick="acceptPrivacy()">
                <i class="fas fa-check"></i> I Accept
            </button>
        </div>
    </div>
</div>

<style>
/* ============================================
   REGISTER PAGE RESPONSIVE STYLES
   ============================================ */

/* Auth container adjustments for register page */
.auth-page .auth-container {
    max-width: 520px;
    padding: 0 1rem;
}

.auth-page .auth-box {
    margin: 1rem 0;
}

/* Form row - two columns on larger screens */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.form-row .form-group {
    flex: 1 1 100%;
}

@media (min-width: 576px) {
    .form-row .form-group.half {
        flex: 1 1 calc(50% - 0.5rem);
        max-width: calc(50% - 0.5rem);
    }
}

/* Form controls */
.auth-form .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color, #dee2e6);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.auth-form .form-control:focus {
    border-color: var(--primary-color, #007bff);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    outline: none;
}

/* Form group spacing */
.auth-form .form-group {
    margin-bottom: 1rem;
}

.auth-form .form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 500;
    font-size: 0.9rem;
    color: var(--text-color, #333);
}

/* Checkbox styling */
.form-check {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin: 1.25rem 0;
    padding: 1rem;
    background: var(--bg-light, #f8f9fa);
    border-radius: 8px;
    border: 1px solid var(--border-color, #dee2e6);
}

.form-check-input {
    width: 20px;
    height: 20px;
    margin-top: 2px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: var(--primary-color, #007bff);
}

.form-check-label {
    font-size: 0.9rem;
    line-height: 1.5;
    cursor: pointer;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 0;
}

.terms-text {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.terms-read-notice {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: #dc3545;
    padding: 0.35rem 0.5rem;
    background: #fff5f5;
    border-radius: 4px;
    width: fit-content;
}

.terms-read-notice.hidden {
    display: none;
}

/* Read status indicators */
.read-status {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.status-item.not-read {
    background: #fee2e2;
    color: #dc2626;
}

.status-item.read {
    background: #dcfce7;
    color: #16a34a;
}

.status-item.read i {
    color: #16a34a;
}

.status-item.not-read i {
    color: #dc2626;
}

/* Phone Input Group Styles */
.phone-input-group {
    display: flex;
    gap: 0;
    width: 100%;
}

.country-code-select {
    flex: 0 0 auto;
    width: auto;
    min-width: 95px;
    max-width: 110px;
    padding: 0.75rem 0.5rem;
    font-size: 0.95rem;
    border: 1px solid var(--border-color, #dee2e6);
    border-right: none;
    border-radius: 8px 0 0 8px;
    background: var(--bg-light, #f8f9fa);
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    padding-right: 24px;
}

.country-code-select:focus {
    outline: none;
    border-color: var(--primary-color, #007bff);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    z-index: 1;
}

.phone-number-input {
    flex: 1;
    border-radius: 0 8px 8px 0 !important;
    min-width: 0;
}

.phone-hint {
    color: var(--text-muted, #6c757d);
    font-size: 0.75rem;
    margin-top: 0.35rem;
    display: block;
}

.phone-hint.valid {
    color: #28a745;
}

.phone-hint.invalid {
    color: #dc3545;
}

.phone-number-input.is-valid {
    border-color: #28a745;
}

.phone-number-input.is-invalid {
    border-color: #dc3545;
}

/* Mobile responsive for phone input */
@media (max-width: 400px) {
    .country-code-select {
        min-width: 85px;
        max-width: 95px;
        font-size: 0.85rem;
        padding: 0.6rem 0.4rem;
        padding-right: 20px;
    }
    
    .phone-number-input {
        font-size: 0.9rem;
    }
}

#terms:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.terms-label-disabled {
    opacity: 0.8;
}

#terms:not(:disabled) + .form-check-label {
    opacity: 1;
}

#terms:not(:disabled) + .form-check-label .terms-read-notice {
    display: none;
}

/* Button block */
.btn-block {
    width: 100%;
    padding: 0.875rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

/* Terms Link */
.terms-link {
    color: var(--primary-color, #007bff);
    text-decoration: underline;
    font-weight: 500;
}
.terms-link:hover {
    color: var(--primary-dark, #0056b3);
}

/* Terms Modal */
.terms-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    backdrop-filter: blur(3px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.terms-modal-content {
    background-color: var(--card-bg, #fff);
    margin: 2% auto;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.terms-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    background: linear-gradient(135deg, var(--primary-color, #007bff) 0%, var(--primary-dark, #0056b3) 100%);
    color: white;
    border-radius: 12px 12px 0 0;
}

.terms-modal-header h2 {
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.terms-close-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    transition: background 0.2s;
}
.terms-close-btn:hover {
    background: rgba(255,255,255,0.3);
}

.terms-modal-body {
    padding: 1.25rem;
    overflow-y: auto;
    flex: 1;
    line-height: 1.6;
    font-size: 0.95rem;
}

.terms-modal-body h3 {
    color: var(--primary-color, #007bff);
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    border-bottom: 2px solid var(--primary-light, #e3f2fd);
    padding-bottom: 0.5rem;
}

.terms-modal-body h3:first-of-type {
    margin-top: 0;
}

.terms-modal-body p {
    margin-bottom: 0.5rem;
    color: var(--text-color, #333);
}

.terms-modal-body ul {
    margin: 0.5rem 0 0.75rem 1.25rem;
    color: var(--text-muted, #666);
    padding-left: 0;
}

.terms-modal-body ul li {
    margin-bottom: 0.35rem;
}

.terms-effective-date {
    background: var(--bg-light, #f8f9fa);
    padding: 0.6rem 0.8rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.terms-highlight {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 0.875rem;
    margin: 1rem 0;
}

.terms-highlight p {
    color: #856404;
    margin-bottom: 0.5rem;
}

.terms-highlight ul {
    color: #856404;
    margin-bottom: 0.5rem;
}

.terms-agreement-note {
    display: flex;
    gap: 0.75rem;
    background: var(--primary-light, #e3f2fd);
    border: 1px solid var(--primary-color, #007bff);
    border-radius: 8px;
    padding: 0.875rem;
    margin-top: 1.25rem;
    align-items: flex-start;
}

.terms-agreement-note i {
    color: var(--primary-color, #007bff);
    font-size: 1.25rem;
    flex-shrink: 0;
}

.terms-agreement-note p {
    margin: 0;
    color: var(--primary-dark, #0056b3);
    font-weight: 500;
    font-size: 0.9rem;
}

.terms-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    border-top: 1px solid var(--border-color, #dee2e6);
    background: var(--bg-light, #f8f9fa);
    border-radius: 0 0 12px 12px;
}

.terms-modal-footer .btn {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

/* Dark mode support */
[data-theme="dark"] .terms-modal-content {
    background-color: var(--card-bg, #1e1e1e);
}

[data-theme="dark"] .terms-modal-body p,
[data-theme="dark"] .terms-modal-body ul {
    color: var(--text-color, #e0e0e0);
}

[data-theme="dark"] .terms-highlight {
    background: linear-gradient(135deg, #3d3000 0%, #4a3b00 100%);
    border-color: #6d5200;
}

[data-theme="dark"] .terms-highlight p,
[data-theme="dark"] .terms-highlight ul {
    color: #ffc107;
}

/* ============================================
   RESPONSIVE BREAKPOINTS
   ============================================ */

/* Extra small devices (phones, less than 576px) */
@media (max-width: 575.98px) {
    .auth-page .auth-container {
        padding: 0 0.5rem;
        max-width: 100%;
    }
    
    .auth-page .auth-box {
        margin: 0.5rem 0;
        border-radius: 12px;
    }
    
    .auth-header {
        padding: 1.5rem 1rem;
    }
    
    .auth-header h1 {
        font-size: 1.25rem;
    }
    
    .auth-logo {
        width: 60px;
        height: 60px;
    }
    
    .auth-logo i {
        font-size: 1.75rem;
    }
    
    .auth-body {
        padding: 1.25rem 1rem;
    }
    
    .auth-body h2 {
        font-size: 1.1rem;
    }
    
    .auth-form .form-control {
        padding: 0.625rem 0.875rem;
        font-size: 16px; /* Prevents iOS zoom */
    }
    
    .form-row {
        gap: 0;
    }
    
    .form-row .form-group.half {
        flex: 1 1 100%;
        max-width: 100%;
    }
    
    .btn-block {
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
    }
    
    .form-check {
        padding: 0.75rem;
        gap: 0.5rem;
    }
    
    .form-check-input {
        width: 22px;
        height: 22px;
    }
    
    .form-check-label {
        font-size: 0.85rem;
        line-height: 1.4;
    }
    
    .terms-read-notice {
        font-size: 0.75rem;
    }
    
    .auth-footer {
        padding: 1rem;
        font-size: 0.85rem;
    }
    
    /* Terms Modal - Full screen on mobile */
    .terms-modal-content {
        margin: 0;
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border-radius: 0;
    }
    
    .terms-modal-header {
        border-radius: 0;
        padding: 0.875rem 1rem;
        position: sticky;
        top: 0;
    }
    
    .terms-modal-header h2 {
        font-size: 1rem;
    }
    
    .terms-modal-body {
        padding: 1rem;
        font-size: 0.9rem;
    }
    
    .terms-modal-body h3 {
        font-size: 0.95rem;
    }
    
    .terms-modal-body ul {
        margin-left: 1rem;
    }
    
    .terms-highlight {
        padding: 0.75rem;
    }
    
    .terms-agreement-note {
        flex-direction: column;
        gap: 0.5rem;
        padding: 0.75rem;
    }
    
    .terms-modal-footer {
        border-radius: 0;
        padding: 0.75rem 1rem;
        position: sticky;
        bottom: 0;
        flex-wrap: wrap;
    }
    
    .terms-modal-footer .btn {
        flex: 1;
        min-width: 100px;
    }
}

/* Small devices (landscape phones, 576px and up) */
@media (min-width: 576px) and (max-width: 767.98px) {
    .auth-page .auth-container {
        max-width: 480px;
    }
    
    .terms-modal-content {
        margin: 3% auto;
        width: 95%;
    }
}

/* Medium devices (tablets, 768px and up) */
@media (min-width: 768px) {
    .auth-page .auth-container {
        max-width: 520px;
    }
    
    .auth-header {
        padding: 2rem 2rem;
    }
    
    .auth-body {
        padding: 2rem;
    }
    
    .terms-modal-content {
        margin: 5% auto;
        width: 90%;
        max-width: 700px;
    }
    
    .terms-modal-header h2 {
        font-size: 1.25rem;
    }
    
    .terms-modal-body {
        padding: 1.5rem;
    }
}

/* Large devices (desktops, 992px and up) */
@media (min-width: 992px) {
    .terms-modal-content {
        max-width: 800px;
    }
}

/* Landscape mode on small devices */
@media (max-height: 500px) and (orientation: landscape) {
    .auth-header {
        padding: 1rem;
    }
    
    .auth-logo {
        width: 50px;
        height: 50px;
        margin-bottom: 0.5rem;
    }
    
    .auth-header h1 {
        font-size: 1.1rem;
    }
    
    .auth-header p {
        display: none;
    }
    
    .auth-body {
        padding: 1rem;
    }
    
    .auth-body h2,
    .auth-body > p {
        display: none;
    }
    
    .auth-form .form-group {
        margin-bottom: 0.5rem;
    }
    
    .form-check {
        margin: 0.75rem 0;
    }
}

/* High DPI / Retina support */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .auth-form .form-control {
        -webkit-font-smoothing: antialiased;
    }
}

/* Touch device optimizations */
@media (hover: none) and (pointer: coarse) {
    .auth-form .form-control {
        min-height: 44px; /* Apple's recommended touch target */
    }
    
    .btn-block {
        min-height: 48px;
    }
    
    .form-check-input {
        width: 22px;
        height: 22px;
    }
    
    .terms-close-btn {
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

/* Reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .terms-modal,
    .terms-modal-content {
        animation: none;
    }
    
    .auth-form .form-control,
    .terms-close-btn {
        transition: none;
    }
}
</style>

<script>
let termsRead = false;
let privacyRead = false;

// Phone validation patterns by country code
const phonePatterns = {
    '+91': { pattern: /^[6-9][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 6-9' },
    '+1': { pattern: /^[2-9][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number' },
    '+44': { pattern: /^[1-9][0-9]{9,10}$/, length: 10, hint: 'Enter 10-11 digit number' },
    '+61': { pattern: /^[4][0-9]{8}$/, length: 9, hint: 'Enter 9-digit mobile number starting with 4' },
    '+971': { pattern: /^[5][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number starting with 5' },
    '+966': { pattern: /^[5][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number starting with 5' },
    '+974': { pattern: /^[3567][0-9]{7}$/, length: 8, hint: 'Enter 8-digit number' },
    '+973': { pattern: /^[3][0-9]{7}$/, length: 8, hint: 'Enter 8-digit number starting with 3' },
    '+968': { pattern: /^[79][0-9]{7}$/, length: 8, hint: 'Enter 8-digit number starting with 7 or 9' },
    '+965': { pattern: /^[569][0-9]{7}$/, length: 8, hint: 'Enter 8-digit number' },
    '+977': { pattern: /^[9][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 9' },
    '+94': { pattern: /^[7][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number starting with 7' },
    '+880': { pattern: /^[1][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 1' },
    '+92': { pattern: /^[3][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 3' },
    '+60': { pattern: /^[1][0-9]{8,9}$/, length: 10, hint: 'Enter 9-10 digit number' },
    '+65': { pattern: /^[89][0-9]{7}$/, length: 8, hint: 'Enter 8-digit number starting with 8 or 9' },
    '+49': { pattern: /^[1][5-7][0-9]{8,9}$/, length: 11, hint: 'Enter 10-11 digit mobile number' },
    '+33': { pattern: /^[67][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number starting with 6 or 7' },
    '+39': { pattern: /^[3][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 3' },
    '+34': { pattern: /^[67][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number starting with 6 or 7' },
    '+27': { pattern: /^[6-8][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number' },
    '+254': { pattern: /^[7][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number starting with 7' },
    '+234': { pattern: /^[789][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number' },
    '+55': { pattern: /^[1-9][0-9]{10}$/, length: 11, hint: 'Enter 11-digit number' },
    '+52': { pattern: /^[1-9][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number' },
    '+86': { pattern: /^[1][3-9][0-9]{9}$/, length: 11, hint: 'Enter 11-digit number starting with 1' },
    '+81': { pattern: /^[789][0-9]{8,9}$/, length: 10, hint: 'Enter 9-10 digit number' },
    '+82': { pattern: /^[1][0-9]{9,10}$/, length: 10, hint: 'Enter 10-11 digit number' },
    '+7': { pattern: /^[9][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 9' },
    '+62': { pattern: /^[8][0-9]{9,11}$/, length: 11, hint: 'Enter 10-12 digit number starting with 8' },
    '+66': { pattern: /^[689][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number' },
    '+84': { pattern: /^[3589][0-9]{8}$/, length: 9, hint: 'Enter 9-digit number' },
    '+63': { pattern: /^[9][0-9]{9}$/, length: 10, hint: 'Enter 10-digit number starting with 9' },
    'DEFAULT': { pattern: /^[0-9]{7,15}$/, length: 10, hint: 'Enter phone number' }
};

// Update phone hint and validation based on country code
function updatePhoneValidation() {
    const countrySelect = document.getElementById('country_code');
    const phoneInput = document.getElementById('phone_number');
    const phoneHint = document.getElementById('phoneHint');
    
    const countryCode = countrySelect.value;
    const config = phonePatterns[countryCode] || phonePatterns['DEFAULT'];
    
    // Update hint text
    phoneHint.textContent = config.hint;
    phoneHint.className = 'form-hint phone-hint';
    
    // Update maxlength
    phoneInput.maxLength = config.length + 2; // Allow a bit more for flexibility
    phoneInput.placeholder = '9'.repeat(config.length);
    
    // Revalidate if there's a value
    if (phoneInput.value) {
        validatePhoneInput();
    }
}

// Validate phone input
function validatePhoneInput() {
    const countrySelect = document.getElementById('country_code');
    const phoneInput = document.getElementById('phone_number');
    const phoneHint = document.getElementById('phoneHint');
    
    const countryCode = countrySelect.value;
    const config = phonePatterns[countryCode] || phonePatterns['DEFAULT'];
    const phone = phoneInput.value.replace(/[\s\-\.]/g, '');
    
    if (!phone) {
        phoneInput.classList.remove('is-valid', 'is-invalid');
        phoneHint.className = 'form-hint phone-hint';
        phoneHint.textContent = config.hint;
        return;
    }
    
    if (config.pattern.test(phone)) {
        phoneInput.classList.remove('is-invalid');
        phoneInput.classList.add('is-valid');
        phoneHint.className = 'form-hint phone-hint valid';
        phoneHint.textContent = '✓ Valid phone number';
    } else {
        phoneInput.classList.remove('is-valid');
        phoneInput.classList.add('is-invalid');
        phoneHint.className = 'form-hint phone-hint invalid';
        phoneHint.textContent = config.hint;
    }
}

// Only allow numbers in phone input
function filterPhoneInput(e) {
    // Allow only numbers
    const input = e.target;
    input.value = input.value.replace(/[^0-9]/g, '');
    validatePhoneInput();
}

function openTermsModal() {
    document.getElementById('termsModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeTermsModal() {
    document.getElementById('termsModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function openPrivacyModal() {
    document.getElementById('privacyModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePrivacyModal() {
    document.getElementById('privacyModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function updateTermsStatus() {
    const termsStatus = document.getElementById('termsStatus');
    if (termsRead) {
        termsStatus.classList.remove('not-read');
        termsStatus.classList.add('read');
        termsStatus.innerHTML = '<i class="fas fa-check-circle"></i> Terms';
    }
}

function updatePrivacyStatus() {
    const privacyStatus = document.getElementById('privacyStatus');
    if (privacyRead) {
        privacyStatus.classList.remove('not-read');
        privacyStatus.classList.add('read');
        privacyStatus.innerHTML = '<i class="fas fa-check-circle"></i> Privacy';
    }
}

function checkBothRead() {
    if (termsRead && privacyRead) {
        const checkbox = document.getElementById('terms');
        const label = checkbox.nextElementSibling;
        const notice = document.querySelector('.terms-read-notice');
        
        checkbox.disabled = false;
        label.classList.remove('terms-label-disabled');
        if (notice) notice.classList.add('hidden');
    }
}

function acceptTerms() {
    termsRead = true;
    updateTermsStatus();
    checkBothRead();
    closeTermsModal();
    
    // If both read, check the checkbox
    if (termsRead && privacyRead) {
        document.getElementById('terms').checked = true;
    }
}

function acceptPrivacy() {
    privacyRead = true;
    updatePrivacyStatus();
    checkBothRead();
    closePrivacyModal();
    
    // If both read, check the checkbox
    if (termsRead && privacyRead) {
        document.getElementById('terms').checked = true;
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Phone validation setup
    const countrySelect = document.getElementById('country_code');
    const phoneInput = document.getElementById('phone_number');
    
    if (countrySelect && phoneInput) {
        countrySelect.addEventListener('change', updatePhoneValidation);
        phoneInput.addEventListener('input', filterPhoneInput);
        phoneInput.addEventListener('blur', validatePhoneInput);
        
        // Initialize hint on page load
        updatePhoneValidation();
    }
    
    // Terms modal scroll tracking
    const termsModalBody = document.querySelector('#termsModal .terms-modal-body');
    if (termsModalBody) {
        termsModalBody.addEventListener('scroll', function() {
            const scrolledToBottom = (this.scrollHeight - this.scrollTop - this.clientHeight) < 50;
            if (scrolledToBottom && !termsRead) {
                termsRead = true;
                updateTermsStatus();
                checkBothRead();
            }
        });
    }
    
    // Privacy modal scroll tracking
    const privacyModalBody = document.querySelector('#privacyModal .terms-modal-body');
    if (privacyModalBody) {
        privacyModalBody.addEventListener('scroll', function() {
            const scrolledToBottom = (this.scrollHeight - this.scrollTop - this.clientHeight) < 50;
            if (scrolledToBottom && !privacyRead) {
                privacyRead = true;
                updatePrivacyStatus();
                checkBothRead();
            }
        });
    }
});

// Close modals when clicking outside
document.getElementById('termsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTermsModal();
    }
});

document.getElementById('privacyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePrivacyModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTermsModal();
        closePrivacyModal();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
