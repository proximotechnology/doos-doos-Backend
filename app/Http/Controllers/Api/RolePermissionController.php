<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionController extends Controller
{
    //
    public function index()
    {
        // if (auth('web')->user()->can('Read-Roles')) {
        $roles = Role::withCount('permissions')->get();
        return response()->json([
            'status' => true,
            'message' => 'Successfully send',
            'data' => $roles,
        ]);
        // } else {
        //     return abort(401);
        // }
    }


    public function store(Request $request)
    {
        // if (auth('admin')->user()->can('Create-Role')) {
        $validator = Validator($request->all(), [
            'guard_name' => 'required|string|in:user',
            'name' => 'required|string|min:3|max:40',
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
        // } else {
        //     return abort(401);
        // }
    }

    public function update(Request $request, $id)
    {
        // if (auth('admin')->user()->can('Update-Role')) {
        $validator = Validator($request->all(), [
            'name' => 'required|string|min:3|max:40',
            'guard_name' => 'required|string|in:user',
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
        // } else {
        //     return abort(401);
        // }
    }


    public function destroy($id)
    {
        $isDeleted = DB::delete('DELETE FROM roles WHERE id = ?', [$id]);

        return response()->json(
            [
                'message' => $isDeleted ? 'Deleted successfully' : 'Delete failed, please try again'
            ],
            $isDeleted ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }

    public function indexPermissions()
    {
        // if (auth('admin')->user()->can('Read-Permissions')) {
        $permissions = Permission::all();
        return response()->json([
            'status' => true,
            'message' => 'Successfully send',
            'data' => $permissions,
        ]);        // } else {
        //     return abort(401);
        // }
    }


    public function show($id)
    {
        // if (auth('admin')->user()->can('Read-Roles')) {
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
        // } else {
        //     return abort(401);
        // }
    }

    public function permissionsRole(Request $request)
    {
        $validator = Validator($request->all(), [
            'role_id' => 'required|integer|exists:roles,id',
            'permission_id' => 'required|integer|exists:permissions,id',
        ]);
        if (!$validator->fails()) {
            $role =  Role::findOrFail($request->get('role_id'));
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
