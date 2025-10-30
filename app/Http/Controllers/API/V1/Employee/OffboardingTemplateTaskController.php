<?php

namespace App\Http\Controllers\API\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\OffboardingTemplateTask;
use App\Models\Employee\OffboardingTemplate;
use Illuminate\Http\Request;

class OffboardingTemplateTaskController extends Controller
{
    public function index(Request $request)
    {
        $q = OffboardingTemplateTask::with('template.organization');
        if ($request->offboarding_template_id) $q->where('offboarding_template_id', $request->offboarding_template_id);
        $tasks = $q->orderBy('due_before_days', 'asc')->get();
        return response()->json(['success' => true, 'data' => $tasks], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'offboarding_template_id' => 'required|exists:offboarding_templates,id',
            'task_name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'due_before_days' => 'required|integer|min:1|max:365',
            'default_assigned_role' => 'required|in:hr,it,manager,admin,security,finance,facilities',
        ]);
        $task = OffboardingTemplateTask::create($validated);
        return response()->json(['success' => true, 'data' => $task], 201);
    }

    public function show($id)
    {
        $task = OffboardingTemplateTask::with('template.organization')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $task], 200);
    }

    public function update(Request $request, $id)
    {
        $task = OffboardingTemplateTask::findOrFail($id);
        $validated = $request->validate([
            'offboarding_template_id' => 'sometimes|exists:offboarding_templates,id',
            'task_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'due_before_days' => 'sometimes|integer|min:1|max:365',
            'default_assigned_role' => 'sometimes|in:hr,it,manager,admin,security,finance,facilities',
        ]);
        $task->update($validated);
        return response()->json(['success' => true, 'data' => $task], 200);
    }

    public function destroy($id)
    {
        $task = OffboardingTemplateTask::findOrFail($id);
        $task->delete();
        return response()->json(['success' => true, 'message' => 'Task deleted'], 200);
    }

    // Get by template
    public function byTemplate($templateId)
    {
        $template = OffboardingTemplate::findOrFail($templateId);
        $tasks = OffboardingTemplateTask::where('offboarding_template_id', $templateId)->get();
        return response()->json(['success' => true, 'template' => $template->name, 'data' => $tasks], 200);
    }
}
