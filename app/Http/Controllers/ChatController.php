<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function getAdminAndStaffIds()
    {
        return User::whereIn('role', ['owner', 'karyawan'])->pluck('id')->toArray();

        // jika menggunakan Spatie Permission:
        // return User::role(['owner', 'karyawan'])->pluck('id')->toArray();
    }

    public function index(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
        ]);

        $currentUser = auth()->user();
        $otherUserId = $request->receiver_id;
        $adminStaffIds = $this->getAdminAndStaffIds();

        // Cek apakah interaksi ini antara (customer) dg (owner/karyawan)
        $isCurrentAdmin = in_array($currentUser->id, $adminStaffIds);
        $isOtherAdmin   = in_array($otherUserId, $adminStaffIds);

        return Chat::query()
            ->with([
                'sender:id,name,profil',
                'receiver:id,name,profil',
            ])
            ->where(function ($q) use ($currentUser, $otherUserId, $adminStaffIds, $isCurrentAdmin, $isOtherAdmin) {

                if ($isCurrentAdmin && !$isOtherAdmin) {
                    $q->whereIn('sender_id', $adminStaffIds)->where('receiver_id', $otherUserId);
                } elseif (!$isCurrentAdmin && $isOtherAdmin) {
                    $q->where('sender_id', $currentUser->id)->whereIn('receiver_id', $adminStaffIds);
                } else {
                    $q->where('sender_id', $currentUser->id)->where('receiver_id', $otherUserId);
                }
            })
            ->orWhere(function ($q) use ($currentUser, $otherUserId, $adminStaffIds, $isCurrentAdmin, $isOtherAdmin) {
                if ($isCurrentAdmin && !$isOtherAdmin) {
                    $q->where('sender_id', $otherUserId)->whereIn('receiver_id', $adminStaffIds);
                } elseif (!$isCurrentAdmin && $isOtherAdmin) {
                    $q->whereIn('sender_id', $adminStaffIds)->where('receiver_id', $currentUser->id);
                } else {
                    $q->where('sender_id', $otherUserId)->where('receiver_id', $currentUser->id);
                }
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'required|string|max:5000',
        ]);

        $sender = auth()->user();
        $adminStaffIds = $this->getAdminAndStaffIds();

        $chat = Chat::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $data['receiver_id'],
            'message'     => $data['message'],
            'is_read'     => 0,
        ]);

        if (!in_array($sender->id, $adminStaffIds)) {
            $recipients = User::whereIn('id', $adminStaffIds)->get();
            foreach ($recipients as $recipient) {
                NotificationHelper::send(
                    $recipient,
                    'Pesan Baru dari Customer',
                    $sender->name . ' : ' . $data['message'],
                    'chat',
                    [
                        'reference_id' => $sender->id,
                        'sender_id'    => $sender->id,
                        'sender_name'  => $sender->name,
                        'receiver_id'  => $recipient->id,
                        'message'      => $data['message'],
                    ]
                );
            }
        } else {
            $receiver = User::find($data['receiver_id']);
            if ($receiver) {
                NotificationHelper::send(
                    $receiver,
                    'Pesan Baru',
                    $sender->name . ' : ' . $data['message'],
                    'chat',
                    [
                        'reference_id' => $sender->id,
                        'sender_id'    => $sender->id,
                        'sender_name'  => $sender->name,
                        'receiver_id'  => $receiver->id,
                        'message'      => $data['message'],
                    ]
                );
            }
        }

        return response()->json($chat);
    }

    public function chatRooms()
    {
        $currentUser = auth()->user();
        $adminStaffIds = $this->getAdminAndStaffIds();
        $isAdminOrStaff = in_array($currentUser->id, $adminStaffIds);

        if ($isAdminOrStaff) {
            $customerIds = Chat::selectRaw("
                    CASE
                        WHEN sender_id IN (" . implode(',', $adminStaffIds) . ") THEN receiver_id
                        ELSE sender_id
                    END as user_id
                ")
                ->whereIn('sender_id', $adminStaffIds)
                ->orWhereIn('receiver_id', $adminStaffIds)
                ->groupBy('user_id')
                ->pluck('user_id')
                ->reject(fn($id) => in_array($id, $adminStaffIds));

            $result = [];

            foreach ($customerIds as $userId) {
                $user = User::find($userId);
                if (!$user) continue;

                $lastMessage = Chat::where(function ($q) use ($adminStaffIds, $userId) {
                    $q->whereIn('sender_id', $adminStaffIds)->where('receiver_id', $userId);
                })
                    ->orWhere(function ($q) use ($adminStaffIds, $userId) {
                        $q->where('sender_id', $userId)->whereIn('receiver_id', $adminStaffIds);
                    })
                    ->latest()
                    ->first();

                $unread = Chat::where('sender_id', $userId)
                    ->whereIn('receiver_id', $adminStaffIds)
                    ->where('is_read', 0)
                    ->count();

                $result[] = [
                    'receiver_id'       => $userId,
                    'customer_name'     => $user->name,
                    'last_message'      => $lastMessage->message ?? '',
                    'last_message_time' => $lastMessage->created_at ?? null,
                    'unread'            => $unread,
                ];
            }
        } else {
            $users = Chat::selectRaw("
                    CASE
                        WHEN sender_id = ? THEN receiver_id
                        ELSE sender_id
                    END as user_id
                ", [$currentUser->id])
                ->where('sender_id', $currentUser->id)
                ->orWhere('receiver_id', $currentUser->id)
                ->groupBy('user_id')
                ->pluck('user_id');

            $result = [];

            foreach ($users as $userId) {
                $user = User::find($userId);
                if (!$user) continue;

                $lastMessage = Chat::where(function ($q) use ($currentUser, $userId) {
                    $q->where('sender_id', $currentUser->id)->where('receiver_id', $userId);
                })
                    ->orWhere(function ($q) use ($currentUser, $userId) {
                        $q->where('sender_id', $userId)->where('receiver_id', $currentUser->id);
                    })
                    ->latest()
                    ->first();

                $unread = Chat::where('sender_id', $userId)
                    ->where('receiver_id', $currentUser->id)
                    ->where('is_read', 0)
                    ->count();

                $result[] = [
                    'receiver_id'       => $userId,
                    'customer_name'     => $user->name,
                    'last_message'      => $lastMessage->message ?? '',
                    'last_message_time' => $lastMessage->created_at ?? null,
                    'unread'            => $unread,
                ];
            }
        }

        usort($result, function ($a, $b) {
            return strtotime($b['last_message_time']) <=> strtotime($a['last_message_time']);
        });

        return response()->json([
            'data'         => $result,
            'unread_count' => array_sum(array_column($result, 'unread')),
        ]);
    }

    public function markAsRead($senderId)
    {
        $currentUser = auth()->user();
        $adminStaffIds = $this->getAdminAndStaffIds();

        $query = Chat::where('sender_id', $senderId);

        if (in_array($currentUser->id, $adminStaffIds)) {
            $query->whereIn('receiver_id', $adminStaffIds);
        } else {
            $query->where('receiver_id', $currentUser->id);
        }

        $query->update(['is_read' => 1]);

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
