<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     *
     * @param User $receiver
     * @param string $title
     * @param string $message
     * @param string|null $type
     * @param array $data
     */
    public static function send(
        User $receiver,
        string $title,
        string $message,
        ?string $type = null,
        array $data = []
    ): void {
        Notification::create([
            'user_id' => $receiver->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'reference_id' => $data['reference_id'] ?? null,
            'is_read' => false,
        ]);

        if (empty($receiver->fcm_token)) {
            return;
        }

        try {

            $accessToken = FirebaseHelper::getAccessToken();

            $client = new Client([
                'timeout' => 30,
            ]);

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
                                'title' => $title,
                                'body' => $message,
                            ],

                            'data' => array_merge(
                                [
                                    'type' => $type ?? '',
                                ],
                                collect($data)
                                    ->map(fn($value) => (string)$value)
                                    ->toArray()
                            ),

                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => self::getChannelId($type),
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
                                            'title' => $title,
                                            'body' => $message,
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

            Log::error('FCM Notification Error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Menentukan channel notification.
     */
    private static function getChannelId(?string $type): string
    {
        return match ($type) {
            'chat' => 'chat_channel',
            'transaction' => 'transaction_channel',
            default => 'default_channel',
        };
    }
}
