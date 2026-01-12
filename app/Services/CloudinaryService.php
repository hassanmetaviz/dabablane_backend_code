<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    private static function getCloudinaryInstance(): Cloudinary
    {

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_KEY');
        $apiSecret = env('CLOUDINARY_SECRET');

        Log::info('Cloudinary credentials check', [
            'cloud_name' => $cloudName,
            'api_key_exists' => !empty($apiKey),
            'api_secret_exists' => !empty($apiSecret),
        ]);

        return new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    /**
     * Upload a video to Cloudinary.
     *
     * @param UploadedFile $file
     * @param string|null $folder
     * @return array{url: string, public_id: string}
     *
     * @throws \RuntimeException
     */
    public static function uploadVideo(UploadedFile $file, ?string $folder = null): array
    {
        $folder = $folder ?? config('services.cloudinary.blanes_folder', 'blanes/videos');

        try {
            $cloudinary = self::getCloudinaryInstance();

            $options = [
                'folder' => $folder,
                'resource_type' => 'video',
            ];

            if (!is_readable($file->getRealPath())) {
                throw new \RuntimeException('File is not readable or does not exist');
            }

            $uploadResult = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $options
            );

            Log::info('Cloudinary upload result', [
                'secure_url' => $uploadResult['secure_url'] ?? null,
                'public_id' => $uploadResult['public_id'] ?? null,
                'resource_type' => $uploadResult['resource_type'] ?? null,
            ]);

            if (empty($uploadResult['secure_url']) || empty($uploadResult['public_id'])) {
                throw new \RuntimeException('Cloudinary upload returned incomplete data');
            }

            return [
                'url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id'],
            ];
        } catch (\Throwable $exception) {
            Log::error('Cloudinary video upload failed', [
                'message' => $exception->getMessage(),
                'file_name' => $file->getClientOriginalName(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new \RuntimeException('Video upload failed: ' . $exception->getMessage());
        }
    }

    /**
     * Delete a video from Cloudinary if a public ID is available.
     */
    public static function deleteVideo(?string $publicId): void
    {
        if (!$publicId) {
            return;
        }

        try {
            $cloudinary = self::getCloudinaryInstance();
            $cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => 'video'
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete Cloudinary video', [
                'public_id' => $publicId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}