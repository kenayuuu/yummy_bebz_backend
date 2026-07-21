<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        Log::info('MASUK MENU CONTROLLER');

        $menus = Menu::withAvg('ratings', 'rating')
            ->where('status', 'available')
            ->latest()
            ->get();

        $menus->each(function ($menu) {
            $menu->rating = round($menu->ratings_avg_rating ?? 0, 1);
        });

        foreach ($menus as $menu) {
            if ($menu->gambar && !str_starts_with($menu->gambar, 'http')) {
                $menu->gambar = asset('storage/' . $menu->gambar);
            }
        }

        return response()->json($menus);
    }

    public function store(Request $request)
    {
        if ($request->has('waktu_mulai') && $request->waktu_mulai) {
            $request->merge([
                'waktu_mulai' => substr($request->waktu_mulai, 0, 5)
            ]);
        }
        if ($request->has('waktu_selesai') && $request->waktu_selesai) {
            $request->merge([
                'waktu_selesai' => substr($request->waktu_selesai, 0, 5)
            ]);
        }

        $validated = $request->validate([
            'nama_menu' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'harga_modal' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tanggal' => ['nullable'],
            'waktu_mulai' => ['nullable', 'date_format:H:i'],
            'waktu_selesai' => ['nullable', 'date_format:H:i'],
            // 'waktu_mulai' => ['nullable'],
            // 'waktu_selesai' => ['nullable'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $validated['keuntungan'] =
            $validated['harga_jual'] - $validated['harga_modal'];

        if ($request->hasFile('gambar')) {
            $path = $request->file('gambar')->store('menus', 'public');
            $validated['gambar'] = $path;
        }

        $menu = Menu::create($validated);

        if ($menu->gambar) {
            $menu->gambar = asset('storage/' . $menu->gambar);
        }

        return response()->json([
            'message' => 'Menu berhasil ditambahkan.',
            'data' => $menu,
        ], 201);
    }

    public function show(Menu $menu)
    {
        if ($menu->gambar) {
            $menu->gambar = asset('storage/' . $menu->gambar);
        }

        return response()->json($menu);
    }

    public function update(Request $request, Menu $menu)
    {
        if ($request->has('waktu_mulai') && $request->waktu_mulai) {
            $request->merge([
                'waktu_mulai' => substr($request->waktu_mulai, 0, 5)
            ]);
        }
        if ($request->has('waktu_selesai') && $request->waktu_selesai) {
            $request->merge([
                'waktu_selesai' => substr($request->waktu_selesai, 0, 5)
            ]);
        }

        $validated = $request->validate([
            'nama_menu' => ['sometimes', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'harga_modal' => ['sometimes', 'numeric', 'min:0'],
            'harga_jual' => ['sometimes', 'numeric', 'min:0'],
            'gambar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tanggal' => ['nullable'],
            'waktu_mulai' => ['nullable', 'date_format:H:i'],
            'waktu_selesai' => ['nullable', 'date_format:H:i'],
            // 'waktu_mulai' => ['nullable'],
            // 'waktu_selesai' => ['nullable'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        $hargaModal = $validated['harga_modal'] ?? $menu->harga_modal;
        $hargaJual = $validated['harga_jual'] ?? $menu->harga_jual;

        $validated['keuntungan'] = $hargaJual - $hargaModal;

        if ($request->hasFile('gambar')) {

            if ($menu->gambar) {
                $oldPath = str_replace(asset('storage') . '/', '', $menu->gambar);

                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('gambar')->store('menus', 'public');

            $validated['gambar'] = $path;
        }

        $menu->update($validated);

        if ($menu->gambar) {
            $menu->gambar = asset('storage/' . $menu->gambar);
        }

        return response()->json([
            'message' => 'Menu berhasil diperbarui.',
            'data' => $menu,
        ]);
    }

    public function destroy(Menu $menu)
    {
        // getRawOriginal agar path asli yang dihapus, bukan URL asset()
        if ($menu->getRawOriginal('gambar')) {
            Storage::disk('public')->delete($menu->getRawOriginal('gambar'));
        }

        $menu->delete();

        return response()->json([
            'message' => 'Menu berhasil dihapus.',
        ]);
    }
}
