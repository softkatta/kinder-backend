<?php

namespace App\Services\Dashboard;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\CmsItem;
use App\Models\Admission;
use App\Models\ContactInquiry;
use App\Models\Guest;
use App\Models\IdCard;
use App\Models\JobApplication;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /** @return array<string, mixed> */
    public function admin(): array
    {
        $studentCards = IdCard::query()->where('card_type', 'student');
        $studentTotal = (clone $studentCards)->count();
        $studentActive = (clone $studentCards)->where('status', 'active')->count();

        $teacherCount = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
            ->count();

        $pendingInquiries = ContactInquiry::query()->whereIn('status', ['new', 'pending'])->count();
        $pendingAdmissions = Admission::query()->whereIn('status', ['pending', 'review'])->count();
        $pendingPayments = Payment::query()->where('status', 'pending')->count();

        $verifiedTotal = (float) Payment::query()->where('status', 'verified')->sum('amount');
        $allPayments = (float) Payment::query()->sum('amount');
        $collectionPct = $allPayments > 0 ? (int) round(($verifiedTotal / $allPayments) * 100) : 0;

        $today = now()->toDateString();
        $presentToday = AttendanceRecord::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['present', 'late', 'half_day'])
            ->count();
        $attendanceDenom = max($studentActive, 1);
        $attendancePct = (int) round(($presentToday / $attendanceDenom) * 100);

        $currentYear = AcademicYear::query()->where('is_current', true)->first();

        return [
            'greeting' => 'Welcome back',
            'academic_year' => $currentYear?->label ?? $currentYear?->name,
            'stats' => [
                ['label' => 'Student ID Cards', 'value' => $studentTotal, 'change' => "{$studentActive} active"],
                ['label' => 'Teachers', 'value' => $teacherCount, 'change' => 'Active staff accounts'],
                ['label' => 'Pending Enquiries', 'value' => $pendingInquiries, 'change' => 'Contact form'],
                ['label' => 'Fee Collection', 'value' => '₹'.$this->formatAmount($verifiedTotal), 'change' => "{$collectionPct}% verified"],
            ],
            'hero' => [
                'collection_pct' => $collectionPct,
                'attendance_pct' => $attendancePct,
                'present_today' => $presentToday,
                'alerts' => $pendingInquiries + $pendingAdmissions + $pendingPayments,
                'pending_admissions' => $pendingInquiries + $pendingAdmissions,
                'pending_payments' => $pendingPayments,
            ],
            'fee_trend' => $this->feeTrend(),
            'today_snapshot' => [
                ['label' => 'Present Today', 'value' => (string) $presentToday, 'note' => "{$attendancePct}% of active students"],
                ['label' => 'New Enquiries', 'value' => (string) ContactInquiry::query()->where('created_at', '>=', now()->subDays(7))->count(), 'note' => 'Last 7 days'],
                ['label' => 'Staff Accounts', 'value' => (string) $teacherCount, 'note' => 'Active teachers'],
                ['label' => 'CMS Events', 'value' => (string) CmsItem::query()->where('type', 'event')->where('status', 'published')->count(), 'note' => 'Published'],
            ],
            'quick_actions' => [
                ['title' => 'Review enquiries', 'meta' => "{$pendingInquiries} pending", 'link' => '/admin/admissions'],
                ['title' => 'Verify payments', 'meta' => "{$pendingPayments} pending", 'link' => '/admin/payments'],
                ['title' => 'Student ID cards', 'meta' => "{$studentTotal} records", 'link' => '/admin/students'],
                ['title' => 'School settings', 'meta' => 'Profile & integrations', 'link' => '/admin/settings'],
            ],
            'recent_activity' => $this->recentActivity(),
        ];
    }

    /** @return array<string, mixed> */
    public function teacher(User $user): array
    {
        $today = now()->toDateString();
        $markedToday = AttendanceRecord::query()->whereDate('date', $today)->count();
        $studentCards = IdCard::query()->where('card_type', 'student')->where('status', 'active')->count();
        $upcomingEvents = CmsItem::query()->where('type', 'event')->where('status', 'published')->count();

        return [
            'greeting' => 'Good day, '.$user->name,
            'stats' => [
                ['label' => 'Attendance Marked', 'value' => $markedToday, 'change' => 'Today'],
                ['label' => 'Active Students', 'value' => $studentCards, 'change' => 'ID cards on file'],
                ['label' => 'School Events', 'value' => $upcomingEvents, 'change' => 'Published in CMS'],
                ['label' => 'Your Notifications', 'value' => UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(), 'change' => 'Unread'],
            ],
            'schedule' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function parent(User $user): array
    {
        $cards = IdCard::query()
            ->where('card_type', 'student')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereJsonContains('meta->parent_email', $user->email);
            })
            ->get();

        $children = $cards->map(function (IdCard $card) {
            $todayStatus = AttendanceRecord::query()
                ->where('id_card_id', $card->id)
                ->whereDate('date', now()->toDateString())
                ->value('status');

            return [
                'name' => $card->full_name,
                'class' => is_array($card->meta) ? ($card->meta['class'] ?? $card->academic_year ?? '—') : ($card->academic_year ?? '—'),
                'attendance' => $todayStatus ? ucfirst(str_replace('_', ' ', $todayStatus)).' today' : 'Not marked today',
            ];
        })->values()->all();

        $payments = Payment::query()
            ->where(function ($q) use ($user) {
                $q->where('payer_phone', $user->phone)
                    ->orWhere('payer_name', 'like', '%'.$user->name.'%');
            })
            ->latest()
            ->limit(5)
            ->get();

        $pendingFees = $payments->where('status', 'pending')->sum('amount');

        return [
            'greeting' => 'Hello, '.$user->name,
            'children' => $children,
            'stats' => [
                ['label' => 'Children', 'value' => count($children), 'change' => 'Linked ID cards'],
                ['label' => 'Pending Fees', 'value' => '₹'.$this->formatAmount((float) $pendingFees), 'change' => 'Awaiting verification'],
                ['label' => 'Payments', 'value' => $payments->count(), 'change' => 'Recent records'],
                ['label' => 'Notifications', 'value' => UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(), 'change' => 'Unread'],
            ],
            'notices' => CmsItem::query()
                ->where('type', 'blog')
                ->where('status', 'published')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (CmsItem $item) => [
                    'title' => $item->title,
                    'date' => $item->updated_at?->format('d M Y') ?? '',
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function student(User $user): array
    {
        $card = IdCard::query()->where('user_id', $user->id)->where('card_type', 'student')->first();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthRecords = $card
            ? AttendanceRecord::query()->where('id_card_id', $card->id)->whereBetween('date', [$monthStart, $monthEnd])->get()
            : collect();
        $presentDays = $monthRecords->whereIn('status', ['present', 'late', 'half_day'])->count();
        $attendancePct = $monthRecords->count() > 0 ? (int) round(($presentDays / $monthRecords->count()) * 100) : 0;

        return [
            'greeting' => 'Hi, '.$user->name.'!',
            'class' => $card && is_array($card->meta) ? ($card->meta['class'] ?? null) : null,
            'stats' => [
                ['label' => 'Attendance', 'value' => $monthRecords->count() ? "{$attendancePct}%" : '—', 'change' => 'This month'],
                ['label' => 'ID Card', 'value' => $card?->status ? ucfirst($card->status) : '—', 'change' => $card?->card_number ?? 'Not linked'],
                ['label' => 'Notifications', 'value' => UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(), 'change' => 'Unread'],
                ['label' => 'School Events', 'value' => CmsItem::query()->where('type', 'event')->where('status', 'published')->count(), 'change' => 'Published'],
            ],
            'homework' => [],
            'activities' => CmsItem::query()
                ->where('type', 'activity')
                ->where('status', 'published')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (CmsItem $item) => [
                    'title' => $item->title,
                    'time' => $item->updated_at?->format('d M Y') ?? '',
                ])->values()->all(),
        ];
    }

    /** @return list<array{period: string, month: string, value: number}> */
    private function feeTrend(): array
    {
        $anchor = now()->copy()->startOfMonth();
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push($anchor->copy()->subMonths($i));
        }

        return $months->map(function (Carbon $month) use ($months) {
            $sum = (float) Payment::query()
                ->where('status', 'verified')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('amount');

            $years = $months->map(fn (Carbon $m) => $m->year)->unique();
            $label = $years->count() > 1
                ? $month->format("M 'y")
                : $month->format('M');

            return [
                'period' => $month->format('Y-m'),
                'month' => $label,
                'value' => (int) round($sum / 1000) ?: 0,
            ];
        })->values()->all();
    }

    /** @return list<array{title: string, time: string}> */
    private function recentActivity(): array
    {
        $items = collect();

        Payment::query()->latest()->limit(5)->get()->each(function (Payment $p) use ($items) {
            $items->push([
                'at' => $p->created_at,
                'title' => "Payment {$p->status}: ₹".number_format((float) $p->amount, 0).' from '.$p->payer_name,
                'time' => $p->created_at?->diffForHumans() ?? '',
            ]);
        });

        ContactInquiry::query()->latest()->limit(5)->get()->each(function (ContactInquiry $c) use ($items) {
            $items->push([
                'at' => $c->created_at,
                'title' => "Contact enquiry from {$c->name}",
                'time' => $c->created_at?->diffForHumans() ?? '',
            ]);
        });

        JobApplication::query()->latest()->limit(5)->get()->each(function (JobApplication $j) use ($items) {
            $items->push([
                'at' => $j->created_at,
                'title' => "Job application: {$j->full_name}",
                'time' => $j->created_at?->diffForHumans() ?? '',
            ]);
        });

        Guest::query()->latest()->limit(3)->get()->each(function (Guest $g) use ($items) {
            $items->push([
                'at' => $g->created_at,
                'title' => "Guest pass created: {$g->full_name}",
                'time' => $g->created_at?->diffForHumans() ?? '',
            ]);
        });

        return $items->sortByDesc('at')->take(8)->map(fn ($row) => [
            'title' => $row['title'],
            'time' => $row['time'],
        ])->values()->all();
    }

    private function formatAmount(float $amount): string
    {
        if ($amount >= 100000) {
            return number_format($amount / 100000, 1).'L';
        }

        return number_format($amount, 0);
    }

    /** @return array<string, mixed> */
    public function adminSidebar(): array
    {
        $studentTotal = IdCard::query()->where('card_type', 'student')->count();
        $staffCount = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teacher', 'admin', 'super_admin', 'staff']))
            ->count();
        $currentYear = AcademicYear::query()->where('is_current', true)->first();

        return [
            'badges' => [
                'admissions' => ContactInquiry::query()->whereIn('status', ['new', 'pending'])->count()
                    + Admission::query()->whereIn('status', ['pending', 'review'])->count(),
                'payments' => Payment::query()->where('status', 'pending')->count(),
            ],
            'year_card' => [
                'label' => $currentYear?->label ?? $currentYear?->name ?? '—',
                'students' => $studentTotal,
                'staff' => $staffCount,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function cmsTypeSummary(): array
    {
        $types = [
            'program' => 'Programs',
            'facility' => 'Facilities',
            'activity' => 'Activities',
            'event' => 'Events',
            'blog' => 'Blog Posts',
            'gallery' => 'Gallery',
            'faq' => 'FAQs',
            'job' => 'Careers / Jobs',
            'page' => 'Legal Pages',
        ];

        $counts = CmsItem::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $published = CmsItem::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->where('status', 'published')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return collect($types)->map(fn ($label, $type) => [
            'title' => $label,
            'type' => $type,
            'count' => (int) ($counts[$type] ?? 0),
            'published' => (int) ($published[$type] ?? 0),
            'status' => ((int) ($counts[$type] ?? 0)) > 0 && (int) ($published[$type] ?? 0) === (int) ($counts[$type] ?? 0)
                ? 'Published'
                : (((int) ($published[$type] ?? 0)) > 0 ? 'Mixed' : 'Draft'),
        ])->values()->all();
    }
}
