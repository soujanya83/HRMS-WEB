<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug'];
    public $timestamps = false;

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_page');
    }
}
