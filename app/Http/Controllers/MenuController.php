<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::latest()->get();

        return response()->json($menus);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_menu' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'harga' => ['required', 'numeric', 'min:0'],
            'keuntungan' => ['required', 'numeric', 'min:0'],
            'gambar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tanggal' => ['nullable'],
            'waktu_mulai' => ['nullable'],
            'waktu_selesai' => ['nullable'],
            'status' => ['required', 'string', 'max:50'],
        ]);

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
        $validated = $request->validate([
            'nama_menu' => ['sometimes', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'harga' => ['sometimes', 'numeric', 'min:0'],
            'keuntungan' => ['sometimes', 'numeric', 'min:0'],
            'gambar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'tanggal' => ['nullable'],
            'waktu_mulai' => ['nullable'],
            'waktu_selesai' => ['nullable'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

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
        if ($menu->gambar) {

            $oldPath = str_replace(asset('storage') . '/', '', $menu->gambar);

            Storage::disk('public')->delete($oldPath);
        }

        $menu->delete();

        return response()->json([
            'message' => 'Menu berhasil dihapus.',
        ]);
    }
}
