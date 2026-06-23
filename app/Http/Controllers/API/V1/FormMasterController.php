<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FormMaster;

class FormMasterController extends Controller
{
    public function index()
{
    $forms = \App\Models\FormMaster::query()
        ->orderBy('sort_order')
        ->get();

    return response()->json([
        'status' => true,
        'data' => $forms
    ]);


    }


    public function updateOrder(Request $request)
{
    $request->validate([
        'forms' => ['required', 'array']
    ]);

    DB::beginTransaction();

    try {

        foreach ($request->forms as $form) {

            FormMaster::where('id', $form['id'])
                ->update([
                    'sort_order' => $form['sort_order']
                ]);
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Order Updated Successfully'
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}



public function toggleStatus($id)
{
    $form = FormMaster::findOrFail($id);

    $form->update([
        'is_active' => !$form->is_active
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Status Updated'
    ]);
}

}
