<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:20'],
            'profil' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($request->hasFile('profil')) {

            if (
                $user->profil &&
                Storage::disk('public')->exists(str_replace('storage/', '', $user->profil))
            ) {

                Storage::disk('public')->delete(
                    str_replace('storage/', '', $user->profil)
                );
            }

            $path = $request->file('profil')->store('profile', 'public');

            $validated['profil'] = asset('storage/' . $path);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();

        $user->update([
            'fcm_token' => $request->fcm_token
        ]);

        return response()->json([
            'message' => 'FCM token saved successfully',
        ]);
    }

    public function getPublicProfile($id)
    {
        // Cari user berdasarkan id
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'profil' => $user->profil, // Url foto profil owner dari database
        ], 200);
    }
}
