<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\FileHelper;
use Illuminate\Support\Str;


class Category extends Model
{
    protected $fillable = ['name', 'description', 'icon_url', 'image_url', 'slug', 'status'];
    protected $appends = ['image_link'];

    /**
     * Automatically generate the slug from the name.
     *
     * @param string $value
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            $category->generateUniqueSlug();
        });
        
        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->generateUniqueSlug();
            }
        });
    }

    /**
     * Generate a unique slug.
     *
     * @return void
     */
    protected function generateUniqueSlug()
    {
        $slug = Str::slug($this->name);
        $count = static::where('slug', 'like', $slug.'%')
            ->where('id', '!=', $this->id)
            ->count();
            
        if ($count > 0) {
            $slug .= '-'.($count + 1);
        }
        
        $this->slug = $slug;
    }

    /**
     * Scope a query to only include active categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function blanes()
    {
        return $this->hasMany(Blane::class, 'categories_id');
    }

    public function getImageLinkAttribute()
    {
        return FileHelper::getFile('categories_images', $this->image_url);
    }
}