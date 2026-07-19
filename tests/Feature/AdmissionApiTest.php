<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class AdmissionApiTest extends TestCase
{
    public function test_public_can_submit_admission(): void
    {
        $response = $this->postJson('/api/v1/admissions', [
            'applicant_name' => 'Test Child '.Str::random(4),
            'grade_level' => 'nursery',
            'parent_info' => [
                'full_name' => 'Test Parent',
                'email' => 'parent-test-'.Str::random(6).'@example.com',
                'phone' => '+91 90000 12345',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);
    }
}
