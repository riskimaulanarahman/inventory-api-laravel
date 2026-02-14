<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'display_name',
        'phone',
        'must_reset_password',
        'is_active',
    ];

    protected $casts = [
        'must_reset_password' => 'boolean',
        'is_active' => 'boolean',
    ];
}
