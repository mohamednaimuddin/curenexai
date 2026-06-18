<?php
require_once 'includes/init.php';

$seoPage = [
    'title' => 'AI Homeopathy Software | CurenexAI Case Analysis & Repertory Tools',
    'ogTitle' => 'AI Homeopathy Software | CurenexAI',
    'description' => 'CurenexAI is AI homeopathy software for doctors who want case analysis support, repertory workflows, remedy suggestions, lab report analysis, and patient management.',
    'keywords' => 'AI homeopathy software, homeopathy AI, AI remedy suggestions, homeopathic AI software, AI repertory software, homeopathy case analysis AI, CurenexAI',
    'canonical' => 'https://curenexai.com/ai-homeopathy-software',
    'schemaDescription' => 'CurenexAI is AI homeopathy software that supports remedy suggestions, repertory analysis, patient records, lab report analysis, and digital prescriptions for qualified practitioners.',
    'schemaFeatures' => ['AI remedy suggestions', 'RAG-based diagnosis support', 'Digital repertory search', 'Lab report analysis', 'Dermo AI skin analysis', 'Patient management', 'Digital prescriptions'],
    'icon' => 'fas fa-brain',
    'eyebrow' => 'AI homeopathy software for doctors',
    'h1' => 'AI Homeopathy Software for Case Analysis and Repertory Support',
    'lead' => 'CurenexAI helps homeopathic doctors use AI as a clinical assistant: reviewing symptoms, repertory context, lab reports, and remedy possibilities while the final prescription remains with the qualified practitioner.',
    'panelTitle' => 'AI with doctor oversight',
    'panelText' => 'The goal is not to replace clinical judgment. CurenexAI uses AI to organize context, surface possibilities, and help doctors review cases more efficiently.',
    'metrics' => [
        ['value' => 'RAG', 'label' => 'Grounded case support'],
        ['value' => 'Gemini', 'label' => 'AI remedy review'],
        ['value' => 'Dermo', 'label' => 'Skin image analysis']
    ],
    'primaryTitle' => 'How AI supports homeopathy workflow',
    'primaryText' => 'AI works best when it helps the doctor see relevant patterns faster, compare remedy possibilities, and keep patient context organized.',
    'cards' => [
        ['icon' => 'fas fa-notes-medical', 'title' => 'Case note analysis', 'text' => 'Use AI support to review symptoms, modalities, mental generals, physical generals, and clinical context from case-taking notes.', 'variant' => 'good'],
        ['icon' => 'fas fa-book-open', 'title' => 'Repertory assistance', 'text' => 'Connect case information with repertory and materia medica context for a clearer remedy review workflow.', 'variant' => 'good'],
        ['icon' => 'fas fa-prescription', 'title' => 'Prescription workflow', 'text' => 'Move from analysis to digital prescription without separating patient context from the clinical decision.', 'variant' => 'good']
    ],
    'sections' => [
        [
            'title' => 'AI features inside CurenexAI',
            'text' => 'CurenexAI combines several AI-assisted tools for homeopathic clinical workflows.',
            'items' => [
                ['icon' => 'fas fa-robot', 'title' => 'Remedy suggestions', 'text' => 'Generate remedy candidates from symptoms and clinical notes for the doctor to review.'],
                ['icon' => 'fas fa-database', 'title' => 'RAG-based diagnosis support', 'text' => 'Use retrieval-augmented workflows to ground analysis in available repertory and clinical context.'],
                ['icon' => 'fas fa-camera-retro', 'title' => 'Dermo AI', 'text' => 'Support dermatology-related cases with skin image analysis and case-context review.']
            ]
        ],
        [
            'title' => 'Why AI-first matters now',
            'text' => 'Traditional repertory software is powerful, but modern clinics increasingly need intelligent support across the full case workflow.',
            'items' => [
                ['title' => 'Speed', 'text' => 'AI can help organize long case notes and highlight clinically relevant details faster.'],
                ['title' => 'Context', 'text' => 'Patient history, lab reports, rubrics, and prescriptions can stay connected in one workflow.'],
                ['title' => 'Reviewability', 'text' => 'Doctors can use AI suggestions as review material instead of treating them as automatic prescriptions.']
            ]
        ]
    ],
    'faq' => [
        ['question' => 'Which homeopathy software uses AI?', 'answer' => 'CurenexAI uses AI for remedy suggestions, RAG-based diagnosis support, lab report analysis, Dermo skin analysis, and case workflow assistance.'],
        ['question' => 'Can AI prescribe homeopathic remedies by itself?', 'answer' => 'No. CurenexAI is designed as decision support. The qualified doctor must review the case and make the final clinical decision.'],
        ['question' => 'Does CurenexAI include repertory tools?', 'answer' => 'Yes. CurenexAI includes digital repertory workflows and connects them with AI-assisted case review.'],
        ['question' => 'Is AI useful for homeopathic clinics?', 'answer' => 'AI can help clinics summarize information, review patterns, analyze reports, and compare remedy possibilities more efficiently.']
    ],
    'ctaTitle' => 'Start using AI in your homeopathy workflow',
    'ctaText' => 'Create a free beta account and test AI-assisted remedy review, repertory support, lab report analysis, and patient management in CurenexAI.'
];

require 'includes/seo_landing_template.php';