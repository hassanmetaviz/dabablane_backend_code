<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\FileHelper;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToVendor;

class Blane extends Model
{
    use HasFactory, BelongsToVendor;

    protected $fillable = [
        'vendor_id',
        'subcategories_id',
        'categories_id',
        'name',
        'description',
        'price_current',
        'price_old',
        'advantages',
        'conditions',
        'city',
        'district',
        'subdistricts',
        'status',
        'type',
        'reservation_type',
        'online',
        'partiel',
        'cash',
        'on_top',
        'views',
        'start_day',
        'end_day',
        'stock',
        'max_orders',
        'livraison_in_city',
        'livraison_out_city',
        'allow_out_of_city',
        'start_date',
        'expiration_date',
        'slug',
        'jours_creneaux',
        'dates',
        'type_time',
        'heure_debut',
        'heure_fin',
        'intervale_reservation',
        'personnes_prestation',
        'nombre_max_reservation',
        'nombre_personnes',
        'max_reservation_par_creneau',
        'partiel_field',
        'tva',
        'commerce_name',
        'commerce_phone',
        'is_digital',
        'visibility',
        'share_token',
        'video_url',
        'video_public_id',
        'availability_per_day',
    ];

    protected $appends = ['rating'];

    protected $casts = [
        'jours_creneaux' => 'array',
        'dates' => 'array',
        'online' => 'boolean',
        'partiel' => 'boolean',
        'cash' => 'boolean',
        'on_top' => 'boolean',
        'is_digital' => 'boolean',
        'allow_out_of_city' => 'boolean',
        'heure_debut' => 'datetime',
        'heure_fin' => 'datetime',
        'start_day' => 'datetime',
        'end_day' => 'datetime',
        'start_date' => 'datetime',
        'expiration_date' => 'datetime',
        'intervale_reservation' => 'integer',
        'availability_per_day' => 'integer',
        'available_time_slots' => 'array',
        'available_periods' => 'array'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Additional creating logic for commerce_name backward compatibility
        static::creating(function ($blane) {
            if (auth()->check() && auth()->user()->hasRole('vendor')) {
                // Keep commerce_name for backward compatibility during migration
                if (empty($blane->commerce_name)) {
                    $blane->commerce_name = auth()->user()->company_name;
                }
            }
        });

        // Expiration check
        static::retrieved(function ($blane) {
            if ($blane->expiration_date && $blane->status !== 'expired') {
                if ($blane->expiration_date <= now()) {
                    DB::table('blanes')
                        ->where('id', $blane->id)
                        ->update(['status' => 'expired']);
                    $blane->status = 'expired';
                }
            }
        });
    }

    public function scopeWithActiveVendorOrNoVendor($query)
    {
        return $query->where(function ($query) {
            // New way: Check by vendor_id if available
            $query->where(function ($q) {
                $q->whereNotNull('vendor_id')
                    ->whereHas('vendor', function ($vendorQuery) {
                        $vendorQuery->where('status', 'active');
                    });
            })
                // Old way: Fallback to commerce_name for backward compatibility
                ->orWhere(function ($q) {
                    $q->whereNull('vendor_id')
                        ->whereHas('vendorByCommerceName', function ($vendorQuery) {
                            $vendorQuery->where('status', 'active');
                        });
                })
                ->orWhereNull('commerce_name')
                ->orWhere(function ($q) {
                    $q->whereNotNull('commerce_name')
                        ->whereNull('vendor_id')
                        ->whereDoesntHave('vendorByCommerceName')
                        ->where('status', 'active')
                        ->where(function ($subQ) {
                            $subQ->whereNull('expiration_date')
                                ->orWhere('expiration_date', '>=', now());
                        });
                });
        });
    }

    /**
     * Scope a query to only include active blanes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include non-expired blanes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expiration_date', '>=', now());
    }

    /**
     * Scope a query to only include featured blanes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeatured($query)
    {
        return $query->where('on_top', true);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class, 'subcategories_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }


    /**
     * Get the images for the Blane.
     */
    public function blaneImages()
    {
        return $this->hasMany(BlaneImage::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function getRatingAttribute()
    {
        return $this->ratings()
            ->avg('rating') ?? 0;
    }

    public function vendor()
    {
        // Use vendor_id (new way) - preferred relationship
        // For backward compatibility with old blanes (vendor_id NULL), 
        // controllers will handle fallback to commerce_name filtering
        return $this->belongsTo(User::class, 'vendor_id')
            ->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status', 'landline', 'phone', 'address', 'city', 'district', 'subdistrict', 'logoUrl', 'blane_limit');
    }

    /**
     * Legacy relationship for backward compatibility.
     * Used when vendor_id is NULL (old blanes).
     */
    public function vendorByCommerceName()
    {
        return $this->hasOne(User::class, 'company_name', 'commerce_name')
            ->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status', 'landline', 'phone', 'address', 'city', 'district', 'subdistrict', 'logoUrl', 'blane_limit');
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



    /**
     * Get available time slots based on start time, end time and booking interval
     * 
     * @return array
     */
    public function getAvailableTimeSlots(): array
    {
        $timeSlots = [];
        $startTime = \Carbon\Carbon::parse($this->heure_debut);
        $endTime = \Carbon\Carbon::parse($this->heure_fin);
        $interval = $this->intervale_reservation ?? 60;
        $maxReservations = $this->max_reservation_par_creneau ?? 3;

        while ($startTime <= $endTime) {
            $timeSlot = $startTime->format('H:i');

            $currentReservations = $this->reservations()
                ->where('date', now()->format('Y-m-d'))
                ->where('time', $timeSlot)
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($currentReservations < $maxReservations) {
                $timeSlots[] = $timeSlot;
            }

            $startTime->addMinutes($interval);
        }

        return $timeSlots;
    }

    /**
     * Check if a given time is valid for booking
     * 
     * @param string $time Time in format "H:i"
     * @return bool
     */
    public function isValidBookingTime(string $time): bool
    {
        $timeObj = \Carbon\Carbon::createFromFormat('H:i', $time);

        if (!$timeObj || !$this->heure_debut || !$this->heure_fin) {
            return false;
        }

        if ($timeObj < $this->heure_debut || $timeObj > $this->heure_fin) {
            return false;
        }

        if (!$this->intervale_reservation) {
            return true;
        }

        $interval = (int) $this->intervale_reservation;

        $startTime = $this->heure_debut->copy();
        $valid = false;

        while ($startTime <= $this->heure_fin) {
            if ($startTime->format('H:i') === $timeObj->format('H:i')) {
                $valid = true;
                break;
            }
            $startTime->addMinutes($interval);
        }

        return $valid;
    }

    /**
     * Get the available time slots attribute
     * 
     * @return array
     */
    public function getAvailableTimeSlotsAttribute(): array
    {
        return $this->getAvailableTimeSlots();
    }

    /**
     * Get available periods based on max_reservation_par_creneau
     * 
     * @return array
     */
    public function getAvailablePeriods(): array
    {
        $periods = [];
        $maxReservations = $this->max_reservation_par_creneau ?? 3;

        $blaneStartDate = $this->start_date ? \Carbon\Carbon::parse($this->start_date)->startOfDay() : null;
        $blaneExpirationDate = $this->expiration_date ? \Carbon\Carbon::parse($this->expiration_date)->endOfDay() : null;

        $startDate = $this->start_date ?? now();
        $endDate = $this->end_date ?? now()->addDays(30);

        if (!empty($this->dates)) {
            foreach ($this->dates as $range) {
                if (isset($range['start']) && isset($range['end'])) {
                    $start = \Carbon\Carbon::parse($range['start'])->startOfDay();
                    $end = \Carbon\Carbon::parse($range['end'])->endOfDay();

                    $isWithinDateRange = true;
                    if ($blaneStartDate && $start < $blaneStartDate) {
                        $isWithinDateRange = false;
                    }
                    if ($blaneExpirationDate && $end > $blaneExpirationDate) {
                        $isWithinDateRange = false;
                    }

                    $reservationsCount = $this->reservations()
                        ->where(function ($query) use ($start, $end) {
                            $query->where('date', $start->format('Y-m-d'))
                                ->where('end_date', $end->format('Y-m-d'));
                        })
                        ->where('status', '!=', 'cancelled')
                        ->sum('quantity');

                    $remainingCapacity = max(0, $maxReservations - $reservationsCount);
                    $percentageFull = $maxReservations > 0 ? round(($reservationsCount / $maxReservations) * 100) : 0;

                    $isAvailable = $isWithinDateRange && $reservationsCount < $maxReservations;

                    $daysCount = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

                    $periods[] = [
                        'start' => $start->format('Y-m-d'),
                        'end' => $end->format('Y-m-d'),
                        'available' => $isAvailable,
                        'currentReservations' => $reservationsCount,
                        'maxReservations' => $maxReservations,
                        'remainingCapacity' => $remainingCapacity,
                        'percentageFull' => $percentageFull,
                        'daysCount' => $daysCount,
                        'period_name' => $start->format('d M') . ' - ' . $end->format('d M Y'),
                        'isWeekend' => $this->isPeriodWeekend($start, $end)
                    ];
                }
            }

            return $periods;
        }

        $currentDate = $startDate->copy();

        for ($length = 1; $length <= 3; $length++) {
            $currentDate = $startDate->copy();

            while ($currentDate->addDays($length) <= $endDate) {
                $periodStart = $currentDate->copy()->subDays($length)->startOfDay();
                $periodEnd = $currentDate->copy()->endOfDay();

                $isWithinDateRange = true;
                if ($blaneStartDate && $periodStart < $blaneStartDate) {
                    $isWithinDateRange = false;
                }
                if ($blaneExpirationDate && $periodEnd > $blaneExpirationDate) {
                    $isWithinDateRange = false;
                }

                $reservationsCount = $this->reservations()
                    ->where(function ($query) use ($periodStart, $periodEnd) {
                        $query->where('date', $periodStart->format('Y-m-d'))
                            ->where('end_date', $periodEnd->format('Y-m-d'));
                    })
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $remainingCapacity = max(0, $maxReservations - $reservationsCount);
                $percentageFull = $maxReservations > 0 ? round(($reservationsCount / $maxReservations) * 100) : 0;

                $isAvailable = $isWithinDateRange && $reservationsCount < $maxReservations;

                $daysCount = (int) $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1;

                $periods[] = [
                    'start' => $periodStart->format('Y-m-d'),
                    'end' => $periodEnd->format('Y-m-d'),
                    'available' => $isAvailable,
                    'currentReservations' => $reservationsCount,
                    'maxReservations' => $maxReservations,
                    'remainingCapacity' => $remainingCapacity,
                    'percentageFull' => $percentageFull,
                    'daysCount' => $daysCount,
                    'period_name' => $periodStart->format('d M') . ' - ' . $periodEnd->format('d M Y'),
                    'isWeekend' => $this->isPeriodWeekend($periodStart, $periodEnd)
                ];
            }
        }

        return $periods;
    }

    /**
     * Check if a period contains weekend days
     * 
     * @param \Carbon\Carbon $start
     * @param \Carbon\Carbon $end
     * @return bool
     */
    private function isPeriodWeekend(\Carbon\Carbon $start, \Carbon\Carbon $end): bool
    {
        $current = $start->copy();

        while ($current <= $end) {
            if ($current->isWeekend()) {
                return true;
            }
            $current->addDay();
        }

        return false;
    }

    /**
     * Get the available periods attribute
     * 
     * @return array
     */
    public function getAvailablePeriodsAttribute(): array
    {
        return $this->getAvailablePeriods();
    }

    /**
     * Generate a unique slug
     * 
     * @param string $name
     * @return string
     */
    public static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

}
