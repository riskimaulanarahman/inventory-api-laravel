<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageDaily extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'usage_daily';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'day',
        'login_count',
        'movement_count',
        'transfer_count',
        'active_user_count',
    ];
}
