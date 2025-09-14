<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionController extends Controller
{
    //
    public function index()
    {
        if (auth('sanctum')->user()->can('Read-Roles')) {
            $roles = Role::withCount('permissions')->get()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions_count,
                    'created_at' => Carbon::parse($role->created_at)->format('Y-m-d'),
                ];
            });
            return response()->json([
                'status' => true,
                'message' => 'Successfully send',
                'data' => $roles,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function store(Request $request)
    {
        if (auth('sanctum')->user()->can('Create-Role')) {
            $validator = Validator($request->all(), [
                'guard_name' => 'required|string|in:sanctum',
                'name' => 'required|string|min:2|max:40|unique:roles,name',
            ]);

            if (!$validator->fails()) {
                $role = new Role();
                $role->name = $request->input('name');
                $role->guard_name = $request->input('guard_name');
                $isSaved = $role->save();
                return response()->json(['message' => $isSaved ? 'Created successfully' : 'Creation failed, please try again'], $isSaved ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST);
            } else {
                return response()->json(['message' => $validator->getMessageBag()->first()], Response::HTTP_BAD_REQUEST);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function update(Request $request, $id)
    {
        if (auth('sanctum')->user()->can('Update-Role')) {
            $validator = Validator($request->all(), [
                'name' => 'required|string|min:2|max:40',
                'guard_name' => 'required|string|in:sanctum',
            ]);

            if (!$validator->fails()) {
                $role = Role::findOrFail($id);
                $role->name = $request->get('name');
                $role->guard_name = $request->get('guard_name');
                $isSaved = $role->save();
                return response()->json(['message' => $isSaved ? 'Updated successfully' : 'Update failed, please try again'], $isSaved ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
            } else {
                return response()->json(['message' => $validator->getMessageBag()->first()], Response::HTTP_BAD_REQUEST);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function destroy($id)
    {
        if (auth('sanctum')->user()->can('Delete-Role')) {
            $isDeleted = DB::delete('DELETE FROM roles WHERE id = ?', [$id]);

            return response()->json(
                [
                    'message' => $isDeleted ? 'Deleted successfully' : 'Delete failed, please try again'
                ],
                $isDeleted ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
            );
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function indexPermissions()
    {
        if (auth('sanctum')->user()->can('Read-Permissions')) {
            $permissions = Permission::all()->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'created_at' => Carbon::parse($permission->created_at)->format('Y-m-d'),
                ];
            });
            return response()->json([
                'status' => true,
                'message' => 'Successfully send',
                'data' => $permissions,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function show($id)
    {
        if (auth('sanctum')->user()->can('Read-Roles')) {
            $role = Role::findOrFail($id);
            $rolePermissions = $role->permissions;
            $permissions = Permission::where('guard_name', '=', $role->guard_name)->get();
            foreach ($permissions as $permission) {
                $permission->setAttribute('granted', false);
                foreach ($rolePermissions as $rolePermission) {
                    if ($rolePermission->id == $permission->id) {
                        $permission->setAttribute('granted', true);
                        break;
                    }
                }
            }
            return response()->json([
                'status' => true,
                'message' => 'Successfully send',
                'role' => $role,
                'permissions' => $permissions
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function permissionsRole(Request $request)
    {
        $validator = Validator($request->all(), [
            'role_id' => 'required|integer|exists:roles,id',
            'permission_id' => 'required|integer|exists:permissions,id',
        ]);
        if (!$validator->fails()) {
            $role = Role::findOrFail($request->get('role_id'));
            $permission = Permission::findOrFail($request->get('permission_id'));
            if ($role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
                return response()->json(['message' => 'You do not have permission to delete this item'], Response::HTTP_OK);
            } else {
                $role->givePermissionTo($permission);
                return response()->json(['message' => 'Permission granted successfully'], Response::HTTP_OK);
            }
        } else {
            return response()->json(['message' => $validator->getMessageBag()->first()], Response::HTTP_BAD_REQUEST);
        }
    }

    
}
