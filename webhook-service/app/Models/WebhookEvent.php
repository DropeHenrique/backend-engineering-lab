<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'lab_event_type',
        'correlation_id',
        'http_status',
        'status',
        'response_body',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
        ];
    }
}
