<?php

namespace SabitAhmad\SteadFast\Models;

use Illuminate\Database\Eloquent\Model;

class SteadfastLog extends Model
{
    protected $table = 'steadfast_logs';

    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
