<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'count'   => $modules->count(),
            'data'    => $modules
        ]);
    }

    public function pages($id)
    {
        $module = Module::with('pages')->find($id);

        // Module not found
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
                'reason'  => 'Invalid module id',
                'count'   => 0,
                'data'    => []
            ], 404);
        }

        // Pages empty
        if ($module->pages->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No pages found for this module',
                'reason'  => 'Module exists but no pages are linked',
                'count'   => 0,
                'data'    => []
            ], 200);
        }

        // Success
            return response()->json([
            'message'     => 'Pages fetched successfully',
            'module_id'   => $module->id,
            'module_name' => $module->name,
            'count'       => $module->pages->count(),
            'data'        => $module->pages
        ]);

    }
}

