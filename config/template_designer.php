<?php

return [
    /** @var array<string, string> category slug => storage path (under public/storage) */
    'default_backgrounds' => [
        'graduation_certificate' => 'templates/backgrounds/graduation-certificate.png',
        'achievement_certificate' => 'templates/backgrounds/achievement-certificate.png',
        'participation_certificate' => 'templates/backgrounds/participation-certificate.png',
        'winner_certificate' => 'templates/backgrounds/winner-certificate.png',
        'best_attendance_certificate' => 'templates/backgrounds/best-attendance-certificate.png',
        'good_behaviour_certificate' => 'templates/backgrounds/good-behaviour-certificate.png',
        'creativity_award_certificate' => 'templates/backgrounds/creativity-award-certificate.png',
        'birthday_certificate' => 'templates/backgrounds/birthday-certificate.png',
        'bonafide' => 'templates/backgrounds/bonafide-certificate.png',
        'leaving_certificate' => 'templates/backgrounds/leaving-certificate.png',
    ],

    /** Category slug used when printing from Marksheets page */
    'exam_categories' => [
        'marksheet' => 'marksheet',
        'certificate' => [
            'pass' => 'achievement_certificate',
            'fail' => 'participation_certificate',
            'absent' => 'participation_certificate',
        ],
    ],

    /**
     * Optional fixed template slug per document type (overrides latest-in-category).
     * Example: 'certificate' => 'achivement-certificate'
     */
    'exam_templates' => [
        'certificate' => env('EXAM_CERTIFICATE_TEMPLATE_SLUG', 'achivement-certificate'),
        'marksheet' => env('EXAM_MARKSHEET_TEMPLATE_SLUG', 'default-marksheet'),
    ],

    /** Public URL for certificate verification links (React app) */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')),
];
