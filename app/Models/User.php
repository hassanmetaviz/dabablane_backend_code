<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'phone',
        'city',
        'district',
        'subdistrict',
        'provider',
        'accessToken',
        'avatar',
        'company_name',
        'landline',
        'businessCategory',
        'subCategory',
        'description',
        'address',
        'ice',
        'rc',
        'vat',
        'logoUrl',
        'coverPhotoUrl',
        'rcCertificateUrl',
        'status',
        'ribUrl',
        'facebook',
        'tiktok',
        'instagram',
        'custom_commission_rate',
        'rib_account',
        'isDiamond',
        'blane_limit',

    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'custom_commission_rate' => 'decimal:2',
            'isDiamond' => 'boolean',
            'blane_limit' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->blane_limit)) {
                $user->blane_limit = 6;
            }
        });

        static::created(function ($user) {
            if (empty($user->blane_limit)) {
                $user->update(['blane_limit' => 6]);
            }
        });
    }

    public function coverMedia()
    {
        return $this->hasMany(VendorCoverMedia::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include pending users.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include diamond vendors.
     */
    public function scopeDiamond($query)
    {
        return $query->where('isDiamond', true);
    }
    public function hasActiveSubscription()
    {
        return $this->purchases()
            ->where('status', 'completed')
            ->where('end_date', '>=', now())
            ->exists();
    }
}