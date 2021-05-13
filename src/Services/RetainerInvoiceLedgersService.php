<?php

namespace Rutatiina\RetainerInvoice\Services;

use Rutatiina\RetainerInvoice\Models\RetainerInvoiceItem;
use Rutatiina\RetainerInvoice\Models\RetainerInvoiceItemTax;
use Rutatiina\RetainerInvoice\Models\RetainerInvoiceLedger;

class RetainerInvoiceLedgersService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['retainer_invoice_id'] = $data['id'];
            RetainerInvoiceLedger::create($ledger);
        }
        unset($ledger);

    }

}
