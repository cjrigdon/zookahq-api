<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ArInvoiceLine extends Model {
    protected $fillable = ['ar_invoice_id','inventory_item_id','description','quantity','unit_price','line_total','sort_order'];
    protected function casts(): array { return ['quantity'=>'decimal:2','unit_price'=>'decimal:2','line_total'=>'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(ArInvoice::class, 'ar_invoice_id'); }
}
