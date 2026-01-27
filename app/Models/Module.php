<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public $timestamps = false;

    public function pages()
    {
        return $this->belongsToMany(Page::class, 'module_page');
    }
}
