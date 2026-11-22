<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventDignitary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'title',
        'organization',
        'created_by',
        'updated_by',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
