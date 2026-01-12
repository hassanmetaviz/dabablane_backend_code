<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CommissionChart extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'category_id',
        'file_path',
        'file_size',
        'mime_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the commission chart.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the file URL for download.
     */
    public function getFileUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get the file path for storage.
     */
    public function getStoragePathAttribute()
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Scope active commission charts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}