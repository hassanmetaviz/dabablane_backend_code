<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'is_active',
        'description',
        'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active terms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by latest first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include user terms.
     */
    public function scopeForUser($query)
    {
        return $query->where('type', 'user');
    }

    /**
     * Scope a query to only include vendor terms.
     */
    public function scopeForVendor($query)
    {
        return $query->where('type', 'vendor');
    }
}