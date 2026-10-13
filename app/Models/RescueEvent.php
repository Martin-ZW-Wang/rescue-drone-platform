<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescueEvent extends Model
{
    protected $fillable = [
        'event_type',
        'tid',
        'status',
        'conf',
        'message',
        'original_image_path',
        'annotated_image_path',
        'bbox_json',
        'dbg_json',
        'event_time',
        'review_status',
        'review_note',
    ];

    protected $casts = [
        'bbox_json' => 'array',
        'dbg_json' => 'array',
        'event_time' => 'datetime',
    ];
}
