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
        foreach ($validated['rosters'] as $row) {
            $created[] = Roster::create($row);
        }
        return response()->json(['success' => true, 'data' => $created, 'count' => count($created)], 201);
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

            foreach ($validated['employee_ids'] as $employeeId) {
                for ($date = Carbon::parse($period->start_date);
                    $date->lte($period->end_date);
                    $date->addDay()) {

                    if (
                        Roster::where('employee_id', $employeeId)
                            ->where('roster_date', $date->toDateString())
                            ->exists()
                    ) continue;

                    $created[] = Roster::create([
                        'organization_id' => $period->organization_id,
                        'roster_period_id' => $period->id,
                        'employee_id' => $employeeId,
                        'shift_id' => $validated['shift_id'],
                        'roster_date' => $date->toDateString(),
                       // 'start_time' => $validated['start_time'],
                        //'end_time' => $validated['end_time'],
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
