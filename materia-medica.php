<?php
require_once 'includes/init.php';

$pageTitle = 'Online Materia Medica for Homeopathy | CurenexAI';
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

    <title>Online Materia Medica for Homeopathy | AI Search | CurenexAI</title>
    <meta name="title" content="Online Materia Medica for Homeopathy | AI Search | CurenexAI">
    <meta name="description" content="Use CurenexAI for online materia medica reference, remedy comparison, AI-supported homeopathy search, digital repertory, and clinical case workflow in one platform.">
    <meta name="keywords" content="materia medica, online materia medica, digital materia medica, homeopathy materia medica, boericke materia medica, kent materia medica, homeopathic remedies, AI materia medica, homeopathy software">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <link rel="canonical" href="https://curenexai.com/materia-medica">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://curenexai.com/materia-medica">
    <meta property="og:title" content="Online Materia Medica for Homeopathy | CurenexAI">
    <meta property="og:description" content="Search remedy context, compare materia medica notes, and connect remedy study with digital repertory and patient workflow.">
    <meta property="og:image" content="https://curenexai.com/assets/image/xrunbg.png">
    <meta property="og:site_name" content="CurenexAI">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Online Materia Medica for Homeopathy | CurenexAI">
    <meta name="twitter:description" content="AI-supported materia medica reference, digital repertory, and clinic workflow for homeopathic doctors.">
    <meta name="twitter:image" content="https://curenexai.com/assets/image/xrunbg.png">

    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "CurenexAI",
        "url": "https://curenexai.com/materia-medica",
        "applicationCategory": "HealthApplication",
        "operatingSystem": "Web Browser, Android, iOS",
        "description": "Online materia medica reference, AI-supported remedy search, digital repertory, patient records, and digital prescription workflow for homeopathic doctors.",
        "featureList": [
            "Online materia medica reference",
            "AI-supported remedy search",
            "Digital repertory",
            "Remedy comparison",
            "Patient records",
            "Digital prescriptions"
        ],
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "audience": {
            "@type": "Audience",
            "audienceType": "Homeopathic Doctors, BHMS Practitioners, MD Homeopathy, Homeopathy Students"
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
                "name": "What is materia medica in homeopathy?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Materia medica is the reference study of homeopathic remedies, including characteristic symptoms, modalities, mental and physical indications, and clinical remedy relationships."
                }
            },
            {
                "@type": "Question",
                "name": "Does CurenexAI include materia medica support?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes. CurenexAI connects materia medica reference with AI-supported remedy review, digital repertory search, patient notes, and prescription workflow."
                }
            },
            {
                "@type": "Question",
                "name": "Why use AI with materia medica?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "AI can help doctors move from patient language to remedy context faster, but final remedy selection should always remain under the doctor's clinical judgment."
                }
            },
            {
                "@type": "Question",
                "name": "Is CurenexAI useful for Boericke or Kent study?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "CurenexAI is designed to support classical homeopathy workflows by connecting remedy study, repertory search, and clinical case documentation in one digital workspace."
                }
            }
        ]
    }
    </script>

    <style>
        :root {
            --bg: #f6fbf9;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --line: #dbe7e5;
            --brand: #0f766e;
            --brand-strong: #115e59;
            --accent: #b45309;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.14), transparent 30%),
                linear-gradient(180deg, #fbfffd 0%, var(--bg) 100%);
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

        .topbar a,
        .button {
            text-decoration: none;
            font-weight: 700;
        }

        .hero {
            padding: 48px 0 28px;
            display: grid;
            gap: 28px;
            grid-template-columns: minmax(0, 1.05fr) minmax(300px, 0.95fr);
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
            font-weight: 800;
        }

        h1 {
            margin: 18px 0 14px;
            font-size: clamp(2.3rem, 4vw, 4.25rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        h2 {
            margin: 0 0 10px;
            font-size: clamp(1.55rem, 2.4vw, 2.35rem);
            letter-spacing: -0.02em;
        }

        .lead,
        .section-copy,
        .card p,
        .card li,
        .faq p {
            color: var(--muted);
        }

        .lead {
            font-size: 1.08rem;
            max-width: 64ch;
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

        .panel,
        .card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--line);
        }

        .panel {
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        section {
            padding: 26px 0;
        }

        .grid,
        .faq,
        .compare-grid,
        .metric-list {
            display: grid;
            gap: 18px;
        }

        .grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .compare-grid,
        .faq {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .metric-list {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .card,
        .metric {
            border-radius: 20px;
            padding: 22px;
        }

        .metric {
            background: #fff;
            border: 1px solid var(--line);
        }

        .metric strong {
            display: block;
            font-size: 1.2rem;
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
        .faq p {
            margin: 0;
        }

        .card ul {
            margin: 14px 0 0;
            padding-left: 18px;
        }

        .compare-good {
            border-top: 4px solid var(--brand);
        }

        .compare-study {
            border-top: 4px solid var(--accent);
        }

        .cta {
            padding: 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #0f172a, #115e59);
            color: #fff;
        }

        .cta p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 62ch;
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
                <span class="eyebrow"><i class="fas fa-book-open"></i> Online Materia Medica for Homeopathy</span>
                <h1>AI-Powered Materia Medica Search for Homeopathy Doctors</h1>
                <p class="lead">CurenexAI helps doctors study remedy pictures, compare materia medica context, connect symptoms with repertory rubrics, and keep the final prescription workflow in one clinical workspace.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-rocket"></i> Start Free Beta</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy"><i class="fas fa-magnifying-glass"></i> Digital Repertory</a>
                </div>
            </div>

            <div class="panel">
                <h2>Why doctors search materia medica online</h2>
                <p class="section-copy">Materia medica is high-volume because every student and practitioner needs remedy reference. CurenexAI connects that study habit with practical case analysis.</p>
                <div class="metric-list">
                    <div class="metric">
                        <strong>Remedy Context</strong>
                        <span>Review characteristic indications and remedy relationships.</span>
                    </div>
                    <div class="metric">
                        <strong>AI Search</strong>
                        <span>Move from patient language to remedy study faster.</span>
                    </div>
                    <div class="metric">
                        <strong>Repertory Link</strong>
                        <span>Connect materia medica reading with rubrics.</span>
                    </div>
                    <div class="metric">
                        <strong>Case Workflow</strong>
                        <span>Keep notes and prescriptions with the patient file.</span>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2>What makes digital materia medica useful</h2>
            <p class="section-copy">A good online materia medica should not be just a copied book page. It should help the doctor understand remedy character, compare close remedies, and return to the patient case without losing context.</p>
            <div class="grid">
                <article class="card">
                    <i class="fas fa-leaf"></i>
                    <h3>Remedy picture review</h3>
                    <p>Study mental, physical, modality, and general indications while evaluating the patient story.</p>
                </article>
                <article class="card">
                    <i class="fas fa-scale-balanced"></i>
                    <h3>Compare close remedies</h3>
                    <p>Use remedy context to separate similar options after repertory analysis.</p>
                </article>
                <article class="card">
                    <i class="fas fa-notes-medical"></i>
                    <h3>Clinical continuity</h3>
                    <p>Save notes, prescription reasoning, and follow-up details in the same software workflow.</p>
                </article>
            </div>
        </section>

        <section>
            <h2>Materia medica plus repertory is stronger</h2>
            <div class="compare-grid">
                <article class="card compare-study">
                    <h3>Only reading materia medica</h3>
                    <ul>
                        <li>Good for remedy study and memory building</li>
                        <li>Can be slow during a live consultation</li>
                        <li>Harder to connect many symptoms together</li>
                        <li>Case notes often stay in another file or notebook</li>
                    </ul>
                </article>
                <article class="card compare-good">
                    <h3>CurenexAI workflow</h3>
                    <ul>
                        <li>Review materia medica beside repertory thinking</li>
                        <li>Use AI support to explore remedy context</li>
                        <li>Store patient records and prescription history</li>
                        <li>Build a clinic-ready workflow from the first case</li>
                    </ul>
                </article>
            </div>
        </section>

        <section>
            <h2>Built for common materia medica searches</h2>
            <p class="section-copy">This page is designed around how doctors and students actually search: materia medica, online materia medica, Boericke materia medica, Kent repertory, homeopathic remedies, and AI homeopathy software.</p>
            <div class="grid">
                <article class="card">
                    <i class="fas fa-user-graduate"></i>
                    <h3>Students and fresh doctors</h3>
                    <p>Use digital remedy reference while learning classical remedy pictures and case-taking habits.</p>
                </article>
                <article class="card">
                    <i class="fas fa-user-doctor"></i>
                    <h3>Practicing doctors</h3>
                    <p>Review remedies during busy consultations without separating notes, repertory, and prescriptions.</p>
                </article>
                <article class="card">
                    <i class="fas fa-clinic-medical"></i>
                    <h3>Growing clinics</h3>
                    <p>Keep materia medica reference connected with patient management and follow-up continuity.</p>
                </article>
            </div>
        </section>

        <section>
            <h2>Frequently asked questions</h2>
            <div class="faq">
                <article class="card">
                    <h3>What is materia medica?</h3>
                    <p>Materia medica is the study of homeopathic remedies and their characteristic symptom pictures.</p>
                </article>
                <article class="card">
                    <h3>Is online materia medica enough for prescribing?</h3>
                    <p>It is a reference tool. Doctors should combine materia medica with repertory, case-taking, clinical judgment, and follow-up observation.</p>
                </article>
                <article class="card">
                    <h3>Does CurenexAI replace Boericke or Kent?</h3>
                    <p>No. It supports classical study by making remedy context, repertory search, and case documentation easier to use digitally.</p>
                </article>
                <article class="card">
                    <h3>Can fresh homeopathy doctors use it?</h3>
                    <p>Yes. CurenexAI is useful for fresh doctors who want to learn remedy pictures while building clean patient records from day one.</p>
                </article>
            </div>
        </section>

        <section>
            <div class="cta">
                <h2>Start with materia medica, then move into full clinic workflow</h2>
                <p>Use CurenexAI for remedy reference, digital repertory, AI-supported case analysis, patient records, and digital prescriptions.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Create Free Account</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/support.php"><i class="fas fa-comments"></i> Contact Support</a>
                </div>
            </div>
        </section>

        <footer>
            CurenexAI is homeopathic clinical software. It is not a medicine or skincare product.
            <a href="<?php echo APP_URL; ?>/kent-repertory-online">Kent repertory online</a>
            <span> | </span>
            <a href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy">Explore digital repertory software &rarr;</a>
        </footer>
    </div>
</body>
</html>
