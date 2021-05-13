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
            $item['estimate_id'] = $data['id'];

            $itemTaxes = (is_array($item['taxes'])) ? $item['taxes'] : [] ;
            unset($item['taxes']);

            $itemModel = RetainerInvoiceItem::create($item);

            foreach ($itemTaxes as $tax)
            {
                //save the taxes attached to the item
                $itemTax = new RetainerInvoiceItemTax;
                $itemTax->tenant_id = $item['tenant_id'];
                $itemTax->estimate_id = $item['estimate_id'];
                $itemTax->estimate_item_id = $itemModel->id;
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
