<?php

namespace App\Http\Controllers\API\V1\Rostering;

use App\Http\Controllers\Controller;
use App\Models\Rostering\Roster;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Rostering\RosterPeriod;

class RosterController extends Controller
{
    // List all roster entries (optionally by org, employee, date, shift)
    public function index(Request $request)
    {
        $query = Roster::with(['employee', 'organization', 'shift', 'creator']);
        if ($request->organization_id) $query->where('organization_id', $request->organization_id);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->roster_date) $query->where('roster_date', $request->roster_date);
        if ($request->shift_id) $query->where('shift_id', $request->shift_id);
        $rosters = $query->orderBy('roster_date')->get();
        return response()->json(['success' => true, 'data' => $rosters], 200);
    }

    // Create roster entry
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'roster_date' => 'required|date',
           // 'start_time' => 'required|date_format:H:i',
           //'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
            'created_by' => 'required|exists:users,id',
        ]);

        $date = \Carbon\Carbon::parse($validated['roster_date']);
        $dayOfWeek = $date->dayOfWeek;
        $holidayDates = \App\Models\HolidayModel::where('organization_id', $validated['organization_id'])
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
            return response()->json([
                'error' => 'Cannot create roster for this day',
                'reason' => $reason,
                'date' => $date->toDateString()
            ], 422);
        }
        $roster = Roster::create($validated);
        return response()->json(['success' => true, 'data' => $roster], 201);
    }

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


}
