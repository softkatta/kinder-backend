<?php

namespace App\Services\Search;

use App\Models\Admission;
use App\Models\IdCard;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class ErpSearchService
{
    /** @return list<array{type: string, label: string, subtitle: string, url: string}> */
    public function search(User $user, string $query, int $limit = 8): array
    {
        $term = trim($query);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $roles = $user->roleNames();
        $results = collect();

        if (in_array('super_admin', $roles, true)) {
            $results = $results->merge($this->searchStudents($term, '/admin/students'))
                ->merge($this->searchUsers($term))
                ->merge($this->searchPayments($term))
                ->merge($this->searchAdmissions($term));
        }

        if ($user->hasAnyRole(['teacher', 'staff'])) {
            $results = $results->merge($this->searchStudents($term, '/teacher/students'));
        }

        if ($user->hasRole('parent')) {
            $results = $results->merge($this->searchParentChildren($user, $term));
        }

        if ($user->hasRole('student')) {
            $card = IdCard::query()->where('user_id', $user->id)->where('card_type', 'student')->first();
            if ($card && (str_contains(mb_strtolower($card->full_name), mb_strtolower($term)) || str_contains(mb_strtolower($card->card_number), mb_strtolower($term)))) {
                $results->push([
                    'type' => 'profile',
                    'label' => $card->full_name,
                    'subtitle' => 'My student profile',
                    'url' => '/student',
                ]);
            }
        }

        return $results->unique(fn (array $row) => $row['type'].'|'.$row['url'].'|'.$row['label'])
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return Collection<int, array{type: string, label: string, subtitle: string, url: string}> */
    private function searchStudents(string $term, string $basePath): Collection
    {
        return IdCard::query()
            ->where('card_type', 'student')
            ->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('card_number', 'like', "%{$term}%");
            })
            ->limit(5)
            ->get()
            ->map(function (IdCard $card) use ($basePath) {
                $meta = is_array($card->meta) ? $card->meta : [];

                return [
                    'type' => 'student',
                    'label' => $card->full_name,
                    'subtitle' => ($meta['class'] ?? 'Student').' · '.$card->card_number,
                    'url' => $basePath === '/admin/students' ? "/admin/students/{$card->id}" : $basePath,
                ];
            });
    }

    /** @return Collection<int, array{type: string, label: string, subtitle: string, url: string}> */
    private function searchUsers(string $term): Collection
    {
        return User::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'type' => 'user',
                'label' => $user->name,
                'subtitle' => $user->email,
                'url' => '/admin/users',
            ]);
    }

    /** @return Collection<int, array{type: string, label: string, subtitle: string, url: string}> */
    private function searchPayments(string $term): Collection
    {
        return Payment::query()
            ->where(function ($q) use ($term) {
                $q->where('student_name', 'like', "%{$term}%")
                    ->orWhere('payer_name', 'like', "%{$term}%")
                    ->orWhere('payment_reference', 'like', "%{$term}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment) => [
                'type' => 'payment',
                'label' => $payment->student_name ?: $payment->payer_name,
                'subtitle' => '₹'.number_format((float) $payment->amount, 0).' · '.ucfirst($payment->status),
                'url' => '/admin/payments',
            ]);
    }

    /** @return Collection<int, array{type: string, label: string, subtitle: string, url: string}> */
    private function searchAdmissions(string $term): Collection
    {
        return Admission::query()
            ->where(function ($q) use ($term) {
                $q->where('applicant_name', 'like', "%{$term}%")
                    ->orWhere('grade_level', 'like', "%{$term}%");
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Admission $row) => [
                'type' => 'admission',
                'label' => $row->applicant_name,
                'subtitle' => ucfirst($row->status).' · '.strtoupper((string) ($row->grade_level ?: '—')),
                'url' => '/admin/admissions',
            ]);
    }

    /** @return Collection<int, array{type: string, label: string, subtitle: string, url: string}> */
    private function searchParentChildren(User $user, string $term): Collection
    {
        return IdCard::query()
            ->where('card_type', 'student')
            ->where(function ($q) use ($user) {
                $q->whereJsonContains('meta->parent_email', $user->email)
                    ->orWhereJsonContains('meta->parent_phone', $user->phone);
            })
            ->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('card_number', 'like', "%{$term}%");
            })
            ->limit(5)
            ->get()
            ->map(function (IdCard $card) {
                $meta = is_array($card->meta) ? $card->meta : [];

                return [
                    'type' => 'child',
                    'label' => $card->full_name,
                    'subtitle' => ($meta['class'] ?? 'Child').' · '.$card->card_number,
                    'url' => '/parent/children',
                ];
            });
    }
}
