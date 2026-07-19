<?php

namespace App\Services\Portal;

use App\Models\AttendanceRecord;
use App\Models\CmsItem;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\IdCard;
use App\Models\Payment;
use App\Models\StudentFee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PortalService
{
    /** @return Collection<int, IdCard> */
    public function parentChildCards(User $user): Collection
    {
        return IdCard::query()
            ->where('card_type', 'student')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereJsonContains('meta->parent_email', $user->email)
                    ->orWhereJsonContains('meta->parent_phone', $user->phone);
            })
            ->orderBy('full_name')
            ->get();
    }

    public function studentCard(User $user): ?IdCard
    {
        return IdCard::query()
            ->where('user_id', $user->id)
            ->where('card_type', 'student')
            ->first();
    }

    /** @return list<array<string, mixed>> */
    public function parentChildren(User $user): array
    {
        return $this->parentChildCards($user)->map(function (IdCard $card) {
            $meta = is_array($card->meta) ? $card->meta : [];
            $today = AttendanceRecord::query()
                ->where('id_card_id', $card->id)
                ->whereDate('date', now()->toDateString())
                ->first();

            return [
                'id' => $card->id,
                'name' => $card->full_name,
                'class' => $meta['class'] ?? $card->academic_year ?? '—',
                'admission_number' => $card->card_number,
                'blood_group' => $card->blood_group,
                'photo_path' => $card->photo_path,
                'attendance_today' => $today ? ucfirst(str_replace('_', ' ', $today->status)) : 'Not marked',
                'emergency_contact' => $card->emergency_contact,
            ];
        })->values()->all();
    }

    /** @return array{items: list<array<string, mixed>>, child_name: ?string, child_id: ?int, children: list<array<string, mixed>>} */
    public function parentFees(User $user, ?int $idCardId = null): array
    {
        $children = $this->parentChildCards($user);
        $child = $idCardId
            ? $children->firstWhere('id', $idCardId) ?? $children->first()
            : $children->first();
        $childName = $child?->full_name;

        $childrenList = $children->map(fn (IdCard $c) => [
            'id' => $c->id,
            'name' => $c->full_name,
            'class' => (is_array($c->meta) ? ($c->meta['class_name'] ?? $c->meta['class'] ?? '—') : '—'),
        ])->values()->all();

        $studentFees = $child
            ? StudentFee::query()
                ->where('id_card_id', $child->id)
                ->orderBy('due_date')
                ->get()
            : collect();

        if ($studentFees->isNotEmpty()) {
            $items = $studentFees->map(function (StudentFee $fee) use ($childName) {
                $balance = $fee->balance();

                return [
                    'term' => $fee->title,
                    'amount' => '₹'.number_format((float) $fee->amount, 0),
                    'paid' => '₹'.number_format((float) $fee->paid_amount, 0),
                    'balance' => '₹'.number_format($balance, 0),
                    'balance_num' => $balance,
                    'due' => $fee->due_date?->format('Y-m-d') ?? '—',
                    'can_pay' => $balance > 0 && ! in_array($fee->status, ['paid', 'waived'], true),
                    'student_name' => $childName,
                    'status' => $fee->status,
                ];
            })->values()->all();

            return ['items' => $items, 'child_name' => $childName, 'child_id' => $child?->id, 'children' => $childrenList];
        }

        $payments = Payment::query()
            ->where(function ($q) use ($user, $children) {
                $q->where('payer_phone', $user->phone)
                    ->orWhere('payer_name', 'like', '%'.$user->name.'%');
                foreach ($children as $c) {
                    $q->orWhere('student_name', $c->full_name);
                }
            })
            ->latest()
            ->get();

        $verified = (float) $payments->where('status', 'verified')->sum('amount');
        $pending = (float) $payments->where('status', 'pending')->sum('amount');
        $totalDue = $this->estimatedAnnualFee($child);

        $items = [];
        if ($totalDue > 0) {
            $items[] = [
                'term' => 'Annual Tuition (estimated)',
                'amount' => '₹'.number_format($totalDue, 0),
                'paid' => '₹'.number_format($verified, 0),
                'balance' => '₹'.number_format(max(0, $totalDue - $verified), 0),
                'balance_num' => max(0, $totalDue - $verified),
                'due' => now()->addMonth()->format('Y-m-d'),
                'can_pay' => max(0, $totalDue - $verified) > 0,
                'student_name' => $childName,
            ];
        }

        foreach ($payments->take(5) as $payment) {
            $items[] = [
                'term' => ucfirst($payment->payment_method).' payment — '.$payment->created_at->format('d M Y'),
                'amount' => '₹'.number_format((float) $payment->amount, 0),
                'paid' => $payment->status === 'verified' ? '₹'.number_format((float) $payment->amount, 0) : '₹0',
                'balance' => $payment->status === 'verified' ? '₹0' : '₹'.number_format((float) $payment->amount, 0),
                'balance_num' => $payment->status === 'verified' ? 0 : (float) $payment->amount,
                'due' => $payment->status === 'verified' ? 'Paid' : 'Pending verification',
                'can_pay' => false,
                'student_name' => $payment->student_name,
                'status' => $payment->status,
            ];
        }

        if ($pending > 0 && ! collect($items)->contains(fn ($i) => ($i['status'] ?? '') === 'pending')) {
            $items[] = [
                'term' => 'Pending verification',
                'amount' => '₹'.number_format($pending, 0),
                'paid' => '₹0',
                'balance' => '₹'.number_format($pending, 0),
                'balance_num' => $pending,
                'due' => 'Awaiting admin',
                'can_pay' => false,
                'student_name' => $childName,
            ];
        }

        return ['items' => $items, 'child_name' => $childName, 'child_id' => $child?->id, 'children' => $childrenList];
    }

    private function estimatedAnnualFee(?IdCard $card): float
    {
        if (! $card) {
            return 0;
        }
        $meta = is_array($card->meta) ? $card->meta : [];
        $grade = strtolower((string) ($meta['grade_level'] ?? $meta['class'] ?? ''));
        $program = CmsItem::query()->where('type', 'program')->where('status', 'published')
            ->where(function ($q) use ($grade) {
                $q->where('slug', $grade)
                    ->orWhereJsonContains('meta->grade_level', $grade);
            })->first();
        if (! $program || ! is_array($program->meta)) {
            return 45000;
        }
        $price = (string) ($program->meta['price_yearly'] ?? $program->meta['price'] ?? '');
        preg_match('/[\d,]+/', $price, $m);

        return isset($m[0]) ? (float) str_replace(',', '', $m[0]) : 45000;
    }

    /** @return array{records: list<array<string, string>>, child_name: ?string, summary: string} */
    public function parentAttendance(User $user, ?int $idCardId = null): array
    {
        $children = $this->parentChildCards($user);
        $card = $idCardId
            ? $children->firstWhere('id', $idCardId)
            : $children->first();

        if (! $card) {
            return ['records' => [], 'child_name' => null, 'summary' => 'No linked student ID card'];
        }

        $start = now()->startOfMonth()->toDateString();
        $records = AttendanceRecord::query()
            ->where('id_card_id', $card->id)
            ->whereDate('date', '>=', $start)
            ->orderByDesc('date')
            ->limit(30)
            ->get();

        $present = $records->whereIn('status', ['present', 'late', 'half_day'])->count();
        $pct = $records->count() > 0 ? (int) round(($present / $records->count()) * 100) : 0;

        return [
            'child_name' => $card->full_name,
            'summary' => "{$pct}% this month",
            'records' => $records->map(fn (AttendanceRecord $r) => [
                'date' => $r->date->format('Y-m-d'),
                'status' => ucfirst(str_replace('_', ' ', $r->status)),
                'in' => $r->check_in_time ? Carbon::parse($r->check_in_time)->format('h:i A') : '—',
                'out' => $r->check_out_time ? Carbon::parse($r->check_out_time)->format('h:i A') : '—',
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, string>> */
    public function portalNotices(string $locale = 'en'): array
    {
        return CmsItem::query()
            ->whereIn('type', ['notice', 'blog'])
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (CmsItem $item) => [
                'id' => $item->id,
                'title' => $item->toPublicArray($locale)['title'] ?? $item->title,
                'body' => $item->toPublicArray($locale)['summary'] ?? $item->toPublicArray($locale)['body'] ?? '',
                'date' => $item->updated_at?->format('d M Y') ?? '',
            ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function homeworkItems(string $locale = 'en', ?User $teacher = null): array
    {
        $dbItems = Homework::query()
            ->where('status', 'active')
            ->when($teacher, fn ($q) => $q->where('teacher_user_id', $teacher->id))
            ->orderByDesc('due_date')
            ->limit(20)
            ->get();

        if ($dbItems->isNotEmpty()) {
            return $dbItems->map(fn (Homework $hw) => [
                'id' => $hw->id,
                'title' => $hw->title,
                'due' => $hw->due_date?->format('d M Y') ?? '—',
                'status' => 'Pending',
                'emoji' => $hw->emoji ?? '📚',
                'body' => $hw->body,
                'class_name' => $hw->class_name,
            ])->values()->all();
        }

        return CmsItem::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('type', 'notice')
                    ->orWhere(function ($q2) {
                        $q2->where('type', 'blog')
                            ->where(function ($q3) {
                                $q3->whereJsonContains('meta->category', 'Homework')
                                    ->orWhereJsonContains('meta->portal', 'homework');
                            });
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(function (CmsItem $item) use ($locale) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $pub = $item->toPublicArray($locale);

                return [
                    'id' => $item->id,
                    'title' => $pub['title'] ?? $item->title,
                    'due' => $meta['due'] ?? $item->updated_at?->format('d M Y') ?? '—',
                    'status' => $meta['status'] ?? 'Pending',
                    'emoji' => $meta['emoji'] ?? '📚',
                ];
            })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function studentHomeworkItems(User $user): array
    {
        $card = $this->studentCard($user);
        if (! $card) {
            return [];
        }

        $meta = is_array($card->meta) ? $card->meta : [];
        $className = $meta['class_name'] ?? $meta['class'] ?? null;

        $items = Homework::query()
            ->where('status', 'active')
            ->when($className, fn ($q) => $q->where(function ($q2) use ($className) {
                $q2->where('class_name', $className)->orWhereNull('class_name');
            }))
            ->orderByDesc('due_date')
            ->limit(20)
            ->get();

        if ($items->isNotEmpty()) {
            $submissions = HomeworkSubmission::query()
                ->where('id_card_id', $card->id)
                ->whereIn('homework_id', $items->pluck('id'))
                ->get()
                ->keyBy('homework_id');

            return $items->map(function (Homework $hw) use ($submissions) {
                $sub = $submissions->get($hw->id);
                $status = match ($sub?->status) {
                    'reviewed' => 'Reviewed',
                    'returned' => 'Returned',
                    'submitted' => 'Submitted',
                    default => ($hw->due_date && $hw->due_date->isPast() ? 'Overdue' : 'Pending'),
                };

                return [
                    'id' => $hw->id,
                    'title' => $hw->title,
                    'due' => $hw->due_date?->format('d M Y') ?? '—',
                    'status' => $status,
                    'emoji' => $hw->emoji ?? '📚',
                    'body' => $hw->body,
                    'submission_id' => $sub?->id,
                    'can_submit' => ! $sub,
                ];
            })->values()->all();
        }

        return $this->homeworkItems();
    }

    /** @return array<string, mixed> */
    public function createHomework(array $data, User $teacher): array
    {
        $tenant = \App\Models\Tenant::query()->first();
        $dueDate = $this->parseDueDate($data['due'] ?? $data['due_date'] ?? null);

        $homework = Homework::create([
            'tenant_id' => $tenant?->id,
            'teacher_user_id' => $teacher->id,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'class_name' => $data['class_name'] ?? null,
            'due_date' => $dueDate,
            'emoji' => $data['emoji'] ?? '📚',
            'status' => 'active',
        ]);

        return [
            'id' => $homework->id,
            'title' => $homework->title,
            'due' => $homework->due_date?->format('d M Y') ?? '—',
            'status' => 'Pending',
            'emoji' => $homework->emoji ?? '📚',
        ];
    }

    private function parseDueDate(mixed $due): ?Carbon
    {
        if (! $due) {
            return now()->addDays(3);
        }
        try {
            return Carbon::parse($due);
        } catch (\Throwable) {
            return now()->addDays(7);
        }
    }

    /** @return list<array<string, string>> */
    public function teacherStudents(?User $teacher = null): array
    {
        $classFilter = $this->teacherClassFilter($teacher);

        $query = IdCard::query()
            ->where('card_type', 'student')
            ->where('status', 'active')
            ->orderBy('full_name');

        if ($classFilter) {
            $query->where(function ($q) use ($classFilter) {
                $q->whereJsonContains('meta->class_name', $classFilter)
                    ->orWhereJsonContains('meta->class', $classFilter);
            });
        }

        return $query->get()
            ->map(function (IdCard $card) {
                $meta = is_array($card->meta) ? $card->meta : [];

                return [
                    'id' => $card->id,
                    'name' => $card->full_name,
                    'class' => $meta['class'] ?? $card->academic_year ?? '—',
                    'admission_number' => $card->card_number,
                    'status' => ucfirst($card->status),
                ];
            })->values()->all();
    }

    private function teacherClassFilter(?User $teacher): ?string
    {
        if (! $teacher) {
            return null;
        }

        $card = IdCard::query()
            ->where('user_id', $teacher->id)
            ->where('card_type', 'teacher')
            ->first();

        $meta = is_array($card?->meta) ? $card->meta : [];
        $filter = $meta['assigned_class'] ?? $meta['class_name'] ?? null;

        if (! $filter && ! empty($meta['designation'])) {
            $filter = preg_replace('/\s+Teacher$/i', '', (string) $meta['designation']);
        }

        return $filter ? trim($filter) : null;
    }

    /** @return array{calendar: list<array<string, string>>, summary: string} */
    public function studentAttendance(User $user): array
    {
        $card = $this->studentCard($user);
        if (! $card) {
            return ['calendar' => [], 'summary' => 'ID card not linked'];
        }

        $start = now()->startOfWeek(Carbon::MONDAY);
        $days = collect();
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $record = AttendanceRecord::query()
                ->where('id_card_id', $card->id)
                ->whereDate('date', $date->toDateString())
                ->first();
            $days->push([
                'day' => $date->format('D'),
                'date' => $date->format('Y-m-d'),
                'status' => $record ? ucfirst(str_replace('_', ' ', $record->status)) : ($date->isFuture() ? '—' : 'Absent'),
            ]);
        }

        $monthRecords = AttendanceRecord::query()
            ->where('id_card_id', $card->id)
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();
        $present = $monthRecords->whereIn('status', ['present', 'late', 'half_day'])->count();
        $pct = $monthRecords->count() > 0 ? (int) round(($present / $monthRecords->count()) * 100) : 0;

        return ['calendar' => $days->all(), 'summary' => "{$pct}% this month"];
    }

    /** @return array{total: int, rewards: list<array<string, mixed>>} */
    public function studentRewards(User $user): array
    {
        $card = $this->studentCard($user);
        $meta = is_array($card?->meta) ? $card->meta : [];
        $monthPresent = 0;
        if ($card) {
            $monthPresent = AttendanceRecord::query()
                ->where('id_card_id', $card->id)
                ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                ->whereIn('status', ['present', 'late', 'half_day'])
                ->count();
        }
        $base = (int) ($meta['reward_stars'] ?? 10);

        $rewards = [
            ['title' => 'Perfect Attendance Star', 'stars' => min(5, $monthPresent), 'desc' => 'Days present this month'],
            ['title' => 'Kindness Badge', 'stars' => (int) ($meta['kindness_stars'] ?? 3), 'desc' => 'Helping friends & teachers'],
            ['title' => 'Creative Explorer', 'stars' => (int) ($meta['creative_stars'] ?? 4), 'desc' => 'Art & craft participation'],
            ['title' => 'Reading Champion', 'stars' => (int) ($meta['reading_stars'] ?? 2), 'desc' => 'Story time participation'],
        ];

        return ['total' => $base + collect($rewards)->sum('stars'), 'rewards' => $rewards];
    }

    /** @return list<array<string, string>> */
    public function studentActivities(string $locale = 'en'): array
    {
        return CmsItem::query()
            ->where('type', 'activity')
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (CmsItem $item) => [
                'title' => $item->toPublicArray($locale)['title'] ?? $item->title,
                'time' => $item->updated_at?->format('d M Y') ?? '',
                'summary' => $item->toPublicArray($locale)['summary'] ?? '',
            ])->values()->all();
    }
}
