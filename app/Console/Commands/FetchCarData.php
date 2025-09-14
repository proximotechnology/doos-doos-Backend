<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\ModelYear;
use Exception;

class FetchCarData extends Command
{
    protected $signature = 'cars:fetch {--retry= : Number of retries for failed requests}';
    protected $description = 'Fetch car data from CarQuery API and store in database';

    // إعدادات Rate Limiting
    protected $maxRetries = 3;
    protected $baseDelay = 3; // الوقت الأساسي بين الطلبات بالثواني
    protected $maxDelay = 30; // أقصى وقت انتظار بين المحاولات

    public function handle()
    {
        $this->info('Starting to fetch car data...');

        // تحديد عدد المحاولات من الخيارات أو استخدام القيمة الافتراضية
        $this->maxRetries = (int) $this->option('retry') ?: $this->maxRetries;

        try {
            // جلب جميع البراندات
            $this->fetchBrands();

            // جلب الموديلات لكل براند
            $this->fetchModels();

            // جلب سنوات الصنع لكل موديل
            $this->fetchModelYears();

            $this->info('Car data fetched and stored successfully!');
        } catch (Exception $e) {
            $this->error('Failed to fetch car data: ' . $e->getMessage());
            Log::error('Failed to fetch car data: ' . $e->getMessage());
            return 1; // رمز خطأ
        }

        return 0; // نجاح
    }

    protected function fetchBrands()
    {
        $this->info('Fetching brands...');

        $response = $this->withRetry(function () {
            return Http::withOptions(['verify' => false])
                ->get('https://www.carqueryapi.com/api/0.3/?cmd=getMakes');
        });

        // التحقق من وجود response قبل استدعاء successful()
        if (!$response) {
            $this->error('Failed to fetch brands after ' . $this->maxRetries . ' attempts');
            throw new Exception('Failed to fetch brands - No response received');
        }

        if ($response->successful()) {
            $data = $response->json();
            $brands = $data['Makes'] ?? [];

            if (empty($brands)) {
                $this->warn('No brands found in API response');
                return;
            }

            foreach ($brands as $brandData) {
                Brand::updateOrCreate(
                    ['make_id' => $brandData['make_id']],
                    [
                        'name' => $brandData['make_display'],
                        'country' => $brandData['make_country']
                    ]
                );
            }

            $this->info('Brands fetched: ' . count($brands));
        } else {
            $this->error('Failed to fetch brands. Status: ' . $response->status());
            throw new Exception('Failed to fetch brands - HTTP Status: ' . $response->status());
        }
    }

    protected function fetchModels()
    {
        $this->info('Fetching models for each brand...');

        $brands = Brand::all();

        if ($brands->isEmpty()) {
            $this->warn('No brands found in database. Skipping models fetch.');
            return;
        }

        foreach ($brands as $brand) {
            $this->info("Fetching models for brand: {$brand->name}");

            $response = $this->withRetry(function () use ($brand) {
                return Http::withOptions(['verify' => false])
                    ->get('https://www.carqueryapi.com/api/0.3/', [
                        'cmd' => 'getModels',
                        'make' => $brand->make_id
                    ]);
            });

            if (!$response) {
                $this->error("Failed to fetch models for brand: {$brand->name} after " . $this->maxRetries . " attempts");
                continue;
            }

            if ($response->successful()) {
                $data = $response->json();
                $models = $data['Models'] ?? [];

                if (empty($models)) {
                    $this->warn("No models found for brand: {$brand->name}");
                    continue;
                }

                foreach ($models as $modelData) {
                    CarModel::updateOrCreate(
                        [
                            'brand_id' => $brand->id,
                            'name' => $modelData['model_name']
                        ]
                    );
                }

                $this->info("Fetched " . count($models) . " models for {$brand->name}");
            } else {
                $this->error("Failed to fetch models for brand: {$brand->name}. Status: " . $response->status());
                continue;
            }

            // تأخير لتجنب تجاوز حدود API
            sleep($this->baseDelay);
        }
    }

    protected function fetchModelYears()
    {
        $this->info('Fetching years for each model...');

        $models = CarModel::with('brand')->get();

        if ($models->isEmpty()) {
            $this->warn('No models found in database. Skipping years fetch.');
            return;
        }

        foreach ($models as $model) {
            $this->info("Fetching years for model: {$model->brand->name} {$model->name}");

            $response = $this->withRetry(function () use ($model) {
                return Http::withOptions(['verify' => false])
                    ->get('https://www.carqueryapi.com/api/0.3/', [
                        'cmd' => 'getTrims',
                        'make' => $model->brand->make_id,
                        'model' => $model->name
                    ]);
            });

            if (!$response) {
                $this->error("Failed to fetch years for model: {$model->brand->name} {$model->name} after " . $this->maxRetries . " attempts");
                continue;
            }

            if ($response->successful()) {
                $data = $response->json();
                $trims = $data['Trims'] ?? [];
                $years = [];

                foreach ($trims as $trim) {
                    $year = $trim['model_year'] ?? null;
                    if ($year && is_numeric($year) && !in_array($year, $years)) {
                        $years[] = $year;

                        ModelYear::updateOrCreate(
                            [
                                'car_model_id' => $model->id,
                                'year' => $year
                            ]
                        );
                    }
                }

                $this->info("Fetched " . count($years) . " years for {$model->brand->name} {$model->name}");
            } else {
                $this->error("Failed to fetch years for model: {$model->brand->name} {$model->name}. Status: " . $response->status());
                continue;
            }

            // تأخير لتجنب تجاوز حدود API
            sleep($this->baseDelay);
        }
    }

    /**
     * دالة للمحاولة مع إعادة المحاولة التصاعدية (Exponential Backoff)
     *
     * @param callable $request
     * @return \Illuminate\Http\Client\Response|null
     */
    protected function withRetry(callable $request)
    {
        $attempt = 0;
        $lastResponse = null;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = $request();

                if ($response->successful()) {
                    return $response;
                }

                $lastResponse = $response;
                $this->warn("Request failed with status: " . $response->status() . ". Attempt: " . ($attempt + 1) . "/" . $this->maxRetries);
            } catch (Exception $e) {
                $lastException = $e;
                $this->warn("Request exception: " . $e->getMessage() . ". Attempt: " . ($attempt + 1) . "/" . $this->maxRetries);
            }

            $attempt++;

            if ($attempt < $this->maxRetries) {
                // الانتظار التصاعدي (Exponential Backoff)
                $delay = min($this->baseDelay * pow(2, $attempt), $this->maxDelay);
                $this->info("Waiting {$delay} seconds before next attempt...");
                sleep($delay);
            }
        }

        // بعد كل المحاولات الفاشلة، ارجع آخر response أو null
        return $lastResponse;
    }
}
