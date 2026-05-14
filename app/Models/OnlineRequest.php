<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineRequest extends Model
{
    protected $fillable = [
        'branch_id',
        'customer_id',
        'vehicle_id',
        'source',
        'external_row_hash',
        'submitted_at',
        'problem_description',
        'terms_accepted',
        'status',
        'raw_data',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'terms_accepted' => 'boolean',
        'raw_data' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}