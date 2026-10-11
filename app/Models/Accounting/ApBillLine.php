<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ApBillLine extends Model {
    protected $fillable = ['ap_bill_id','gl_account_id','description','quantity','unit_cost','line_total'];
    protected function casts(): array { return ['quantity'=>'decimal:2','unit_cost'=>'decimal:2','line_total'=>'decimal:2']; }
    public function bill(): BelongsTo { return $this->belongsTo(ApBill::class, 'ap_bill_id'); }
}
