<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\FileHelper;

class MobileBanner extends Model
{
    protected $table = 'mobile_banners';

    protected $fillable = [
        'title',
        'description',
        'image_url',
        'link',
        'order',
        'is_active',
        'start_date',
        'end_date'
    ];

    protected $appends = ['image_link'];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'order' => 'integer'
    ];

    public function getImageLinkAttribute()
    {
        if (!$this->image_url) {
            return null;
        }

        $result = FileHelper::getFile('mobile_banner_images', $this->image_url);

        if (is_array($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Scope a query to only include active banners.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope a query to order banners by order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('created_at', 'desc');
    }
}
