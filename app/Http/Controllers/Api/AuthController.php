<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Mail\WelcomeMail;
use App\Mail\TeamMemberInvite;
use App\Mail\PasswordChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'account_type' => 'required|in:buyer,seller',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => 'required|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',
            'country_code' => 'nullable|string|max:3',
            'vat_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'duns_number' => 'nullable|string|max:50',
            'location' => 'required|string|max:50',
            'terms_accepted' => 'required|accepted',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $company = null;
            if (!empty($validated['company_name'])) {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'address' => $validated['company_address'] ?? null,
                    'city' => $validated['city'],
                    'zip_code' => $validated['zip_code'] ?? null,
                    'country' => $validated['country'],
                    'country_code' => $validated['country_code'] ?? null,
                    'vat_number' => $validated['vat_number'] ?? null,
                    'registration_number' => $validated['registration_number'] ?? null,
                    'duns_number' => $validated['duns_number'] ?? null,
                    'is_active' => true,
                ]);
            }

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account_type' => $validated['account_type'],
                'role' => 'user',
                'phone' => $validated['phone'],
                'company_id' => $company?->id,
                'is_company_owner' => true,
                'company_name' => $validated['company_name'] ?? null,
                'company_address' => $validated['company_address'] ?? null,
                'city' => $validated['city'],
                'zip_code' => $validated['zip_code'] ?? null,
                'country' => $validated['country'],
                'country_code' => $validated['country_code'] ?? null,
                'vat_number' => $validated['vat_number'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'duns_number' => $validated['duns_number'] ?? null,
                'location' => $validated['location'],
                'terms_accepted_at' => now(),
                'is_active' => true,
                'is_verified' => false,
                'approved_at' => null,
            ]);
        });

        // Send welcome email
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            \Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Registration successful. Please wait for admin approval.',
            'user' => $user,
            'requires_approval' => true,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Pogrešni pristupni podaci.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Vaš nalog je deaktiviran.'],
            ]);
        }

        if ($user->role !== 'admin' && !$user->isApproved()) {
            throw ValidationException::withMessages([
                'email' => ['Vaš nalog čeka odobrenje. Obavestićemo vas putem emaila.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('company'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('company', 'teamMembers'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'city' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:50',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Trenutna lozinka nije ispravna.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Send password changed notification
        try {
            Mail::to($user->email)->send(new PasswordChanged($user));
        } catch (\Exception $e) {
            \Log::error('Failed to send password changed email: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function addTeamMember(Request $request)
    {
        $owner = $request->user();

        if (!$owner->isCompanyOwner()) {
            return response()->json(['message' => 'Only company owners can add team members'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['nullable', Password::min(8)],
        ]);

        // Generate temp password if not provided
        $tempPassword = $validated['password'] ?? Str::random(12);

        $member = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'account_type' => $owner->account_type,
            'role' => 'user',
            'company_id' => $owner->company_id,
            'is_company_owner' => false,
            'company_name' => $owner->company_name,
            'city' => $owner->city,
            'country' => $owner->country,
            'location' => $owner->location,
            'is_active' => true,
            'is_verified' => true,
            'terms_accepted_at' => now(),
            'approved_at' => now(),
            'approved_by' => $owner->id,
        ]);

        // Send team invite email
        try {
            Mail::to($member->email)->send(new TeamMemberInvite($member, $owner, $tempPassword));
        } catch (\Exception $e) {
            \Log::error('Failed to send team invite email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Team member added successfully',
            'user' => $member,
        ], 201);
    }

    public function getTeamMembers(Request $request)
    {
        $user = $request->user();

        if (!$user->company_id) {
            return response()->json(['data' => []]);
        }

        $members = User::where('company_id', $user->company_id)->get();

        return response()->json(['data' => $members]);
    }

    public function removeTeamMember(Request $request, User $member)
    {
        $owner = $request->user();

        if (!$owner->isCompanyOwner()) {
            return response()->json(['message' => 'Only company owners can remove team members'], 403);
        }

        if ($member->company_id !== $owner->company_id) {
            return response()->json(['message' => 'User is not in your company'], 403);
        }

        if ($member->is_company_owner) {
            return response()->json(['message' => 'Cannot remove company owner'], 403);
        }

        $member->delete();

        return response()->json(['message' => 'Team member removed successfully']);
    }

    public function updateCurrency(Request $request)
    {
        $validated = $request->validate([
            'currency' => 'required|string|in:EUR,USD,SOL',
        ]);

        $user = $request->user();
        $user->update(['preferred_currency' => $validated['currency']]);

        return response()->json([
            'message' => 'Valuta uspešno promenjena',
            'user' => $user->fresh(),
        ]);
    }

    public function updateWallet(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $user->update(['wallet_address' => $validated['wallet_address']]);

        return response()->json([
            'message' => 'Wallet adresa uspešno sačuvana',
            'user' => $user->fresh(),
        ]);
    }
}
