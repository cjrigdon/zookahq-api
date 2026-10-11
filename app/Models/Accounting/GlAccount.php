<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class GlAccount extends Model {
    protected $fillable = ['code','name','type','is_active','description'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function journalLines(): HasMany { return $this->hasMany(GlJournalLine::class); }
}
