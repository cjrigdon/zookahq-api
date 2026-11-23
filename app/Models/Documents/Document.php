<?php

namespace App\Models\Documents;

use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Contract;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    public const STATUS_GENERATED = 'generated';

    public const STATUS_SENT = 'sent';

    /** @var array<string, class-string<Model>> */
    public const DOCUMENTABLE_MAP = [
        'event' => Event::class,
        'account' => Account::class,
        'contact' => Contact::class,
    ];

    protected $fillable = [
        'document_template_id',
        'documentable_type',
        'documentable_id',
        'contract_id',
        'title',
        'original_filename',
        'disk_path',
        'mime_type',
        'file_size',
        'status',
        'emailed_to',
        'emailed_at',
        'merge_snapshot',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'emailed_at' => 'datetime',
            'merge_snapshot' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
