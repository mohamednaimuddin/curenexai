<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Homeopathic Assistant</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .install-body {
            padding: 40px;
        }
        
        .step {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .step:last-child {
            border-bottom: none;
        }
        
        .step-number {
            display: inline-block;
            width: 35px;
            height: 35px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 35px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .step h3 {
            display: inline;
            font-size: 20px;
            color: #333;
        }
        
        .step-content {
            margin-top: 15px;
            padding-left: 45px;
            color: #666;
            line-height: 1.8;
        }
        
        .status-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }
        
        .status-ok {
            color: #10b981;
            font-weight: bold;
        }
        
        .status-error {
            color: #ef4444;
            font-weight: bold;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #e11d48;
        }
        
        .instructions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🏥 Homeopathic Assistant</h1>
            <p>Installation & Setup Guide</p>
        </div>
        
        <div class="install-body">
            <?php
            // Check system requirements
            $phpVersion = phpversion();
            $phpOk = version_compare($phpVersion, '7.4.0', '>=');
            $pdoOk = extension_loaded('pdo') && extension_loaded('pdo_mysql');
            $mbstringOk = extension_loaded('mbstring');
            $jsonOk = extension_loaded('json');
            
            // Check database connection
            $dbFile = __DIR__ . '/config/config.php';
            $dbConnected = false;
            $dbError = '';
            
            if (file_exists($dbFile)) {
                require_once $dbFile;
                try {
                    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                    $pdo = new PDO($dsn, DB_USER, DB_PASS);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Check if tables exist
                    $stmt = $pdo->query("SHOW TABLES LIKE 'doctors'");
                    $tableExists = $stmt->rowCount() > 0;
                    
                    if ($tableExists) {
                        $dbConnected = true;
                    } else {
                        $dbError = 'Database connected but tables not found. Please import schema.sql';
                    }
                } catch (PDOException $e) {
                    $dbError = $e->getMessage();
                }
            } else {
                $dbError = 'Config file not found';
            }
            
            $allOk = $phpOk && $pdoOk && $mbstringOk && $jsonOk && $dbConnected;
            ?>
            
            <!-- System Requirements Check -->
            <div class="step">
                <span class="step-number">1</span>
                <h3>System Requirements Check</h3>
                <div class="step-content">
                    <div class="status-box">
                        <div class="status-item">
                            <span>PHP Version (>= 7.4)</span>
                            <span class="<?php echo $phpOk ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $phpVersion . ' ' . ($phpOk ? '✓' : '✗'); ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span>PDO MySQL Extension</span>
                            <span class="<?php echo $pdoOk ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $pdoOk ? '✓ Enabled' : '✗ Disabled'; ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span>Mbstring Extension</span>
                            <span class="<?php echo $mbstringOk ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $mbstringOk ? '✓ Enabled' : '✗ Disabled'; ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span>JSON Extension</span>
                            <span class="<?php echo $jsonOk ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $jsonOk ? '✓ Enabled' : '✗ Disabled'; ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span>Database Connection</span>
                            <span class="<?php echo $dbConnected ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $dbConnected ? '✓ Connected' : '✗ ' . $dbError; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Installation Instructions -->
            <?php if (!$dbConnected): ?>
            <div class="step">
                <span class="step-number">2</span>
                <h3>Database Setup</h3>
                <div class="step-content">
                    <div class="instructions">
                        <ol>
                            <li>Open <strong>phpMyAdmin</strong>: <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a></li>
                            <li>Create a new database named: <code>homeopathic_system</code></li>
                            <li>Select the database and click <strong>Import</strong> tab</li>
                            <li>Choose file: <code>database/schema.sql</code></li>
                            <li>Click <strong>Go</strong> to import</li>
                            <li>Refresh this page to verify installation</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-warning">
                        ⚠️ <strong>Important:</strong> Make sure to update database credentials in <code>config/config.php</code> if needed.
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="step">
                <span class="step-number">3</span>
                <h3>Configure Gemini API (Optional)</h3>
                <div class="step-content">
                    <p>For AI-powered remedy suggestions, you need to set up Google Gemini API:</p>
                    <div class="instructions">
                        <ol>
                            <li>Get your API key from: <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                            <li>Open <code>config/config.php</code></li>
                            <li>Update: <code>define('GEMINI_API_KEY', 'your-api-key');</code></li>
                            <li>Save the file</li>
                        </ol>
                    </div>
                    <p>✨ You can skip this step and add it later if you want to test the system first.</p>
                </div>
            </div>
            
            <!-- Success or Next Steps -->
            <?php if ($allOk): ?>
            <div class="alert alert-success">
                ✅ <strong>Installation Complete!</strong> Your system is ready to use.
            </div>
            
            <div class="step">
                <span class="step-number">4</span>
                <h3>Default Admin Login</h3>
                <div class="step-content">
                    <div class="status-box">
                        <p><strong>Email:</strong> <code>admin@homeo.local</code></p>
                        <p><strong>Password:</strong> <code>admin123</code></p>
                    </div>
                    <div class="alert alert-warning">
                        ⚠️ <strong>Security:</strong> Please change the default password after first login!
                    </div>
                    <br>
                    <a href="login.php" class="btn">Go to Login →</a>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-error">
                ❌ <strong>Installation Incomplete!</strong> Please fix the issues above and refresh this page.
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
