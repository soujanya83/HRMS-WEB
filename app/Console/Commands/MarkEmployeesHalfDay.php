<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Employee\Leave;
use App\Models\Employee\Attendance;

class MarkEmployeesHalfDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-employees-half-day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark users as Half day if they have not checked in check-in + half_day_after_minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->toDateString();

        // employees who not marked attendance and arriving more than grace time and exceeded half_day_time as per organization
        $UserWithOutAttendance = User::whereDoesntHave('attendances', function($query) use ($today){
   $query->whereDate('created_at', $today);
        })->get();

        foreach( $UserWithOutAttendance  as $user ){
              if (!$user->employee) {
        $this->warn("Skipping: No employee record found for user ID {$user->id}");
        continue;
    }

       Attendance::create([
        'employee_id' => $user->employee->id,  // ✅ correct ID
        'status' => 'half_day',
        'date' => $today,
    ]);

      $this->info('Half-Day users marked successfully for ' . $today);
        }


    }
}
