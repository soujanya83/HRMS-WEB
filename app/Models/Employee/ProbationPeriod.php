<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProbationPeriod extends Model
{
    use HasFactory;
    protected $fillable = ['employee_id', 'start_date', 'end_date', 'feedback', 'status', 'confirmation_date'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
