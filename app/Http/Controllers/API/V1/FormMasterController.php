<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FormMaster;

class FormMasterController extends Controller
{
  public function index(Request $request)
{
    $employeeId = $request->employee_id;

    $forms = \App\Models\FormMaster::orderBy('sort_order')->get();

    $filledCount = 0;

    if ($employeeId) {

        $forms->transform(function ($form) use ($employeeId, &$filledCount) {

            $isFilled = DB::table($form->table_name)
                ->where('employee_id', $employeeId)
                ->exists();

            if ($isFilled) {
                $filledCount++;
            }

            $form->is_filled = $isFilled;

            return $form;
        });
    }

    return response()->json([
        'status' => true,
        'completed_forms' => $filledCount,
        'total_forms' => $forms->count(),
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
