<?php

namespace App\Models\Booking;

use App\Models\Documents\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'type', 'email', 'phone', 'website', 'billing_address', 'address_2',
        'city', 'state', 'postal_code', 'country',
        'owner_user_id', 'converted_from_lead_id', 'created_by', 'updated_by',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(\App\Models\Events\Event::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(AccountNote::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
