<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Models\Notification;
use App\Helpers\FirebaseHelper;
use App\Models\Chat;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
        ]);

        $userId = auth()->id();

        return Chat::query()
            ->with([
                'sender:id,name,profil',
                'receiver:id,name,profil',
            ])
            ->where(function ($q) use ($userId, $request) {
                $q->where('sender_id', $userId)
                    ->where('receiver_id', $request->receiver_id);
            })
            ->orWhere(function ($q) use ($userId, $request) {
                $q->where('sender_id', $request->receiver_id)
                    ->where('receiver_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:5000',
        ]);

        $sender = auth()->user();

        $chat = Chat::create([
            'sender_id' => $sender->id,
            'receiver_id' => $data['receiver_id'],
            'message' => $data['message'],
            'is_read' => 0,
        ]);

        $receiver = User::find($data['receiver_id']);

        NotificationHelper::send(
            $receiver,
            'Pesan Baru',
            $sender->name . ' : ' . $data['message'],
            'chat',
            [
                'reference_id' => $sender->id,
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'receiver_id' => $receiver->id,
                'message' => $data['message'],
            ]
        );

        return response()->json($chat);
    }

    public function chatRooms()
    {
        $ownerId = auth()->id();

        $users = Chat::selectRaw("
                CASE
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as user_id
            ", [$ownerId])
            ->where('sender_id', $ownerId)
            ->orWhere('receiver_id', $ownerId)
            ->groupBy('user_id')
            ->pluck('user_id');

        $result = [];

        foreach ($users as $userId) {
            $user = User::find($userId);

            if (!$user) {
                continue;
            }

            $lastMessage = Chat::where(function ($q) use ($ownerId, $userId) {
                $q->where('sender_id', $ownerId)
                    ->where('receiver_id', $userId);
            })
                ->orWhere(function ($q) use ($ownerId, $userId) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', $ownerId);
                })
                ->latest()
                ->first();

            $unread = Chat::where('sender_id', $userId)
                ->where('receiver_id', $ownerId)
                ->where('is_read', 0)
                ->count();

            $result[] = [
                'receiver_id' => $userId,
                'customer_name' => $user->name,
                'last_message' => $lastMessage->message ?? '',
                'last_message_time' => $lastMessage->created_at ?? null,
                'unread' => $unread,
            ];
        }

        usort($result, function ($a, $b) {
            return strtotime($b['last_message_time'])
                <=> strtotime($a['last_message_time']);
        });

        return response()->json([
            'data' => $result,
            'unread_count' => array_sum(array_column($result, 'unread')),
        ]);
    }

    public function markAsRead($senderId)
    {
        $ownerId = auth()->id();

        Chat::where('sender_id', $senderId)
            ->where('receiver_id', $ownerId)
            ->update([
                'is_read' => 1,
            ]);

        return response()->json([
            'message' => 'Marked as read',
        ]);
    }

    public function notifications()
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->get();
    }
}
