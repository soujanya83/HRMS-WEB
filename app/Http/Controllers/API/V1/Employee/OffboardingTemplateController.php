<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\OffboardingTemplate;
use Illuminate\Http\Request;

class OffboardingTemplateController extends Controller
{
    public function index(Request $request)
    {
        $q = OffboardingTemplate::with(['organization', 'tasks']);
        if ($request->organization_id) $q->where('organization_id', $request->organization_id);
        $templates = $q->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $templates], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255|unique:offboarding_templates,name',
            'description' => 'required|string|max:1000',
        ]);
        $template = OffboardingTemplate::create($validated);
        return response()->json(['success' => true, 'data' => $template], 201);
    }

    public function show($id)
    {
        $template = OffboardingTemplate::with(['organization', 'tasks'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $template], 200);
    }

    public function update(Request $request, $id)
    {
        $template = OffboardingTemplate::findOrFail($id);
        $validated = $request->validate([
            'organization_id' => 'sometimes|exists:organizations,id',
            'name' => 'sometimes|string|max:255|unique:offboarding_templates,name,' . $id,
            'description' => 'sometimes|string|max:1000',
        ]);
        $template->update($validated);
        return response()->json(['success' => true, 'data' => $template], 200);
    }

    public function destroy($id)
    {
        $template = OffboardingTemplate::findOrFail($id);
        if ($template->tasks()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete template with tasks'], 400);
        }
        $template->delete();
        return response()->json(['success' => true, 'message' => 'Offboarding template deleted'], 200);
    }

    // Clone template
    public function clone(Request $request, $id)
    {
        $original = OffboardingTemplate::with('tasks')->findOrFail($id);
        $validated = $request->validate(['name' => 'required|string|max:255|unique:offboarding_templates,name']);
        $new = OffboardingTemplate::create([
            'organization_id' => $original->organization_id,
            'name' => $validated['name'],
            'description' => $original->description . ' (Copy)',
        ]);
        foreach ($original->tasks as $t) {
            $new->tasks()->create([
                'task_name' => $t->task_name,
                'description' => $t->description,
                'due_before_days' => $t->due_before_days,
                'default_assigned_role' => $t->default_assigned_role,
            ]);
        }
        return response()->json(['success' => true, 'data' => $new->load('tasks')], 201);
    }
}
