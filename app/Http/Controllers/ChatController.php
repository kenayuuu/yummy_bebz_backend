<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Fitur chat tersedia sebagai placeholder API.',
            'data' => [],
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        return response()->json([
            'message' => 'Pesan berhasil diterima.',
            'data' => [
                'sender' => $request->user()->name,
                'text' => $validated['message'],
                'sent_at' => now()->toDateTimeString(),
            ],
        ], 201);
    }
}
