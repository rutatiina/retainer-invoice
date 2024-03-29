<?php

namespace Rutatiina\RetainerInvoice\Services;

use Rutatiina\FinancialAccounting\Services\ItemBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

trait ApprovalService
{
    public static function run($data)
    {
        if (strtolower($data['status']) == 'draft')
        {
            //cannot update balances for drafts
            return false;
        }

        if (isset($data['balances_where_updated']) && $data['balances_where_updated'])
        {
            //cannot update balances for task already completed
            return false;
        }

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currentlly inventory update for estimates is disabled

        //Update the account balances
        AccountBalanceUpdateService::doubleEntry($data);

        //Update the contact balances
        ContactBalanceUpdateService::doubleEntry($data);

        //Update the item balances
        ItemBalanceUpdateService::entry($data);

        return true;
    }

}
