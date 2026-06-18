<?php
if (!isset($seoPage) || !is_array($seoPage)) {
    http_response_code(500);
    exit('SEO page configuration missing.');
}

function seoText($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function seoUrl($path) {
    if (preg_match('/^https?:\/\//', $path)) {
        return $path;
    }

    return APP_URL . $path;
}

$canonical = $seoPage['canonical'];
$schemaFeatures = $seoPage['schemaFeatures'] ?? [];
$faqItems = $seoPage['faq'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#14b8a6">

    <title><?php echo seoText($seoPage['title']); ?></title>
    <meta name="title" content="<?php echo seoText($seoPage['title']); ?>">
    <meta name="description" content="<?php echo seoText($seoPage['description']); ?>">
    <meta name="keywords" content="<?php echo seoText($seoPage['keywords']); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="<?php echo seoText($canonical); ?>">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo seoText($canonical); ?>">
    <meta property="og:title" content="<?php echo seoText($seoPage['ogTitle'] ?? $seoPage['title']); ?>">
    <meta property="og:description" content="<?php echo seoText($seoPage['description']); ?>">
    <meta property="og:image" content="https://curenexai.com/assets/image/xrunbg.png">
    <meta property="og:site_name" content="CurenexAI">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo seoText($seoPage['ogTitle'] ?? $seoPage['title']); ?>">
    <meta name="twitter:description" content="<?php echo seoText($seoPage['description']); ?>">
    <meta name="twitter:image" content="https://curenexai.com/assets/image/xrunbg.png">

    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/image/favicon/favicon.ico">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script type="application/ld+json">
    <?php
    echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'CurenexAI',
        'url' => $canonical,
        'applicationCategory' => 'MedicalApplication',
        'operatingSystem' => 'Web',
        'description' => $seoPage['schemaDescription'] ?? $seoPage['description'],
        'featureList' => $schemaFeatures,
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'USD',
            'description' => 'Free during beta period'
        ],
        'audience' => [
            '@type' => 'Audience',
            'audienceType' => 'Homeopathic Doctors, BHMS Practitioners, MD Homeopathy, Homeopathic Clinics'
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'CurenexAI',
            'url' => 'https://curenexai.com/'
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ?>
    </script>

    <?php if (!empty($faqItems)): ?>
    <script type="application/ld+json">
    <?php
    echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(function ($item) {
            return [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer']
                ]
            ];
        }, $faqItems)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ?>
    </script>
    <?php endif; ?>

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
            --ink: #1f2937;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.14), transparent 32%),
                linear-gradient(180deg, #f8fffe 0%, var(--bg) 100%);
            line-height: 1.6;
        }

        a { color: var(--brand); }

        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .topbar { padding: 18px 0; }
        .topbar a { text-decoration: none; font-weight: 600; }

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
        }

        h1 {
            margin: 18px 0 14px;
            font-size: clamp(2.25rem, 4vw, 4.1rem);
            line-height: 1.05;
        }

        h2 {
            margin: 0 0 10px;
            font-size: clamp(1.55rem, 2.4vw, 2.35rem);
        }

        h3 { margin: 0 0 8px; font-size: 1.1rem; }

        .lead {
            font-size: 1.08rem;
            color: var(--muted);
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
            text-decoration: none;
            font-weight: 700;
        }

        .button-primary { background: linear-gradient(135deg, var(--brand), #14b8a6); color: #fff; }
        .button-secondary { background: #fff; border: 1px solid var(--line); color: var(--text); }

        .panel,
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
        }

        .panel {
            background: rgba(255, 255, 255, 0.86);
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(8px);
        }

        .metric-list,
        .grid-2,
        .grid-3,
        .faq {
            display: grid;
            gap: 18px;
        }

        .metric-list { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 18px; }
        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .faq { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        .metric {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
        }

        .metric strong { display: block; font-size: 1.28rem; }
        .metric span, .section-copy, .card p, .card li, .faq p { color: var(--muted); }
        section { padding: 26px 0; }
        .section-copy { margin: 0 0 18px; max-width: 72ch; }
        .card i { color: var(--brand); font-size: 1.2rem; margin-bottom: 12px; }
        .card ul, .card ol { margin: 14px 0 0; padding-left: 20px; }
        .card.accent { border-top: 4px solid var(--accent); }
        .card.good { border-top: 4px solid var(--brand); }

        .cta {
            padding: 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #0f172a, #115e59);
            color: #fff;
        }

        .cta h2 { color: #fff; }
        .cta p { color: rgba(255, 255, 255, 0.82); max-width: 60ch; }
        .cta .button-secondary { color: #fff; border-color: rgba(255, 255, 255, 0.25); background: rgba(255, 255, 255, 0.08); }

        .related { display: flex; flex-wrap: wrap; gap: 12px; }
        .related a {
            background: #fff;
            border: 1px solid var(--line);
            padding: 10px 14px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            color: var(--text);
        }

        .related a:hover { border-color: var(--brand); color: var(--brand); }
        footer { padding: 28px 0 48px; color: var(--muted); font-size: 0.92rem; }

        @media (max-width: 960px) {
            .hero,
            .metric-list,
            .grid-2,
            .grid-3,
            .faq { grid-template-columns: 1fr; }
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
                <span class="eyebrow"><i class="<?php echo seoText($seoPage['icon'] ?? 'fas fa-robot'); ?>"></i> <?php echo seoText($seoPage['eyebrow']); ?></span>
                <h1><?php echo seoText($seoPage['h1']); ?></h1>
                <p class="lead"><?php echo seoText($seoPage['lead']); ?></p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register"><i class="fas fa-rocket"></i> Start Free Beta</a>
                    <a class="button button-secondary" href="#details"><i class="fas fa-arrow-down"></i> Explore Features</a>
                </div>
            </div>

            <div class="panel">
                <h2><?php echo seoText($seoPage['panelTitle']); ?></h2>
                <p class="section-copy"><?php echo seoText($seoPage['panelText']); ?></p>
                <div class="metric-list">
                    <?php foreach (($seoPage['metrics'] ?? []) as $metric): ?>
                    <div class="metric"><strong><?php echo seoText($metric['value']); ?></strong><span><?php echo seoText($metric['label']); ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="details">
            <h2><?php echo seoText($seoPage['primaryTitle']); ?></h2>
            <p class="section-copy"><?php echo seoText($seoPage['primaryText']); ?></p>
            <div class="grid-3">
                <?php foreach (($seoPage['cards'] ?? []) as $card): ?>
                <article class="card <?php echo seoText($card['variant'] ?? ''); ?>">
                    <i class="<?php echo seoText($card['icon'] ?? 'fas fa-check-circle'); ?>"></i>
                    <h3><?php echo seoText($card['title']); ?></h3>
                    <p><?php echo seoText($card['text']); ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php foreach (($seoPage['sections'] ?? []) as $section): ?>
        <section>
            <h2><?php echo seoText($section['title']); ?></h2>
            <?php if (!empty($section['text'])): ?><p class="section-copy"><?php echo seoText($section['text']); ?></p><?php endif; ?>
            <div class="<?php echo seoText($section['layout'] ?? 'grid-2'); ?>">
                <?php foreach (($section['items'] ?? []) as $item): ?>
                <article class="card <?php echo seoText($item['variant'] ?? ''); ?>">
                    <?php if (!empty($item['icon'])): ?><i class="<?php echo seoText($item['icon']); ?>"></i><?php endif; ?>
                    <h3><?php echo seoText($item['title']); ?></h3>
                    <?php if (!empty($item['text'])): ?><p><?php echo seoText($item['text']); ?></p><?php endif; ?>
                    <?php if (!empty($item['list'])): ?>
                    <ul>
                        <?php foreach ($item['list'] as $listItem): ?><li><?php echo seoText($listItem); ?></li><?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <?php if (!empty($faqItems)): ?>
        <section>
            <h2>Frequently asked questions</h2>
            <div class="faq">
                <?php foreach ($faqItems as $faq): ?>
                <article class="card">
                    <h3><?php echo seoText($faq['question']); ?></h3>
                    <p><?php echo seoText($faq['answer']); ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section>
            <div class="cta">
                <h2><?php echo seoText($seoPage['ctaTitle']); ?></h2>
                <p><?php echo seoText($seoPage['ctaText']); ?></p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo APP_URL; ?>/register"><i class="fas fa-user-plus"></i> Create Free Account</a>
                    <a class="button button-secondary" href="<?php echo APP_URL; ?>/documentation"><i class="fas fa-book-open"></i> Read Documentation</a>
                </div>
            </div>
        </section>

        <section>
            <h2>Related homeopathy software pages</h2>
            <p class="section-copy">Explore the CurenexAI SEO cluster for AI homeopathy software, digital repertory, cloud access, and RadarOpus alternative searches.</p>
            <div class="related">
                <a href="<?php echo APP_URL; ?>/radaropus-alternative">RadarOpus Alternative</a>
                <a href="<?php echo APP_URL; ?>/best-homeopathy-software">Best Homeopathy Software</a>
                <a href="<?php echo APP_URL; ?>/ai-homeopathy-software">AI Homeopathy Software</a>
                <a href="<?php echo APP_URL; ?>/cloud-homeopathy-software">Cloud Homeopathy Software</a>
                <a href="<?php echo APP_URL; ?>/homeopathy-repertory-software">Homeopathy Repertory Software</a>
                <a href="<?php echo APP_URL; ?>/digital-repertory-software-homeopathy">Digital Repertory Software</a>
            </div>
        </section>

        <footer>
            CurenexAI is homeopathic clinical software for qualified practitioners. It is not a medicine, drug, or skincare product.
        </footer>
    </div>
</body>
</html>