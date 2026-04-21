<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartVehicleCompatibility extends Model
{
    protected $fillable = [
        'part_id',
        'make',
        'model',
        'year_from',
        'year_to',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }
}