<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'code',
        'name',
        'description',
        'monthly_price',
        'yearly_price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
