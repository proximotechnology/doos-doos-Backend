<?php

namespace App\Http\Controllers;

use App\Models\Representative;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RepresentativeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $representatives = Representative::with('user')->get();

        return response()->json([
            'status' => true,
            'data' => $representatives
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'country' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'country' => $request->country,
                'phone' => $request->phone,
                'type' => 2, // Assuming 2 is the type for representatives
                'email_verified_at' => now() // Mark as verified immediately
            ]);

            // Create the representative
            $representative = Representative::create([
                'user_id' => $user->id,
            ]);

            // Trigger email verification event (even though we're verifying immediately)

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Representative created successfully',
                'data' => $representative->load('user')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to create representative',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $representative = Representative::with('user')->find($id);

        if (!$representative) {
            return response()->json([
                'status' => false,
                'message' => 'Representative not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $representative
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Representative $representative)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, $id)
{
    $representative = Representative::find($id);

    if (!$representative) {
        return response()->json([
            'status' => false,
            'message' => 'Representative not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|string|email|max:255|unique:users,email,'.$representative->user_id,
        'password' => 'sometimes|string|min:8',
        'country' => 'nullable|string|max:255',
        'phone' => 'sometimes|string|max:20',
        'status' => 'sometimes|string|in:active,pending,banned' // Updated validation
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        // Update user
        $user = $representative->user;
        $userData = [];

        if ($request->has('name')) $userData['name'] = $request->name;
        if ($request->has('email')) $userData['email'] = $request->email;
        if ($request->has('password')) $userData['password'] = Hash::make($request->password);
        if ($request->has('country')) $userData['country'] = $request->country;
        if ($request->has('phone')) $userData['phone'] = $request->phone;

        if (!empty($userData)) {
            $user->update($userData);
        }

        // Update representative
        $representativeData = [];
        if ($request->has('status')) {
            $representativeData['status'] = $request->status;

            // Additional logic based on status
            if ($request->status == 'banned') {
                // Example: Revoke tokens if banned
                $user->tokens()->delete();
            }
        }

        if (!empty($representativeData)) {
            $representative->update($representativeData);
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Representative updated successfully',
            'data' => $representative->fresh()->load('user')
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'status' => false,
            'message' => 'Failed to update representative',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Remove the specified resource from storage.
     */
  public function destroy($id)
    {
        $representative = Representative::find($id);

        if (!$representative) {
            return response()->json([
                'status' => false,
                'message' => 'Representative not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $user = $representative->user;
            $representative->delete();
            $user->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Representative deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete representative',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
