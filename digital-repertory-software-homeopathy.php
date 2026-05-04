<?php
require_once 'includes/init.php';

$pageTitle = 'Digital Repertory Software for Homeopathy Doctors | CurenexAI';
?>
<!DOCTYPE html>
<html lang="en" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#14b8a6">

    <title>Digital Repertory Software for Homeopathy Doctors | CurenexAI</title>
    <meta name="title" content="Digital Repertory Software for Homeopathy Doctors | CurenexAI">
    <meta name="description" content="CurenexAI is digital repertory software for homeopathy doctors with rubric search, remedy suggestions, materia medica reference, and patient management.">
    <meta name="keywords" content="digital repertory software for homeopathy, digital repertory software, homeopathy software, AI homeopathy software, repertory software for homeopathic doctors, digital reperatory software for homeopathy">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <link rel="canonical" href="https://curenexai.com/digital-repertory-software-homeopathy.php">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://curenexai.com/digital-repertory-software-homeopathy.php">
    <meta property="og:title" content="Digital Repertory Software for Homeopathy Doctors | CurenexAI">
    <meta property="og:description" content="AI homeopathy software with digital repertory, remedy search, materia medica, and digital prescription workflows for modern clinics.">
    <meta property="og:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">
    <meta property="og:site_name" content="CurenexAI">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Digital Repertory Software for Homeopathy Doctors | CurenexAI">
    <meta name="twitter:description" content="AI homeopathy software with digital repertory, rubric search, and patient management.">
    <meta name="twitter:image" content="https://curenexai.com/assets/image/CURENEXAI PNG.png">

    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "CurenexAI",
        "url": "https://curenexai.com/digital-repertory-software-homeopathy.php",
        "applicationCategory": "HealthApplication",
        "operatingSystem": "Web Browser, Android, iOS",
        "description": "CurenexAI is digital repertory software for homeopathy doctors with rubric search, remedy suggestions, materia medica reference, and patient management.",
        "featureList": [
            "Digital repertory rubric search",
            "AI-assisted remedy suggestions",
            "Materia medica reference",
            "Patient management",
            "Digital prescriptions"
        ],
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        }
    }
    </script>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What is digital repertory software for homeopathy?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Digital repertory software for homeopathy helps doctors search rubrics, compare remedies, review materia medica, and organize case analysis faster than manual repertory books."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI homeopathy software or digital repertory software?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI is both. It works as homeopathy software for daily practice management and as digital repertory software for rubric search, remedy analysis, and AI-assisted case support."
                }
            },
            {
                "@type": "Question",
                "name": "Does CurenexAI support searches for digital reperatory software for homeopathy?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. Some users spell repertory as reperatory, but they mean the same workflow: searching rubrics, comparing remedies, and using homeopathy software to support case-taking."
                }
            }
        ]
    }
    </script>

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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.16), transparent 32%),
                linear-gradient(180deg, #f8fffe 0%, var(--bg) 100%);
            line-height: 1.6;
        }

        a {
            color: var(--brand);
        }

        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .topbar {
            padding: 18px 0;
        }

        .topbar a {
            text-decoration: none;
            font-weight: 600;
        }

        .hero {
            padding: 48px 0 28px;
            display: grid;
            gap: 28px;
            grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
            align-items: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.08);
            color: var(--brand-strong);
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        h1 {
            margin: 18px 0 14px;
            font-size: clamp(2.3rem, 4vw, 4.3rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .lead {
            font-size: 1.08rem;
            color: var(--muted);
            max-width: 62ch;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 24px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
        }

        .button-primary {
            background: linear-gradient(135deg, var(--brand), #14b8a6);
            color: #fff;
        }

        .button-secondary {
            background: #fff;
            border: 1px solid var(--line);
            color: var(--text);
        }

        .panel {
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(219, 231, 229, 0.9);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(8px);
        }

        .metric-list,
        .grid,
        .faq,
        .compare-grid {
            display: grid;
            gap: 18px;
        }

        .metric-list {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 16px;
        }

        .metric {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px;
        }

        .metric strong {
            display: block;
            font-size: 1.4rem;
        }

        section {
            padding: 26px 0;
        }

        h2 {
            margin: 0 0 10px;
            font-size: clamp(1.6rem, 2.4vw, 2.4rem);
            letter-spacing: -0.02em;
        }

        .section-copy {
            margin: 0 0 18px;
            color: var(--muted);
            max-width: 70ch;
        }

        .grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
        }

        .card i {
            color: var(--brand);
            font-size: 1.2rem;
            margin-bottom: 12px;
        }

        .card h3,
        .faq h3 {
            margin: 0 0 8px;
            font-size: 1.1rem;
        }

        .card p,
        .faq p,
        .compare-grid p,
        .card li {
            margin: 0;
            color: var(--muted);
        }

        .card ul {
            margin: 14px 0 0;
            padding-left: 18px;
        }

        .compare-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .compare-good {
            border-top: 4px solid var(--brand);
        }

        .compare-bad {
            border-top: 4px solid var(--accent);
        }

        .faq {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cta {
            padding: 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #0f172a, #115e59);
            color: #fff;
        }

        .cta p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 58ch;
        }

        .cta .button-secondary {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.08);
        }

        footer {
            padding: 28px 0 48px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        @media (max-width: 960px) {
            .hero,
            .grid,
            .faq,
            .compare-grid,
            .metric-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <a href="<?php echo APP_URL; ?>/">&larr; Back to CurenexAI Home</a>
        </div>

        <section class="hero">
            <div>
                <span class="eyebrow"><i class="fas fa-book-medical"></i> SEO Landing Page for Homeopathy Software Search</span>
                <h1>Digital Repertory Software for Homeopathy Doctors</h1>
                <p class="lead">CurenexAI brings digital repertory search, AI-assisted remedy analysis, materia medica reference, patient records, and digital prescriptions into one focused homeopathy software platform.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-rocket"></i> Start Free Beta</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/documentation.php"><i class="fas fa-book"></i> Read Documentation</a>
                </div>
            </div>

            <div class="panel">
                <h2>Why clinics search for this</h2>
                <p class="section-copy">Doctors usually want faster rubric search, cleaner case-taking, and fewer fragmented tools. This page is built around that exact commercial search intent.</p>
                <div class="metric-list">
                    <div class="metric">
                        <strong>Rubric Search</strong>
                        <span>Find repertory entries faster during consultations.</span>
                    </div>
                    <div class="metric">
                        <strong>AI Support</strong>
                        <span>Use symptom context to review likely remedies.</span>
                    </div>
                    <div class="metric">
                        <strong>Clinic Workflow</strong>
                        <span>Keep patients, notes, and prescriptions together.</span>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2>What makes good digital repertory software</h2>
            <p class="section-copy">Homeopathy software should do more than store patient records. It needs repertory speed, remedy context, and a workflow that supports real case-taking without slowing the doctor down.</p>
            <div class="grid">
                <article class="card">
                    <i class="fas fa-magnifying-glass"></i>
                    <h3>Fast rubric exploration</h3>
                    <p>Search repertory rubrics quickly and narrow remedy options during the consultation instead of jumping between books and tabs.</p>
                </article>
                <article class="card">
                    <i class="fas fa-brain"></i>
                    <h3>AI-assisted remedy review</h3>
                    <p>Use AI support to surface remedy suggestions from symptom patterns while keeping doctor judgment in control.</p>
                </article>
                <article class="card">
                    <i class="fas fa-notes-medical"></i>
                    <h3>Connected patient workflow</h3>
                    <p>Move from repertory analysis to notes, remedy comparison, and prescription generation without losing case context.</p>
                </article>
            </div>
        </section>

        <section>
            <h2>CurenexAI as AI homeopathy software</h2>
            <p class="section-copy">CurenexAI is not only a digital repertory. It is homeopathy software designed for day-to-day clinical work, combining analysis, references, and practice management in one system.</p>
            <div class="grid">
                <article class="card">
                    <i class="fas fa-book-open"></i>
                    <h3>Digital repertory</h3>
                    <p>Search rubrics and review remedy relationships with a digital workflow built for modern clinics.</p>
                </article>
                <article class="card">
                    <i class="fas fa-leaf"></i>
                    <h3>Materia medica support</h3>
                    <p>Use remedy context and reference material while evaluating the case, not as a disconnected afterthought.</p>
                </article>
                <article class="card">
                    <i class="fas fa-users"></i>
                    <h3>Patient management</h3>
                    <p>Store consultation history, manage follow-up context, and keep clinical records available when needed.</p>
                </article>
            </div>
        </section>

        <section>
            <h2>Compared with older homeopathy workflows</h2>
            <div class="compare-grid">
                <article class="card compare-bad">
                    <h3>Traditional fragmented workflow</h3>
                    <ul>
                        <li>Paper repertory or multiple disconnected tools</li>
                        <li>Manual cross-checking between notes and remedies</li>
                        <li>Slower consultation flow</li>
                        <li>Harder follow-up continuity</li>
                    </ul>
                </article>
                <article class="card compare-good">
                    <h3>CurenexAI workflow</h3>
                    <ul>
                        <li>Digital repertory and patient workflow in one place</li>
                        <li>AI-assisted remedy review with doctor oversight</li>
                        <li>Faster search and cleaner documentation</li>
                        <li>Digital prescriptions and ongoing case history</li>
                    </ul>
                </article>
            </div>
        </section>

        <section>
            <h2>Frequently asked questions</h2>
            <div class="faq">
                <article class="card">
                    <h3>Is this page about digital repertory software for homeopathy?</h3>
                    <p>Yes. This page targets doctors looking for repertory software, homeopathy software, and AI homeopathy software for clinical use.</p>
                </article>
                <article class="card">
                    <h3>What if users search for digital reperatory software for homeopathy?</h3>
                    <p>That misspelling usually means the same need: software for rubric search, remedy comparison, and digital case analysis.</p>
                </article>
                <article class="card">
                    <h3>Is CurenexAI only repertory software?</h3>
                    <p>No. It also includes patient management, AI-supported analysis, materia medica context, and prescription workflows.</p>
                </article>
                <article class="card">
                    <h3>Who is this built for?</h3>
                    <p>Homeopathic doctors, BHMS and MD Homeopathy practitioners, clinics, and students who want a faster digital workflow.</p>
                </article>
            </div>
        </section>

        <section>
            <div class="cta">
                <h2>Try CurenexAI for digital repertory and clinic workflow</h2>
                <p>Use one platform for repertory search, AI-supported remedy suggestions, patient management, and digital prescriptions.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Create Free Account</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/support.php"><i class="fas fa-comments"></i> Contact Support</a>
                </div>
            </div>
        </section>

        <footer>
            CurenexAI is homeopathic clinical software. It is not a medicine or skincare product.
        </footer>
    </div>
</body>
</html>