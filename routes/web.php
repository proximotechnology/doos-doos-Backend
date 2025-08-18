<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentPlanController;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/pusher', function () {
    return view('pusher_react');
});

Route::get('api/payment/success', [PaymentPlanController::class, 'success'])->name('payment.success');
Route::get('api/payment/cancel', [PaymentPlanController::class, 'cancel'])->name('payment.cancel');



Route::get('/pusherprivate', function () {
    return view('pusher_private');
});


























































/*Route::get('/test-montypay', function () {
    // 1. جلب بيانات الاعتماد من ملف .env
    $merchantKey = env('MONTYPAY_MERCHANT_KEY');
    $merchantPass = env('MONTYPAY_MERCHANT_PASSWORD');
    $apiEndpoint = env('MONTYPAY_API_ENDPOINT', 'https://checkout.montypay.com/api/v1/session');

    // 2. التحقق من وجود بيانات الاعتماد
    if (empty($merchantKey) || empty($merchantPass)) {
        return response()->json([
            'success' => false,
            'message' => 'بيانات الاعتماد غير مكتملة في ملف .env'
        ], 400);
    }

    // 3. إعداد بيانات الطلب الثابتة للتجربة
    $orderData = [
        'number' => 'ORDER-' . time() . '-' . rand(1000, 9999),
        'amount' => '10.00', // يجب أن يكون بتنسيق XX.XX
        'currency' => 'USD',
        'description' => 'Test Payment'
    ];

    // 4. إنشاء الهاش حسب المواصفات الدقيقة
    // الترتيب الصحيح حسب الوثائق: merchant_key + order_number + order_amount + order_currency + order_description + merchant_pass
    $hashString = $merchantKey . $orderData['number'] . $orderData['amount'] .
                $orderData['currency'] . $orderData['description'] . $merchantPass;
        // 5. توليد الهاش حسب المتطلبات
    $md5Hash = md5($hashString);
    $generatedHash = strtoupper(sha1($md5Hash));

    // 6. بناء payload الطلب
    $payload = [
        'merchant_key' => $merchantKey,
        'operation' => 'purchase',
        'order' => [
            'number' => $orderData['number'],
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'description' => $orderData['description']
        ],
        'customer' => [
            'email' => 'test@example.com',
            'name' => 'Test Customer'
        ],
        'success_url' => url('/payment/success'),
        'cancel_url' => url('/payment/cancel'),
        'callback_url' => url('/payment/callback'),
        'hash' => $generatedHash
    ];

    // 7. تسجيل بيانات التصحيح
    Log::debug('MontyPay Hash Generation', [
        'hash_input' => $hashString,
        'md5_hash' => $md5Hash,
        'final_hash' => $generatedHash
    ]);

    // 8. إرسال الطلب إلى MontyPay API
    try {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($apiEndpoint, $payload);

        // 9. معالجة الاستجابة
        return response()->json([
            'success' => $response->successful(),
            'response' => $response->json(),
            'debug_info' => [
                'hash_input' => $hashString,
                'generated_hash' => $generatedHash,
                'hash_steps' => [
                    'md5' => $md5Hash,
                    'sha1' => $generatedHash
                ]
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء معالجة الدفع',
            'error' => $e->getMessage(),
            'debug' => [
                'hash_input' => $hashString,
                'generated_hash' => $generatedHash
            ]
        ], 500);
    }
});
*/


Route::get('/test-montypay-complete', function () {
    // 1. جلب بيانات الاعتماد
    $merchantKey = trim(env('MONTYPAY_MERCHANT_KEY'));
    $merchantPass = trim(env('MONTYPAY_MERCHANT_PASSWORD'));
    $apiEndpoint = env('MONTYPAY_API_ENDPOINT', 'https://checkout.montypay.com/api/v1/session');

    // 2. التحقق من البيانات
    if (empty($merchantKey) || empty($merchantPass)) {
        return response()->json(['error' => 'بيانات الاعتماد ناقصة'], 400);
    }

    // 3. بيانات الطلب الثابتة للتجربة
    $orderData = [
        'number' => 'TEST-' . time(),
        'amount' => '10.00',
        'currency' => 'USD',
        'description' => 'Test Payment'
    ];

    // 4. إنشاء سلسلة الهاش الأساسية بجميع المتغيرات الممكنة
    $hashVariations = [
        'default' => $merchantKey . $orderData['number'] . $orderData['amount'] .
                    $orderData['currency'] . $orderData['description'] . $merchantPass,

        'reverse_order' => $merchantPass . $orderData['description'] . $orderData['currency'] .
                          $orderData['amount'] . $orderData['number'] . $merchantKey,

        'no_description' => $merchantKey . $orderData['number'] . $orderData['amount'] .
                           $orderData['currency'] . $merchantPass,

        'with_colons' => implode(':', [
            $merchantKey, $orderData['number'], $orderData['amount'],
            $orderData['currency'], $orderData['description'], $merchantPass
        ]),

        'amount_no_decimal' => $merchantKey . $orderData['number'] .
                             str_replace('.', '', $orderData['amount']) .
                             $orderData['currency'] . $orderData['description'] . $merchantPass
    ];

    // 5. خوارزميات التجزئة الممكنة
    $hashAlgorithms = [
        'SHA1(MD5)' => fn($s) => strtoupper(sha1(md5($s))),
        'MD5' => fn($s) => strtoupper(md5($s)),
        'SHA1' => fn($s) => strtoupper(sha1($s)),
        'SHA256' => fn($s) => strtoupper(hash('sha256', $s)),
        'MD5(SHA1)' => fn($s) => strtoupper(md5(sha1($s))),
        'Lowercase_SHA1(MD5)' => fn($s) => strtolower(sha1(md5($s)))
    ];
    // 6. بناء payload أساسي
    $basePayload = [
        'merchant_key' => $merchantKey,
        'operation' => 'purchase',
        'order' => [
            'number' => $orderData['number'],
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'description' => $orderData['description']
        ],
        'customer' => [
            'email' => 'test@example.com',
            'name' => 'Test Customer'
        ],
        'success_url' => url('/payment/success'),
        'cancel_url' => url('/payment/cancel'),
        'callback_url' => url('/payment/callback')
    ];

    // 7. اختبار جميع التركيبات
    foreach ($hashVariations as $variationName => $hashString) {
        foreach ($hashAlgorithms as $algName => $algorithm) {
            $generatedHash = $algorithm($hashString);

            $payload = array_merge($basePayload, ['hash' => $generatedHash]);

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->post($apiEndpoint, $payload);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'working_method' => "$variationName + $algName",
                        'payment_url' => $response->json()['payment_url'] ?? null,
                        'debug' => [
                            'hash_input' => $hashString,
                            'generated_hash' => $generatedHash
                        ]
                    ]);
                }

                Log::debug("Failed attempt", [
                    'variation' => $variationName,
                    'algorithm' => $algName,
                    'response' => $response->json()
                ]);

            } catch (\Exception $e) {
                Log::error("API Error: " . $e->getMessage());
            }
        }
    }

    // 8. إذا فشلت جميع المحاولات
    return response()->json([
        'success' => false,
        'message' => 'فشلت جميع محاولات توليد الهاش',
        'debug' => [
            'merchant_key_sample' => substr($merchantKey, 0, 8) . '...',
            'test_values_used' => $orderData
        ]
    ], 400);
});



































// Success callback route
Route::get('/montypay-success', function (Request $request) {
    Log::info('MontyPay Success:', $request->all());
    return response()->json([
        'success' => true,
        'message' => 'Payment completed successfully!',
        'data' => $request->all()
    ]);
});

// Failure callback route
Route::get('/montypay-failure', function (Request $request) {
    Log::warning('MontyPay Failure:', $request->all());
    return response()->json([
        'success' => false,
        'message' => 'Payment failed!',
        'data' => $request->all()
    ]);
});

// Callback route for asynchronous notifications
Route::post('/montypay-callback', function (Request $request) {
    Log::info('MontyPay Callback:', $request->all());
    return response()->json(['status' => 'received']);
});
