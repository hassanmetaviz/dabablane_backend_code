<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileHelper
{
    public static function uploadFile($file, $directory)
    {
        try {
            $userDir = 'uploads/' . $directory;
            $fileName = time() . '' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($userDir, $fileName, 'public');

            return [
                'file_path' => Storage::url($filePath),
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
            ];
        } catch (\Exception $e) {
            return ['error' => 'File upload failed'];
        }
    }

    public static function getFile($type, $fileName)
    {
        if (empty($fileName)) {
            return null;
        }

        if (filter_var($fileName, FILTER_VALIDATE_URL)) {
            return $fileName;
        }

        $filePath = 'uploads/' . $type . '/' . $fileName;

        if (Storage::disk('public')->exists($filePath)) {
            return config('app.url') . Storage::url($filePath);
        }

        return null;
    }


    /**
     * Delete a file from storage
     *
     * @param string $fileName The name of the file to delete
     * @param string $directory The directory where the file is stored
     * @return array
     */
    public static function deleteFile($fileName, $directory)
    {
        try {
            $filePath = 'uploads/' . $directory . '/' . $fileName;

            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
                return ['success' => true];
            }

            return ['error' => 'File not found'];
        } catch (\Exception $e) {
            return ['error' => 'File deletion failed: ' . $e->getMessage()];
        }
    }
}
