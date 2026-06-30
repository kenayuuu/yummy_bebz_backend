<?php

namespace App\Helpers;

use Google\Auth\Credentials\ServiceAccountCredentials;

class FirebaseHelper
{
    public static function getAccessToken()
    {
        $scopes = [
            'https://www.googleapis.com/auth/firebase.messaging'
        ];

        $credentials = new ServiceAccountCredentials(
            $scopes,
            storage_path('app/firebase/firebase-service-account.json')
        );

        $token = $credentials->fetchAuthToken();

        return $token['access_token'];
    }
}
