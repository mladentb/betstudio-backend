<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminCompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::withCount('users');
        
        // Filter seller companies (admin's invoicing companies - without owner from user registration)
        if ($request->get('type') === 'seller') {
            // Seller companies are those created by admin (not linked to user registration)
            // They have owner_id set to an admin user
            $query->whereNotNull('owner_id');
        }
        
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        return response()->json([
            'data' => $query->orderBy('is_default', 'desc')->orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'swift_bic' => 'nullable|string|max:20',
            'continents' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->is_default) {
            Company::where('is_default', true)->update(['is_default' => false]);
        }

        $validated['owner_id'] = $request->user()->id;
        $validated['continents'] = json_encode($validated['continents'] ?? []);
        $company = Company::create($validated);

        return response()->json([
            'message' => 'Company created',
            'data' => $company
        ], 201);
    }

    public function show(Company $company)
    {
        return response()->json(['data' => $company]);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'swift_bic' => 'nullable|string|max:20',
            'continents' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->is_default && !$company->is_default) {
            Company::where('is_default', true)->update(['is_default' => false]);
        }

        if (isset($validated['continents'])) {
            $validated['continents'] = json_encode($validated['continents']);
        }

        $company->update($validated);

        return response()->json([
            'message' => 'Company updated',
            'data' => $company->fresh()
        ]);
    }

    public function uploadLogo(Request $request, Company $company)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Delete old logo if exists
        if ($company->logo_url) {
            $oldPath = str_replace('/storage/', '', $company->logo_url);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new logo
        $path = $request->file('logo')->store('logos', 'public');
        $company->update(['logo_url' => '/storage/' . $path]);

        return response()->json([
            'message' => 'Logo uploaded',
            'logo_url' => '/storage/' . $path,
            'data' => $company->fresh()
        ]);
    }

    public function deleteLogo(Company $company)
    {
        if ($company->logo_url) {
            $path = str_replace('/storage/', '', $company->logo_url);
            Storage::disk('public')->delete($path);
            $company->update(['logo_url' => null]);
        }

        return response()->json([
            'message' => 'Logo deleted',
            'data' => $company->fresh()
        ]);
    }

    public function destroy(Company $company)
    {
        if ($company->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete company with users'
            ], 400);
        }

        // Delete logo file
        if ($company->logo_url) {
            $path = str_replace('/storage/', '', $company->logo_url);
            Storage::disk('public')->delete($path);
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted']);
    }

    /**
     * Get users belonging to a company
     */
    public function users(Company $company)
    {
        $users = $company->users()
            ->select('id', 'name', 'email', 'phone', 'role', 'is_active', 'is_verified', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $users,
            'count' => $users->count()
        ]);
    }
}
