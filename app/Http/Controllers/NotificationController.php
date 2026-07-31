<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Support\CrmNotificationPresentation;

class NotificationController extends Controller
{
    private const API_LIMIT = 15;

    /**
     * Página "Ver todas" as notificações.
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(25);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * JSON para o dropdown do header.
     */
    public function apiList(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $user->notifications()
            ->take(self::API_LIMIT)
            ->get()
            ->map(fn ($n) => $this->formatNotification($n));

        return response()->json([
            'notifications' => $items,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(\Illuminate\Notifications\DatabaseNotification $n): array
    {
        $data = $n->data;
        if (! is_array($data)) {
            $data = [];
        }

        $type = $data['type'] ?? null;
        $icon = CrmNotificationPresentation::iconForType(is_string($type) ? $type : null);

        return [
            'id' => $n->id,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? route('agenda.index'),
            'calendar_event_id' => $data['calendar_event_id'] ?? null,
            'type' => $type,
            'icon_class' => $icon['class'],
            'icon' => $icon['icon'],
        ];
    }
}
