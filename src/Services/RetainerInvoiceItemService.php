<?php

namespace Rutatiina\RetainerInvoice\Services;

use Rutatiina\RetainerInvoice\Models\RetainerInvoiceItem;
use Rutatiina\RetainerInvoice\Models\RetainerInvoiceItemTax;

class RetainerInvoiceItemService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        //print_r($data['items']); exit;

        //Save the items >> $data['items']
        foreach ($data['items'] as &$item)
        {
            $item['retainer_invoice_id'] = $data['id'];

            $itemTaxes = (is_array($item['taxes'])) ? $item['taxes'] : [] ;
            unset($item['taxes']);

            $itemModel = RetainerInvoiceItem::create($item);

            foreach ($itemTaxes as $tax)
            {
                //save the taxes attached to the item
                $itemTax = new RetainerInvoiceItemTax;
                $itemTax->tenant_id = $item['tenant_id'];
                $itemTax->retainer_invoice_id = $item['retainer_invoice_id'];
                $itemTax->retainer_invoice_item_id = $itemModel->id;
                $itemTax->tax_code = $tax['code'];
                $itemTax->amount = $tax['total'];
                $itemTax->inclusive = $tax['inclusive'];
                $itemTax->exclusive = $tax['exclusive'];
                $itemTax->save();
            }
            unset($tax);
        }
        unset($item);

    }

}
