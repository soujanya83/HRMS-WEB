<?php

namespace App\Http\Controllers\API\V1\Rostering;

use App\Http\Controllers\Controller;
use App\Models\Rostering\RosterPeriod;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RosterPeriodController extends Controller
{
    // Create weekly or monthly period
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'type' => 'required|in:weekly,fortnightly,monthly',
            'start_date' => 'required|date',
            'created_by' => 'required|exists:users,id',
        ]);

        if ($validated['type'] === 'weekly') {
            $start = Carbon::parse($validated['start_date'])->startOfWeek();
            $end   = $start->copy()->endOfWeek();
        } elseif ($validated['type'] === 'fortnightly') {
            $start = Carbon::parse($validated['start_date'])->startOfWeek();
            $end   = $start->copy()->addDays(13)->endOfDay(); // 2 weeks (14 days)
        } else {
            $start = Carbon::parse($validated['start_date'])->startOfMonth();
            $end   = $start->copy()->endOfMonth();
        }

        $period = RosterPeriod::create([
            'organization_id' => $validated['organization_id'],
            'type' => $validated['type'],
            'start_date' => $start,
            'end_date' => $end,
            'created_by' => $validated['created_by'],
        ]);

        return response()->json(['success' => true, 'data' => $period], 201);
    }

    // Publish period
    public function publish($id)
    {
        $period = RosterPeriod::findOrFail($id);

        if ($period->status !== 'draft') {
            return response()->json(['error' => 'Only draft can be published'], 422);
        }

        $period->update(['status' => 'published']);

        return response()->json(['success' => true, 'message' => 'Roster published']);
    }

    // Lock period
    public function lock($id)
    {
        $period = RosterPeriod::findOrFail($id);

        if ($period->status !== 'published') {
            return response()->json(['error' => 'Only published roster can be locked'], 422);
        }

        $period->update(['status' => 'locked']);

        return response()->json(['success' => true, 'message' => 'Roster locked']);
    }

    // List periods
    public function index(Request $request)
    {
        $query = RosterPeriod::withCount('rosters');

        if ($request->organization_id) {
            $query->where('organization_id', $request->organization_id);
        }

        return response()->json(['success' => true, 'data' => $query->latest()->get()]);
    }
}
