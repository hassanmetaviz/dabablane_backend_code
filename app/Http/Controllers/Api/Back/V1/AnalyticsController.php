<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Blane;
use App\Models\Coupon;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function getAnalytics()
    {
        $now = Carbon::now();

        $totalOrders = Order::count();
        $orderRevenue = Order::whereNotIn('status', ['failed', 'cancelled'])->sum('total_price');
        $reservationRevenue = Reservation::whereNotIn('status', ['failed', 'cancelled'])->sum('total_price');
        $totalRevenue = $orderRevenue + $reservationRevenue;
        $totalUsers = User::count();
        $totalReservations = Reservation::count();

        $totalAvailableBlanes = Blane::where('status', 'active')
            ->where('expiration_date', '>', $now)
            ->count();

        // Total des blanes expirés (by date)
        $totalExpiredBlanes = Blane::where('expiration_date', '<=', $now)->count();

        // Total des coupons utilisés
        $totalUsedCoupons = Coupon::where('is_active', false)->count();

        // Calculate changes (placeholders for now)
        $changeOrders = 0; // Calculate the change in orders
        $changeRevenue = 0; // Calculate the change in revenue
        $changeUsers = 5.1; // Calculate the change in users
        $changeReservations = 1.8; // Calculate the change in reservations
        $changeAvailableBlanes = 2.9; // Calculate the change in available blanes
        $changeExpiredBlanes = 5; // Calculate the change in expired blanes
        $changeUsedCoupons = 2.3; // Calculate the change in used coupons

        $thirtyDaysAgo = $now->copy()->subDays(30);

        $activeBlanes = Blane::where('status', 'active')
            ->where('expiration_date', '>', $now)
            ->count();
        $inactiveBlanes = Blane::where('status', 'inactive')->count();
        $expiredBlanes = Blane::where('expiration_date', '<=', $now)->count();

        $nearExpirationBlanes = Blane::where('status', 'active')
            ->whereBetween('expiration_date', [
                $now,
                $now->copy()->addDays(3)
            ])->count();

        $previousActiveBlanes = Blane::where('status', 'active')
            ->where('expiration_date', '>', $thirtyDaysAgo)
            ->where('created_at', '<=', $thirtyDaysAgo)
            ->count();

        $previousInactiveBlanes = Blane::where('status', 'inactive')
            ->where('created_at', '<=', $thirtyDaysAgo)
            ->count();

        $changeActiveBlanes = $previousActiveBlanes ?
            (($activeBlanes - $previousActiveBlanes) / $previousActiveBlanes) * 100 : 0;

        $changeInactiveBlanes = $previousInactiveBlanes ?
            (($inactiveBlanes - $previousInactiveBlanes) / $previousInactiveBlanes) * 100 : 0;

        $totalBookings = $totalOrders + $totalReservations;
        $averageBasket = $totalBookings > 0
            ? round($totalRevenue / $totalBookings, 2)
            : 0;

        $confirmedStatuses = [
            Reservation::STATUS_CLIENT_CONFIRMED,
            Reservation::STATUS_RETAILER_CONFIRMED,
            Reservation::STATUS_ADMIN_CONFIRMED,
            Reservation::STATUS_CONFIRMED,
        ];

        $confirmedReservationsCount = Reservation::whereIn('status', $confirmedStatuses)->count();
        $blaneConfirmPercentage = $totalReservations > 0
            ? round(($confirmedReservationsCount / $totalReservations) * 100, 2)
            : 0;

        return response()->json([
            [
                'name' => 'Total Orders',
                'value' => $totalOrders,
                'change' => $changeOrders,
                'icon' => 'OrdersIcon',
            ],
            [
                'name' => 'Total Revenue',
                'value' => $totalRevenue,
                'change' => $changeRevenue,
                'icon' => 'DollarSignIcon',
            ],
            [
                'name' => 'Total Users',
                'value' => $totalUsers,
                'change' => $changeUsers,
                'icon' => 'UsersIcon',
            ],
            [
                'name' => 'Total Reservations',
                'value' => $totalReservations,
                'change' => $changeReservations,
                'icon' => 'ReservationsIcon',
            ],
            [
                'name' => 'Total Available Blanes',
                'value' => $totalAvailableBlanes,
                'change' => $changeAvailableBlanes,
                'icon' => 'BlanesIcon',
            ],
            [
                'name' => 'Total Expired Blanes',
                'value' => $totalExpiredBlanes,
                'change' => $changeExpiredBlanes,
                'icon' => 'ExpiredBlanesIcon',
            ],
            [
                'name' => 'Total Used Coupons',
                'value' => $totalUsedCoupons,
                'change' => $changeUsedCoupons,
                'icon' => 'CouponsIcon',
            ],
            [
                'name' => 'Active Blanes',
                'value' => $activeBlanes,
                'change' => round($changeActiveBlanes, 1),
                'icon' => 'ActiveBlanesIcon',
                'details' => [
                    'near_expiration' => $nearExpirationBlanes,
                    'percentage_active' => round(($activeBlanes / max(1, $activeBlanes + $inactiveBlanes + $expiredBlanes)) * 100, 1)
                ]
            ],
            [
                'name' => 'Inactive Blanes',
                'value' => $inactiveBlanes,
                'change' => round($changeInactiveBlanes, 1),
                'icon' => 'InactiveBlanesIcon',
                'details' => [
                    'percentage_inactive' => round(($inactiveBlanes / max(1, $activeBlanes + $inactiveBlanes + $expiredBlanes)) * 100, 1)
                ]
            ],
            [
                'name' => 'Expired Blanes',
                'value' => $expiredBlanes,
                'icon' => 'ExpiredBlanesIcon',
                'details' => [
                    'percentage_expired' => round(($expiredBlanes / max(1, $activeBlanes + $inactiveBlanes + $expiredBlanes)) * 100, 1)
                ]
            ],
            [
                'name' => 'Near Expiration',
                'value' => $nearExpirationBlanes,
                'icon' => 'NearExpirationIcon',
                'details' => [
                    'percentage_near_expiration' => round(($nearExpirationBlanes / max(1, $activeBlanes)) * 100, 1),
                    'days_threshold' => 3
                ]
            ],
            [
                'name' => 'Average Basket',
                'value' => $averageBasket,
                'icon' => 'BasketIcon',
                'currency' => 'MAD',
                'details' => [
                    'total_bookings' => $totalBookings,
                    'formula' => 'total_revenue / total_bookings'
                ]
            ],
            [
                'name' => 'Blane Confirm',
                'value' => $confirmedReservationsCount,
                'icon' => 'ConfirmIcon',
                'details' => [
                    'percentage' => $blaneConfirmPercentage,
                    'total_reservations' => $totalReservations
                ]
            ]
        ]);
    }

    public function getBlanesStatus()
    {
        $now = Carbon::now();

        return response()->json([
            'active' => Blane::where('status', 'active')
                ->where('expiration_date', '>', $now)
                ->count(),
            'inactive' => Blane::where('status', 'inactive')->count(),
            'expired' => Blane::where('expiration_date', '<=', $now)->count(),
            'total' => Blane::count(),
            'last_updated' => $now->toDateTimeString()
        ]);
    }

    public function getNearExpiration()
    {
        $now = Carbon::now();
        $threeDaysFromNow = $now->copy()->addDays(3);

        $nearExpiration = Blane::where('status', 'active')
            ->whereBetween('expiration_date', [$now, $threeDaysFromNow])
            ->with('category')
            ->get();

        return response()->json([
            'count' => $nearExpiration->count(),
            'blanes' => $nearExpiration,
            'threshold_date' => $threeDaysFromNow->toDateString()
        ]);
    }

    public function getStatusDistribution()
    {
        $now = Carbon::now();
        $total = Blane::count();
        $active = Blane::where('status', 'active')
            ->where('expiration_date', '>', $now)
            ->count();
        $inactive = Blane::where('status', 'inactive')->count();
        $expired = Blane::where('expiration_date', '<=', $now)->count();

        return response()->json([
            'distribution' => [
                'active' => [
                    'count' => $active,
                    'percentage' => $total > 0 ? round(($active / $total) * 100, 1) : 0
                ],
                'inactive' => [
                    'count' => $inactive,
                    'percentage' => $total > 0 ? round(($inactive / $total) * 100, 1) : 0
                ],
                'expired' => [
                    'count' => $expired,
                    'percentage' => $total > 0 ? round(($expired / $total) * 100, 1) : 0
                ]
            ],
            'total' => $total
        ]);
    }

    /**
     * Get analytics for a specific vendor
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVendorAnalytics(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'company_name' => 'nullable|string|max:255', // Old way - for backward compatibility
                'vendor_id' => 'nullable|integer|exists:users,id', // New way - preferred
                'period' => 'nullable|string|in:week,month,custom',
                'start_date' => 'nullable|date|required_if:period,custom',
                'end_date' => 'nullable|date|after_or_equal:start_date|required_if:period,custom',
            ]);

            $user = Auth::user();

            if (!$user->hasRole(['admin', 'vendor'])) {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'message' => 'Access denied. Admin or Vendor role required.',
                ], 403);
            }

            // Support both vendor_id (new way) and company_name (old way) for backward compatibility
            $vendor = null;
            if ($request->filled('vendor_id')) {
                // New preferred way: use vendor_id
                $vendor = User::whereHas('roles', function ($q) {
                    $q->where('name', 'vendor');
                })->find($request->input('vendor_id'));

                if (!$vendor) {
                    return response()->json([
                        'status' => false,
                        'code' => 404,
                        'message' => 'Vendor not found with the specified vendor ID.',
                    ], 404);
                }
            } elseif ($request->filled('company_name')) {
                // Old way: support company_name for backward compatibility
                $vendor = User::where('company_name', $request->input('company_name'))
                    ->whereHas('roles', function ($q) {
                        $q->where('name', 'vendor');
                    })
                    ->first();

                if (!$vendor) {
                    return response()->json([
                        'status' => false,
                        'code' => 404,
                        'message' => 'Vendor not found with the specified company name.',
                    ], 404);
                }
            } elseif ($user->hasRole('vendor')) {
                // Auto-use authenticated vendor if no parameter provided
                $vendor = $user;
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'message' => 'Either vendor_id or company_name is required for admin users.',
                ], 400);
            }

            $now = Carbon::now();

            $period = $request->input('period', 'month');
            $startDate = null;
            $endDate = $now;

            switch ($period) {
                case 'week':
                    $startDate = $now->copy()->subWeek();
                    break;
                case 'month':
                    $startDate = $now->copy()->subMonth();
                    break;
                case 'custom':
                    $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : $now->copy()->subMonth();
                    $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : $now;
                    break;
            }

            $periodLength = $startDate->diffInDays($endDate);
            $previousStartDate = $startDate->copy()->subDays($periodLength);
            $previousEndDate = $startDate->copy();

            // Helper closure to filter by vendor - supports both vendor_id (new way) and commerce_name (old way)
            $filterByVendor = function ($query) use ($vendor) {
                if ($vendor->id) {
                    // New way: use vendor_id when available
                    return $query->where(function ($q) use ($vendor) {
                        $q->where('vendor_id', $vendor->id)
                            ->orWhereHas('blane', function ($blaneQuery) use ($vendor) {
                                $blaneQuery->where(function ($subQ) use ($vendor) {
                                    $subQ->where('vendor_id', $vendor->id)
                                        ->orWhere(function ($fallbackQ) use ($vendor) {
                                            $fallbackQ->whereNull('vendor_id')
                                                ->where('commerce_name', $vendor->company_name);
                                        });
                                });
                            });
                    });
                } else {
                    // Old way: fall back to commerce_name for backward compatibility
                    return $query->whereHas('blane', function ($blaneQuery) use ($vendor) {
                        $blaneQuery->where('commerce_name', $vendor->company_name);
                    });
                }
            };

            // Helper closure to filter Blane by vendor
            $filterBlaneByVendor = function ($query) use ($vendor) {
                if ($vendor->id) {
                    return $query->where(function ($q) use ($vendor) {
                        $q->where('vendor_id', $vendor->id)
                            ->orWhere(function ($subQ) use ($vendor) {
                                $subQ->whereNull('vendor_id')
                                    ->where('commerce_name', $vendor->company_name);
                            });
                    });
                } else {
                    return $query->where('commerce_name', $vendor->company_name);
                }
            };

            $totalOrders = $filterByVendor(Order::query())->whereBetween('created_at', [$startDate, $endDate])->count();

            $orderRevenue = $filterByVendor(Order::query())
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_price');

            $reservationRevenue = $filterByVendor(Reservation::query())
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_price');

            $totalRevenue = $orderRevenue + $reservationRevenue;

            $previousOrders = $filterByVendor(Order::query())->whereBetween('created_at', [$previousStartDate, $previousEndDate])->count();

            $previousOrderRevenue = $filterByVendor(Order::query())
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->sum('total_price');

            $previousReservationRevenue = $filterByVendor(Reservation::query())
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->sum('total_price');

            $previousRevenue = $previousOrderRevenue + $previousReservationRevenue;

            $changeOrders = $previousOrders ?
                round((($totalOrders - $previousOrders) / $previousOrders) * 100, 1) : 0;
            $changeRevenue = $previousRevenue ?
                round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0;

            $totalReservations = $filterByVendor(Reservation::query())->whereBetween('created_at', [$startDate, $endDate])->count();
            $previousReservations = $filterByVendor(Reservation::query())->whereBetween('created_at', [$previousStartDate, $previousEndDate])->count();
            $changeReservations = $previousReservations ?
                round((($totalReservations - $previousReservations) / $previousReservations) * 100, 1) : 0;

            $totalBlanes = $filterBlaneByVendor(Blane::query())->whereBetween('created_at', [$startDate, $endDate])->count();
            $activeBlanes = $filterBlaneByVendor(Blane::query())
                ->where('status', 'active')
                ->where('expiration_date', '>', $now)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $inactiveBlanes = $filterBlaneByVendor(Blane::query())
                ->where('status', 'inactive')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $expiredBlanes = $filterBlaneByVendor(Blane::query())
                ->where('expiration_date', '<=', $now)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $nearExpirationBlanes = $filterBlaneByVendor(Blane::query())
                ->where('status', 'active')
                ->whereBetween('expiration_date', [
                    $now,
                    $now->copy()->addDays(3)
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $previousActiveBlanes = $filterBlaneByVendor(Blane::query())
                ->where('status', 'active')
                ->where('expiration_date', '>', $previousEndDate)
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->count();
            $previousInactiveBlanes = $filterBlaneByVendor(Blane::query())
                ->where('status', 'inactive')
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->count();

            $changeActiveBlanes = $previousActiveBlanes ?
                round((($activeBlanes - $previousActiveBlanes) / $previousActiveBlanes) * 100, 1) : 0;
            $changeInactiveBlanes = $previousInactiveBlanes ?
                round((($inactiveBlanes - $previousInactiveBlanes) / $previousInactiveBlanes) * 100, 1) : 0;

            $recentStartDate = $endDate->copy()->subDays(7);
            $recentOrders = $filterByVendor(Order::query())->whereBetween('created_at', [$recentStartDate, $endDate])->count();
            $recentReservations = $filterByVendor(Reservation::query())->whereBetween('created_at', [$recentStartDate, $endDate])->count();
            $recentBlanes = $filterBlaneByVendor(Blane::query())
                ->whereBetween('created_at', [$recentStartDate, $endDate])
                ->count();

            $topBlanes = $filterBlaneByVendor(Blane::query())
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCount([
                    'orders' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);
                    },
                    'reservations' => function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);
                    }
                ])
                ->orderBy('orders_count', 'desc')
                ->orderBy('reservations_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'orders_count', 'reservations_count', 'price_current']);

            $totalBookings = $totalOrders + $totalReservations;
            $averageBasket = $totalBookings > 0
                ? round($totalRevenue / $totalBookings, 2)
                : 0;

            $confirmedStatuses = [
                Reservation::STATUS_CLIENT_CONFIRMED,
                Reservation::STATUS_RETAILER_CONFIRMED,
                Reservation::STATUS_ADMIN_CONFIRMED,
                Reservation::STATUS_CONFIRMED,
            ];

            $confirmedReservationsCount = $filterByVendor(Reservation::query())
                ->whereIn('status', $confirmedStatuses)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $blaneConfirmPercentage = $totalReservations > 0
                ? round(($confirmedReservationsCount / $totalReservations) * 100, 2)
                : 0;

            return response()->json([
                'vendor_info' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'company_name' => $vendor->company_name,
                    'email' => $vendor->email,
                ],
                'analytics' => [
                    [
                        'name' => 'Total Orders',
                        'value' => $totalOrders,
                        'change' => $changeOrders,
                        'icon' => 'OrdersIcon',
                        'recent' => $recentOrders,
                    ],
                    [
                        'name' => 'Total Revenue',
                        'value' => $totalRevenue,
                        'change' => $changeRevenue,
                        'icon' => 'DollarSignIcon',
                        'currency' => 'MAD',
                    ],
                    [
                        'name' => 'Total Reservations',
                        'value' => $totalReservations,
                        'change' => $changeReservations,
                        'icon' => 'ReservationsIcon',
                        'recent' => $recentReservations,
                    ],
                    [
                        'name' => 'Total Blanes',
                        'value' => $totalBlanes,
                        'change' => 0,
                        'icon' => 'BlanesIcon',
                        'recent' => $recentBlanes,
                    ],
                    [
                        'name' => 'Active Blanes',
                        'value' => $activeBlanes,
                        'change' => $changeActiveBlanes,
                        'icon' => 'ActiveBlanesIcon',
                        'details' => [
                            'near_expiration' => $nearExpirationBlanes,
                            'percentage_active' => $totalBlanes > 0 ?
                                round(($activeBlanes / $totalBlanes) * 100, 1) : 0
                        ]
                    ],
                    [
                        'name' => 'Inactive Blanes',
                        'value' => $inactiveBlanes,
                        'change' => $changeInactiveBlanes,
                        'icon' => 'InactiveBlanesIcon',
                        'details' => [
                            'percentage_inactive' => $totalBlanes > 0 ?
                                round(($inactiveBlanes / $totalBlanes) * 100, 1) : 0
                        ]
                    ],
                    [
                        'name' => 'Expired Blanes',
                        'value' => $expiredBlanes,
                        'icon' => 'ExpiredBlanesIcon',
                        'details' => [
                            'percentage_expired' => $totalBlanes > 0 ?
                                round(($expiredBlanes / $totalBlanes) * 100, 1) : 0
                        ]
                    ],
                    [
                        'name' => 'Near Expiration',
                        'value' => $nearExpirationBlanes,
                        'icon' => 'NearExpirationIcon',
                        'details' => [
                            'percentage_near_expiration' => $activeBlanes > 0 ?
                                round(($nearExpirationBlanes / $activeBlanes) * 100, 1) : 0,
                            'days_threshold' => 3
                        ]
                    ],
                    [
                        'name' => 'Average Basket',
                        'value' => $averageBasket,
                        'icon' => 'BasketIcon',
                        'currency' => 'MAD',
                        'details' => [
                            'total_bookings' => $totalBookings,
                            'formula' => 'total_revenue / total_bookings'
                        ]
                    ],
                    [
                        'name' => 'Blane Confirm',
                        'value' => $confirmedReservationsCount,
                        'icon' => 'ConfirmIcon',
                        'details' => [
                            'percentage' => $blaneConfirmPercentage,
                            'total_reservations' => $totalReservations
                        ]
                    ]
                ],
                'top_performing_blanes' => $topBlanes,
                'period' => [
                    'type' => $period,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'previous_start_date' => $previousStartDate->toDateString(),
                    'previous_end_date' => $previousEndDate->toDateString(),
                    'days' => $periodLength
                ],
                'last_updated' => $now->toDateTimeString()
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}