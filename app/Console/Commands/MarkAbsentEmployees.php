<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Employee\Leave;
use App\Models\Employee\Attendance;

class MarkAbsentEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-absent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark users as absent if they have not checked in today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
          $today = Carbon::today()->toDateString();

        // Get users who haven't marked attendance today
        $usersWithoutAttendance = User::whereDoesntHave('attendances', function ($query) use ($today) {
            $query->whereDate('created_at', $today);
        })->get();

    foreach ($usersWithoutAttendance as $user) {

    if (!$user->employee) {
        $this->warn("Skipping: No employee record found for user ID {$user->id}");
        continue;
    }

    Attendance::create([
        'employee_id' => $user->employee->id,  // ✅ correct ID
        'status' => 'absent',
        'date' => $today,
    ]);
}

        $this->info('Absent users marked successfully for ' . $today);
    }
}
