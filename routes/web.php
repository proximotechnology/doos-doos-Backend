<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentPlanController;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\PaymentController;

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

Route::get('/test-montypay', function () {
    // 1. جلب بيانات الاعتماد - استخدم القيم من Postman للتجربة
    $merchantKey = "342269d6-7453-11f0-aafb-1a735aa47a45";
    $merchantPass = "1c2d91eb50ce0162f1dc83d2a5386e8e";
    $apiEndpoint = 'https://checkout.montypay.com/api/v1/session';

    // 2. استخدام نفس بيانات الطلب كما في Postman
    $orderData = [
        'number' => "b07",
        'amount' => "1.00",
        'currency' => "USD",
        'description' => "Doos Doos Test"
    ];

    // 3. توليد الهاش بنفس الطريقة المستخدمة في Postman
    $hashString = $orderData['number'] .
                 $orderData['amount'] .
                 $orderData['currency'] .
                 $orderData['description'] .
                 $merchantPass;

    // تحويل إلى uppercase كما في المثال
    $hashString = strtoupper($hashString);

    // 4. تطبيق نفس خوارزمية التجزئة: SHA1(MD5(string))
    $md5Hash = md5($hashString);
    $generatedHash = sha1($md5Hash); // لا نحتاج strtoupper هنا لأن sha1 يعيد hex lowercase

    // 5. بناء payload مطابق تمامًا لـ Postman
    $payload = [
        'merchant_key' => $merchantKey,
        'operation' => 'purchase',
        'cancel_url' => 'https://portal.montypay.com/cancel',
        'success_url' => 'https://portal.montypay.com/success',
        'hash' => $generatedHash,
        'order' => [
            'description' => $orderData['description'],
            'number' => $orderData['number'],
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency']
        ],
        'customer' => [
            'name' => 'montypay test',
            'email' => 'test@montypay.com'
        ],
        'billing_address' => [
            'country' => 'AE',
            'city' => 'Dubai',
            'address' => 'Dubai'
        ]
    ];

    try {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->post($apiEndpoint, $payload);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'payment_url' => $response->json()['payment_url'] ?? null,
                'session_data' => $response->json(),
                'debug' => [
                    'hash_input' => $hashString,
                    'generated_hash' => $generatedHash,
                    'md5_intermediate' => $md5Hash
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'فشل في إنشاء الجلسة',
            'response' => $response->json(),
            'status_code' => $response->status(),
            'debug' => [
                'hash_input' => $hashString,
                'generated_hash' => $generatedHash,
                'md5_intermediate' => $md5Hash
            ]
        ], 400);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'خطأ في الاتصال: ' . $e->getMessage(),
            'debug' => [
                'hash_input' => $hashString,
                'generated_hash' => $generatedHash,
                'md5_intermediate' => $md5Hash
            ]
        ], 500);
    }
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
