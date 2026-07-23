<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileUploadController extends Controller
{
    public function uploadCms(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'cms', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], 5120);
    }

    public function uploadCmsVideo(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'cms/videos', ['video/mp4', 'video/webm', 'video/quicktime'], 102400);
    }

    public function upload(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'uploads', ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], 10240);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'documents', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], 10240);
    }

    public function uploadAdmissionPhoto(Request $request): JsonResponse
    {
        $result = $this->storeFileData($request, 'admissions', ['image/jpeg', 'image/png', 'image/webp'], 5120);

        if ($request->filled('admission_id')) {
            Admission::query()
                ->where('id', $request->integer('admission_id'))
                ->update(['photo_path' => $result['path']]);
        }

        return ApiResponse::success($result, 'File uploaded');
    }

    public function uploadPaymentProof(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'payments', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 5120);
    }

    public function uploadGuest(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'guests', ['image/jpeg', 'image/png', 'image/webp'], 5120);
    }

    public function uploadHomework(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'homework', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 10240);
    }

    public function uploadTeacherPhoto(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'teachers', ['image/jpeg', 'image/png', 'image/webp'], 5120);
    }

    public function uploadTemplateBackground(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'templates/backgrounds', ['image/png', 'image/jpeg', 'image/webp'], 20480);
    }

    public function uploadTemplateAsset(Request $request): JsonResponse
    {
        return $this->storeFile($request, 'templates/assets', ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'], 5120);
    }

    private function storeFile(Request $request, string $folder, array $mimes, int $maxKb): JsonResponse
    {
        return ApiResponse::success(
            $this->storeFileData($request, $folder, $mimes, $maxKb),
            'File uploaded',
        );
    }

    /** @return array{path: string, url: string} */
    private function storeFileData(Request $request, string $folder, array $mimes, int $maxKb): array
    {
        $file = $this->resolveUploadedFile($request);

        $request->validate([
            'file' => 'required|file|max:'.$maxKb,
        ]);

        $this->assertAllowedFileType($file, $mimes);

        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $stored = $file->storeAs($folder, $name, 'public');

        return [
            'path' => $stored,
            'url' => '/storage/'.str_replace('\\', '/', $stored),
        ];
    }

    /** @param  array<int, string>  $mimes */
    private function assertAllowedFileType(\Illuminate\Http\UploadedFile $file, array $mimes): void
    {
        $mime = strtolower($file->getMimeType() ?: '');
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        $mimeAliases = [
            'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
        ];
        if (isset($mimeAliases[$mime])) {
            $mime = $mimeAliases[$mime];
        }

        if (in_array($mime, $mimes, true)) {
            return;
        }

        $extensionToMime = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
        ];

        if ($extension !== '' && isset($extensionToMime[$extension]) && in_array($extensionToMime[$extension], $mimes, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => ['Invalid file type. Allowed: '.implode(', ', $mimes).'.'],
        ]);
    }

    private function resolveUploadedFile(Request $request): \Illuminate\Http\UploadedFile
    {
        $uploadMax = ini_get('upload_max_filesize') ?: '2M';
        $postMax = ini_get('post_max_size') ?: '8M';

        if (! $request->hasFile('file')) {
            $contentLength = (int) $request->header('Content-Length', 0);
            if ($contentLength > $this->iniSizeToBytes($postMax)) {
                throw ValidationException::withMessages([
                    'file' => ["File is too large for the server (post_max_size is {$postMax}). Compress the image or restart PHP with higher limits."],
                ]);
            }

            throw ValidationException::withMessages([
                'file' => ["No file received. Your PHP upload limit is {$uploadMax} â€” use a smaller image (under 2 MB) or restart the backend with: php -d upload_max_filesize=25M -d post_max_size=30M artisan serve --port=8010"],
            ]);
        }

        $file = $request->file('file');
        if (! $file->isValid()) {
            $message = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "File exceeds PHP upload limit ({$uploadMax}). The app will auto-compress â€” refresh the page and try again, or use a smaller PNG/JPEG.",
                UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                default => 'Upload failed. Please try a different image.',
            };

            throw ValidationException::withMessages(['file' => [$message]]);
        }

        return $file;
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}

