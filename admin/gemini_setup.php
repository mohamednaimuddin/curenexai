<?php
/**
 * Gemini API Setup and Testing
 */
require_once '../includes/init.php';

// Check if logged in and is admin
if (!isLoggedIn()) {
    redirect('/login.php');
}

$pageTitle = 'Gemini AI Setup';
$error = '';
$success = '';
$testResult = null;

// Handle API key update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_api_key') {
        $apiKey = trim($_POST['api_key'] ?? '');
        $model = trim($_POST['model'] ?? 'gemini-1.5-flash');
        $provider = $_POST['provider'] ?? 'gemini';
        
        // Don't update API key if it's the masked placeholder or empty (keep existing)
        $updateApiKey = true;
        if (empty($apiKey) || $apiKey === '••••••••••••••••' || preg_match('/^•+$/', $apiKey)) {
            $updateApiKey = false;
        }
        
        // Update config file
        $configFile = BASE_PATH . '/config/config.php';
        $configContent = file_get_contents($configFile);
        
        // Update API key only if a new one was provided
        if ($updateApiKey) {
            $configContent = preg_replace(
                "/define\('GEMINI_API_KEY',\s*'[^']*'\);/",
                "define('GEMINI_API_KEY', '$apiKey');",
                $configContent
            );
        }
        
        // Update model
        $configContent = preg_replace(
            "/define\('GEMINI_MODEL',\s*'[^']*'\);/",
            "define('GEMINI_MODEL', '$model');",
            $configContent
        );
        
        // Update provider
        $configContent = preg_replace(
            "/define\('AI_PROVIDER',\s*'[^']*'\);/",
            "define('AI_PROVIDER', '$provider');",
            $configContent
        );
        
        if (file_put_contents($configFile, $configContent)) {
            $success = 'Configuration saved successfully! Please refresh the page.';
        } else {
            $error = 'Failed to save configuration. Check file permissions.';
        }
    } elseif ($_POST['action'] === 'test_connection') {
        try {
            require_once BASE_PATH . '/includes/gemini_api.php';
            $gemini = new GeminiAPI();
            $testResult = $gemini->testConnection();
        } catch (Exception $e) {
            $testResult = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

require_once '../includes/header.php';
?>

<style>
.setup-container {
    max-width: 900px;
    margin: 0 auto;
}

.info-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.info-box h2 {
    margin-top: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-box i {
    font-size: 2em;
}

.setup-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-group {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: white;
    color: #667eea;
    padding: 12px 30px;
    border: 2px solid #667eea;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: #f8f9ff;
}

.test-result {
    margin-top: 25px;
    padding: 20px;
    border-radius: 8px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.test-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.test-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin-top: 20px;
}

.feature-list li {
    padding: 10px 0;
    padding-left: 30px;
    position: relative;
}

.feature-list li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #4ade80;
    font-weight: bold;
    font-size: 1.2em;
}

.steps {
    background: #f8f9ff;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.steps ol {
    margin: 10px 0;
    padding-left: 20px;
}

.steps li {
    margin: 10px 0;
    line-height: 1.6;
}

.steps code {
    background: white;
    padding: 2px 8px;
    border-radius: 4px;
    color: #667eea;
    font-family: 'Courier New', monospace;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.model-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.model-option {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.model-option:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.model-option input[type="radio"] {
    margin-right: 10px;
}

.model-option.selected {
    border-color: #667eea;
    background: #f8f9ff;
}
</style>

<div class="setup-container">
    <div class="info-box">
        <h2>
            <i class="fas fa-robot"></i>
            Google Gemini AI Integration
        </h2>
        <p>Enhance your homeopathy practice with AI-powered remedy suggestions powered by Google's Gemini AI.</p>
        <ul class="feature-list">
            <li>Intelligent remedy recommendations based on symptoms</li>
            <li>Analysis of patient constitution and modalities</li>
            <li>Differential diagnosis assistance</li>
            <li>FREE API with generous usage limits</li>
        </ul>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="setup-card">
        <h3><i class="fas fa-cog"></i> Configuration</h3>
        
        <div class="steps">
            <strong>How to get your FREE Gemini API key:</strong>
            <ol>
                <li>Visit <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                <li>Sign in with your Google account</li>
                <li>Click "Create API Key"</li>
                <li>Copy the API key and paste it below</li>
            </ol>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="action" value="save_api_key">
            
            <div class="form-group">
                <label for="provider">AI Provider:</label>
                <select name="provider" id="provider" class="form-control">
                    <option value="gemini" <?= AI_PROVIDER === 'gemini' ? 'selected' : '' ?>>Google Gemini (Recommended)</option>
                    <option value="local" <?= AI_PROVIDER === 'local' ? 'selected' : '' ?>>Local RAG (No API needed)</option>
                    <option value="huggingface" <?= AI_PROVIDER === 'huggingface' ? 'selected' : '' ?>>Hugging Face</option>
                </select>
                <small style="color: #666; margin-top: 5px; display: block;">
                    Gemini provides the best results. Local RAG works offline but with limited accuracy.
                </small>
            </div>

            <div class="form-group">
                <label for="api_key">Gemini API Key:</label>
                <div style="position: relative;">
                    <input 
                        type="password" 
                        name="api_key" 
                        id="api_key" 
                        class="form-control" 
                        value="<?= !empty(GEMINI_API_KEY) ? '••••••••••••••••' : '' ?>"
                        placeholder="Enter your Gemini API key"
                        onfocus="if(this.value==='••••••••••••••••') this.value=''"
                        autocomplete="new-password"
                    >
                    <button type="button" onclick="toggleApiKeyVisibility()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666;">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                <small style="color: #666; margin-top: 5px; display: block;">
                    <?php if (!empty(GEMINI_API_KEY)): ?>
                        <span style="color: #28a745;"><i class="fas fa-check-circle"></i> API key configured. Enter a new key to replace it.</span>
                    <?php else: ?>
                        Your API key is stored securely in config.php
                    <?php endif; ?>
                </small>
            </div>
            <script>
            function toggleApiKeyVisibility() {
                const input = document.getElementById('api_key');
                const icon = document.getElementById('toggleIcon');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
            </script>

            <div class="form-group">
                <label>Select Model:</label>
                <div class="model-options">
                    <label class="model-option <?= GEMINI_MODEL === 'gemini-2.5-flash-lite' ? 'selected' : '' ?>">
                        <input type="radio" name="model" value="gemini-2.5-flash-lite" <?= GEMINI_MODEL === 'gemini-2.5-flash-lite' ? 'checked' : '' ?>>
                        <div>
                            <strong>Gemini 2.5 Flash Lite</strong><br>
                            <small>⭐ Recommended - Latest, fast & reliable</small>
                        </div>
                    </label>
                    <label class="model-option <?= GEMINI_MODEL === 'gemini-2.5-flash' ? 'selected' : '' ?>">
                        <input type="radio" name="model" value="gemini-2.5-flash" <?= GEMINI_MODEL === 'gemini-2.5-flash' ? 'checked' : '' ?>>
                        <div>
                            <strong>Gemini 2.5 Flash</strong><br>
                            <small>Most capable - Better for complex cases</small>
                        </div>
                    </label>
                    <label class="model-option <?= GEMINI_MODEL === 'gemini-2.0-flash' ? 'selected' : '' ?>">
                        <input type="radio" name="model" value="gemini-2.0-flash" <?= GEMINI_MODEL === 'gemini-2.0-flash' ? 'checked' : '' ?>>
                        <div>
                            <strong>Gemini 2.0 Flash</strong><br>
                            <small>Fast - Good balance of speed & capability</small>
                        </div>
                    </label>
                </div>
                <small style="color: #666; margin-top: 10px; display: block;">
                    <i class="fas fa-info-circle"></i> gemini-2.5-flash-lite has the best free tier quota and latest improvements.
                </small>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>

    <div class="setup-card">
        <h3><i class="fas fa-vial"></i> Test Connection</h3>
        <p>Test your Gemini API configuration to ensure everything is working correctly.</p>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="test_connection">
            <button type="submit" class="btn-secondary">
                <i class="fas fa-flask"></i> Test Gemini API
            </button>
        </form>

        <?php if ($testResult): ?>
            <div class="test-result <?= $testResult['success'] ? 'success' : 'error' ?>">
                <?php if ($testResult['success']): ?>
                    <h4><i class="fas fa-check-circle"></i> Connection Successful!</h4>
                    <p><strong>Model:</strong> <?= htmlspecialchars($testResult['model']) ?></p>
                    <p><strong>Response:</strong> <?= htmlspecialchars($testResult['response']) ?></p>
                    <p style="margin-top: 15px;">
                        <i class="fas fa-info-circle"></i> 
                        Your Gemini API is configured correctly and ready to use!
                    </p>
                <?php else: ?>
                    <h4><i class="fas fa-times-circle"></i> Connection Failed</h4>
                    <p><strong>Error:</strong> <?= htmlspecialchars($testResult['error']) ?></p>
                    <p style="margin-top: 15px;">
                        Please check your API key and try again. Make sure you've entered a valid Gemini API key.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="setup-card">
        <h3><i class="fas fa-info-circle"></i> Current Configuration</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px 0; font-weight: 600;">AI Provider:</td>
                <td style="padding: 12px 0;"><?= htmlspecialchars(AI_PROVIDER) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px 0; font-weight: 600;">Model:</td>
                <td style="padding: 12px 0;"><?= htmlspecialchars(GEMINI_MODEL) ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px 0; font-weight: 600;">API Key:</td>
                <td style="padding: 12px 0;">
                    <?php if (empty(GEMINI_API_KEY)): ?>
                        <span style="color: #dc3545;"><i class="fas fa-times-circle"></i> Not configured</span>
                    <?php else: 
                        $key = GEMINI_API_KEY;
                        $masked = substr($key, 0, 4) . str_repeat('•', max(0, strlen($key) - 8)) . substr($key, -4);
                    ?>
                        <span style="color: #28a745;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($masked) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px 0; font-weight: 600;">Fallback Enabled:</td>
                <td style="padding: 12px 0;"><?= AI_USE_LOCAL_FALLBACK ? 'Yes' : 'No' ?></td>
            </tr>
        </table>
    </div>
</div>

<script>
// Add visual feedback for model selection
document.querySelectorAll('.model-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.model-option').forEach(option => {
            option.classList.remove('selected');
        });
        this.closest('.model-option').classList.add('selected');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
