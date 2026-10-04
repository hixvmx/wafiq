<?php

namespace App\Http\Controllers;

use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/** The member's in-app notifications (the bell), for the current company only. */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = self::query($request)
            ->latest()
            ->paginate(30)
            ->through(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Index', ['notifications' => $notifications]);
    }

    /** Opens what the notification is about, and marks it read. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = self::query($request)->findOrFail($id);
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('home'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        self::query($request)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    /** @return Builder<DatabaseNotification> */
    public static function query(Request $request): Builder
    {
        return $request->user()->notifications()->getQuery()
            ->where('data->company_id', app(CurrentCompany::class)->id());
    }
}
