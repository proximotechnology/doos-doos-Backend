<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                    // $role = Role::findOrFail($request->role_id);
                    // $role->guard_name = 'sanctum';
                    // $role->save();
                    // $permissions = $role->permissions;
                    // foreach ($permissions as $permission) {
                    //     $permission->guard_name = 'sanctum';
                    //     $permission->save();
                    // }
                    // $admin->assignRole($role);
                    // $admin->givePermissionTo($permissions);
                    $admin->assignRole(Role::findOrFail($request->input('role_id')));
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
            $validator = Validator($request->all(), [
                'role_id' => 'required|integer|exists:roles,id',
                'name' => 'required|string|min:3',
                'email' => 'required|string|email|unique:users,email,' . $user->id,
                'phone' => 'required|string|numeric|unique:users,phone,' . $user->id,
                'country' => 'required|string|min:3',
            ]);

            if (!$validator->fails()) {
                $user->name = $request->get('name');
                $user->email = $request->get('email');
                $user->phone = $request->get('phone');
                $user->country = $request->get('country');
                $user->type = 1;
                $user->syncRoles(Role::findOrFail($request->get('role_id')));
                $isSaved = $user->save();
                return response()->json(['message' => $isSaved ? 'Update successfully.' : 'Update failed.'], $isSaved ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST);
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
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // تحقق من الصلاحية أولاً
        if (!auth('sanctum')->user()->can('Delete-Admin')) {
            return response()->json([
                'message' => 'Sorry, you do not have permission to access this page.'
            ], Response::HTTP_FORBIDDEN);
        }

        // منع حذف المشرف الرئيسي (يفضل باستخدام role أو flag بدل ID ثابت)
        if ($user->id == 3 || $user->type === 1) {
            return response()->json([
                'message' => 'The main supervisor cannot be deleted.'
            ], Response::HTTP_FORBIDDEN);
        }

        // تنفيذ الحذف
        $isDeleted = $user->delete();

        return response()->json([
            'message' => $isDeleted
                ? 'The deletion process was completed successfully.'
                : 'Deletion failed'
        ], $isDeleted ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }



    public function allRoles()
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user || !$user->can('Create-Admin')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }

        $roles = Role::withCount('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
            ];
        });
        return response()->json([
            'status' => true,
            'message' => 'Successfully fetched roles',
            'data' => $roles,
        ], Response::HTTP_OK);
    }
}
