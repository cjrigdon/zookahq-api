<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class GlJournalEntry extends Model {
    use SoftDeletes;
    protected $fillable = ['entry_number','entry_date','status','memo','source','source_id','created_by'];
    protected function casts(): array { return ['entry_date'=>'date']; }
    public function lines(): HasMany { return $this->hasMany(GlJournalLine::class); }
}
