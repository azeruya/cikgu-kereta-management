<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'part_id',
        'item_type',
        'service_name',
        'service_hours',
        'cost_price',
        'selling_price',
        'quantity',
        'total_price',
        'note',
    ];

    protected $casts = [
        'service_hours' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function part()
    {
        return $this->belongsTo(Part::class);
    }
}
