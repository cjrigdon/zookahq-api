<?php

namespace App\Models\Events;

use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Contract;
use App\Models\Booking\Proposal;
use App\Models\Documents\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'opportunity',
        'tentative',
        'confirmed',
        'completed',
        'cancelled',
    ];

    protected $fillable = [
        'name', 'status', 'event_type_id', 'account_id', 'contact_id', 'value', 'client_venue_budget',
        'starts_at', 'ends_at', 'load_in_at', 'load_out_at',
        'venue_space', 'space_id', 'expected_attendance', 'alcohol_served',
        'description', 'purpose',
        'planner_name', 'planner_title', 'planner_organization', 'planner_email', 'planner_phone',
        'referral',
        'owner_user_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'load_in_at' => 'datetime',
            'load_out_at' => 'datetime',
            'value' => 'decimal:2',
            'client_venue_budget' => 'decimal:2',
            'alcohol_served' => 'boolean',
        ];
    }

    public function functions(): HasMany
    {
        return $this->hasMany(EventFunction::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(EventChange::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function dignitaries(): HasMany
    {
        return $this->hasMany(EventDignitary::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EventNote::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
