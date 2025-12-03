<?php

namespace App\Http\Controllers\API\V1\Rostering;

use App\Http\Controllers\Controller;
use App\Models\Rostering\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    // List all shifts (optionally by organization)
    public function index(Request $request)
    {
        $query = Shift::with('organization');
        if ($request->organization_id) $query->where('organization_id', $request->organization_id);
        $shifts = $query->get();
        return response()->json(['success' => true, 'data' => $shifts], 200);
    }

    // Create shift
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'color_code' => 'nullable|string|max:7',
            'notes' => 'nullable|string|max:500',
        ]);
        $shift = Shift::create($validated);
        return response()->json(['success' => true, 'data' => $shift], 201);
    }

    // Show single shift
    public function show($id)
    {
        $shift = Shift::with('organization', 'rosters')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $shift], 200);
    }

    // Update shift
    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'color_code' => 'sometimes|string|max:7',
            'notes' => 'nullable|string|max:500',
        ]);
        $shift->update($validated);
        return response()->json(['success' => true, 'data' => $shift], 200);
    }

    // Soft delete shift
    public function destroy($id)
    {
        $shift = Shift::findOrFail($id);
        $shift->delete();
        return response()->json(['success' => true, 'message' => 'Shift deleted'], 200);
    }

    // Restore deleted shift
    public function restore($id)
    {
        $shift = Shift::onlyTrashed()->findOrFail($id);
        $shift->restore();
        return response()->json(['success' => true, 'message' => 'Shift restored', 'data' => $shift], 200);
    }

    // List deleted (trashed) shifts
    public function trashed()
    {
        $shifts = Shift::onlyTrashed()->with('organization')->get();
        return response()->json(['success' => true, 'data' => $shifts], 200);
    }

    // Calendar view: get all shifts for a date range
    public function calendar(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        $shifts = Shift::with('rosters')
            ->where('organization_id', $validated['organization_id'])
            ->whereHas('rosters', function ($q) use ($validated) {
                $q->whereBetween('roster_date', [$validated['start_date'], $validated['end_date']]);
            })
            ->get();
        return response()->json(['success' => true, 'data' => $shifts], 200);
    }
}
