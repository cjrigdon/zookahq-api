<?php

namespace App\Models\Booking;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_name', 'account_address', 'account_address_2', 'account_city', 'account_state', 'account_postal_code',
        'contact_first_name', 'contact_last_name', 'contact_email', 'contact_title', 'contact_phone',
        'source',
        'status', 'notes',
        'event_date', 'load_in_at', 'event_starts_at', 'event_ends_at', 'load_out_at',
        'owner_user_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'load_in_at' => 'datetime',
            'event_starts_at' => 'datetime',
            'event_ends_at' => 'datetime',
            'load_out_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
