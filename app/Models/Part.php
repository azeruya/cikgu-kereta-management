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

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock_threshold');
    }

    public function scopeGeneric($query)
    {
        return $query->where('is_generic', true);
    }

    public function scopeCompatibleWithVehicle($query, $vehicle)
    {
        return $query->where(function ($q) use ($vehicle) {
            $q->where('is_generic', true)
            ->orWhereHas('compatibilities', function ($compat) use ($vehicle) {
                $compat->where('make', $vehicle->make)
                        ->where('model', $vehicle->model)
                        ->where(function ($y) use ($vehicle) {
                            $y->whereNull('year_from')
                            ->orWhere('year_from', '<=', $vehicle->year);
                        })
                        ->where(function ($y) use ($vehicle) {
                            $y->whereNull('year_to')
                            ->orWhere('year_to', '>=', $vehicle->year);
                        });
            });
        });
    }
}
