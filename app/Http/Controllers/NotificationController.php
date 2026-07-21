<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $notifications = Notification::where('user_id', $userId)
            ->latest()
            ->get();

        $unreadCount = Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
        ]);

        $notification = Notification::create([
            ...$validated,
            'is_read' => 0,
        ]);

        return response()->json([
            'message' => 'Notifikasi berhasil dibuat.',
            'data' => $notification,
        ], 201);
    }

    public function show(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        if (!$notification->is_read) {
            $notification->update(['is_read' => 1]);
        }

        return response()->json([
            'message' => 'Detail notifikasi berhasil diambil.',
            'data' => $notification
        ]);
    }

    public function update(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        $notification->update([
            'is_read' => $request->boolean('is_read', true),
        ]);

        return response()->json([
            'message' => 'Status notifikasi diperbarui.',
            'data' => $notification->fresh(),
        ]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notifikasi berhasil dihapus.',
        ]);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json([
            'message' => 'Semua notifikasi sudah dibaca.'
        ]);
    }
}
