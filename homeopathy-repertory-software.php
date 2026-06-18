<?php
require_once 'includes/init.php';

$seoPage = [
    'title' => 'Homeopathy Repertory Software | CurenexAI Digital Repertory & AI',
    'ogTitle' => 'Homeopathy Repertory Software | CurenexAI',
    'description' => 'CurenexAI homeopathy repertory software helps doctors search rubrics, review remedies, connect materia medica, manage patients, and use AI-assisted case analysis.',
    'keywords' => 'homeopathy repertory software, homeopathic repertory software, repertory software for homeopathy, digital repertory software, Kent repertory software, AI repertory software, CurenexAI',
    'canonical' => 'https://curenexai.com/homeopathy-repertory-software',
    'schemaDescription' => 'CurenexAI is homeopathy repertory software with digital rubric search, materia medica context, AI-assisted remedy review, patient records, and digital prescriptions.',
    'schemaFeatures' => ['Digital repertory search', 'Kent rubric workflow', 'Materia medica reference', 'AI remedy suggestions', 'Patient records', 'Digital prescriptions', 'Cloud access'],
    'icon' => 'fas fa-book-medical',
    'eyebrow' => 'Homeopathy repertory software with AI support',
    'h1' => 'Homeopathy Repertory Software for Digital Rubric and Remedy Review',
    'lead' => 'CurenexAI supports homeopathic doctors with repertory workflows, rubric exploration, remedy review, materia medica context, patient records, and AI-assisted case analysis in one platform.',
    'panelTitle' => 'Repertory plus clinic workflow',
    'panelText' => 'A repertory tool is most useful when it connects directly with the patient case, prescription history, and follow-up context.',
    'metrics' => [
        ['value' => 'Rubrics', 'label' => 'Digital search'],
        ['value' => 'Remedies', 'label' => 'AI-assisted review'],
        ['value' => 'Cases', 'label' => 'Patient context']
    ],
    'primaryTitle' => 'What modern repertory software should do',
    'primaryText' => 'Doctors need more than isolated rubric lookup. Modern repertory software should connect symptom analysis, remedy review, materia medica, and patient records.',
    'cards' => [
        ['icon' => 'fas fa-search', 'title' => 'Rubric search', 'text' => 'Search repertory rubrics and organize relevant findings during case analysis.', 'variant' => 'good'],
        ['icon' => 'fas fa-pills', 'title' => 'Remedy review', 'text' => 'Compare remedy possibilities using repertory context, case notes, and AI-assisted review.', 'variant' => 'good'],
        ['icon' => 'fas fa-file-prescription', 'title' => 'Prescription connection', 'text' => 'Carry repertory conclusions into patient-specific prescriptions and follow-up records.', 'variant' => 'good']
    ],
    'sections' => [
        [
            'title' => 'Repertory workflow inside CurenexAI',
            'text' => 'CurenexAI helps connect repertory work with the broader clinical workflow.',
            'items' => [
                ['title' => 'Search and select rubrics', 'text' => 'Use digital search to identify relevant rubrics from case-taking details.'],
                ['title' => 'Review remedy context', 'text' => 'Connect rubrics with remedy possibilities and materia medica references.'],
                ['title' => 'Use AI as a reviewer', 'text' => 'Let AI help organize case context and surface possibilities for doctor review.']
            ]
        ],
        [
            'title' => 'Why repertory software should be connected',
            'text' => 'Disconnected repertory tools create extra work. CurenexAI keeps the repertory close to patient records, lab context, and prescriptions.',
            'items' => [
                ['icon' => 'fas fa-user-md', 'title' => 'Patient-centered analysis', 'text' => 'Keep repertory decisions tied to the patient timeline and consultation notes.'],
                ['icon' => 'fas fa-cloud', 'title' => 'Online access', 'text' => 'Use repertory workflows in a browser instead of depending only on desktop software.']
            ]
        ]
    ],
    'faq' => [
        ['question' => 'What is homeopathy repertory software?', 'answer' => 'Homeopathy repertory software helps doctors search rubrics, compare remedies, and support case analysis using digital repertory workflows.'],
        ['question' => 'Does CurenexAI include repertory tools?', 'answer' => 'Yes. CurenexAI supports digital repertory workflows and connects them with AI-assisted case review, patient records, and prescriptions.'],
        ['question' => 'Is this different from digital repertory software?', 'answer' => 'The terms overlap. Digital repertory software focuses on electronic rubric and remedy search, while CurenexAI also includes patient management, AI support, lab report analysis, and prescriptions.'],
        ['question' => 'Can AI help with repertory analysis?', 'answer' => 'AI can help organize case notes, review possible remedy patterns, and surface relevant context, but the qualified doctor must make the final decision.']
    ],
    'ctaTitle' => 'Use repertory software connected to your full clinic workflow',
    'ctaText' => 'Try CurenexAI free during beta and combine digital repertory, AI-assisted analysis, patient records, and prescriptions in one platform.'
];

require 'includes/seo_landing_template.php';