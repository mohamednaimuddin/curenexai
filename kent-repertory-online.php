<?php
require_once 'includes/init.php';

$pageTitle = 'Kent Repertory Online | CurenexAI';
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

    <title>Kent Repertory Online | AI Search for Homeopathy | CurenexAI</title>
    <meta name="title" content="Kent Repertory Online | AI Search for Homeopathy | CurenexAI">
    <meta name="description" content="Use CurenexAI for Kent repertory online search, AI-assisted rubric analysis, remedy comparison, materia medica context, patient records, and digital prescriptions.">
    <meta name="keywords" content="kent repertory online, kent repertory, online repertory, homeopathic repertory, repertory software, digital repertory, AI repertory, homeopathy repertory software, Kent repertory software">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <link rel="canonical" href="https://curenexai.com/kent-repertory-online">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://curenexai.com/kent-repertory-online">
    <meta property="og:title" content="Kent Repertory Online | CurenexAI">
    <meta property="og:description" content="Search Kent repertory rubrics online with AI support, materia medica context, and clinic workflow in CurenexAI.">
    <meta property="og:image" content="https://curenexai.com/assets/image/xrunbg.png">
    <meta property="og:site_name" content="CurenexAI">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Kent Repertory Online | CurenexAI">
    <meta name="twitter:description" content="AI-assisted Kent repertory search, rubric analysis, and homeopathy clinic workflow.">
    <meta name="twitter:image" content="https://curenexai.com/assets/image/xrunbg.png">

    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "CurenexAI",
        "url": "https://curenexai.com/kent-repertory-online",
        "applicationCategory": "HealthApplication",
        "operatingSystem": "Web Browser, Android, iOS",
        "description": "Kent repertory online search, AI-assisted rubric analysis, remedy comparison, materia medica context, patient records, and digital prescriptions for homeopathic doctors.",
        "featureList": [
            "Kent repertory online search",
            "AI-assisted rubric analysis",
            "Digital repertory",
            "Materia medica context",
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
                "name": "What is Kent repertory online?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Kent repertory online means searching Kent-style homeopathic repertory rubrics digitally instead of using only printed repertory books."
                }
            },
            {
                "@type": "Question",
                "name": "Does CurenexAI support Kent repertory workflows?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. CurenexAI supports digital repertory search, rubric exploration, remedy comparison, and AI-assisted case analysis for homeopathic doctors."
                }
            },
            {
                "@type": "Question",
                "name": "Is online repertory enough for prescribing?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Online repertory is a clinical reference and analysis tool. Final prescribing should combine repertory, materia medica, case-taking, follow-up, and doctor judgment."
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

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #fbfffe 0%, var(--bg) 100%);
            line-height: 1.6;
        }

        a { color: var(--brand); }
        .shell { max-width: 1180px; margin: 0 auto; padding: 0 20px; }
        .topbar { padding: 18px 0; }
        .topbar a { text-decoration: none; font-weight: 700; }

        .hero {
            padding: 48px 0 28px;
            display: grid;
            gap: 28px;
            grid-template-columns: minmax(0, 1.08fr) minmax(300px, 0.92fr);
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
            font-weight: 800;
        }

        h1 {
            margin: 18px 0 14px;
            font-size: clamp(2.3rem, 4vw, 4.2rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        h2 {
            margin: 0 0 10px;
            font-size: clamp(1.55rem, 2.4vw, 2.35rem);
            letter-spacing: -0.02em;
        }

        .lead, .section-copy, .card p, .card li, .metric span { color: var(--muted); }
        .lead { font-size: 1.08rem; max-width: 64ch; }

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
            font-weight: 800;
        }

        .button-primary { background: linear-gradient(135deg, var(--brand), #14b8a6); color: #fff; }
        .button-secondary { background: #fff; border: 1px solid var(--line); color: var(--text); }

        .panel, .card, .metric {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
        }

        .panel {
            padding: 24px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        section { padding: 26px 0; }
        .grid, .compare-grid, .metric-list, .faq { display: grid; gap: 18px; }
        .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .compare-grid, .faq { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .metric-list { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 16px; }
        .card, .metric { padding: 22px; }
        .metric strong { display: block; font-size: 1.15rem; }
        .card i { color: var(--brand); font-size: 1.2rem; margin-bottom: 12px; }
        .card h3, .faq h3 { margin: 0 0 8px; font-size: 1.1rem; }
        .card p, .faq p { margin: 0; }
        .card ul { margin: 14px 0 0; padding-left: 18px; }
        .compare-good { border-top: 4px solid var(--brand); }
        .compare-old { border-top: 4px solid var(--accent); }

        .cta {
            padding: 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #0f172a, #115e59);
            color: #fff;
        }

        .cta p { color: rgba(255, 255, 255, 0.82); max-width: 62ch; }
        .cta .button-secondary { color: #fff; border-color: rgba(255, 255, 255, 0.25); background: rgba(255, 255, 255, 0.08); }
        footer { padding: 28px 0 48px; color: var(--muted); font-size: 0.95rem; }

        @media (max-width: 960px) {
            .hero, .grid, .compare-grid, .metric-list, .faq { grid-template-columns: 1fr; }
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
                <span class="eyebrow"><i class="fas fa-book-medical"></i> Kent Repertory Online</span>
                <h1>Kent Repertory Online Search with AI Support</h1>
                <p class="lead">CurenexAI helps homeopathy doctors search repertory rubrics, compare remedies, review materia medica context, and move from analysis to patient records and prescriptions in one workflow.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-rocket"></i> Start Free Beta</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/materia-medica"><i class="fas fa-leaf"></i> Materia Medica</a>
                </div>
            </div>

            <div class="panel">
                <h2>Why Kent repertory search matters</h2>
                <p class="section-copy">Doctors search for Kent repertory online because they need a faster way to move from symptoms to rubrics and remedies during real consultations.</p>
                <div class="metric-list">
                    <div class="metric">
                        <strong>Rubrics</strong>
                        <span>Explore repertory entries without manual page hunting.</span>
                    </div>
                    <div class="metric">
                        <strong>Remedies</strong>
                        <span>Compare remedy relationships from the case context.</span>
                    </div>
                    <div class="metric">
                        <strong>Materia Medica</strong>
                        <span>Cross-check remedy pictures after repertorization.</span>
                    </div>
                    <div class="metric">
                        <strong>Clinic Notes</strong>
                        <span>Keep repertory reasoning with the patient record.</span>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2>What CurenexAI adds to online repertory</h2>
            <p class="section-copy">CurenexAI is built for doctors who want more than a static repertory lookup. It connects search, AI support, remedy study, and clinical documentation.</p>
            <div class="grid">
                <article class="card">
                    <i class="fas fa-magnifying-glass"></i>
                    <h3>Fast rubric search</h3>
                    <p>Search for rubrics using practical patient language and review matching repertory entries quickly.</p>
                </article>
                <article class="card">
                    <i class="fas fa-brain"></i>
                    <h3>AI-assisted analysis</h3>
                    <p>Use AI support to explore symptom patterns while keeping final judgment with the doctor.</p>
                </article>
                <article class="card">
                    <i class="fas fa-file-prescription"></i>
                    <h3>Prescription workflow</h3>
                    <p>Move from repertory analysis to notes, remedy comparison, and digital prescriptions in the same workspace.</p>
                </article>
            </div>
        </section>

        <section>
            <h2>Online repertory vs clinic-ready repertory software</h2>
            <div class="compare-grid">
                <article class="card compare-old">
                    <h3>Basic online repertory</h3>
                    <ul>
                        <li>Useful for looking up rubrics</li>
                        <li>Usually separate from patient files</li>
                        <li>Limited follow-up continuity</li>
                        <li>Materia medica may be disconnected</li>
                    </ul>
                </article>
                <article class="card compare-good">
                    <h3>CurenexAI workflow</h3>
                    <ul>
                        <li>Kent repertory style rubric search</li>
                        <li>AI-supported remedy review</li>
                        <li>Patient records and digital prescriptions</li>
                        <li>Materia medica context in the same platform</li>
                    </ul>
                </article>
            </div>
        </section>

        <section>
            <h2>Frequently asked questions</h2>
            <div class="faq">
                <article class="card">
                    <h3>Is CurenexAI Kent repertory software?</h3>
                    <p>CurenexAI supports Kent repertory style workflows with digital rubric search, remedy comparison, and AI-assisted analysis.</p>
                </article>
                <article class="card">
                    <h3>Can fresh doctors use this?</h3>
                    <p>Yes. Fresh homeopathy doctors can use it to learn repertory thinking while building clean patient records from day one.</p>
                </article>
                <article class="card">
                    <h3>Does it include materia medica?</h3>
                    <p>CurenexAI connects repertory analysis with materia medica context so doctors can cross-check remedy pictures.</p>
                </article>
                <article class="card">
                    <h3>Is AI replacing the doctor?</h3>
                    <p>No. AI is used as support for search and review. Final case analysis and prescription remain with the qualified doctor.</p>
                </article>
            </div>
        </section>

        <section>
            <div class="cta">
                <h2>Try Kent repertory online inside CurenexAI</h2>
                <p>Use one platform for digital repertory search, AI-supported remedy suggestions, materia medica reference, patient management, and digital prescriptions.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Create Free Account</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy"><i class="fas fa-book"></i> Digital Repertory</a>
                </div>
            </div>
        </section>

        <footer>
            CurenexAI is homeopathic clinical software. It is not a medicine or skincare product.
            <a href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy">Digital repertory software</a>
            <span> | </span>
            <a href="<?php echo APP_URL; ?>/materia-medica">Online materia medica</a>
        </footer>
    </div>
</body>
</html>
