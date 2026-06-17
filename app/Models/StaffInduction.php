<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffInduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_id',
        'form_data',
    ];

    protected $casts = [
        'form_data' => 'array',
    ];

    /**
     * Automatically format the signature paths to full URLs when sending JSON to the frontend.
     */
    public function getFormDataAttribute($value)
    {
        $data = json_decode($value, true);
        if (!$data) return $data;

        $sections = [
            'hr_orientation', 'policies_procedures', 'child_safe', 'work_health_safety',
            'key_people', 'centre_base', 'montessori_environment', 'active_supervision',
            'family_communication', 'team_collaboration'
        ];

        // Format section signatures
        foreach ($sections as $section) {
            if (!empty($data[$section]['educatorSign']) && !str_starts_with($data[$section]['educatorSign'], 'http')) {
                $data[$section]['educatorSign'] = asset('storage/' . $data[$section]['educatorSign']);
            }
            if (!empty($data[$section]['supervisorSign']) && !str_starts_with($data[$section]['supervisorSign'], 'http')) {
                $data[$section]['supervisorSign'] = asset('storage/' . $data[$section]['supervisorSign']);
            }
        }

        // Format declaration signatures
        if (!empty($data['declaration']['employeeSignature']) && !str_starts_with($data['declaration']['employeeSignature'], 'http')) {
            $data['declaration']['employeeSignature'] = asset('storage/' . $data['declaration']['employeeSignature']);
        }
        if (!empty($data['declaration']['supervisorSignature']) && !str_starts_with($data['declaration']['supervisorSignature'], 'http')) {
            $data['declaration']['supervisorSignature'] = asset('storage/' . $data['declaration']['supervisorSignature']);
        }

        return $data;
    }
}