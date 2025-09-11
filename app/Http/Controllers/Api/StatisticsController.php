<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller
{
    //
    public function indexUsersStatus(Request $request)
    {
        $data = [
            'active_users'   => User::whereNotNull('email_verified_at')->count(),
            'inactive_users' => User::whereNull('email_verified_at')->count(),
            'total_users'    => User::count(),
        ];

        return response()->json([
            'status'  => true,
            'message' => 'Successfully retrieved users statistics',
            'data'    => $data
        ], Response::HTTP_OK);
    }
}
