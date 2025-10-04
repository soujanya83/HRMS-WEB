<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;
    protected $fillable = ['employee_id', 'document_type', 'file_name', 'file_url', 'issue_date', 'expiry_date'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
