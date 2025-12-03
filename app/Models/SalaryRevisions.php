<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee\Employee;;
use App\Models\SalaryStructure;
use App\Models\User;

class SalaryRevisions extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salary_structure_id',
        'old_base_salary',
        'new_base_salary',
        'effective_from',
        'reason',
        'approved_by'
    ];

    protected $casts = [
        'old_base_salary' => 'decimal:2',
        'new_base_salary' => 'decimal:2',
        'effective_from' => 'date'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function structure()
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
