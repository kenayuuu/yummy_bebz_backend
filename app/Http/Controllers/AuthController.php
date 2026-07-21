<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
        ]);

        return response()->json([
            'message' => 'Register berhasil.',
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak terdaftar.'
            ], 404);
        }

        // Generate OTP 6 digit
        $otp = random_int(100000, 999999);

        $user->reset_otp = $otp;
        $user->reset_otp_expired_at = Carbon::now()->addMinutes(5);
        $user->save();

        Mail::raw(
            "Kode OTP untuk reset password Yummy Bebz adalah:\n\n"
                . $otp .
                "\n\nKode ini berlaku selama 5 menit.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Kode OTP Reset Password');
            }
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim ke email.'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan.'
            ], 404);
        }

        if (!$user->reset_otp || !$user->reset_otp_expired_at) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak ditemukan.'
            ], 400);
        }

        if (now()->gt($user->reset_otp_expired_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP telah kedaluwarsa.'
            ], 400);
        }

        if ($request->otp !== $user->reset_otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP salah.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP valid.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan.'
            ], 404);
        }

        if (!$user->reset_otp || !$user->reset_otp_expired_at) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan minta OTP baru.'
            ], 400);
        }

        if (now()->gt($user->reset_otp_expired_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP telah kedaluwarsa.'
            ], 400);
        }

        if ($user->reset_otp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP salah.'
            ], 400);
        }

        $user->password = Hash::make($request->password);

        // Hapus OTP setelah password berhasil diubah
        $user->reset_otp = null;
        $user->reset_otp_expired_at = null;

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.'
        ]);
    }
}
