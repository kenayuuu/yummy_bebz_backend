<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    public static function send(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): bool {

        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS'));

        $scopes = [
            'https://www.googleapis.com/auth/firebase.messaging',
        ];

        $credentials = new ServiceAccountCredentials(
            $scopes,
            $credentialsPath
        );

        $accessToken = $credentials->fetchAuthToken();

        if (!isset($accessToken['access_token'])) {
            Log::error('FCM gagal mendapatkan access token', $accessToken);
            return false;
        }

        $projectId = env('FIREBASE_PROJECT_ID');

        $response = Http::withToken($accessToken['access_token'])
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                [
                    'message' => [
                        'token' => $token,

                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],

                        'data' => collect($data)
                            ->map(fn($v) => (string) $v)
                            ->toArray(),

                        'android' => [
                            'priority' => 'HIGH',
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {

            Log::error('FCM Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
