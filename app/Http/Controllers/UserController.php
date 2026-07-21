<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Mendapatkan semua daftar user untuk kelola user oleh Owner.
     */
    public function index(Request $request)
    {
        // Proteksi: Pastikan hanya Owner yang bisa mengakses
        if ($request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'Akses ditolak. Hanya Owner yang diperbolehkan.'
            ], 403);
        }

        // Ambil semua user kecuali akun Owner itu sendiri (agar Owner tidak sengaja mengubah rolenya sendiri)
        $users = User::where('id', '!=', $request->user()->id)
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Mengubah role user (Misal: customer -> karyawan).
     */
    public function updateRole(Request $request, $id)
    {
        // Proteksi: Pastikan hanya Owner yang bisa mengubah role
        if ($request->user()->role !== 'owner') {
            return response()->json([
                'message' => 'Akses ditolak. Hanya Owner yang diperbolehkan.'
            ], 403);
        }

        // Validasi pilihan role yang diizinkan
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
