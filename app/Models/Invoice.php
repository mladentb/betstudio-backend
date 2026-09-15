<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'order_id', 'user_id', 'seller_company_id',
        'buyer_company_name', 'buyer_address', 'buyer_vat',
        'subtotal', 'discount_percent', 'discount_amount', 'tax_rate', 'tax_amount', 'total', 'currency',
        'status', 'issue_date', 'due_date', 'paid_at', 'notes', 'pdf_url'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sellerCompany()
    {
        return $this->belongsTo(Company::class, 'seller_company_id');
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $lastInvoice = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $number = $lastInvoice ? (int)substr($lastInvoice->invoice_number, -5) + 1 : 1;
        return $prefix . $year . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}
