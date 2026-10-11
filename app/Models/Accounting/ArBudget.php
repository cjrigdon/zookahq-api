<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class ArBudget extends Model {
    use SoftDeletes;
    protected $fillable = ['name','period_type','period_start','period_end','gl_account_id','budget_amount','actual_amount','notes','created_by','updated_by'];
    protected function casts(): array { return ['period_start'=>'date','period_end'=>'date','budget_amount'=>'decimal:2','actual_amount'=>'decimal:2']; }
    public function glAccount(): BelongsTo { return $this->belongsTo(GlAccount::class); }
}
