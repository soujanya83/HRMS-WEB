<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\EmployeeDocument;
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeDocumentController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => EmployeeDocument::with('employee')->orderBy('issue_date', 'desc')->get()
        ]);
    }

    public function show($id)
    {
        $doc = EmployeeDocument::with('employee')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $doc]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'document_type' => 'required|string|max:190',
            'file_name'     => 'required|string|max:191',
            'file'          => 'required|file|max:10240', // 10MB
            'issue_date'    => 'nullable|date',
            'expiry_date'   => 'nullable|date|after_or_equal:issue_date',
        ]);
        $path = $request->file('file')->store('employee_docs', 'public');
        $validated['file_url'] = Storage::url($path);

        $doc = EmployeeDocument::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $doc
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $doc = EmployeeDocument::findOrFail($id);
        $validated = $request->validate([
            'document_type' => 'sometimes|string|max:190',
            'file_name'     => 'sometimes|string|max:191',
            'file'          => 'nullable|file|max:10240',
            'issue_date'    => 'nullable|date',
            'expiry_date'   => 'nullable|date|after_or_equal:issue_date',
        ]);
        if ($request->hasFile('file')) {
            if ($doc->file_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $doc->file_url));
            }
            $validated['file_url'] = Storage::url($request->file('file')->store('employee_docs', 'public'));
        }

        $doc->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document updated',
            'data' => $doc
        ]);
    }

    public function destroy($id)
    {
        $doc = EmployeeDocument::findOrFail($id);
        if ($doc->file_url) Storage::disk('public')->delete(str_replace('/storage/', '', $doc->file_url));
        $doc->delete();
        return response()->json(['success' => true, 'message' => 'Document deleted']);
    }

    public function byEmployee($employeeId)
    {
        $docs = EmployeeDocument::where('employee_id', $employeeId)->orderBy('issue_date', 'desc')->get();
        return response()->json(['success' => true, 'data' => $docs]);
    }
}
