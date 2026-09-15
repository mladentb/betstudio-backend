<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'company_id', 'status',
        'subtotal', 'discount_percent', 'discount_amount', 'tax', 'total', 'currency',
        'exchange_rate', 'total_eur',
        'payment_status', 'payment_method', 'paid_at', 'notes',
        'solana_tx_signature'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'total_eur' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $year = date('Y');
        $lastOrder = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $number = $lastOrder ? (int)substr($lastOrder->order_number, -5) + 1 : 1;
        return $prefix . $year . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}
