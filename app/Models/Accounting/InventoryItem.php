<?php
namespace App\Models\Accounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class InventoryItem extends Model {
    use SoftDeletes;
    protected $fillable = ['sku','name','category','unit','quantity_on_hand','reorder_level','unit_cost','unit_price','is_active','notes','created_by','updated_by'];
    protected function casts(): array { return ['quantity_on_hand'=>'decimal:2','reorder_level'=>'decimal:2','unit_cost'=>'decimal:2','unit_price'=>'decimal:2','is_active'=>'boolean']; }
}
