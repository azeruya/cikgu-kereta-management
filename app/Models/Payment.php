<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'payment_date',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
