<?php

namespace App\Http\Controllers\API\V1\Rostering;

use App\Http\Controllers\Controller;
use App\Models\Rostering\Roster;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Rostering\RosterPeriod;
use App\Models\HolidayModel;
use Carbon\CarbonPeriod;


class RosterController extends Controller
{
    // List all roster entries (optionally by org, employee, date, shift)
   public function index(Request $request)
{
    $user = auth()->user();

    $query = Roster::with([
        'employee.department', // 👈 load department
        'organization',
        'shift',
        'creator'
    ]);

    if ($request->organization_id) $query->where('organization_id', $request->organization_id);
    if ($request->employee_id) $query->where('employee_id', $request->employee_id);
    if ($request->roster_date) $query->where('roster_date', $request->roster_date);
    if ($request->shift_id) $query->where('shift_id', $request->shift_id);

    if ($request->start_date && $request->end_date) {
        $query->whereBetween('roster_date', [$request->start_date, $request->end_date]);
    }

    // 👇 NEW: Check if the user is an Employee 
    // We check globally via Spatie, OR specifically for the requested organization using your custom method.
    $isEmployee = $user->hasRole('Employee') || 
                  ($request->organization_id && $user->hasRoleForOrganization('Employee', $request->organization_id));

    if ($isEmployee) {
        // Option A: If you have a 'rosterPeriod' relationship set up on the Roster model
        $query->whereHas('period', function ($q) {
            $q->where('status', '!=', 'draft');
        });

        /* // Option B: If you DON'T have a relationship set up, use a subquery fallback:
        // $query->whereNotIn('roster_period_id', \App\Models\RosterPeriod::where('status', 'draft')->select('id'));
        */
    }

    $rosters = $query->orderBy('roster_date')->get();

    // 👇 Attach attendance + department safely
    $rosters->transform(function ($roster) {
        // Department name (null safe)
        $roster->department_name = optional(optional($roster->employee)->department)->name;
        
        // Attendance status
        $attendance = \App\Models\Employee\Attendance::where('employee_id', $roster->employee_id)
            ->where('date', $roster->roster_date)
            ->first();

        $roster->attendance_status = $attendance ? $attendance->status : null;

        return $roster;
    });

    return response()->json([
        'success' => true,
        'data' => $rosters
    ], 200);
}

    public function getTodayShift(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer'
            ]);

            $today = now()->toDateString();

            // ✅ Get today's roster
            $roster = \App\Models\Rostering\Roster::where('employee_id', $request->employee_id)
                ->where('roster_date', $today)
                ->first();

            if (!$roster) {
                return response()->json([
                    'status' => false,
                    'message' => 'No roster found for today'
                ], 404);
            }

            // ✅ Get shift details
            $shift = \App\Models\Rostering\Shift::where('id', $roster->shift_id)
                ->select('name', 'start_time', 'end_time', 'color_code')
                ->first();

            if (!$shift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Today shift fetched successfully',
                'data' => [
                    'date' => $today,
                    'shift' => $shift
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create roster entry
    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'organization_id' => 'required|exists:organizations,id',
    //         'employee_id' => 'required|exists:employees,id',
    //         'shift_id' => 'required|exists:shifts,id',
    //         'roster_date' => 'required|date',
    //        // 'start_time' => 'required|date_format:H:i',
    //        //'end_time' => 'required|date_format:H:i|after:start_time',
    //         'notes' => 'nullable|string|max:500',
    //         'created_by' => 'required|exists:users,id',
    //     ]);

    //     $date = \Carbon\Carbon::parse($validated['roster_date']);
    //     $dayOfWeek = $date->dayOfWeek;
    //     $holidayDates = \App\Models\HolidayModel::where('organization_id', $validated['organization_id'])
    //         ->where('is_active', true)
    //         ->pluck('holiday_date')
    //         ->map(function ($d) { return \Carbon\Carbon::parse($d)->toDateString(); })
    //         ->toArray();
    //     $reason = null;
    //     if ($dayOfWeek === \Carbon\Carbon::SUNDAY) {
    //         $reason = 'sunday';
    //     } elseif ($dayOfWeek === \Carbon\Carbon::SATURDAY) {
    //         $reason = 'saturday';
    //     } elseif (in_array($date->toDateString(), $holidayDates)) {
    //         $reason = 'holiday';
    //     }
    //     if ($reason) {
    //         return response()->json([
    //             'error' => 'Cannot create roster for this day',
    //             'reason' => $reason,
    //             'date' => $date->toDateString()
    //         ], 422);
    //     }
    //     $roster = Roster::create($validated);
    //     return response()->json(['success' => true, 'data' => $roster], 201);
    // }

    // Single roster entry
    public function show($id)
    {
        $roster = Roster::with(['employee', 'organization', 'shift', 'creator'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $roster], 200);
    }

    // Update roster entry
    public function update(Request $request, $id)
    {
        $roster = Roster::findOrFail($id);
        if ($roster->period && $roster->period->status === 'locked') {
            return response()->json(['error' => 'Roster period is locked'], 403);
        }
        $validated = $request->validate([
            'shift_id' => 'sometimes|exists:shifts,id',
            'roster_date' => 'sometimes|date',
           // 'start_time' => 'sometimes|date_format:H:i',
           // 'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
            'employee_id' => 'sometimes|exists:employees,id',
        ]);
        $roster->update($validated);
        return response()->json(['success' => true, 'data' => $roster], 200);
    }

    // Delete
    public function destroy($id)
    {
        $roster = Roster::findOrFail($id);
        if ($roster->period && $roster->period->status === 'locked') {
            return response()->json(['error' => 'Roster period is locked'], 403);
        }
        $roster->delete();
        return response()->json(['success' => true, 'message' => 'Roster deleted'], 200);
    }

    // Bulk create rosters for a week/month
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'rosters' => 'required|array|min:1',
            'rosters.*.organization_id' => 'required|exists:organizations,id',
            'rosters.*.employee_id' => 'required|exists:employees,id',
            'rosters.*.shift_id' => 'required|exists:shifts,id',
            'rosters.*.roster_date' => 'required|date',
          //  'rosters.*.start_time' => 'required|date_format:H:i',
            //'rosters.*.end_time' => 'required|date_format:H:i|after:rosters.*.start_time',
            'rosters.*.notes' => 'nullable|string|max:500',
            'rosters.*.created_by' => 'required|exists:users,id',
        ]);
        $created = [];
        $updated = [];
        $skipped = [];
        foreach ($validated['rosters'] as $row) {
            $date = \Carbon\Carbon::parse($row['roster_date']);
            $dayOfWeek = $date->dayOfWeek;
            $holidayDates = \App\Models\HolidayModel::where('organization_id', $row['organization_id'])
                ->where('is_active', true)
                ->pluck('holiday_date')
                ->map(function ($d) { return \Carbon\Carbon::parse($d)->toDateString(); })
                ->toArray();
            $reason = null;
            if ($dayOfWeek === \Carbon\Carbon::SUNDAY) {
                $reason = 'sunday';
            } elseif ($dayOfWeek === \Carbon\Carbon::SATURDAY) {
                $reason = 'saturday';
            } elseif (in_array($date->toDateString(), $holidayDates)) {
                $reason = 'holiday';
            }
            if ($reason) {
                $skipped[] = [
                    'roster_date' => $date->toDateString(),
                    'employee_id' => $row['employee_id'],
                    'reason' => $reason,
                ];
                continue;
            }
            // Check for existing roster
            $existing = Roster::where('employee_id', $row['employee_id'])
                ->where('roster_date', $date->toDateString())
                ->first();
            if ($existing) {
                $existing->fill($row);
                $existing->save();
                $updated[] = $existing;
            } else {
                $created[] = Roster::create($row);
            }
        }
        return response()->json([
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'created_count' => count($created),
            'updated_count' => count($updated),
            'skipped' => $skipped
        ], 201);
    }

    // Get rosters by employee (with date range option)
    public function byEmployee(Request $request, $employeeId)
    {
        $query = Roster::with(['employee', 'shift', 'organization']);
        $query->where('employee_id', $employeeId);
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('roster_date', [$request->start_date, $request->end_date]);
        }
        $rosters = $query->orderBy('roster_date')->get();
        return response()->json(['success' => true, 'data' => $rosters], 200);
    }


    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'roster_period_id' => 'required|exists:roster_periods,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            //   'start_time' => 'required|date_format:H:i',
            // 'end_time' => 'required|date_format:H:i|after:start_time',
            'created_by' => 'required|exists:users,id',
        ]);

        $period = RosterPeriod::findOrFail($validated['roster_period_id']);

        if ($period->status === 'locked') {
            return response()->json(['error' => 'Roster period is locked'], 403);
        }

        $created = [];
        $holidayDates = \App\Models\HolidayModel::where('organization_id', $period->organization_id)
            ->where('is_active', true)
            ->pluck('holiday_date')
            ->map(function ($d) { return Carbon::parse($d)->toDateString(); })
            ->toArray();

        foreach ($validated['employee_ids'] as $employeeId) {
            for ($date = Carbon::parse($period->start_date);
                $date->lte($period->end_date);
                $date->addDay()) {
                $dayOfWeek = $date->dayOfWeek;
                $dateStr = $date->toDateString();
                // Skip Saturday (6), Sunday (0), and holidays
                if ($dayOfWeek === Carbon::SUNDAY || $dayOfWeek === Carbon::SATURDAY || in_array($dateStr, $holidayDates)) {
                    continue;
                }
                if (
                    Roster::where('employee_id', $employeeId)
                        ->where('roster_date', $dateStr)
                        ->exists()
                ) continue;
                $created[] = Roster::create([
                    'organization_id' => $period->organization_id,
                    'roster_period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'shift_id' => $validated['shift_id'],
                    'roster_date' => $dateStr,
                    'created_by' => $validated['created_by'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($created),
            'data' => $created
        ], 201);
    }

    public function byPeriod($periodId)
    {
        $rosters = Roster::with(['employee', 'shift'])
            ->where('roster_period_id', $periodId)
            ->orderBy('roster_date')
            ->get();

        return response()->json(['success' => true, 'data' => $rosters]);
    }










    //new apiss

    /**
     * Create or Update Rosters for Single/Multiple Employees and Date Ranges
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'required|integer',
            'organization_id' => 'required|integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'break_start' => 'nullable',
            'break_end' => 'nullable',
            'break_grace_minutes' => 'nullable|integer',
            'total_working_time' => 'nullable',
            'status' => 'required|in:draft,published',
            'notes' => 'nullable|string',
            'created_by' => 'required|integer', // Ya auth()->id() use kar sakte hain
        ]);

        $period = CarbonPeriod::create($request->from_date, $request->to_date);
        $rosters = [];

        foreach ($period as $date) {
            // Saturday aur Sunday skip karein
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($request->employee_ids as $empId) {
                // updateOrCreate check karega agar (employee_id + roster_date) match hua to update, warna create.
                $roster = Roster::updateOrCreate(
                    [
                        'employee_id' => $empId,
                        'roster_date' => $date->format('Y-m-d'),
                    ],
                    [
                        'organization_id' => $request->organization_id,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'break_start' => $request->break_start,
                        'break_end' => $request->break_end,
                        'break_grace_minutes' => $request->break_grace_minutes,
                        'total_working_time' => $request->total_working_time,
                        'status' => $request->status,
                        'notes' => $request->notes,
                        'created_by' => $request->created_by,
                    ]
                );
                
                $rosters[] = $roster;
            }
        }

        return response()->json([
            'message' => 'Rosters saved successfully.',
            'data' => $rosters
        ], 200);
    }

    /**
     * Drag and Drop: Move Roster (Cut & Paste)
     */
    public function move(Request $request)
    {
        $request->validate([
            'roster_id' => 'required|exists:rosters,id',
            'target_employee_id' => 'required|integer',
            'target_roster_date' => 'required|date',
        ]);

        $sourceRoster = Roster::findOrFail($request->roster_id);
        $targetDate = Carbon::parse($request->target_roster_date);

        if ($targetDate->isWeekend()) {
            return response()->json(['message' => 'Cannot move roster to a weekend (Saturday/Sunday).'], 422);
        }

        // Check if a roster already exists at the target location
        $existingTarget = Roster::where('employee_id', $request->target_employee_id)
            ->where('roster_date', $targetDate->format('Y-m-d'))
            ->first();

        if ($existingTarget && $existingTarget->id !== $sourceRoster->id) {
            // Target pe phle se koi roster hai, usko overwrite (update) kar denge source ke data se
            $existingTarget->update([
                'start_time' => $sourceRoster->start_time,
                'end_time' => $sourceRoster->end_time,
                'break_start' => $sourceRoster->break_start,
                'break_end' => $sourceRoster->break_end,
                'status' => $sourceRoster->status,
                'notes' => $sourceRoster->notes,
            ]);
            // Source roster ko delete kar denge kyunki ye move action hai
            $sourceRoster->delete();
            $updatedRoster = $existingTarget;
        } else {
            // Target khali hai, just update the source roster's date and employee
            $sourceRoster->update([
                'employee_id' => $request->target_employee_id,
                'roster_date' => $targetDate->format('Y-m-d'),
            ]);
            $updatedRoster = $sourceRoster;
        }

        return response()->json([
            'message' => 'Roster moved successfully.',
            'data' => $updatedRoster
        ]);
    }

    /**
     * Drag and Drop: Copy Roster
     */
    public function copy(Request $request)
    {
        $request->validate([
            'roster_id' => 'required|exists:rosters,id',
            'target_employee_id' => 'required|integer',
            'target_roster_date' => 'required|date',
        ]);

        $sourceRoster = Roster::findOrFail($request->roster_id);
        $targetDate = Carbon::parse($request->target_roster_date);

        if ($targetDate->isWeekend()) {
            return response()->json(['message' => 'Cannot copy roster to a weekend.'], 422);
        }

        // Copy karte time target par updateOrCreate laga diya
        $copiedRoster = Roster::updateOrCreate(
            [
                'employee_id' => $request->target_employee_id,
                'roster_date' => $targetDate->format('Y-m-d'),
            ],
            [
                'organization_id' => $sourceRoster->organization_id,
                'start_time' => $sourceRoster->start_time,
                'end_time' => $sourceRoster->end_time,
                'break_start' => $sourceRoster->break_start,
                'break_end' => $sourceRoster->break_end,
                'break_grace_minutes' => $sourceRoster->break_grace_minutes,
                'total_working_time' => $sourceRoster->total_working_time,
                'status' => $sourceRoster->status,
                'notes' => $sourceRoster->notes,
                'created_by' => $sourceRoster->created_by,
            ]
        );

        return response()->json([
            'message' => 'Roster copied successfully.',
            'data' => $copiedRoster
        ]);
    }

    /**
     * Bulk Status Update
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'roster_ids' => 'required|array',
            'roster_ids.*' => 'integer|exists:rosters,id',
            'status' => 'required|in:draft,published',
        ]);

        Roster::whereIn('id', $request->roster_ids)->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Roster statuses updated to ' . $request->status . ' successfully.'
        ]);
    }

    /**
     * Get Rosters (Optional: Helpful for frontend calendar plotting)
     */
    // public function index(Request $request)
    // {
    //     $query = Roster::query();

    //     if ($request->has('organization_id')) {
    //         $query->where('organization_id', $request->organization_id);
    //     }
    //     if ($request->has('from_date') && $request->has('to_date')) {
    //         $query->whereBetween('roster_date', [$request->from_date, $request->to_date]);
    //     }

    //     return response()->json([
    //         'data' => $query->get()
    //     ]);
    // }

}
