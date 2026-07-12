<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index()
    {
        $ratings = Rating::with(['user', 'menu'])
            ->latest()
            ->get();

        return response()->json($ratings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => ['required', 'exists:menus,id'],
            'transaction_id' => ['required', 'exists:transactions,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'komentar' => ['nullable', 'string'],
        ]);

        $rating = Rating::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Rating berhasil ditambahkan.',
            'data' => $rating->load(['user', 'menu']),
        ], 201);
    }

    public function show(Rating $rating)
    {
        return response()->json($rating->load(['user', 'menu']));
    }

    public function update(Request $request, Rating $rating)
    {
        if ($rating->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Rating tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'komentar' => ['sometimes', 'nullable', 'string'],
        ]);

        $rating->update($validated);

        return response()->json([
            'message' => 'Rating berhasil diperbarui.',
            'data' => $rating->fresh(),
        ]);
    }

    public function destroy(Request $request, Rating $rating)
    {
        if ($request->user()->role === 'customer' && $rating->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Rating tidak ditemukan.'], 404);
        }

        $rating->delete();

        return response()->json([
            'message' => 'Rating berhasil dihapus.',
        ]);
    }
}
