<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class ArPayment extends Model {
    use SoftDeletes;
    protected $fillable = ['ar_invoice_id','payment_number','status','method','amount','paid_at','reference','authorize_net_trans_id','authorize_net_response','notes','via_portal','created_by'];
    protected function casts(): array { return ['amount'=>'decimal:2','paid_at'=>'date','authorize_net_response'=>'array','via_portal'=>'boolean']; }
    public function invoice(): BelongsTo { return $this->belongsTo(ArInvoice::class, 'ar_invoice_id'); }
}
