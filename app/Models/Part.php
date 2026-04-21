<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'variant',
        'sku',
        'description',
        'cost_price',
        'selling_price',
        'stock',
        'min_stock_threshold',
        'image',
        'is_generic',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_generic' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function compatibilities()
    {
        return $this->hasMany(PartVehicleCompatibility::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
