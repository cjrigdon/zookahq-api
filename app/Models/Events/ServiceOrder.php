<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id', 'event_function_id', 'title', 'status', 'service_type',
        'service_at', 'amount', 'instructions', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventFunction(): BelongsTo
    {
        return $this->belongsTo(EventFunction::class);
    }
}
