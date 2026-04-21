<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'vehicle_id',
        'customer_id',
        'status',
        'document_number',
        'total_amount',
        'discount_amount',
        'notes',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaidAmountAttribute()
    {
        return $this->payments->sum('amount_paid');
    }

    public function getBalanceAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->balance <= 0) {
            return 'Paid';
        } elseif ($this->paid_amount > 0) {
            return 'Partially Paid';
        } else {
            return 'Unpaid';
        }
    }
}
