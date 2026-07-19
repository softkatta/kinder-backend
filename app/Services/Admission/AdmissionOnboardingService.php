<?php

namespace App\Services\Admission;

use App\Models\Admission;
use App\Models\IdCard;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IdCard\IdCardService;
use App\Services\Notifications\IntegrationSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdmissionOnboardingService
{
    public function __construct(
        private readonly IdCardService $idCards,
        private readonly IntegrationSettingsService $integrations,
    ) {}

    /** @return array{student_id_card_id: int, parent_user_id: ?int, parent_created: bool, temp_password: ?string} */
    public function provisionFromAdmission(Admission $admission): array
    {
        if ($admission->student_id_card_id) {
            return [
                'student_id_card_id' => $admission->student_id_card_id,
                'parent_user_id' => $admission->parent_user_id,
                'parent_created' => false,
                'temp_password' => null,
            ];
        }

        return DB::transaction(function () use ($admission) {
            $parent = is_array($admission->parent_info) ? $admission->parent_info : [];
            $parentEmail = isset($parent['email']) ? strtolower(trim((string) $parent['email'])) : null;
            $parentName = trim((string) ($parent['full_name'] ?? 'Parent'));
            $parentPhone = $parent['phone'] ?? null;
            $tenant = Tenant::query()->first();
            $year = date('Y').'-'.(date('Y') + 1);
            $grade = strtoupper((string) ($admission->grade_level ?: 'Nursery'));

            $parentUser = null;
            $parentCreated = false;
            $tempPassword = null;

            if ($parentEmail) {
                $parentUser = User::query()->where('email', $parentEmail)->first();

                if (! $parentUser) {
                    $tempPassword = Str::password(10);
                    $parentUser = User::create([
                        'tenant_id' => $tenant?->id,
                        'name' => $parentName ?: 'Parent',
                        'email' => $parentEmail,
                        'phone' => $parentPhone,
                        'password' => Hash::make($tempPassword),
                        'is_active' => true,
                    ]);

                    $parentRole = Role::query()->where('name', 'parent')->first();
                    if ($parentRole) {
                        $parentUser->roles()->syncWithoutDetaching([$parentRole->id]);
                    }

                    $parentCreated = true;
                    $this->sendParentWelcomeEmail($parentUser, $admission, $tempPassword);
                } elseif (! $parentUser->hasRole('parent')) {
                    $parentRole = Role::query()->where('name', 'parent')->first();
                    if ($parentRole) {
                        $parentUser->roles()->syncWithoutDetaching([$parentRole->id]);
                    }
                }
            }

            $studentCard = $this->idCards->create([
                'card_type' => 'student',
                'full_name' => $admission->applicant_name,
                'photo_path' => $admission->photo_path,
                'academic_year' => $year,
                'emergency_contact' => $parentPhone,
                'meta' => [
                    'admission_number' => sprintf('ADM-%s-%04d', date('Y'), $admission->id),
                    'admission_id' => $admission->id,
                    'class' => $grade,
                    'class_name' => $grade,
                    'section_name' => 'A',
                    'parent_name' => $parentName,
                    'parent_email' => $parentEmail,
                    'parent_phone' => $parentPhone,
                    'dob' => $admission->dob?->format('Y-m-d'),
                    'gender' => $admission->gender,
                ],
            ]);

            if ($parentUser) {
                $existingParentCard = IdCard::query()
                    ->where('card_type', 'parent')
                    ->where('user_id', $parentUser->id)
                    ->first();

                if (! $existingParentCard) {
                    $this->idCards->create([
                        'card_type' => 'parent',
                        'full_name' => $parentUser->name,
                        'user_id' => $parentUser->id,
                        'academic_year' => $year,
                        'emergency_contact' => $parentPhone,
                        'meta' => [
                            'relationship' => 'Guardian',
                            'student_names' => $admission->applicant_name,
                        ],
                    ]);
                } else {
                    $meta = is_array($existingParentCard->meta) ? $existingParentCard->meta : [];
                    $names = trim((string) ($meta['student_names'] ?? ''));
                    if (! str_contains($names, $admission->applicant_name)) {
                        $meta['student_names'] = $names ? "{$names}, {$admission->applicant_name}" : $admission->applicant_name;
                        $existingParentCard->update(['meta' => $meta]);
                    }
                }
            }

            $admission->update([
                'student_id_card_id' => $studentCard->id,
                'parent_user_id' => $parentUser?->id,
            ]);

            return [
                'student_id_card_id' => $studentCard->id,
                'parent_user_id' => $parentUser?->id,
                'parent_created' => $parentCreated,
                'temp_password' => $tempPassword,
            ];
        });
    }

    private function sendParentWelcomeEmail(User $parent, Admission $admission, string $tempPassword): void
    {
        $settings = $this->integrations->get();
        $school = config('app.name', 'Little Stars Kindergarten');
        $loginUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/login';
        $subject = "{$school} — Parent portal access for {$admission->applicant_name}";
        $body = "Hello {$parent->name},\n\n"
            ."{$admission->applicant_name}'s admission has been approved.\n\n"
            ."Parent portal login:\n"
            ."Email: {$parent->email}\n"
            ."Temporary password: {$tempPassword}\n"
            ."Sign in: {$loginUrl}\n\n"
            ."Please change your password after first login.\n\n"
            ."— {$school}";

        if ($settings->email_enabled && $parent->email) {
            try {
                Mail::raw($body, function ($message) use ($parent, $subject, $settings) {
                    $message->to($parent->email)
                        ->subject($subject)
                        ->from(
                            $settings->email_from_address ?: config('mail.from.address'),
                            $settings->email_from_name ?: config('mail.from.name'),
                        );
                });

                return;
            } catch (\Throwable $e) {
                Log::warning('Parent welcome email failed', ['user' => $parent->id, 'error' => $e->getMessage()]);
            }
        }

        Log::info('Parent portal credentials generated', [
            'user' => $parent->id,
            'email' => $parent->email,
            'temp_password' => $tempPassword,
        ]);
    }
}
