<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //  
        // dd(auth()->user()->roles);
        if (auth('sanctum')->user()->can('Read-Admins')) {
            $admins = User::where('type', '=', 1)->get()->map(function ($admin) {
                return [
                    'id' => $admin->id ?? '',
                    'role' => $admin->roles[0]->name ?? '',
                    'name' => $admin->name ?? '',
                    'email' => $admin->email ?? '',
                    'phone' => $admin->phone ?? '',
                    'country' => $admin->country ?? '',
                    'email_verified_at' => $admin->email_verified_at ? true : false,
                    'created_at' => Carbon::parse($admin->created_at)->format('Y-m-d') ?? '',
                ];
            });
            return response()->json(['status' => true, 'message' => 'Successfully get', 'data' => $admins], Response::HTTP_OK);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        if (auth('sanctum')->user()->can('Create-Admin')) {
            $validator = Validator($request->all(), [
                'role_id' => 'required|integer|exists:roles,id',
                'name' => 'required|string|min:3',
                'email' => 'required|string|email|unique:users,email',
                'phone' => 'required|string|numeric|unique:users,phone',
                'country' => 'required|string|min:3',
            ]);

            if (!$validator->fails()) {
                $admin = new User();
                $admin->name = $request->get('name');
                $admin->email = $request->get('email');
                $admin->phone = $request->get('phone');
                $admin->country = $request->get('country');
                $password = Str::random(12);
                $admin->password = Hash::make($password);
                $admin->type = 1;
                $isSaved = $admin->save();
                if ($isSaved) {
                    $role = Role::findOrFail($request->role_id);
                    $role->guard_name = 'sanctum';
                    $role->save();
                    $permissions = $role->permissions;
                    foreach ($permissions as $permission) {
                        $permission->guard_name = 'sanctum';
                        $permission->save();
                    }
                    $admin->assignRole($role);
                    $admin->givePermissionTo($permissions);

                    Mail::to($admin->email)->send(new OTPMail($password, 'test'));
                }
                return response()->json(['message' => $isSaved ? 'Created successfully.' : 'Creation failed.'], $isSaved ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST);
            } else {
                return response()->json(['status' => true, 'message' => $validator->getMessageBag()->first()], Response::HTTP_BAD_REQUEST);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        if (auth('sanctum')->user()->can('Update-Admin')) {
            dd($user);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
