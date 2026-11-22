<?php

namespace App\Models\Booking;

use App\Models\Events\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id', 'title', 'version', 'status', 'amount',
        'valid_until', 'terms', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_until' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }
}
