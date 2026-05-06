<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainEvent extends Model
{
    protected $fillable = [
        'correlation_id',
        'type',
        'payload',
        'metadata',
        'status',
        'duration_ms',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
        ];
    }
}
