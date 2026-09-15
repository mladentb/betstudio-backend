<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\AccountApproved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('company');

        if ($request->get('status') === 'pending') {
            $query->whereNull('approved_at')->where('is_active', true);
        } elseif ($request->get('status') === 'approved') {
            $query->whereNotNull('approved_at');
        } elseif ($request->get('status') === 'rejected') {
            $query->where('is_active', false);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 20);
        
        if ($perPage == 0) {
            return response()->json(['data' => $query->get()]);
        }

        return $query->paginate($perPage);
    }

    public function show(User $user)
    {
        return response()->json([
            'data' => $user->load('company', 'approvedBy'),
        ]);
    }

    public function approve(Request $request, User $user)
    {
        if ($user->isApproved()) {
            return response()->json(['message' => 'User is already approved'], 400);
        }

        $user->update([
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'is_verified' => true,
        ]);

        try {
            Mail::to($user->email)->send(new AccountApproved($user));
        } catch (\Exception $e) {
            \Log::error('Failed to send approval email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'User approved successfully',
            'data' => $user->fresh(),
        ]);
    }

    public function reject(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User rejected',
            'data' => $user->fresh(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'role' => 'sometimes|in:admin,user',
            'account_type' => 'sometimes|in:buyer,seller',
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
            // Company info
            'company_name' => 'sometimes|nullable|string|max:255',
            'company_address' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'zip_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',
            'vat_number' => 'sometimes|nullable|string|max:50',
            'registration_number' => 'sometimes|nullable|string|max:50',
            'duns_number' => 'sometimes|nullable|string|max:50',
            'continent' => 'sometimes|nullable|string|max:50',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot delete admin users'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function pendingCount()
    {
        $count = User::whereNull('approved_at')
            ->where('is_active', true)
            ->where('role', '!=', 'admin')
            ->count();

        return response()->json(['count' => $count]);
    }
}
