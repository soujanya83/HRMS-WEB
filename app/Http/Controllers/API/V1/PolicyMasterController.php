<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PolicyMaster;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PolicyMasterController extends Controller
{
    public function index()
{
    $policies = PolicyMaster::orderBy('sort_order')->get();

    return response()->json([
        'status'=>true,
        'data'=>$policies
    ]);
}

public function store(Request $request)
{
    $request->validate([

        'policy_name'=>'required|max:255',

        'description'=>'nullable',

        'document'=>'nullable|file|mimes:pdf,doc,docx|max:10240',

        'is_required'=>'nullable|boolean'

    ]);

    $document = null;

    if($request->hasFile('document'))
    {
        $document = $request
            ->file('document')
            ->store('policies','public');
    }

    $policy = PolicyMaster::create([

        'policy_name'=>$request->policy_name,

        'slug'=>Str::slug($request->policy_name),

        'description'=>$request->description,

        'document'=>$document,

        'sort_order'=>PolicyMaster::max('sort_order')+1,

        'is_required'=>$request->is_required ?? true

    ]);

    return response()->json([

        'status'=>true,

        'message'=>'Policy Created',

        'data'=>$policy

    ]);
}

public function update(Request $request,$id)
{
    $policy=PolicyMaster::findOrFail($id);

    $request->validate([

        'policy_name'=>'required|max:255',

        'description'=>'nullable',

        'document'=>'nullable|file|mimes:pdf,doc,docx|max:10240'

    ]);

    if($request->hasFile('document'))
    {
        if($policy->document)
        {
            Storage::disk('public')->delete($policy->document);
        }

        $policy->document=$request
            ->file('document')
            ->store('policies','public');
    }

    $policy->update([

        'policy_name'=>$request->policy_name,

        'slug'=>Str::slug($request->policy_name),

        'description'=>$request->description,

        'is_required'=>$request->is_required

    ]);

    return response()->json([

        'status'=>true,

        'message'=>'Updated Successfully'

    ]);
}

public function destroy($id)
{
    $policy=PolicyMaster::findOrFail($id);

    if($policy->document)
    {
        Storage::disk('public')->delete($policy->document);
    }

    $policy->delete();

    return response()->json([

        'status'=>true,

        'message'=>'Deleted Successfully'

    ]);
}

public function updateOrder(Request $request)
{
    DB::transaction(function() use($request){

        foreach($request->policies as $policy)
        {
            PolicyMaster::where('id',$policy['id'])
                ->update([
                    'sort_order'=>$policy['sort_order']
                ]);
        }

    });

    return response()->json([
        'status'=>true,
        'message'=>'Order Updated'
    ]);
}

public function toggleStatus($id)
{
    $policy=PolicyMaster::findOrFail($id);

    $policy->update([
        'is_active'=>!$policy->is_active
    ]);

    return response()->json([
        'status'=>true
    ]);
}

}
