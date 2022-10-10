<?php

namespace Rutatiina\RetainerInvoice\Models;

use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetainerInvoiceItem extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logName = 'TxnItem';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_retainer_invoice_items';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);
    }

    public function retainer_invoice()
    {
        return $this->belongsTo('Rutatiina\RetainerInvoice\Models\RetainerInvoice', 'retainer_invoice_id', 'id');
    }

    public function taxes()
    {
        return $this->hasMany('Rutatiina\RetainerInvoice\Models\RetainerInvoiceItemTax', 'retainer_invoice_item_id', 'id');
    }

}
