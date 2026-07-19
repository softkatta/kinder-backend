<?php

$base = dirname(__DIR__).'/resources/sample-media';
foreach (['avatars', 'gallery', 'pages', 'heroes', 'cms'] as $dir) {
    if (! is_dir("{$base}/{$dir}")) {
        mkdir("{$base}/{$dir}", 0777, true);
    }
}

function makeSampleImage(string $path, int $w, int $h, array $c1, array $c2, string $label): void
{
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $r = (int) ($c1[0] + ($c2[0] - $c1[0]) * $t);
        $g = (int) ($c1[1] + ($c2[1] - $c1[1]) * $t);
        $b = (int) ($c1[2] + ($c2[2] - $c1[2]) * $t);
        $col = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $col);
    }
    for ($i = 0; $i < 5; $i++) {
        $alpha = imagecolorallocatealpha($im, 255, 255, 255, 100);
        imagefilledellipse($im, random_int(0, $w), random_int(0, $h), random_int(80, 220), random_int(80, 220), $alpha);
    }
    $white = imagecolorallocate($im, 255, 255, 255);
    $shadow = imagecolorallocatealpha($im, 0, 0, 0, 80);
    $font = 5;
    $tw = imagefontwidth($font) * strlen($label);
    $th = imagefontheight($font);
    $x = (int) (($w - $tw) / 2);
    $y = (int) (($h - $th) / 2);
    imagestring($im, $font, $x + 2, $y + 2, $label, $shadow);
    imagestring($im, $font, $x, $y, $label, $white);

    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (str_ends_with($path, '.png')) {
        imagepng($im, $path);
    } else {
        imagejpeg($im, $path, 88);
    }
    imagedestroy($im);
    echo "wrote {$path}\n";
}

$files = [
    ['heroes/hero-1.jpg', 1600, 900, [56, 189, 248], [251, 146, 60], 'Little Stars Campus'],
    ['heroes/hero-2.jpg', 1600, 900, [52, 211, 153], [56, 189, 248], 'Learning Through Play'],
    ['heroes/hero-3.jpg', 1600, 900, [251, 191, 36], [244, 114, 182], 'Happy Little Minds'],
    ['logo.png', 512, 512, [14, 165, 233], [251, 146, 60], 'LS'],
    ['cover.jpg', 1600, 600, [125, 211, 252], [254, 215, 170], 'Little Stars Cover'],
    ['home-about.jpg', 1200, 900, [165, 243, 252], [254, 249, 195], 'About Our School'],
    ['home-why.jpg', 1200, 900, [187, 247, 208], [186, 230, 253], 'Why Choose Us'],
    ['pages/about.jpg', 1600, 700, [125, 211, 252], [196, 181, 253], 'About'],
    ['pages/programs.jpg', 1600, 700, [253, 186, 116], [251, 207, 232], 'Programs'],
    ['pages/facilities.jpg', 1600, 700, [110, 231, 183], [147, 197, 253], 'Facilities'],
    ['pages/activities.jpg', 1600, 700, [253, 164, 175], [253, 224, 71], 'Activities'],
    ['pages/curriculum.jpg', 1600, 700, [147, 197, 253], [196, 181, 253], 'Curriculum'],
    ['pages/staff.jpg', 1600, 700, [252, 211, 77], [165, 243, 252], 'Our Staff'],
    ['pages/events.jpg', 1600, 700, [249, 168, 212], [253, 186, 116], 'Events'],
    ['pages/blog.jpg', 1600, 700, [167, 243, 208], [186, 230, 253], 'Blog'],
    ['pages/gallery.jpg', 1600, 700, [253, 186, 116], [196, 181, 253], 'Gallery'],
    ['pages/careers.jpg', 1600, 700, [125, 211, 252], [134, 239, 172], 'Careers'],
    ['pages/faq.jpg', 1600, 700, [196, 181, 253], [165, 243, 252], 'FAQ'],
    ['pages/admission.jpg', 1600, 700, [251, 146, 60], [253, 224, 71], 'Admission'],
    ['pages/book-tour.jpg', 1600, 700, [52, 211, 153], [125, 211, 252], 'Book a Tour'],
    ['pages/payment.jpg', 1600, 700, [110, 231, 183], [253, 186, 116], 'Payment'],
    ['pages/live.jpg', 1600, 700, [244, 114, 182], [147, 197, 253], 'Watch Live'],
    ['pages/legal.jpg', 1600, 700, [148, 163, 184], [203, 213, 225], 'Legal'],
    ['pages/contact.jpg', 1600, 700, [56, 189, 248], [167, 243, 208], 'Contact'],
    ['cms/nursery.jpg', 1000, 750, [253, 224, 71], [251, 146, 60], 'Nursery'],
    ['cms/lkg.jpg', 1000, 750, [125, 211, 252], [52, 211, 153], 'LKG'],
    ['cms/ukg.jpg', 1000, 750, [196, 181, 253], [251, 146, 60], 'UKG'],
    ['cms/facility-smart.jpg', 1000, 750, [56, 189, 248], [147, 197, 253], 'Smart Class'],
    ['cms/facility-playground.jpg', 1000, 750, [52, 211, 153], [253, 224, 71], 'Playground'],
    ['cms/facility-library.jpg', 1000, 750, [251, 146, 60], [253, 186, 116], 'Library'],
    ['cms/facility-art.jpg', 1000, 750, [244, 114, 182], [253, 224, 71], 'Art Room'],
    ['cms/facility-music.jpg', 1000, 750, [167, 139, 250], [56, 189, 248], 'Music Room'],
    ['cms/facility-transport.jpg', 1000, 750, [125, 211, 252], [74, 222, 128], 'Transport'],
    ['cms/activity-art.jpg', 1000, 750, [251, 113, 133], [253, 186, 116], 'Art'],
    ['cms/activity-music.jpg', 1000, 750, [129, 140, 248], [56, 189, 248], 'Music'],
    ['cms/activity-sports.jpg', 1000, 750, [74, 222, 128], [253, 224, 71], 'Sports'],
    ['cms/activity-dance.jpg', 1000, 750, [244, 114, 182], [196, 181, 253], 'Dance'],
    ['cms/activity-yoga.jpg', 1000, 750, [167, 243, 208], [165, 243, 252], 'Yoga'],
    ['cms/activity-story.jpg', 1000, 750, [253, 186, 116], [196, 181, 253], 'Storytime'],
    ['cms/event-ptm.jpg', 1000, 750, [125, 211, 252], [254, 215, 170], 'PTM'],
    ['cms/event-annual.jpg', 1000, 750, [251, 146, 60], [244, 114, 182], 'Annual Day'],
    ['cms/blog-play.jpg', 1000, 750, [52, 211, 153], [125, 211, 252], 'Play Learning'],
    ['cms/blog-first-day.jpg', 1000, 750, [253, 224, 71], [56, 189, 248], 'First Day Tips'],
    ['gallery/classroom-1.jpg', 1200, 900, [125, 211, 252], [254, 240, 138], 'Classroom Fun'],
    ['gallery/classroom-2.jpg', 1200, 900, [167, 243, 208], [253, 186, 116], 'Circle Time'],
    ['gallery/festival-1.jpg', 1200, 900, [251, 146, 60], [244, 114, 182], 'Festival'],
    ['gallery/festival-2.jpg', 1200, 900, [253, 224, 71], [196, 181, 253], 'Celebration'],
    ['gallery/outdoor-1.jpg', 1200, 900, [74, 222, 128], [56, 189, 248], 'Outdoor Play'],
    ['gallery/art-1.jpg', 1200, 900, [244, 114, 182], [253, 186, 116], 'Art Display'],
    ['avatars/student-aarav.jpg', 400, 400, [56, 189, 248], [147, 197, 253], 'Aarav'],
    ['avatars/student-ananya.jpg', 400, 400, [244, 114, 182], [251, 207, 232], 'Ananya'],
    ['avatars/student-vihaan.jpg', 400, 400, [52, 211, 153], [167, 243, 208], 'Vihaan'],
    ['avatars/teacher.jpg', 400, 400, [251, 146, 60], [253, 224, 71], 'Teacher'],
    ['avatars/staff.jpg', 400, 400, [148, 163, 184], [203, 213, 225], 'Staff'],
    ['avatars/parent.jpg', 400, 400, [167, 139, 250], [196, 181, 253], 'Parent'],
    ['avatars/principal.jpg', 400, 400, [14, 165, 233], [251, 146, 60], 'Principal'],
    ['avatars/staff-nursery.jpg', 400, 400, [251, 113, 133], [253, 186, 116], 'Ananya D'],
    ['avatars/staff-lkg.jpg', 400, 400, [56, 189, 248], [74, 222, 128], 'Rohan'],
    ['avatars/staff-activity.jpg', 400, 400, [244, 114, 182], [167, 243, 208], 'Sneha'],
    ['avatars/admission-1.jpg', 400, 400, [253, 224, 71], [56, 189, 248], 'Riya'],
    ['avatars/admission-2.jpg', 400, 400, [125, 211, 252], [251, 146, 60], 'Kabir'],
    ['avatars/admission-3.jpg', 400, 400, [196, 181, 253], [74, 222, 128], 'Sara'],
];

foreach ($files as $f) {
    makeSampleImage("{$base}/{$f[0]}", $f[1], $f[2], $f[3], $f[4], $f[5]);
}

echo 'done '.count($files)."\n";
