<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\OpenRouterService;

class DeepSeekController extends Controller
{ protected $openRouter;

    public function __construct(OpenRouterService $openRouter)
    {
        $this->openRouter = $openRouter;
    }

    public function chat(Request $request)
    {
        $messages = [
            [
                'role' => 'user',
                'content' => $request->input('message')
            ]
        ];

        $response = $this->openRouter->chat($messages);

        return response()->json([
            'reply' => $response['choices'][0]['message']['content']
        ]);
    }
}
