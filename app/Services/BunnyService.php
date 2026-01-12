<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BunnyService
{
    /**
     * Get Bunny.net Storage configuration
     */
    private static function getConfig(): array
    {
        return [
            'storage_zone' => env('BUNNY_STORAGE_ZONE'),
            'api_key' => env('BUNNY_STORAGE_API_KEY'),
            'region' => env('BUNNY_REGION', 'ny'),
            'cdn_url' => env('BUNNY_CDN_URL'),
            'blanes_folder' => env('BUNNY_BLANES_FOLDER', 'blanes'),
        ];
    }

    /**
     * Upload a file (image or video) to Bunny.net Storage.
     *
     * @param UploadedFile $file
     * @param string|null $folder
     * @param string $type 'image' or 'video'
     * @return array{url: string, path: string}
     *
     * @throws \RuntimeException
     */
    public static function uploadFile(UploadedFile $file, ?string $folder = null, string $type = 'image'): array
    {
        $config = self::getConfig();

        if (empty($config['storage_zone']) || empty($config['api_key'])) {
            throw new \RuntimeException('Bunny.net configuration is missing. Please set BUNNY_STORAGE_ZONE and BUNNY_STORAGE_API_KEY in your .env file.');
        }

        $apiKey = trim($config['api_key'], " \t\n\r\0\x0B\"'");
        $storageZone = trim($config['storage_zone'], " \t\n\r\0\x0B\"'");
        $region = trim($config['region'], " \t\n\r\0\x0B\"'");

        if (empty($apiKey)) {
            throw new \RuntimeException('BUNNY_STORAGE_API_KEY is empty. Please check your .env file.');
        }

        if (empty($storageZone)) {
            throw new \RuntimeException('BUNNY_STORAGE_ZONE is empty. Please check your .env file.');
        }

        $folder = $folder ?? $config['blanes_folder'] . '/' . ($type === 'video' ? 'videos' : 'images');

        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = trim($folder . '/' . $fileName, '/');

        try {
            $uploadUrl = "https://{$region}.storage.bunnycdn.com/{$storageZone}/{$path}";

            Log::info('Bunny.net upload starting', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'path' => $path,
                'type' => $type,
                'storage_zone' => $storageZone,
                'region' => $region,
                'upload_url' => $uploadUrl,
            ]);

            $response = Http::withHeaders([
                'AccessKey' => $apiKey,
                'Content-Type' => $file->getMimeType(),
            ])
                ->timeout(60)
                ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
                ->put($uploadUrl);

            $httpCode = $response->status();

            Log::debug('Bunny.net upload response', [
                'status' => $httpCode,
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);

            if ($httpCode < 200 || $httpCode >= 300) {
                $errorMessage = 'HTTP ' . $httpCode;
                if (!empty($response->body())) {
                    $errorMessage .= ': ' . $response->body();
                }

                Log::error('Bunny.net upload failed', [
                    'status' => $httpCode,
                    'error' => $errorMessage,
                    'path' => $path,
                    'upload_url' => $uploadUrl,
                    'storage_zone' => $storageZone,
                    'region' => $region,
                ]);

                if ($httpCode === 401) {
                    $helpMessage = 'Bunny.net authentication failed (401 Unauthorized). ';
                    $helpMessage .= 'Please verify: ';
                    $helpMessage .= '1) You are using the regular password (not read-only) from FTP & API access, ';
                    $helpMessage .= '2) The password has no extra spaces or quotes, ';
                    $helpMessage .= '3) BUNNY_STORAGE_ZONE matches exactly: "' . $config['storage_zone'] . '", ';
                    $helpMessage .= '4) The password in .env does not have quotes around it. ';
                    $helpMessage .= 'Error: ' . $errorMessage;
                    throw new \RuntimeException($helpMessage);
                }

                if ($httpCode === 404) {
                    throw new \RuntimeException('Bunny.net storage zone not found. Please verify: 1) The storage zone name is correct, 2) The region is correct for your storage zone.');
                }

                throw new \RuntimeException('Bunny.net upload failed (Status: ' . $httpCode . '): ' . $errorMessage);
            }

            $cdnUrl = rtrim($config['cdn_url'], '/') . '/' . $path;

            Log::info('Bunny.net upload successful', [
                'path' => $path,
                'url' => $cdnUrl,
                'region' => $region,
            ]);

            return [
                'url' => $cdnUrl,
                'path' => $path,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Bunny.net connection error', [
                'message' => $e->getMessage(),
                'upload_url' => $uploadUrl ?? 'Not set',
                'region' => $region ?? 'Not set',
            ]);

            throw new \RuntimeException('Could not connect to Bunny.net storage. Please check: 1) Your region code (' . ($region ?? 'NULL') . ') is correct, 2) Network connectivity, 3) Try a different region or use global endpoint (storage.bunnycdn.com)');
        } catch (\RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Bunny.net upload error', [
                'message' => $exception->getMessage(),
                'file_name' => $file->getClientOriginalName(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new \RuntimeException('File upload failed: ' . $exception->getMessage());
        }
    }

    /**
     * Delete a file from Bunny.net Storage.
     *
     * @param string $path The path to the file (e.g., 'blanes/videos/filename.mp4')
     * @return void
     */
    public static function deleteFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        $config = self::getConfig();

        if (empty($config['storage_zone']) || empty($config['api_key'])) {
            Log::warning('Bunny.net configuration missing, cannot delete file', ['path' => $path]);
            return;
        }

        try {
            // Extract path from full URL if needed
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $parsedUrl = parse_url($path);
                $path = ltrim($parsedUrl['path'] ?? '', '/');
            }

            // Use regional endpoint for delete
            $region = $config['region'] ?? 'ny';
            $deleteUrl = "https://{$region}.storage.bunnycdn.com/{$config['storage_zone']}/{$path}";

            $response = Http::withHeaders([
                'AccessKey' => $config['api_key'],
            ])
                ->timeout(30)
                ->delete($deleteUrl);

            if ($response->successful()) {
                Log::info('Bunny.net file deleted successfully', ['path' => $path]);
            } else {
                Log::warning('Failed to delete Bunny.net file', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete Bunny.net file', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Fallback method using global endpoint if regional fails
     */
    public static function uploadFileWithGlobalEndpoint(UploadedFile $file, ?string $folder = null, string $type = 'image'): array
    {
        $config = self::getConfig();
        $storageZone = trim($config['storage_zone'], " \t\n\r\0\x0B\"'");
        $apiKey = trim($config['api_key'], " \t\n\r\0\x0B\"'");

        $folder = $folder ?? $config['blanes_folder'] . '/' . ($type === 'video' ? 'videos' : 'images');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = trim($folder . '/' . $fileName, '/');

        // Use global endpoint (fallback)
        $uploadUrl = "https://storage.bunnycdn.com/{$storageZone}/{$path}";

        Log::warning('Using global Bunny.net endpoint as fallback', [
            'path' => $path,
            'upload_url' => $uploadUrl,
        ]);

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Content-Type' => $file->getMimeType(),
        ])
            ->timeout(60)
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
            ->put($uploadUrl);

        if ($response->successful()) {
            $cdnUrl = rtrim($config['cdn_url'], '/') . '/' . $path;

            return [
                'url' => $cdnUrl,
                'path' => $path,
            ];
        }

        throw new \RuntimeException('Global endpoint also failed: ' . $response->status() . ' ' . $response->body());
    }

    /**
     * Upload a video to Bunny.net Storage.
     */
    public static function uploadVideo(UploadedFile $file, ?string $folder = null): array
    {
        return self::uploadFile($file, $folder, 'video');
    }

    /**
     * Upload an image to Bunny.net Storage.
     */
    public static function uploadImage(UploadedFile $file, ?string $folder = null): array
    {
        return self::uploadFile($file, $folder, 'image');
    }
}
