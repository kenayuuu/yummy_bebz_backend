<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'Akses ditolak. Hanya Owner yang diperbolehkan.'
            ], 403);
        }

        $users = User::where('id', '!=', $request->user()->id)
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function updateRole(Request $request, $id)
    {
        // hanya Owner yang bisa mengubah role
        if ($request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'Akses ditolak. Hanya Owner yang diperbolehkan.'
            ], 403);
        }

        $request->validate([
            'role' => 'required|in:customer,karyawan,owner',
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        // Ubah role user
        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Role user berhasil diperbarui.',
            'user' => $user
        ]);
    }
}
