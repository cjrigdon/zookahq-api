<?php

namespace App\Models\Documents;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use SoftDeletes;

    public const APPLIES_TO = [
        'event',
        'account',
        'contact',
    ];

    public const KIND_DOCX = 'docx_merge';

    public const KIND_STATIC = 'static';

    protected $fillable = [
        'name',
        'description',
        'applies_to',
        'is_default',
        'is_active',
        'kind',
        'original_filename',
        'disk_path',
        'mime_type',
        'file_size',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isMergeable(): bool
    {
        return $this->kind === self::KIND_DOCX;
    }

    public function makeDefault(): void
    {
        static::query()
            ->where('applies_to', $this->applies_to)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        if (! $this->is_default) {
            $this->is_default = true;
            $this->save();
        }
    }
}
