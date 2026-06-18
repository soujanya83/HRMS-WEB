<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DocumentMaster;

class DocumentMasterController extends Controller
{
    public function index()
{
    return response()->json([
        'status' => true,
        'data' => DocumentMaster::orderBy('sort_order')->get()
    ]);
}

public function updateOrder(Request $request)
{
    $request->validate([
        'documents' => ['required', 'array']
    ]);

    DB::transaction(function () use ($request) {

        foreach ($request->documents as $document) {

            DocumentMaster::where('id', $document['id'])
                ->update([
                    'sort_order' => $document['sort_order']
                ]);
        }
    });

    return response()->json([
        'status' => true,
        'message' => 'Document order updated successfully.'
    ]);
}

public function toggleStatus($id)
{
    $document = DocumentMaster::findOrFail($id);

    $document->update([
        'is_active' => !$document->is_active
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Document status updated.'
    ]);
}

}
