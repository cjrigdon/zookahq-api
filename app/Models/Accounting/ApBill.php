<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class ApBill extends Model {
    use SoftDeletes;
    protected $fillable = ['bill_number','vendor_name','vendor_email','status','bill_date','due_date','subtotal','tax_total','total','amount_paid','balance_due','notes','created_by','updated_by'];
    protected function casts(): array { return ['bill_date'=>'date','due_date'=>'date','subtotal'=>'decimal:2','tax_total'=>'decimal:2','total'=>'decimal:2','amount_paid'=>'decimal:2','balance_due'=>'decimal:2']; }
    public function lines(): HasMany { return $this->hasMany(ApBillLine::class); }
    public function payments(): HasMany { return $this->hasMany(ApPayment::class); }
    public function recalculateTotals(): void {
        $subtotal = (float) $this->lines()->sum('line_total');
        $tax = (float) $this->tax_total;
        $paid = (float) $this->payments()->where('status','completed')->sum('amount');
        $total = $subtotal + $tax;
        $balance = max(0, $total - $paid);
        $status = $this->status === 'void' ? 'void' : ($paid <= 0 ? ($this->status === 'draft' ? 'draft' : 'open') : ($balance <= 0 ? 'paid' : 'partial'));
        $this->forceFill(['subtotal'=>$subtotal,'total'=>$total,'amount_paid'=>$paid,'balance_due'=>$balance,'status'=>$status])->save();
    }
}
