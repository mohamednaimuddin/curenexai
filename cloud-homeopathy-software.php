<?php
require_once 'includes/init.php';

$seoPage = [
    'title' => 'Cloud Homeopathy Software | CurenexAI Online Clinic Platform',
    'ogTitle' => 'Cloud Homeopathy Software | CurenexAI',
    'description' => 'CurenexAI is cloud homeopathy software for doctors who need online access, patient records, repertory tools, AI case support, and digital prescriptions.',
    'keywords' => 'cloud homeopathy software, online homeopathy software, web based homeopathy software, homeopathy clinic software online, cloud repertory software, CurenexAI',
    'canonical' => 'https://curenexai.com/cloud-homeopathy-software',
    'schemaDescription' => 'CurenexAI is cloud homeopathy software for online patient management, AI-assisted case analysis, repertory workflows, digital prescriptions, and lab report analysis.',
    'schemaFeatures' => ['Cloud-based access', 'Online patient records', 'Digital prescriptions', 'AI-assisted analysis', 'Repertory workflows', 'Lab report upload', 'Multi-device use'],
    'icon' => 'fas fa-cloud',
    'eyebrow' => 'Cloud homeopathy software for modern clinics',
    'h1' => 'Cloud Homeopathy Software for Online Clinic Workflows',
    'lead' => 'CurenexAI gives homeopathic doctors browser-based access to patient records, repertory workflows, AI-assisted case analysis, lab reports, and prescriptions without a traditional desktop-only setup.',
    'panelTitle' => 'Why cloud access matters',
    'panelText' => 'Doctors need to work from clinic systems, laptops, tablets, and phones. A cloud-first platform keeps the workflow accessible and consistent.',
    'metrics' => [
        ['value' => 'Browser', 'label' => 'No heavy install'],
        ['value' => 'Online', 'label' => 'Clinic access'],
        ['value' => 'Secure', 'label' => 'Doctor accounts']
    ],
    'primaryTitle' => 'What cloud homeopathy software should include',
    'primaryText' => 'Cloud software should not be only a hosted database. It should support the complete clinical path from case-taking to prescription.',
    'cards' => [
        ['icon' => 'fas fa-user-injured', 'title' => 'Online patient records', 'text' => 'Manage patient details, consultations, history, and follow-ups from one browser-based workflow.', 'variant' => 'good'],
        ['icon' => 'fas fa-book-medical', 'title' => 'Repertory access', 'text' => 'Use digital repertory workflows online without depending on one installed desktop system.', 'variant' => 'good'],
        ['icon' => 'fas fa-brain', 'title' => 'AI case support', 'text' => 'Bring AI-assisted analysis into the same cloud workflow as patient records and prescriptions.', 'variant' => 'good']
    ],
    'sections' => [
        [
            'title' => 'Benefits of moving from desktop to cloud',
            'text' => 'A browser-based platform helps clinics reduce setup friction and make daily work more flexible.',
            'items' => [
                ['title' => 'Access from more devices', 'text' => 'Use CurenexAI from a clinic desktop, laptop, tablet, or phone with a modern browser.'],
                ['title' => 'Simpler onboarding', 'text' => 'Register, verify your account, and start using the platform without a complex local installation.'],
                ['title' => 'Connected records', 'text' => 'Keep patients, consultations, prescriptions, lab reports, and case notes together.']
            ]
        ],
        [
            'title' => 'Cloud-first does not mean generic',
            'text' => 'CurenexAI is designed specifically for homeopathic clinical workflows, not as a generic appointment or billing tool.',
            'items' => [
                ['icon' => 'fas fa-stethoscope', 'title' => 'Clinical case workflow', 'text' => 'Support homeopathic case-taking, repertory review, remedy suggestions, and follow-up context.'],
                ['icon' => 'fas fa-file-medical-alt', 'title' => 'Digital prescriptions', 'text' => 'Create professional prescriptions with treatment instructions and patient-specific context.']
            ]
        ]
    ],
    'faq' => [
        ['question' => 'What is cloud homeopathy software?', 'answer' => 'Cloud homeopathy software runs online in a browser so doctors can manage patients, cases, repertory workflows, and prescriptions without being tied to a single installed computer.'],
        ['question' => 'Is CurenexAI web-based?', 'answer' => 'Yes. CurenexAI is designed as a browser-based homeopathic clinical platform.'],
        ['question' => 'Can I use CurenexAI on multiple devices?', 'answer' => 'CurenexAI is built for online access from modern browsers, including clinic desktops, laptops, tablets, and phones.'],
        ['question' => 'Does cloud software support AI features?', 'answer' => 'Yes. CurenexAI combines cloud access with AI-assisted case analysis, remedy suggestions, lab report analysis, and Dermo AI skin analysis.']
    ],
    'ctaTitle' => 'Move your homeopathy workflow to the cloud',
    'ctaText' => 'Start free during beta and use CurenexAI for online patient records, repertory workflows, AI support, and digital prescriptions.'
];

require 'includes/seo_landing_template.php';