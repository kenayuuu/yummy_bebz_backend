<?php

namespace App\Http\Controllers;

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

        return Chat::where(function ($q) use ($userId, $request) {
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

        Notification::create([
            'user_id' => $receiver->id,
            'title' => 'Pesan Baru',
            'message' => $sender->name . ' : ' . $data['message'],
            'type' => 'chat',
            'reference_id' => $sender->id,
        ]);

        $receiver = User::find($data['receiver_id']);

        if ($receiver && !empty($receiver->fcm_token)) {
            try {
                $accessToken = FirebaseHelper::getAccessToken();

                $client = new Client([
                    'timeout' => 30,
                ]);

                \Log::info($receiver->fcm_token);

                $client->post(
                    'https://fcm.googleapis.com/v1/projects/yummy-bebz/messages:send',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'message' => [
                                'token' => $receiver->fcm_token,

                                'notification' => [
                                    'title' => 'Pesan Baru',
                                    'body' => $sender->name . ' : ' . $data['message'],
                                ],

                                'data' => [
                                    'type' => 'chat',
                                    'sender_id' => (string) $sender->id,
                                    'sender_name' => $sender->name,
                                    'receiver_id' => (string) $receiver->id,
                                    'message' => $data['message'],
                                ],

                                'android' => [
                                    'priority' => 'HIGH',
                                    'notification' => [
                                        'channel_id' => 'chat_channel',
                                        'sound' => 'default',
                                        'default_sound' => true,
                                        'default_vibrate_timings' => true,
                                    ],
                                ],

                                'apns' => [
                                    'headers' => [
                                        'apns-priority' => '10',
                                    ],
                                    'payload' => [
                                        'aps' => [
                                            'alert' => [
                                                'title' => 'Pesan Baru',
                                                'body' => $sender->name . ' : ' . $data['message'],
                                            ],
                                            'sound' => 'default',
                                            'badge' => 1,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                );
            } catch (\Throwable $e) {
                \Log::error('FCM Error: ' . $e->getMessage());
            }
        }

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
