<!DOCTYPE html>
<html lang="en" class="<?php echo isset($htmlClass) ? $htmlClass : ''; ?>" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#14b8a6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Curenex">
    <meta name="application-name" content="Curenex">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - CurenexAI' : 'CurenexAI - AI-Powered Homeopathic Healthcare'; ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Curenex (CurenexAI) - The leading AI-powered homeopathic healthcare platform. Decode Health, Deliver Cure with intelligent diagnosis and personalized treatment.'; ?>">
    <meta name="keywords" content="Curenex, CurenexAI, Curenex AI, curenex, curenexai, homeopathy AI, AI healthcare, homeopathic software, AI diagnosis, remedy finder, <?php echo isset($pageKeywords) ? $pageKeywords : ''; ?>">
    <meta name="author" content="CurenexAI">
    <meta name="robots" content="<?php echo isset($pageRobots) ? $pageRobots : 'noindex, nofollow'; ?>">
    <link rel="canonical" href="<?php echo isset($pageCanonical) ? $pageCanonical : APP_URL; ?>">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Curenex">
    <meta property="og:title" content="<?php echo isset($pageTitle) ? $pageTitle . ' - Curenex' : 'Curenex - AI Healthcare'; ?>">
    <meta property="og:description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Curenex AI-powered homeopathic healthcare platform'; ?>">
    <meta property="og:image" content="<?php echo APP_URL; ?>/assets/image/CURENEXAI PNG.png">
    <meta property="og:url" content="<?php echo isset($pageCanonical) ? $pageCanonical : APP_URL; ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:site" content="@curenexai">
    <meta name="twitter:title" content="<?php echo isset($pageTitle) ? $pageTitle . ' - Curenex' : 'Curenex'; ?>">
    <meta name="twitter:description" content="<?php echo isset($pageDescription) ? $pageDescription : 'AI-powered homeopathic healthcare'; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo APP_URL; ?>/assets/image/CURENEXAI ICON.png">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/image/CURENEXAI ICON.png">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    
    <!-- Preconnect / DNS-prefetch to critical origins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="dns-prefetch" href="https://code.jquery.com">

    <!-- Google Fonts - Inter (non-blocking with swap) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>

    <?php
    // Use file modification time instead of time() so browsers can cache assets
    $__cssMtime = @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1';
    ?>
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo $__cssMtime; ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css"></noscript>
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo APP_URL . $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Font Display Override for Font Awesome (improves FCP by ~30ms) -->
    <style>
        @font-face{font-family:'Font Awesome 6 Brands';font-display:swap}
        @font-face{font-family:'Font Awesome 6 Free';font-display:swap}
        @font-face{font-family:'Font Awesome 6 Solid';font-display:swap}
        @font-face{font-family:'fa-brands-400';font-display:swap}
        @font-face{font-family:'fa-solid-900';font-display:swap}
    </style>
    
    <!-- Page Loader CSS (inline for immediate display) -->
    <style>
        .page-loader{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.98);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;transition:opacity .3s,visibility .3s}
        .page-loader.hidden{opacity:0;visibility:hidden;pointer-events:none}
        .page-loader .loader-spinner{width:50px;height:50px;border:4px solid #e5e7eb;border-top-color:#6366f1;border-radius:50%;animation:spin .8s linear infinite}
        .page-loader .loader-text{margin-top:16px;color:#4b5563;font-size:.95rem;font-weight:500}
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>

    <!-- Schema.org JSON-LD: Organization + WebSite + SoftwareApplication (improves Gemini / Google AI visibility) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": ["Organization", "MedicalOrganization"],
          "@id": "<?php echo APP_URL; ?>/#organization",
          "name": "CurenexAI",
          "alternateName": ["Curenex", "Curenex AI"],
          "url": "<?php echo APP_URL; ?>/",
          "logo": "<?php echo APP_URL; ?>/assets/image/CURENEXAI%20PNG.png",
          "image": "<?php echo APP_URL; ?>/assets/image/CURENEXAI%20PNG.png",
          "description": "CurenexAI is an AI-powered homeopathic healthcare platform for certified doctors providing intelligent diagnosis, repertory analysis and personalized treatment.",
          "medicalSpecialty": "Homeopathic",
          "sameAs": [
            "https://twitter.com/curenexai"
          ]
        },
        {
          "@type": "WebSite",
          "@id": "<?php echo APP_URL; ?>/#website",
          "url": "<?php echo APP_URL; ?>/",
          "name": "CurenexAI",
          "publisher": { "@id": "<?php echo APP_URL; ?>/#organization" },
          "inLanguage": "en",
          "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo APP_URL; ?>/search.php?q={search_term_string}",
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "SoftwareApplication",
          "name": "CurenexAI",
          "applicationCategory": "HealthApplication",
          "operatingSystem": "Web",
          "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
          "publisher": { "@id": "<?php echo APP_URL; ?>/#organization" }
        }
      ]
    }
    </script>

    <!-- Google Analytics (loaded after page load to reduce main-thread work / FID) -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      window.addEventListener('load', function(){
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=G-WDBLVKCVG1';
        document.head.appendChild(s);
        gtag('js', new Date());
        gtag('config', 'G-WDBLVKCVG1', { 'transport_type': 'beacon' });
      });
    </script>
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?><?php echo (isMaintenanceMode() && !canBypassMaintenance()) ? ' maintenance-mode' : ''; ?>">
    
    <?php if (isMaintenanceMode() && !canBypassMaintenance()): ?>
    <!-- Maintenance Mode Banner -->
    <div class="maintenance-banner">
        <div class="maintenance-banner-content">
            <i class="fas fa-tools"></i>
            <span><strong>Maintenance Mode</strong> - System is in read-only mode. You can view data but cannot make changes.</span>
        </div>
    </div>
    
    <!-- Maintenance Watermark -->
    <div class="maintenance-watermark">
        <span>MAINTENANCE</span>
    </div>
    
    <style>
        /* Maintenance Mode Styles */
        .maintenance-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 10px 20px;
            z-index: 10001;
            text-align: center;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .maintenance-banner-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .maintenance-banner i {
            font-size: 18px;
            animation: rotate 2s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Adjust body padding for banner */
        body.maintenance-mode {
            padding-top: 45px !important;
        }
        
        body.maintenance-mode .main-header {
            top: 45px;
        }
        
        body.maintenance-mode .sidebar {
            top: calc(60px + 45px);
            height: calc(100vh - 60px - 45px);
        }
        
        /* Maintenance Watermark */
        .maintenance-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: 900;
            color: rgba(245, 158, 11, 0.06);
            pointer-events: none;
            z-index: 9998;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 20px;
        }
        
        /* Disable form elements in maintenance mode */
        body.maintenance-mode form button[type="submit"],
        body.maintenance-mode form input[type="submit"],
        body.maintenance-mode .btn-primary,
        body.maintenance-mode .btn-success,
        body.maintenance-mode .btn-danger,
        body.maintenance-mode [data-action="delete"],
        body.maintenance-mode [data-action="edit"],
        body.maintenance-mode .add-btn,
        body.maintenance-mode .save-btn,
        body.maintenance-mode .delete-btn {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Show tooltip on disabled buttons */
        body.maintenance-mode form button[type="submit"]::after,
        body.maintenance-mode .btn-primary::after {
            content: 'Disabled during maintenance';
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        body.maintenance-mode form button[type="submit"]:hover::after,
        body.maintenance-mode .btn-primary:hover::after {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .maintenance-banner {
                padding: 8px 15px;
                font-size: 12px;
            }
            
            .maintenance-watermark {
                font-size: 60px;
                letter-spacing: 10px;
            }
            
            body.maintenance-mode {
                padding-top: 40px !important;
            }
            
            body.maintenance-mode .main-header {
                top: 40px;
            }
        }
    </style>
    
    <script>
        // Block form submissions in maintenance mode
        document.addEventListener('DOMContentLoaded', function() {
            if (document.body.classList.contains('maintenance-mode')) {
                // Block all form submissions
                document.querySelectorAll('form').forEach(function(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        showMaintenanceAlert();
                        return false;
                    });
                });
                
                // Block delete and edit actions
                document.querySelectorAll('[data-action="delete"], [data-action="edit"], .delete-btn, .edit-btn').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        showMaintenanceAlert();
                        return false;
                    });
                });
            }
        });
        
        function showMaintenanceAlert() {
            // Create and show toast notification
            const toast = document.createElement('div');
            toast.className = 'maintenance-toast';
            toast.innerHTML = '<i class="fas fa-tools"></i> <span>System is in maintenance mode. Changes cannot be saved.</span>';
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.3);
                z-index: 10002;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
                animation: slideUp 0.3s ease;
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(function() {
                toast.style.animation = 'slideDown 0.3s ease';
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        }
    </script>
    
    <style>
        @keyframes slideUp {
            from { transform: translate(-50%, 100px); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translate(-50%, 0); opacity: 1; }
            to { transform: translate(-50%, 100px); opacity: 0; }
        }
    </style>
    <?php endif; ?>
    
    <!-- Page Loader -->
    <div class="page-loader" id="globalPageLoader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading...</div>
    </div>
    
    <?php if (isLoggedIn()): ?>
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>
        
        <!-- Header Navigation -->
        <header class="main-header">
            <div class="header-content">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="<?php echo APP_URL; ?>/dashboard.php" class="logo">
                        <img src="<?php echo APP_URL; ?>/assets/image/CURENEXAI PNG.png" alt="<?php echo APP_NAME; ?>" class="logo-img">
                    </a>
                </div>
                
                <div class="header-right">
                    <!-- Search (Desktop) -->
                    <div class="header-search">
                        <form action="<?php echo APP_URL; ?>/search.php" method="GET">
                            <input type="text" name="q" placeholder="Search patients, remedies..." class="search-input" autocomplete="off">
                            <button type="submit" class="search-btn" aria-label="Search">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="user-menu">
                        <?php $doctor = getLoggedInDoctor(); ?>
                        <div class="user-info" id="userMenuBtn" role="button" tabindex="0" aria-expanded="false" aria-haspopup="true">
                            <div class="user-avatar">
                                <?php if (!empty($doctor['profile_image'])): ?>
                                    <img src="<?php echo APP_URL . '/uploads/profile_images/' . $doctor['profile_image']; ?>" alt="Profile">
                                <?php else: ?>
                                    <i class="fas fa-user-md"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($doctor['full_name']); ?></span>
                                <span class="user-role">Doctor</span>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        
                        <div class="user-dropdown" id="userDropdown" role="menu">
                            <a href="<?php echo APP_URL; ?>/update_profile.php" role="menuitem">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/gemini_setup.php" role="menuitem">
                                <i class="fas fa-robot"></i> Gemini AI Setup
                            </a>
                            <a href="<?php echo APP_URL; ?>/settings.php" role="menuitem">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <hr>
                            <a href="<?php echo APP_URL; ?>/logout.php" class="text-danger" role="menuitem">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="<?php echo APP_URL; ?>/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">Patient Management</li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/patients/list.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'patients') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/consultations/list.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'consultations') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-stethoscope"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">Clinical Tools</li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/repertory/search.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'repertory') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-book"></i>
                            <span>Repertory</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/materia-medica/list.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'materia-medica') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-flask"></i>
                            <span>Materia Medica</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/prescriptions/list.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'prescriptions') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-prescription"></i>
                            <span>Prescriptions</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/lab.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'lab.php' ? 'active' : ''; ?>">
                            <i class="fas fa-vials"></i>
                            <span>Lab</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/diagnose.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'diagnose.php' ? 'active' : ''; ?>">
                            <i class="fas fa-diagnoses"></i>
                            <span>Diagnose</span>
                            <span class="badge">RAG</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/dermo.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dermo.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hand-holding-medical"></i>
                            <span>Dermo</span>
                            <span class="badge badge-new">AI</span>
                        </a>
                    </li>
                    
                    <?php if (AI_ENABLED): ?>
                    <li class="nav-section">AI Assistant</li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/consultations/list.php?highlight=ai" class="<?php echo strpos($_SERVER['PHP_SELF'], 'ai') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-brain"></i>
                            <span>AI Suggestions</span>
                            <span class="badge">New</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo APP_URL; ?>/qa_runner.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'qa_runner.php' ? 'active' : ''; ?>">
                            <i class="fas fa-vial"></i>
                            <span>QA Testing</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <p class="version">Version <?php echo APP_VERSION; ?></p>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
    <?php else: ?>
        <!-- Public Layout (Login/Register) -->
        <div class="auth-layout">
    <?php endif; ?>
    
    <!-- Flash Messages -->
    <?php
    $flash = getFlash();
    if ($flash):
    ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible">
            <button type="button" class="close" onclick="this.parentElement.remove()">×</button>
            <?php echo $flash['message']; ?>
        </div>
    <?php endif; ?>
