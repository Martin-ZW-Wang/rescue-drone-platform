<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'level',
        'source',
        'message',
        'context_json',
    ];

    protected $casts = [
        'context_json' => 'array',
    ];
}