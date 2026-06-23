<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\MandatoryQuestion;
use Illuminate\Http\Request;

class MandatoryQuestionController extends Controller
{
    // 🔹 GET ALL
    public function index()
    {
        return response()->json(MandatoryQuestion::all());
    }

    // 🔹 STORE
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:255'
        ]);

        $data = MandatoryQuestion::create($request->all());

        return response()->json([
            'message' => 'Question created',
            'data' => $data
        ]);
    }

    // 🔹 SHOW
    public function show($id)
    {
        $data = MandatoryQuestion::findOrFail($id);
        return response()->json($data);
    }

    // 🔹 UPDATE
    public function update(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|string|max:255'
        ]);

        $data = MandatoryQuestion::findOrFail($id);
        $data->update($request->all());

        return response()->json([
            'message' => 'Updated successfully',
            'data' => $data
        ]);
    }

    // 🔹 DELETE
    public function destroy($id)
    {
        MandatoryQuestion::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}