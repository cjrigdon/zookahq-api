<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class ApPayment extends Model {
    use SoftDeletes;
    protected $fillable = ['ap_bill_id','payment_number','status','method','amount','paid_at','reference','notes','created_by'];
    protected function casts(): array { return ['amount'=>'decimal:2','paid_at'=>'date']; }
    public function bill(): BelongsTo { return $this->belongsTo(ApBill::class, 'ap_bill_id'); }
}
