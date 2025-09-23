<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<\Database\Factories\VolEnrollmentFactory> */
class VolEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'beneficiario_id',
        'status',
        'enrolled_at',
        'unenrolled_at',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'unenrolled_at' => 'datetime',
        'enrolled_on' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'inscrito');
    }

    public function scopeWithinEnrollmentRange(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->whereBetween('enrolled_at', [$start, $end]);
    }

    public function scopeMonthly(Builder $query, string|CarbonInterface $month): Builder
    {
        $start = $month instanceof CarbonInterface ? $month->copy()->startOfMonth() : Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $query->whereBetween('enrolled_at', [$start, $end]);
    }

    public function scopeQuarter(Builder $query, int $year, int $quarter): Builder
    {
        $quarter = max(1, min(4, $quarter));
        $startMonth = (($quarter - 1) * 3) + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return $query->whereBetween('enrolled_at', [$start, $end]);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VolGroup::class, 'group_id');
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}