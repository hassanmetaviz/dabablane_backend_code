<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToVendor;

class Rating extends Model
{
    use BelongsToVendor;
    protected $fillable = [
        'vendor_id',
        'blane_id',
        'user_id',
        'rating',
        'comment'
    ];

    public function blane()
    {
        return $this->belongsTo(Blane::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rating) {
            // Auto-assign vendor_id from blane
            if ($rating->blane_id && !$rating->vendor_id) {
                $blane = Blane::find($rating->blane_id);
                if ($blane && $blane->vendor_id) {
                    $rating->vendor_id = $blane->vendor_id;
                }
            }
        });
    }

    /**
     * Scope a query to filter by vendor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|null  $vendorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForVendor($query, $vendorId = null)
    {
        $vendorId = $vendorId ?? (auth()->check() && auth()->user()->hasRole('vendor') ? auth()->id() : null);

        if ($vendorId) {
            return $query->where('vendor_id', $vendorId);
        }

        return $query;
    }
}
