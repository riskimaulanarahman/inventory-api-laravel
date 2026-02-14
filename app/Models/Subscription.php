<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'plan_id',
        'status',
        'current_cycle',
        'trial_start_at',
        'trial_end_at',
        'period_start_at',
        'period_end_at',
        'read_only_mode',
    ];

    protected $casts = [
        'read_only_mode' => 'boolean',
        'trial_start_at' => 'datetime',
        'trial_end_at' => 'datetime',
        'period_start_at' => 'datetime',
        'period_end_at' => 'datetime',
    ];
}
