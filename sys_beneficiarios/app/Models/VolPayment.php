<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<\Database\Factories\VolPaymentFactory> */
class VolPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'beneficiario_id',
        'payment_type',
        'payment_date',
        'receipt_ref',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
