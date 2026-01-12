<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\FileHelper;

class Banner extends Model
{
    protected $table = 'banner';
    protected $fillable = [
        'title',
        'description',
        'image_url',
        'btname1',
        'link',
        'title2',
        'description2',
        'image_url2',
        'btname2',
        'link2'
    ];

    protected $appends = ['image_link', 'image_link2'];

    public function getImageLinkAttribute()
    {
        return FileHelper::getFile('banner_images', $this->image_url);
    }

    public function getImageLink2Attribute()
    {
        return FileHelper::getFile('banner_images', $this->image_url2);
    }
}
