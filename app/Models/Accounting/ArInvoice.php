<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class ArInvoice extends Model {
    use SoftDeletes;
    protected $fillable = ['invoice_number','account_id','event_id','status','invoice_date','due_date','bill_to_name','bill_to_email','subtotal','tax_total','total','amount_paid','balance_due','currency','notes','portal_token','portal_token_expires_at','created_by','updated_by'];
    protected function casts(): array { return ['invoice_date'=>'date','due_date'=>'date','subtotal'=>'decimal:2','tax_total'=>'decimal:2','total'=>'decimal:2','amount_paid'=>'decimal:2','balance_due'=>'decimal:2','portal_token_expires_at'=>'datetime']; }
    public function lines(): HasMany { return $this->hasMany(ArInvoiceLine::class); }
    public function payments(): HasMany { return $this->hasMany(ArPayment::class); }
    public function recalculateTotals(): void {
        $subtotal = (float) $this->lines()->sum('line_total');
        $tax = (float) $this->tax_total;
        $paid = (float) $this->payments()->where('status','completed')->sum('amount');
        $total = $subtotal + $tax;
        $balance = max(0, $total - $paid);
        $status = $this->status === 'void' ? 'void' : ($paid <= 0 ? ($this->status === 'draft' ? 'draft' : 'sent') : ($balance <= 0 ? 'paid' : 'partial'));
        $this->forceFill(['subtotal'=>$subtotal,'total'=>$total,'amount_paid'=>$paid,'balance_due'=>$balance,'status'=>$status])->save();
    }
}
