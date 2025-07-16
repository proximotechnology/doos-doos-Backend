<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    /**
     * Get authenticated user's company info
     */
    public function getMyCompany()
    {
        $user = auth()->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'Company record not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $company
        ]);
    }

    /**
     * Store a new company record
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // Check if user already has a company
        if ($user->company) {
            return response()->json([
                'status' => false,
                'message' => 'You already have a company record'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'legal_name' => 'required|string|max:255',
            'num_of_employees' => 'required|integer',
            'is_under_vat' => 'required|boolean',
            'vat_num' => 'required_if:is_under_vat,true|string|max:255',
            'zip_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'address_1' => 'required|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $company = $user->company()->create($request->all());
        $user->update(['is_company' => 1]);

        return response()->json([
            'status' => true,
            'message' => 'Company created successfully',
            'data' => $company
        ], 201);
    }

    /**
     * Update authenticated user's company info
     */
    public function updateMyCompany(Request $request)
    {
        $user = auth()->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'Company record not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'legal_name' => 'sometimes|string|max:255',
            'num_of_employees' => 'sometimes|integer',
            'is_under_vat' => 'sometimes|boolean',
            'vat_num' => 'required_if:is_under_vat,true|string|max:255',
            'zip_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:100',
            'address_1' => 'sometimes|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $company->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Company updated successfully',
            'data' => $company
        ]);
    }

    // ... keep other existing methods ...
}
