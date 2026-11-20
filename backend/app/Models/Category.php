<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// A type of problem a report can be about.
class Category extends Model
{

    use HasFactory;

    protected $fillable = ['name'];

    // The reports filed under this category.
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
