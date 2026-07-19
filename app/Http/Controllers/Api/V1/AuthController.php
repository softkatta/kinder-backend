<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Guest;
use App\Models\User;
use App\Services\Notifications\IntegrationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly IntegrationSettingsService $integrations,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginId = trim($credentials['email']);
        $user = User::with('roles')->where('email', $loginId)->first();

        if (! $user) {
            $guest = Guest::query()
                ->where('guest_code', $loginId)
                ->orWhere('qr_token', $loginId)
                ->first();
            if ($guest?->user_id) {
                $user = User::with('roles')->find($guest->user_id);
            }
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Your account is inactive. Contact the school office.', 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        $roles = $user->roleNames();

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
            'roles' => $roles,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return ApiResponse::success($this->formatUser($user));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        $message = 'If that email is registered, we sent password reset instructions.';

        if (! $user || ! $user->is_active) {
            return ApiResponse::success(null, $message);
        }

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ],
        );

        $resetUrl = $this->buildResetUrl($user->email, $plainToken);
        $this->sendResetEmail($user, $resetUrl);

        $data = null;
        if (config('app.debug')) {
            $data = ['reset_url' => $resetUrl];
        }

        return ApiResponse::success($data, $message);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($validated['email']));
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! Hash::check($validated['token'], $row->token)) {
            throw ValidationException::withMessages([
                'token' => ['This reset link is invalid or has expired.'],
            ]);
        }

        $createdAt = $row->created_at ? strtotime((string) $row->created_at) : 0;
        if ($createdAt < now()->subHours(24)->getTimestamp()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'token' => ['This reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account found for this email.'],
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password updated. You can sign in with your new password.');
    }

    private function buildResetUrl(string $email, string $token): string
    {
        $frontend = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $query = http_build_query([
            'email' => $email,
            'token' => $token,
        ]);

        return "{$frontend}/reset-password?{$query}";
    }

    private function sendResetEmail(User $user, string $resetUrl): void
    {
        $settings = $this->integrations->get();
        $school = config('app.name', 'Little Stars Kindergarten');
        $subject = "{$school} — Reset your password";
        $body = "Hello {$user->name},\n\n"
            ."We received a request to reset your portal password.\n\n"
            ."Open this link to choose a new password (valid for 24 hours):\n{$resetUrl}\n\n"
            ."If you did not request this, you can ignore this email.\n\n"
            ."— {$school}";

        if ($settings->email_enabled && $user->email) {
            try {
                Mail::raw($body, function ($message) use ($user, $subject, $settings) {
                    $message->to($user->email)
                        ->subject($subject)
                        ->from(
                            $settings->email_from_address ?: config('mail.from.address'),
                            $settings->email_from_name ?: config('mail.from.name'),
                        );
                });

                return;
            } catch (\Throwable $e) {
                Log::warning('Password reset email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        Log::info('Password reset link generated', ['user' => $user->id, 'reset_url' => $resetUrl]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'tenant_id' => $user->tenant_id,
            'roles' => $user->roleNames(),
        ];
    }
}
