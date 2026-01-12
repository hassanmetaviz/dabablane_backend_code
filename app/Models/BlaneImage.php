<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\FileHelper;
use App\Services\BunnyService;

class BlaneImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'blane_id',
        'image_url',
        'media_type',
        'cloudinary_public_id',
        'is_cloudinary',
    ];

    protected $casts = [
        'is_cloudinary' => 'boolean',
    ];

    protected $appends = ['image_link'];

    public function blane()
    {
        return $this->belongsTo(Blane::class);
    }

    public function getImageLinkAttribute()
    {
        if ($this->is_cloudinary && filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return $this->image_url;
        }

        return FileHelper::getFile('blanes_images', $this->image_url);
    }

    /**
     * Delete the physical media asset from disk or Bunny.net.
     */
    public function deletePhysicalFile(): void
    {
        if ($this->is_cloudinary && $this->image_url) {
            try {
                $path = $this->cloudinary_public_id ?? $this->image_url;
                BunnyService::deleteFile($path);
            } catch (\Throwable $exception) {
            }
            return;
        }

        if ($this->image_url) {
            FileHelper::deleteFile($this->image_url, 'blanes_images');
        }
    }
}