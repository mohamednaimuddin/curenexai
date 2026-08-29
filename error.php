<?php
require_once 'includes/init.php';

$statusCode = (int)($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 404));
$allowedCodes = [403, 404, 500];

if (!in_array($statusCode, $allowedCodes, true)) {
    $statusCode = 404;
}

http_response_code($statusCode);

$errorContent = [
    403 => [
        'eyebrow' => 'Access paused',
        'title' => 'This room is doctor-only.',
        'copy' => 'The door is working. It is just politely refusing to open without the right access.',
        'hint' => 'Try logging in again, or return to the homepage and take the well-lit corridor.',
        'icon' => 'fa-lock'
    ],
    404 => [
        'eyebrow' => 'Page not found',
        'title' => 'This page wandered off during repertorization.',
        'copy' => 'We checked Mind, Generalities, and even the tiny footnotes. No matching rubric for this URL.',
        'hint' => 'The page may have moved, been renamed, or typed itself into a dramatic proving.',
        'icon' => 'fa-map-signs'
    ],
    500 => [
        'eyebrow' => 'Server hiccup',
        'title' => 'The server had a very human moment.',
        'copy' => 'Something went wrong behind the curtain. The case is noted and the chart is being reviewed.',
        'hint' => 'Please try again in a moment. If it repeats, contact support with what you were opening.',
        'icon' => 'fa-stethoscope'
    ],
];

$content = $errorContent[$statusCode];
$pageTitle = $statusCode . ' - ' . $content['eyebrow'] . ' | CurenexAI';
?>
<!DOCTYPE html>
<html lang="en" itemscope itemtype="https://schema.org/WebPage">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-18250112030"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-WDBLVKCVG1');
      gtag('config', 'AW-18250112030');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#14b8a6">
    <meta name="robots" content="noindex, follow">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f5fbfa;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --line: #dbe7e5;
            --brand: #0f766e;
            --brand-strong: #115e59;
            --accent: #f59e0b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.18), transparent 32%),
                linear-gradient(180deg, #fbfffe 0%, var(--bg) 100%);
            display: grid;
            place-items: center;
            padding: 24px;
            line-height: 1.6;
        }

        .error-shell {
            width: min(920px, 100%);
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.1);
            padding: clamp(24px, 5vw, 52px);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            color: var(--brand-strong);
            background: rgba(15, 118, 110, 0.08);
            font-weight: 800;
        }

        .code {
            margin: 26px 0 8px;
            font-size: clamp(4.2rem, 12vw, 8rem);
            line-height: 0.9;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: var(--brand);
        }

        h1 {
            margin: 0 0 14px;
            font-size: clamp(2rem, 4vw, 3.45rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        p {
            margin: 0;
            color: var(--muted);
            max-width: 68ch;
            font-size: 1.05rem;
        }

        .hint {
            margin-top: 12px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        .button-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--brand), #14b8a6);
        }

        .button-secondary {
            color: var(--text);
            background: #fff;
            border: 1px solid var(--line);
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
        }

        .quick-links a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
            background: #f8fffd;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
        }

        @media (max-width: 720px) {
            .quick-links {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="error-shell">
        <span class="badge">
            <i class="fas <?php echo htmlspecialchars($content['icon']); ?>"></i>
            <?php echo htmlspecialchars($content['eyebrow']); ?>
        </span>
        <div class="code"><?php echo $statusCode; ?></div>
        <h1><?php echo htmlspecialchars($content['title']); ?></h1>
        <p><?php echo htmlspecialchars($content['copy']); ?></p>
        <p class="hint"><?php echo htmlspecialchars($content['hint']); ?></p>

        <div class="actions">
            <a class="button button-primary" href="<?php echo APP_URL; ?>/">
                <i class="fas fa-house"></i>
                Go Home
            </a>
            <a class="button button-secondary" href="<?php echo APP_URL; ?>/support">
                <i class="fas fa-comments"></i>
                Contact Support
            </a>
        </div>

        <nav class="quick-links" aria-label="Helpful links">
            <a href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy">Digital Repertory</a>
            <a href="<?php echo APP_URL; ?>/materia-medica">Materia Medica</a>
            <a href="<?php echo APP_URL; ?>/kent-repertory-online">Kent Repertory</a>
        </nav>
    </main>
</body>
</html>
