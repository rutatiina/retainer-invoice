<?php

namespace Rutatiina\RetainerInvoice\Traits;

use Rutatiina\FinancialAccounting\Models\Entree;

trait EntreeTrait
{
    public function __construct()
    {}

    public function entree($idOrSlug)
    {
        if (is_numeric($idOrSlug)) {
            $txnEntree = Entree::find($idOrSlug);
        } else {
            $txnEntree = Entree::where('slug', $idOrSlug)->first();
        }

        if ($txnEntree) {
            //do nothing
        } else {
            return false;
        }

        $txnEntree->load('configs', 'configs.txn_type');

        return $txnEntree->toArray();

    }

}
