<?php

namespace Database\Seeders;

use App\Models\CmsItem;
use App\Models\IdCard;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Copies sample media into storage and wires CMS / profile / ID card image paths.
 */
class SampleMediaSeeder extends Seeder
{
    public function run(): void
    {
        $source = resource_path('sample-media');
        if (! File::isDirectory($source)) {
            $this->command?->warn('resources/sample-media missing — run: php scripts/generate-sample-media.php');

            return;
        }

        $destRoot = storage_path('app/public/sample');
        File::ensureDirectoryExists($destRoot);
        File::copyDirectory($source, $destRoot);

        $path = fn (string $rel) => 'sample/'.$rel;

        $cmsImages = [
            'banner:hero-slide-1' => $path('heroes/hero-1.jpg'),
            'banner:hero-slide-2' => $path('heroes/hero-2.jpg'),
            'banner:hero-slide-3' => $path('heroes/hero-3.jpg'),
            'program:nursery' => $path('cms/nursery.jpg'),
            'program:lkg' => $path('cms/lkg.jpg'),
            'program:ukg' => $path('cms/ukg.jpg'),
            'curriculum:nursery-curriculum' => $path('cms/nursery.jpg'),
            'curriculum:lkg-curriculum' => $path('cms/lkg.jpg'),
            'curriculum:ukg-curriculum' => $path('cms/ukg.jpg'),
            'facility:smart' => $path('cms/facility-smart.jpg'),
            'facility:playground' => $path('cms/facility-playground.jpg'),
            'facility:library' => $path('cms/facility-library.jpg'),
            'facility:art-room' => $path('cms/facility-art.jpg'),
            'facility:music-room' => $path('cms/facility-music.jpg'),
            'facility:transport' => $path('cms/facility-transport.jpg'),
            'activity:art' => $path('cms/activity-art.jpg'),
            'activity:music' => $path('cms/activity-music.jpg'),
            'activity:sports' => $path('cms/activity-sports.jpg'),
            'activity:dance' => $path('cms/activity-dance.jpg'),
            'activity:yoga' => $path('cms/activity-yoga.jpg'),
            'activity:storytelling' => $path('cms/activity-story.jpg'),
            'event:parent-teacher-meet' => $path('cms/event-ptm.jpg'),
            'event:annual-day' => $path('cms/event-annual.jpg'),
            'blog:play-based-learning' => $path('cms/blog-play.jpg'),
            'blog:first-day-tips' => $path('cms/blog-first-day.jpg'),
            'gallery:classroom-fun' => $path('gallery/classroom-1.jpg'),
            'gallery:festival-celebrations' => $path('gallery/festival-1.jpg'),
            'staff:principal' => $path('avatars/principal.jpg'),
            'staff:nursery-teacher' => $path('avatars/staff-nursery.jpg'),
            'staff:lkg-teacher' => $path('avatars/staff-lkg.jpg'),
            'staff:activity-coordinator' => $path('avatars/staff-activity.jpg'),
        ];

        foreach ($cmsImages as $key => $image) {
            [$type, $slug] = explode(':', $key, 2);
            CmsItem::query()
                ->where('type', $type)
                ->where('slug', $slug)
                ->update(['image' => $image]);
        }

        // Extra gallery rows with images
        $tenant = Tenant::query()->first();
        $extraGallery = [
            ['slug' => 'circle-time', 'title' => 'Circle Time', 'summary' => 'Morning circle songs', 'image' => $path('gallery/classroom-2.jpg'), 'meta' => ['album' => 'Daily Life']],
            ['slug' => 'diwali-celebration', 'title' => 'Diwali Celebration', 'summary' => 'Lights and rangoli', 'image' => $path('gallery/festival-2.jpg'), 'meta' => ['album' => 'Events']],
            ['slug' => 'outdoor-play', 'title' => 'Outdoor Play', 'summary' => 'Sunshine and slides', 'image' => $path('gallery/outdoor-1.jpg'), 'meta' => ['album' => 'Daily Life']],
            ['slug' => 'art-display', 'title' => 'Art Display', 'summary' => 'Little artists shine', 'image' => $path('gallery/art-1.jpg'), 'meta' => ['album' => 'Activities']],
        ];
        foreach ($extraGallery as $i => $row) {
            CmsItem::updateOrCreate(
                ['tenant_id' => $tenant?->id, 'type' => 'gallery', 'slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'summary' => $row['summary'],
                    'image' => $row['image'],
                    'meta' => $row['meta'],
                    'status' => 'published',
                    'sort_order' => 20 + $i,
                ],
            );
        }

        $profile = CmsItem::query()->where('type', 'school_profile')->where('slug', 'profile')->first();
        if ($profile) {
            $meta = $profile->meta ?? [];
            $meta['cover_image'] = $path('cover.jpg');
            $meta['home_about_image'] = $path('home-about.jpg');
            $meta['home_why_image'] = $path('home-why.jpg');
            $meta['about_page_image'] = $path('home-about.jpg');
            $meta['about_page_image_accent'] = $path('gallery/outdoor-1.jpg');
            $meta['principal_image'] = $path('avatars/principal.jpg');
            $meta['page_about_image'] = $path('pages/about.jpg');
            $meta['page_programs_image'] = $path('pages/programs.jpg');
            $meta['page_facilities_image'] = $path('pages/facilities.jpg');
            $meta['page_activities_image'] = $path('pages/activities.jpg');
            $meta['page_curriculum_image'] = $path('pages/curriculum.jpg');
            $meta['page_staff_image'] = $path('pages/staff.jpg');
            $meta['page_events_image'] = $path('pages/events.jpg');
            $meta['page_blog_image'] = $path('pages/blog.jpg');
            $meta['page_gallery_image'] = $path('pages/gallery.jpg');
            $meta['page_careers_image'] = $path('pages/careers.jpg');
            $meta['page_faq_image'] = $path('pages/faq.jpg');
            $meta['page_admission_image'] = $path('pages/admission.jpg');
            $meta['page_book_tour_image'] = $path('pages/book-tour.jpg');
            $meta['page_payment_image'] = $path('pages/payment.jpg');
            $meta['page_live_image'] = $path('pages/live.jpg');
            $meta['page_legal_image'] = $path('pages/legal.jpg');
            $meta['page_contact_image'] = $path('pages/contact.jpg');
            $profile->update([
                'image' => $path('logo.png'),
                'meta' => $meta,
            ]);
        }

        $avatars = [
            'STU-DEMO001' => $path('avatars/student-aarav.jpg'),
            'STU-DEMO002' => $path('avatars/student-ananya.jpg'),
            'TCH-DEMO001' => $path('avatars/teacher.jpg'),
            'STF-DEMO001' => $path('avatars/staff.jpg'),
            'PAR-DEMO001' => $path('avatars/parent.jpg'),
        ];
        foreach ($avatars as $cardNumber => $photo) {
            IdCard::query()->where('card_number', $cardNumber)->update(['photo_path' => $photo]);
        }

        // Extra UKG student with photo
        IdCard::updateOrCreate(
            ['card_number' => 'STU-DEMO003'],
            [
                'tenant_id' => $tenant?->id,
                'qr_token' => 'LS-DEMO-STU-VIHAAN03',
                'card_type' => 'student',
                'full_name' => 'Vihaan Mehta',
                'photo_path' => $path('avatars/student-vihaan.jpg'),
                'blood_group' => 'A+',
                'academic_year' => date('Y').'-'.(date('Y') + 1),
                'emergency_contact' => '+91 98765 11111',
                'status' => 'active',
                'issue_date' => now()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
                'meta' => [
                    'admission_number' => 'ADM-2025-003',
                    'roll_number' => '05',
                    'class' => 'UKG',
                    'class_name' => 'UKG',
                    'section_name' => 'A',
                    'parent_name' => 'Neha Mehta',
                    'parent_phone' => '+91 98765 11111',
                ],
            ],
        );

        $this->command?->info('Sample media copied to storage/app/public/sample and CMS paths updated.');
    }
}
