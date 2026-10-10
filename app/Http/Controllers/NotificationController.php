<?php

namespace App\Http\Controllers;

use App\Support\TextNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var LengthAwarePaginator $notifications */
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        $notifications->getCollection()->transform(function ($notification) {
            $data = $notification->data;

            $title = TextNormalizer::fixMojibake((string) ($data['title'] ?? ''));
            $message = TextNormalizer::fixMojibake((string) ($data['message'] ?? ''));

            if ($title === 'New family record added') {
                $title = __('ui.notification_new_family_title');
            }

            if (preg_match("/^Family '(.+)' was created\\.$/", $message, $matches) === 1) {
                $message = __('ui.notification_new_family_message', ['name' => $matches[1]]);
            }

            $data['title'] = TextNormalizer::fixMojibake($title);
            $data['message'] = TextNormalizer::fixMojibake($message);
            $notification->data = $data;

            return $notification;
        });

        return view('notifications.index', compact('notifications'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('ui.notifications_marked_read'));
    }
}
