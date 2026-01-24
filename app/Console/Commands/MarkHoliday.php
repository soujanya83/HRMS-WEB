<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Employee\Leave;
use App\Models\Employee\Attendance;
use App\Models\HolidayModel;
use App\Models\Employee\Employee;

class MarkHoliday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-holiday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark users as Holiday if they organization have holiday on that day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->toDateString();

        // mark all the employee with holiday
        // $users = User::whereDoesntHave('attendances', function ($query) use ($today) {
        //     $query->whereDate('created_at', $today);
        // })->get();

          $UserWithOutAttendance = Employee::whereDoesntHave('attendances', function ($query) use ($today) {
            $query->whereDate('date', $today);
        })->get();
        // dd($UserWithOutAttendance);

        foreach ($UserWithOutAttendance as $user) {
            if (!$user) {
                $this->warn("Skipping: No employee record found for user ID {$user->id}");
                continue;
            }

               Attendance::create([
        'employee_id' => $user->id,  // ✅ correct ID
        'status' => 'holiday',
        'date' => $today,
    ]);
        $this->info('Holiday users marked successfully for ' . $today);
        }
    }
}
