<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use App\Models\SalaryRevisions;
use App\Models\SalaryStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class SalaryRevisionsController extends Controller
{
         public function index(Request $request)
    {
        $employee = Employee::where('user_id', Auth::id())->firstOrFail();
        $organizationId = $employee->organization_id;

        $query = SalaryRevisions::with(['employee', 'structure', 'approver']);

        // If employee, restrict only their revisions
        if ($employee->role == 'employee') {
            $query->where('employee_id', $employee->id);
        } else {
            // HR/Admin → all revisions within the organization
            $query->whereHas('employee', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            });
        }

        $revisions = $query->orderBy('effective_from', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $revisions
        ]);
    }
    /**
     * Create Salary Revision
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'new_base_salary'     => 'required|numeric|min:0',
            'effective_from'      => 'required|date',
            'reason'              => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $employee = Employee::where('user_id', Auth::id())->firstOrFail();

            $structure = SalaryStructure::where('employee_id', $employee->id)
                ->first();

            // Fill required fields
            $validated['old_base_salary'] = $structure->base_salary;
            $validated['salary_structure_id'] = $structure->id;
            $validated['approved_by'] = Auth::id();

            // Create revision
            $revision = SalaryRevisions::create($validated);

            // Auto-update salary structure
            if ($request->auto_apply == true) {
                $structure->update([
                    'base_salary' => $validated['new_base_salary'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salary revision created successfully.',
                'data' => $revision->load(['employee', 'structure', 'approver'])
            ], 201);

        } catch (Exception $e) {

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating salary revision.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show Single Revision
     */
    public function show($id)
    {
        $revision = SalaryRevisions::with(['employee', 'structure', 'approver'])->find($id);

        if (!$revision) {
            return response()->json([
                'success' => false,
                'message' => 'Salary revision not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $revision
        ]);
    }

    /**
     * Update Revision
     */
    public function update(Request $request, $id)
    {
        $revision = SalaryRevisions::find($id);

        if (!$revision) {
            return response()->json([
                'success' => false,
                'message' => 'Salary revision not found.'
            ], 404);
        }

        $validated = $request->validate([
            'new_base_salary' => 'required|numeric|min:0',
            'effective_from'  => 'required|date',
            'reason'          => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $revision->update($validated);

            // Auto-update salary structure (optional)
            if ($request->auto_apply == true) {
                $revision->structure->update([
                    'base_salary' => $revision->new_base_salary
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salary revision updated.',
                'data' => $revision->load(['employee', 'structure', 'approver'])
            ]);

        } catch (Exception $e) {

            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating revision.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Revision
     */
    public function destroy($id)
    {
        $revision = SalaryRevisions::find($id);

        if (!$revision) {
            return response()->json([
                'success' => false,
                'message' => 'Salary revision not found.'
            ], 404);
        }

        $revision->delete();

        return response()->json([
            'success' => true,
            'message' => 'Salary revision deleted successfully.'
        ]);
    }
}
