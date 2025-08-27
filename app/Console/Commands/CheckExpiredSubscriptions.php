<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User_Plan;
use App\Models\Cars;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update expired subscriptions and their associated cars';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $this->info('Checking expired subscriptions at: ' . $now);

        // العثور على الاشتراكات النشطة التي انتهت صلاحيتها
        $expiredSubscriptions = User_Plan::where('status', "active")
            ->where(function ($query) use ($now) {
                $query->where('date_end', '<', $now)
                      ->orWhere(DB::raw('STR_TO_DATE(date_end, "%Y-%c-%e %H:%i:%s")'), '<', $now);
            })
            ->get();

        $this->info('Found ' . $expiredSubscriptions->count() . ' expired subscriptions');

        if ($expiredSubscriptions->count() > 0) {
            $this->info('Subscription IDs: ' . $expiredSubscriptions->pluck('id')->implode(', '));
        }

        foreach ($expiredSubscriptions as $subscription) {
            try {
                // بدء معاملة للتحقق من السلامة
                DB::transaction(function () use ($subscription, $now) {
                    $this->info("Processing subscription ID: {$subscription->id}");
                    $this->info("Subscription end date: {$subscription->date_end}");
                    $this->info("Current time: {$now}");

                    // تحديث حالة الاشتراك إلى منتهي
                    $subscription->update([
                        'status' => User_Plan::STATUS_EXPIRED
                    ]);

                    $this->info("Subscription {$subscription->id} marked as expired");

                    // تحديث حالة السيارات المرتبطة بهذا الاشتراك إلى expired
                    $affectedCars = Cars::where('user_plan_id', $subscription->id)
                        ->where('status', 'active')
                        ->update(['status' => 'expired']);

                    $this->info("Updated {$affectedCars} cars to expired status for subscription {$subscription->id}");

                    // تسجيل العملية في السجلات
                    Log::info('Subscription expired and cars deactivated', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'subscription_end_date' => $subscription->date_end,
                        'current_time' => $now,
                        'affected_cars' => $affectedCars,
                        'processed_at' => now()
                    ]);
                });

            } catch (\Exception $e) {
                $this->error("Failed to process subscription {$subscription->id}: " . $e->getMessage());
                Log::error('Failed to process expired subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info('Expired subscriptions check completed successfully at: ' . now());
    }
}
