<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use Illuminate\Support\Facades\Artisan;

/**
 * @OA\Schema(
 *     schema="Notification",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="data", type="object"),
 *     @OA\Property(property="read_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class NotificationController extends BaseController
{
    /**
     * Get all notifications for the authenticated user
     *
     * @OA\Get(
     *     path="/back/v1/notifications",
     *     tags={"Back - Notifications"},
     *     summary="List user notifications",
     *     operationId="backNotificationsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved",
     *         @OA\JsonContent(@OA\Property(property="status", type="string", example="success"), @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Notification")))
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
     */
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(10);
        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    /**
     * Mark a notification as read
     *
     * @OA\Post(
     *     path="/back/v1/notifications/{id}/read",
     *     tags={"Back - Notifications"},
     *     summary="Mark notification as read",
     *     operationId="backNotificationsMarkAsRead",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Notification marked as read", @OA\JsonContent(@OA\Property(property="status", type="string"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * @OA\Post(
     *     path="/back/v1/notifications/read-all",
     *     tags={"Back - Notifications"},
     *     summary="Mark all notifications as read",
     *     operationId="backNotificationsMarkAllAsRead",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All notifications marked as read", @OA\JsonContent(@OA\Property(property="status", type="string"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a notification
     *
     * @OA\Delete(
     *     path="/back/v1/notifications/{id}",
     *     tags={"Back - Notifications"},
     *     summary="Delete a notification",
     *     operationId="backNotificationsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Notification deleted", @OA\JsonContent(@OA\Property(property="status", type="string"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $user->notifications()->findOrFail($id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Delete all notifications
     *
     * @OA\Delete(
     *     path="/back/v1/notifications",
     *     tags={"Back - Notifications"},
     *     summary="Delete all notifications",
     *     operationId="backNotificationsDestroyAll",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All notifications deleted", @OA\JsonContent(@OA\Property(property="status", type="string"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
     */
    public function destroyAll()
    {
        $user = Auth::user();
        $user->notifications()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications deleted'
        ]);
    }
    public function checkExpiration()
{
    Artisan::call('blane:check-expiration');
    return response()->json([
        'status' => 'success',
        'message' => 'Expiration check completed'
    ]);
}
}
