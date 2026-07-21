<?php

return [
    /**
     * Category slug => public-disk path.
     * Shared celebration artwork (navy banner + gold seal) ships in
     * resources/template-backgrounds/celebration-certificate.png and is copied
     * by TemplateDesignerSeeder — safe to re-run on production with --class.
     *
     * @var array<string, string>
     */
    'default_backgrounds' => [
        'graduation_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'achievement_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'participation_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'winner_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'best_attendance_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'good_behaviour_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'creativity_award_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'birthday_certificate' => 'templates/backgrounds/celebration-certificate.png',
        'bonafide' => 'templates/backgrounds/celebration-certificate.png',
        'leaving_certificate' => 'templates/backgrounds/celebration-certificate.png',
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
