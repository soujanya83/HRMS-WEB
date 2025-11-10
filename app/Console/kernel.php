<?php

namespace App\Console;

use App\Models\Employee\Employee;
use App\Models\Organization;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\OrganizationAttendanceRule;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\HolidayModel;
use Illuminate\Support\Facades\Log;



class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
protected function schedule(Schedule $schedule): void
{
    // 🗓️ Get all attendance rules
    $rules = OrganizationAttendanceRule::select(
        'organization_id',
        'check_in',
        'absent_after_minutes',
        'half_day_after_minutes',
        'weekly_off_days'
    )->get();

    $todayDate = Carbon::now()->toDateString();      // e.g. 2025-10-23
    $todayDay  = strtolower(Carbon::now()->format('D')); // e.g. thu

    foreach ($rules as $rule) {

        // 🔹 Step 1: Get check-in base time
        $checkInTime = Carbon::createFromFormat('H:i', $rule->check_in);

        // 🔹 Step 2: Compute half-day & absent cutoff
        $halfDayTime = $rule->half_day_after_minutes
            ? $checkInTime->copy()->addMinutes($rule->half_day_after_minutes)->format('H:i')
            : null;

        $absentTime = $checkInTime->copy()->addMinutes($rule->absent_after_minutes)->format('H:i');

        // 🔹 Step 3: Weekly off logic
        $weeklyOffs = $rule->weekly_off_days
            ? array_map('trim', explode(',', strtolower($rule->weekly_off_days)))
            : [];

        // 🔹 Step 4: Check if today is a holiday for this org
        $holiday = HolidayModel::where('organization_id', $rule->organization_id)
            ->whereDate('holiday_date', $todayDate)
            ->where('is_active', true)
            ->first();

        if ($holiday) {
            // 🌟 Today is a declared holiday
            $schedule->command('attendance:mark-holiday', [$rule->organization_id])
                ->dailyAt('23:52')
                ->appendOutputTo(storage_path("logs/attendance_holiday_org_{$rule->organization_id}.log"));

            Log::info("⛱ Holiday detected ({$holiday->holiday_type}) for Org #{$rule->organization_id} on {$todayDate}");
            continue; // Skip other schedules
        }

        // 🔹 Step 5: If weekly off, mark holiday too
        if (in_array($todayDay, $weeklyOffs)) {
            $schedule->command('attendance:mark-holiday', [$rule->organization_id])
                ->dailyAt($checkInTime->format('H:i'))
                ->appendOutputTo(storage_path("logs/attendance_weeklyoff_org_{$rule->organization_id}.log"));

            Log::info("🕊 Weekly Off ({$todayDay}) marked as holiday for Org #{$rule->organization_id}");
            continue; // Skip other schedules
        }

        // 🔹 Step 6: Otherwise, schedule regular tasks
        if ($halfDayTime) {
            $schedule->command('attendance:mark-employees-half-day', [$rule->organization_id])
                ->dailyAt($halfDayTime)
                ->appendOutputTo(storage_path("logs/attendance_halfday_org_{$rule->organization_id}.log"));
        }

        $schedule->command('attendance:mark-absent', [$rule->organization_id])
            ->dailyAt($absentTime)
            ->appendOutputTo(storage_path("logs/attendance_absent_org_{$rule->organization_id}.log"));
    }
}

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
