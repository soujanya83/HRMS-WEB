<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PolicyMaster;
use App\Models\EmployeePolicyAcknowledgement;

class EmployeePolicyMasterController extends Controller
{
    public function index()
{
    $employeeId = auth()->user()->employee_id;

    $organizationId = auth()->user()->organization_id;

    $policies = PolicyMaster::where('is_active',1)

        ->orderBy('sort_order')

        ->get()

        ->map(function($policy) use($employeeId){

            $ack = EmployeePolicyAcknowledgement::where(
                'employee_id',
                $employeeId
            )

            ->where(
                'policy_master_id',
                $policy->id
            )

            ->first();

            return [

                'id'=>$policy->id,

                'policy_name'=>$policy->policy_name,

                'link'=>$policy->description,

                'is_required'=>$policy->is_required,

                'viewed'=>$ack?->is_viewed ?? false,

                'acknowledged'=>$ack?->is_acknowledged ?? false,

                'viewed_at'=>$ack?->viewed_at,

                'acknowledged_at'=>$ack?->acknowledged_at

            ];

        });

    return response()->json($policies);
}

public function viewed($id)
{
    EmployeePolicyAcknowledgement::updateOrCreate(

        [

            'employee_id'=>auth()->user()->employee_id,

            'policy_master_id'=>$id

        ],

        [

            'organization_id'=>auth()->user()->organization_id,

            'is_viewed'=>true,

            'viewed_at'=>now(),

            'ip_address'=>request()->ip(),

            'user_agent'=>request()->userAgent()

        ]

    );

    return response()->json([

        'message'=>'Viewed'

    ]);
}


public function acknowledge($id)
{
    EmployeePolicyAcknowledgement::updateOrCreate(

        [

            'employee_id'=>auth()->user()->employee_id,

            'policy_master_id'=>$id

        ],

        [

            'organization_id'=>auth()->user()->organization_id,

            'is_acknowledged'=>true,

            'acknowledged_at'=>now(),

            'ip_address'=>request()->ip(),

            'user_agent'=>request()->userAgent()

        ]

    );

    return response()->json([

        'message'=>'Acknowledged'

    ]);
}
}
