<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'funeral_home_id',
    'field',
    'value',
    'source_id',
    'source_record_id',
    'observed_at',
    'is_current',
])]
class FieldValue extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }
}
