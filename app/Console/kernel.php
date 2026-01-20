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
use App\Models\Rostering\Roster;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\info;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
{
    Log::info("Scheduler loaded at " . now());

    // Load attendance rules per organization
    $rules = OrganizationAttendanceRule::get();

    foreach ($rules as $rule) {

        $today = today()->toDateString();
        $todayDay = strtolower(now()->format('D'));

        // Load all shifts for this organization
        $shifts = Roster::where('organization_id', $rule->organization_id)->get();

        foreach ($shifts as $shift) {

            // Validate check-in
            try {
                $shiftCheckIn = Carbon::createFromFormat('H:i', $shift->check_in);
            } catch (\Exception $e) {
                Log::warning("Invalid shift check_in time for Shift {$shift->id}");
                continue;
            }

            // Compute cutoff times
            $halfDayTime = $shiftCheckIn->copy()
                ->addMinutes($rule->half_day_after_minutes)
                ->format('H:i');

            $absentTime = $shiftCheckIn->copy()
                ->addMinutes($rule->absent_after_minutes)
                ->format('H:i');

            // WEEKLY OFF check
            $weeklyOffs = $rule->weekly_off_days
                ? array_map('trim', explode(',', strtolower($rule->weekly_off_days)))
                : [];

            if (in_array($todayDay, $weeklyOffs)) {
                Log::info("Weekly off for Org {$rule->organization_id} - Shift {$shift->id}");
                continue;
            }

            // HOLIDAY check
            $holiday = HolidayModel::where('organization_id', $rule->organization_id)
                ->whereDate('holiday_date', $today)
                ->where('is_active', true)
                ->first();

            if ($holiday) {
                Log::info("Holiday detected for Org {$rule->organization_id}");
                continue;
            }

            // SCHEDULE HALF-DAY
            $schedule->command('attendance:mark-halfday', [
                    $rule->organization_id,
                    $shift->id
                ])
                ->dailyAt($halfDayTime)
                ->appendOutputTo(storage_path("logs/halfday_org_{$rule->organization_id}_shift_{$shift->id}.log"));

            // SCHEDULE ABSENT
            $schedule->command('attendance:mark-absent', [
                    $rule->organization_id,
                    $shift->id
                ])
                ->dailyAt($absentTime)
                ->appendOutputTo(storage_path("logs/absent_org_{$rule->organization_id}_shift_{$shift->id}.log"));

            Log::info(
                "Scheduled shift {$shift->id} 
                | Half Day: {$halfDayTime} 
                | Absent: {$absentTime}"
            );
        }
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
