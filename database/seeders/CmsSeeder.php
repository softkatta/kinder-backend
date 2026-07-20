<?php

namespace Database\Seeders;

use App\Models\CmsItem;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        $items = [
            // Programs
            ['type' => 'program', 'slug' => 'nursery', 'title' => 'Nursery', 'summary' => 'First steps — songs, stories, colourful play, and gentle routines.', 'body' => 'Our Nursery program welcomes children into a warm, playful environment where learning feels like discovery. Through songs, stories, sensory play, and gentle routines, little ones build trust, language, and social skills.', 'meta' => ['grade_level' => 'nursery', 'ages' => '2 – 3 yrs', 'time' => '10 AM – 1 PM', 'price' => '₹3,500/mo', 'price_6month' => '₹19,500/6 mo', 'price_yearly' => '₹38,000/yr', 'highlights' => ['Sensory & motor play', 'Rhymes & storytelling', 'Social bonding', 'Potty-training support'], 'title_mr' => 'नर्सरी', 'summary_mr' => 'पहिली पायरी — गाणी, कथा, रंगीत खेळ आणि सौम्य दिनचर्या.', 'body_mr' => 'आमचा नर्सरी कार्यक्रम लहान मुलांना उबदार, खेळकर वातावरणात स्वागत करतो. गाणी, कथा आणि संवेदनात्मक खेळांद्वारे भाषा व सामाजिक कौशल्ये विकसित होतात.', 'price_mr' => '₹३,५००/महिना', 'price_6month_mr' => '₹१९,५००/६ महिने', 'price_yearly_mr' => '₹३८,०००/वर्ष']],
            ['type' => 'program', 'slug' => 'lkg', 'title' => 'LKG', 'summary' => 'Letters, numbers, social skills, and crafts.', 'body' => 'LKG bridges home and school with structured play, early literacy, numeracy, and creative projects.', 'meta' => ['grade_level' => 'lkg', 'ages' => '3 – 4 yrs', 'time' => '10 AM – 3 PM', 'price' => '₹4,200/mo', 'price_6month' => '₹23,400/6 mo', 'price_yearly' => '₹45,600/yr', 'highlights' => ['Phonics & pre-writing', 'Number sense', 'Art & craft', 'Group activities'], 'title_mr' => 'LKG', 'summary_mr' => 'अक्षरे, संख्या, सामाजिक कौशल्ये आणि हस्तकला.', 'body_mr' => 'LKG मध्ये सुरुवातीची साक्षरता, अंकगणित आणि सर्जनशील प्रकल्पांद्वारे शिक्षण होते.', 'price_mr' => '₹४,२००/महिना', 'price_6month_mr' => '₹२३,४००/६ महिने', 'price_yearly_mr' => '₹४५,६००/वर्ष']],
            ['type' => 'program', 'slug' => 'ukg', 'title' => 'UKG', 'summary' => 'School readiness — reading preparation and creative thinking.', 'body' => 'UKG prepares children for primary school with stronger literacy, problem-solving, and independence.', 'meta' => ['grade_level' => 'ukg', 'ages' => '4.5 – 5.5 yrs', 'time' => '10 AM – 5 PM', 'price' => '₹4,800/mo', 'price_6month' => '₹26,700/6 mo', 'price_yearly' => '₹52,200/yr', 'highlights' => ['Reading readiness', 'Logical thinking', 'Public speaking', 'Independence'], 'title_mr' => 'UKG', 'summary_mr' => 'शाळेची तयारी — वाचन आणि सर्जनशील विचार.', 'body_mr' => 'UKG मध्ये प्राथमिक शाळेसाठी साक्षरता, समस्या सोडवणे आणि स्वावलंबन विकसित केले जाते.', 'price_mr' => '₹४,८००/महिना', 'price_6month_mr' => '₹२६,७००/६ महिने', 'price_yearly_mr' => '₹५२,२००/वर्ष']],

            // Facilities
            ['type' => 'facility', 'slug' => 'smart', 'title' => 'Smart Classrooms', 'summary' => 'Digital boards and interactive learning.', 'body' => 'Bright, air-conditioned classrooms with smart boards and child-friendly furniture.', 'meta' => ['icon' => 'smart', 'highlights' => ['Interactive displays', 'Child-safe furniture', 'Natural lighting', 'Age-appropriate tools']]],
            ['type' => 'facility', 'slug' => 'playground', 'title' => 'Playground', 'summary' => 'Safe outdoor play for physical development.', 'body' => 'Secure outdoor zone with soft flooring and climbing structures.', 'meta' => ['icon' => 'playground', 'highlights' => ['Soft play surfaces', 'Supervised outdoor time', 'Motor skill zones', 'Shaded seating']]],
            ['type' => 'facility', 'slug' => 'library', 'title' => 'Library', 'summary' => 'Picture books and a love of reading.', 'body' => 'Cosy reading corner with picture books and story sets.', 'meta' => ['icon' => 'library', 'highlights' => ['Age-graded books', 'Story sessions', 'Quiet reading corners', 'Parent borrowing']]],
            ['type' => 'facility', 'slug' => 'art-room', 'title' => 'Art Room', 'summary' => 'Creative space for colours and crafts.', 'body' => 'Dedicated art room with child-safe materials and display walls.', 'meta' => ['icon' => 'art', 'highlights' => ['Finger painting', 'Craft corners', 'Seasonal displays', 'Fine motor skills']]],
            ['type' => 'facility', 'slug' => 'music-room', 'title' => 'Music Room', 'summary' => 'Rhymes, instruments, and joyful sound.', 'body' => 'Music sessions with percussion, action songs, and listening time.', 'meta' => ['icon' => 'music', 'highlights' => ['Rhymes & rhythm', 'Instrument play', 'Morning assembly', 'Confidence building']]],
            ['type' => 'facility', 'slug' => 'transport', 'title' => 'Safe Transport', 'summary' => 'GPS-enabled school vans with attendants.', 'body' => 'Door-to-door pick-up and drop with trained attendants on every route.', 'meta' => ['icon' => 'transport', 'highlights' => ['GPS tracking', 'Lady attendant', 'Fixed routes', 'Parent alerts']]],

            // Activities
            ['type' => 'activity', 'slug' => 'art', 'title' => 'Art', 'summary' => 'Colours, painting & creative expression', 'body' => 'Children experiment with colours and textures through art sessions.', 'meta' => ['highlights' => ['Water colours & finger paint', 'Texture exploration', 'Seasonal themes', 'Art exhibitions']]],
            ['type' => 'activity', 'slug' => 'music', 'title' => 'Music', 'summary' => 'Songs, instruments & joy', 'body' => 'From nursery rhymes to percussion circles.', 'meta' => ['highlights' => ['Rhymes & action songs', 'Instrument play', 'Listening skills', 'Morning assemblies']]],
            ['type' => 'activity', 'slug' => 'sports', 'title' => 'Sports', 'summary' => 'Active play & teamwork', 'body' => 'Outdoor games and team challenges.', 'meta' => ['highlights' => ['Obstacle courses', 'Team games', 'Sports day', 'Motor development']]],
            ['type' => 'activity', 'slug' => 'dance', 'title' => 'Dance', 'summary' => 'Movement, rhythm & expression', 'body' => 'Creative movement and festival dance practice.', 'meta' => ['highlights' => ['Creative movement', 'Festival dances', 'Stage confidence', 'Coordination']]],
            ['type' => 'activity', 'slug' => 'yoga', 'title' => 'Yoga & Mindfulness', 'summary' => 'Calm bodies, focused minds', 'body' => 'Age-appropriate yoga and breathing for little learners.', 'meta' => ['highlights' => ['Stretch & balance', 'Breathing games', 'Calm corners', 'Self-regulation']]],
            ['type' => 'activity', 'slug' => 'storytelling', 'title' => 'Storytelling', 'summary' => 'Imagination through stories', 'body' => 'Picture books, puppets, and oral storytelling circles.', 'meta' => ['highlights' => ['Puppet shows', 'Picture books', 'Vocabulary building', 'Listening skills']]],

            // Events
            ['type' => 'event', 'slug' => 'parent-teacher-meet', 'title' => 'Parent–Teacher Meet', 'summary' => 'Meet teachers and review progress.', 'body' => 'Open session for parents to discuss progress with class teachers.', 'meta' => ['date' => '2026-07-05', 'time' => '10:00', 'location' => 'Little Stars campus', 'highlights' => ['One-on-one discussions', 'Progress reports', 'Q&A with coordinators', 'Light refreshments']]],
            ['type' => 'event', 'slug' => 'annual-day', 'title' => 'Annual Day', 'summary' => 'Dance, music, and stage performances.', 'body' => 'Students showcase talents on stage with professional guidance.', 'meta' => ['date' => '2026-08-15', 'time' => '17:00', 'location' => 'Auditorium', 'highlights' => ['Stage practice', 'Costume fitting', 'Music rehearsals', 'Confidence building']]],

            // Blog
            ['type' => 'blog', 'slug' => 'play-based-learning', 'title' => 'Why Play-Based Learning Works', 'summary' => 'How play builds brain connections in early years.', 'body' => "Young children learn best when they feel safe, curious, and engaged. Play is not a break from learning — it is learning.\n\nThrough blocks, pretend play, and outdoor games, children develop language, math, and social skills naturally.", 'meta' => ['author' => 'Dr. Priya Sharma', 'date' => '2026-06-15', 'category' => 'Education', 'readTime' => '4 min', 'featured' => true]],
            ['type' => 'blog', 'slug' => 'first-day-tips', 'title' => 'First Day at School: Tips for Parents', 'summary' => 'Gentle ways to help your child settle in.', 'body' => "A predictable morning routine helps children feel secure. Pack the bag together the night before.\n\nKeep goodbyes short and positive — teachers are trained to comfort new learners quickly.", 'meta' => ['author' => 'Ms. Ananya', 'date' => '2026-06-08', 'category' => 'Parenting', 'readTime' => '5 min', 'featured' => false]],

            // Gallery
            ['type' => 'gallery', 'slug' => 'classroom-fun', 'title' => 'Classroom Fun', 'summary' => 'Learning through play', 'body' => null, 'meta' => ['album' => 'Daily Life']],
            ['type' => 'gallery', 'slug' => 'festival-celebrations', 'title' => 'Festival Celebrations', 'summary' => 'Colourful cultural events', 'body' => null, 'meta' => ['album' => 'Events']],

            // FAQs
            ['type' => 'faq', 'slug' => 'admission-age', 'title' => 'What is the admission age for Nursery?', 'summary' => 'Children aged 2+ years can join Nursery.', 'body' => 'We accept children from 2 years for Nursery, with age-appropriate placement for LKG and UKG.'],
            ['type' => 'faq', 'slug' => 'school-timings', 'title' => 'What are the school timings?', 'summary' => 'Timings vary by program.', 'body' => 'Nursery: 10 AM – 1 PM, LKG: 10 AM – 3 PM, UKG: 10 AM – 5 PM.'],
            ['type' => 'faq', 'slug' => 'transport', 'title' => 'Is transport available?', 'summary' => 'Yes, on selected routes.', 'body' => 'GPS-enabled vans with lady attendants are available on major routes. Contact the office for route availability.'],
            ['type' => 'faq', 'slug' => 'meals', 'title' => 'Are meals provided?', 'summary' => 'Healthy snacks are included.', 'body' => 'We provide hygienic mid-morning snacks. Parents may share allergy information at admission.'],

            // Testimonials
            ['type' => 'testimonial', 'slug' => 'parent-nursery', 'title' => 'Parent of Nursery student', 'summary' => null, 'body' => 'Our daughter settled in within a week. Teachers are warm, caring, and always communicate with us.', 'meta' => ['author' => 'Mrs. Kavita Patil', 'role' => 'Parent of Nursery A', 'rating' => 5]],
            ['type' => 'testimonial', 'slug' => 'parent-lkg', 'title' => 'Parent of LKG student', 'summary' => null, 'body' => 'The play-based approach is wonderful. My son looks forward to school every single day!', 'meta' => ['author' => 'Mr. Amit Deshmukh', 'role' => 'Parent of LKG B', 'rating' => 5]],
            ['type' => 'testimonial', 'slug' => 'parent-ukg', 'title' => 'Parent of UKG student', 'summary' => null, 'body' => 'Excellent preparation for primary school. The annual day and activities are beautifully organised.', 'meta' => ['author' => 'Mrs. Sneha Kulkarni', 'role' => 'Parent of UKG A', 'rating' => 5]],

            // Jobs
            ['type' => 'job', 'slug' => 'nursery-teacher', 'title' => 'Nursery Teacher', 'summary' => 'Passionate educator for Nursery class. ECCE qualification preferred.', 'body' => 'Create joyful, play-based learning experiences for our youngest learners. You will plan daily routines, support social-emotional growth, and partner closely with parents.', 'meta' => ['department' => 'Early Years', 'location' => 'Pune', 'application_deadline' => '2026-08-31', 'employment_type' => 'Full-time', 'salary_range' => '₹15,000 – ₹22,000', 'requirements' => ['ECCE or D.Ed qualification', '2+ years nursery experience', 'Warm communication with parents', 'Passion for play-based learning']]],
            ['type' => 'job', 'slug' => 'lkg-ukg-teacher', 'title' => 'LKG / UKG Teacher', 'summary' => 'Experienced teacher for LKG/UKG with strong parent communication.', 'body' => 'Lead creative classroom planning and school readiness programs for LKG and UKG learners.', 'meta' => ['department' => 'Primary Wing', 'location' => 'Pune', 'application_deadline' => '2026-08-31', 'employment_type' => 'Full-time', 'salary_range' => '₹18,000 – ₹28,000', 'requirements' => ['B.Ed or equivalent', 'Strong literacy & numeracy teaching', 'Experience with annual day prep', 'Excellent parent communication']]],
            ['type' => 'job', 'slug' => 'activity-coordinator', 'title' => 'Activity Coordinator', 'summary' => 'Plan art, music, sports, and festival activities.', 'body' => 'Organise co-curricular programs across all grades with energy and attention to detail.', 'meta' => ['department' => 'Co-curricular', 'location' => 'Pune', 'application_deadline' => '2026-09-15', 'employment_type' => 'Full-time', 'salary_range' => '₹14,000 – ₹20,000', 'requirements' => ['Event planning experience', 'Art / music / sports background', 'Vendor coordination skills', 'Creative festival ideas']]],

            // Legal pages
            ['type' => 'page', 'slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'summary' => 'How we protect your data.', 'body' => 'We respect your privacy and protect personal information collected through our website and school services.'],
            ['type' => 'page', 'slug' => 'terms', 'title' => 'Terms of Service', 'summary' => 'Website terms of use.', 'body' => 'By using this website you agree to our terms and conditions.'],
            ['type' => 'page', 'slug' => 'refund-policy', 'title' => 'Refund Policy', 'summary' => 'Fee refund guidelines.', 'body' => 'Refund requests are processed as per school policy and applicable regulations.'],

            // School profile
            ['type' => 'school_profile', 'slug' => 'profile', 'title' => 'Little Stars Kindergarten', 'summary' => 'Nurturing young minds with joy and care.', 'body' => null, 'meta' => [
                'email' => 'info@littlestars.com',
                'phone' => '+91 98765 43210',
                'address' => '123 Sunshine Lane',
                'city' => 'Pune',
                'hours' => 'Mon – Sat: 8:00 AM – 4:00 PM',
                'short_name' => 'Little Stars',
                'school_name' => 'Little Stars Kindergarten',
                'meta_title' => 'Little Stars Kindergarten | Play-Based Early Learning',
                'meta_description' => 'Nurturing young minds with joy and care. Safe campus, certified teachers, and play-based learning for Nursery, LKG & UKG.',
                'established_year' => '2015',
                'vision' => 'Every child discovers joy in learning through play, care, and community.',
                'mission' => 'We nurture curious, confident learners in a safe, inclusive kindergarten environment.',
                'principal_name' => 'Dr. Priya Sharma',
                'principal_message' => 'Welcome to Little Stars — where little learners shine bright every day.',
                'about_values_label' => 'Our Values',
                'about_values_title' => 'What We Stand For',
                'about_values' => "Care & Nurturing|Every child feels loved, heard, and supported in a warm classroom family.\nSafety First|CCTV, trained staff, and secure entry give parents peace of mind every day.\nJoyful Learning|Play-based routines build curiosity, confidence, and school readiness.\nCommunity|We partner with parents through updates, visits, and open communication.",
                'about_journey_label' => 'Our Journey',
                'about_journey_title' => 'Growing Together Since 2015',
                'about_timeline' => "2015|Little Stars Founded|Opened with Nursery and LKG classes in a cosy neighbourhood campus.\n2018|Campus Expansion|Added playground, library corner, and smart classroom upgrades.\n2022|UKG & Activity Hub|Launched UKG program and dedicated art, music, and sports spaces.\n2026|Digital Parent Connect|Online admission, fee payments, and live school updates for families.",
                'home_about_label' => 'About Us',
                'home_about_title' => 'A Happy Place to Learn & Grow',
                'home_about_paragraphs' => "Little Stars Kindergarten is a warm, joyful early-years school where children learn through play, creativity, and caring routines.\nWe partner closely with parents so every child feels safe, curious, and ready for the next step.",
                'home_why_label' => 'Why Choose Us',
                'home_why_title' => 'Why Parents Trust Little Stars',
                'home_why_panel_title' => 'Safe, caring, and joyful learning',
                'home_why_panel_desc' => 'Certified teachers, child-safe campus, and a play-based curriculum designed for happy little minds.',
                'home_why_choose' => "Certified Teachers|Experienced ECCE educators who know every child by name\nSafe Campus|CCTV, trained staff, and secure entry for peace of mind\nPlay-Based Learning|Children learn language, maths & social skills through joyful play\nParent Partnership|Regular updates, open communication, and parent workshops",
                'home_learning_label' => 'Learning Elements',
                'home_learning_title_accent' => 'Learning',
                'home_learning_title_rest' => 'Through Play',
                'home_learning_paragraphs' => "Our curriculum blends structured routines with free play, art, music, and outdoor time.\nEvery activity is designed to build confidence, curiosity, and school readiness.",
                'home_learning_items' => "art|Art & Craft|Colours, painting and creative expression\nmusic|Music|Rhymes, instruments and joyful sound\nsports|Sports|Active play, teamwork and motor skills\nstory|Storytelling|Puppets, picture books and imagination\ndance|Dance|Movement, rhythm and stage confidence\nyoga|Yoga|Calm bodies and focused minds",
                'home_enroll_steps' => "Apply Online|Fill the admission form on our website\nSchool Visit|Book a tour and meet our teachers\nDocument Verification|Submit age proof and photographs\nWelcome to Little Stars|Receive confirmation and join orientation",
                'home_cta_title' => 'Give Your Child the Best Start',
                'home_cta_subtitle' => 'Admissions open for Nursery, LKG & UKG. Limited seats — apply today!',
                'vision_mr' => 'प्रत्येक मूल खेळ, काळजी आणि समुदायाद्वारे शिकण्यात आनंद शोधते.',
                'mission_mr' => 'आम्ही सुरक्षित, समावेशक वातावरणात जिज्ञासू आणि आत्मविश्वासू शिकणारे तयार करतो.',
                'principal_message_mr' => 'लिटल स्टार्स मध्ये आपले स्वागत — जिथे लहान शिकणारे दररोज चमकतात.',
            ]],

            // Homepage notices (top admission strip ticker)
            ['type' => 'notice', 'slug' => 'admissions-open', 'title' => 'Admissions Open — Limited seats for Nursery, LKG & UKG!', 'summary' => null, 'body' => null, 'sort_order' => 1, 'meta' => []],
            ['type' => 'notice', 'slug' => 'annual-day-notice', 'title' => 'Annual Day on 15 August — all parents welcome!', 'summary' => null, 'body' => null, 'sort_order' => 2, 'meta' => ['link_url' => '/events']],
            ['type' => 'notice', 'slug' => 'parent-meet', 'title' => 'Parent–Teacher Meet on 5 July — book your slot today.', 'summary' => null, 'body' => null, 'sort_order' => 3, 'meta' => ['link_url' => '/contact']],

            ['type' => 'event', 'slug' => 'diwali-holiday', 'title' => 'Diwali Vacation', 'summary' => 'School closed for Diwali break', 'body' => 'Classes resume after the vacation.', 'sort_order' => 4, 'meta' => ['date' => '2026-11-01', 'is_holiday' => true, 'title_mr' => 'दिवाळी सुट्टी']],

            // Portal homework assignments
            ['type' => 'notice', 'slug' => 'homework-nursery-colors', 'title' => 'Colour the rainbow worksheet', 'summary' => 'Complete pages 4–5 in the colour book.', 'body' => 'Bring the worksheet to school on Monday.', 'sort_order' => 10, 'meta' => ['portal' => 'homework', 'category' => 'Homework', 'due' => 'Next Monday', 'status' => 'Pending', 'emoji' => '🌈', 'class_name' => 'Nursery']],
            ['type' => 'notice', 'slug' => 'homework-lkg-phonics', 'title' => 'Practice letter sounds A–E', 'summary' => 'Read aloud with parents for 10 minutes.', 'body' => 'Use the phonics chart sent home.', 'sort_order' => 11, 'meta' => ['portal' => 'homework', 'category' => 'Homework', 'due' => 'Friday', 'status' => 'Pending', 'emoji' => '🔤', 'class_name' => 'LKG']],
            ['type' => 'notice', 'slug' => 'homework-ukg-counting', 'title' => 'Count objects at home (1–20)', 'summary' => 'Find 20 small items and count them.', 'body' => 'Parents may help — note any tricky numbers.', 'sort_order' => 12, 'meta' => ['portal' => 'homework', 'category' => 'Homework', 'due' => 'Wednesday', 'status' => 'Pending', 'emoji' => '🔢', 'class_name' => 'UKG']],

            // Hero carousel slides
            ['type' => 'banner', 'slug' => 'hero-slide-1', 'title' => 'Educating', 'summary' => 'Where Fun Happens!', 'body' => null, 'sort_order' => 1, 'meta' => ['title_rest' => 'Your Children']],
            ['type' => 'banner', 'slug' => 'hero-slide-2', 'title' => 'Growing', 'summary' => 'Learn Through Play', 'body' => null, 'sort_order' => 2, 'meta' => ['title_rest' => 'Every Day']],
            ['type' => 'banner', 'slug' => 'hero-slide-3', 'title' => 'Bright', 'summary' => 'Happy Little Minds', 'body' => null, 'sort_order' => 3, 'meta' => ['title_rest' => 'Future Stars']],

            // Staff
            ['type' => 'staff', 'slug' => 'principal', 'title' => 'Dr. Priya Sharma', 'summary' => 'Principal & Early Childhood Specialist', 'body' => 'Dr. Priya leads Little Stars with 15+ years in ECCE. She believes every child learns best through play, warmth, and positive routines.', 'meta' => ['role' => 'Principal', 'role_mr' => 'प्राचार्य', 'qualification' => 'M.A. ECCE', 'qualification_mr' => 'एम.ए. ईसीसीई', 'title_mr' => 'डॉ. प्रिया शर्मा', 'summary_mr' => 'प्राचार्य आणि बाल्यकाल तज्ज्ञ', 'body_mr' => 'डॉ. प्रियांना १५+ वर्षांचा अनुभव आहे. त्या म्हणतात प्रत्येक मूल खेळ आणि सकारात्मक दिनचर्येद्वारे सर्वोत्तम शिकते.']],
            ['type' => 'staff', 'slug' => 'nursery-teacher', 'title' => 'Ms. Ananya Desai', 'summary' => 'Nursery Class Teacher', 'body' => 'Ananya creates joyful nursery routines with songs, sensory play, and gentle guidance for our youngest learners.', 'meta' => ['role' => 'Nursery Teacher', 'role_mr' => 'नर्सरी शिक्षिका', 'qualification' => 'D.Ed ECCE', 'qualification_mr' => 'डी.एड ईसीसीई', 'title_mr' => 'श्रीमती अनन्या देशाई', 'summary_mr' => 'नर्सरी वर्ग शिक्षिका', 'body_mr' => 'अनन्या गाणी आणि संवेदनात्मक खेळांद्वारे आनंददायी नर्सरी दिनचर्या तयार करते.']],
            ['type' => 'staff', 'slug' => 'lkg-teacher', 'title' => 'Mr. Rohan Kulkarni', 'summary' => 'LKG Class Teacher', 'body' => 'Rohan focuses on phonics, early numeracy, and creative projects that build school readiness.', 'meta' => ['role' => 'LKG Teacher', 'role_mr' => 'LKG शिक्षक', 'qualification' => 'B.Ed', 'qualification_mr' => 'बी.एड', 'title_mr' => 'श्री रोहन कुलकर्णी', 'summary_mr' => 'LKG वर्ग शिक्षक', 'body_mr' => 'रोहन फोनिक्स, अंकगणित आणि सर्जनशील प्रकल्पांवर लक्ष केंद्रित करतो.']],
            ['type' => 'staff', 'slug' => 'activity-coordinator', 'title' => 'Ms. Sneha Patil', 'summary' => 'Activity Coordinator', 'body' => 'Sneha plans art, music, sports, and festival activities across all grades.', 'meta' => ['role' => 'Activity Coordinator', 'role_mr' => 'उपक्रम समन्वयक', 'qualification' => 'B.A. Fine Arts', 'qualification_mr' => 'बी.ए. फाइन आर्ट्स', 'title_mr' => 'श्रीमती स्नेहा पाटील', 'summary_mr' => 'उपक्रम समन्वयक', 'body_mr' => 'स्नेहा कला, संगीत, खेळ आणि सण-उत्सव उपक्रमांचे नियोजन करते.']],

            // Curriculum
            ['type' => 'curriculum', 'slug' => 'nursery-curriculum', 'title' => 'Nursery Curriculum', 'summary' => 'Play-based foundations for toddlers', 'body' => 'Our nursery curriculum focuses on sensory exploration, language exposure, social bonding, and gentle independence through structured play.', 'meta' => ['grade_level' => 'nursery', 'highlights' => ['Sensory & motor skills', 'Rhymes & storytelling', 'Free & guided play', 'Social routines'], 'title_mr' => 'नर्सरी अभ्यासक्रम', 'summary_mr' => 'लहान मुलांसाठी खेळाधारित पाया', 'body_mr' => 'नर्सरी अभ्यासक्रम संवेदना, भाषा, सामाजिक बंध आणि सौम्य स्वावलंबनावर केंद्रित आहे.']],
            ['type' => 'curriculum', 'slug' => 'lkg-curriculum', 'title' => 'LKG Curriculum', 'summary' => 'Early literacy and numeracy', 'body' => 'LKG learners build phonics awareness, number sense, fine motor skills, and classroom confidence.', 'meta' => ['grade_level' => 'lkg', 'highlights' => ['Phonics & pre-writing', 'Numbers 1–20', 'Art & craft', 'Group learning'], 'title_mr' => 'LKG अभ्यासक्रम', 'summary_mr' => 'सुरुवातीची साक्षरता आणि अंकगणित', 'body_mr' => 'LKG मध्ये फोनिक्स, संख्याज्ञान आणि वर्गातील आत्मविश्वास विकसित होतो.']],
            ['type' => 'curriculum', 'slug' => 'ukg-curriculum', 'title' => 'UKG Curriculum', 'summary' => 'School readiness programme', 'body' => 'UKG prepares children for primary school with reading readiness, logical thinking, and expressive communication.', 'meta' => ['grade_level' => 'ukg', 'highlights' => ['Reading readiness', 'Problem solving', 'Public speaking', 'Independence'], 'title_mr' => 'UKG अभ्यासक्रम', 'summary_mr' => 'शाळेची तयारी कार्यक्रम', 'body_mr' => 'UKG मध्ये वाचन तयारी, तार्किक विचार आणि अभिव्यक्त संवादावर भर दिला जातो.']],
        ];

        foreach ($items as $i => $item) {
            CmsItem::updateOrCreate(
                ['tenant_id' => $tenant?->id, 'type' => $item['type'], 'slug' => $item['slug']],
                [...$item, 'tenant_id' => $tenant?->id, 'status' => 'published', 'sort_order' => $item['sort_order'] ?? $i],
            );
        }
    }
}
