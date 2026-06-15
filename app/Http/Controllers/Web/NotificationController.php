<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (string) $user->getAuthIdentifier();

        $unreadCount = Cache::remember(
            CacheKeys::notificationsUnreadCount($userId),
            CacheTtl::NOTIFICATIONS_UNREAD_COUNT,
            fn () => $user->appNotifications()->whereNull('read_at')->count()
        );

        $notifications = Cache::remember(
            CacheKeys::notificationsRecent($userId),
            CacheTtl::NOTIFICATIONS_RECENT,
            function () use ($user) {
                return $user->appNotifications()
                    ->take(15)
                    ->get(['id', 'type', 'title', 'body', 'url', 'icon', 'read_at', 'created_at'])
                    ->map(fn (Notification $n) => [
                        'id' => $n->id,
                        'type' => $n->type,
                        'title' => $n->title,
                        'body' => $n->body,
                        'url' => $n->url,
                        'icon' => $n->icon,
                        'read_at' => $n->read_at,
                        'time' => $n->created_at?->diffForHumans(),
                    ])
                    ->all();
            }
        );

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->appNotifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->update(['read_at' => now()]);
            $this->forgetNotificationCache((string) $user->getAuthIdentifier());
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->forgetNotificationCache((string) $user->getAuthIdentifier());

        return response()->json(['success' => true]);
    }

    private function forgetNotificationCache(string $userId): void
    {
        Cache::forget(CacheKeys::notificationsUnreadCount($userId));
        Cache::forget(CacheKeys::notificationsRecent($userId));
    }
}
