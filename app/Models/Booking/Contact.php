<?php

namespace App\Models\Booking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id', 'first_name', 'last_name', 'email', 'phone', 'title',
        'is_primary', 'converted_from_lead_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
