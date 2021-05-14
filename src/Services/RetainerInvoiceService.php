<?php

namespace Rutatiina\RetainerInvoice\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rutatiina\RetainerInvoice\Models\RetainerInvoice;
use Rutatiina\RetainerInvoice\Models\RetainerInvoiceItem;
use Rutatiina\RetainerInvoice\Models\RetainerInvoiceItemTax;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;
use Rutatiina\Tax\Models\Tax;

class RetainerInvoiceService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function edit($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = RetainerInvoice::findOrFail($id);
        $txn->load('contact', 'items.taxes');
        $txn->setAppends(['taxes']);

        $attributes = $txn->toArray();

        //print_r($attributes); exit;

        $attributes['_method'] = 'PATCH';

        $attributes['contact_id'] = $attributes['debit_contact_id'];
        $attributes['contact']['currency'] = $attributes['contact']['currency_and_exchange_rate'];
        $attributes['contact']['currencies'] = $attributes['contact']['currencies_and_exchange_rates'];

        $attributes['taxes'] = json_decode('{}');
        $attributes['isRecurring'] = false;
        $attributes['recurring'] = [
            'date_range' => [],
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '*',
        ];
        $attributes['contact_notes'] = null;
        $attributes['terms_and_conditions'] = null;

        foreach ($attributes['items'] as $key => $item)
        {
            $selectedItem = [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'tax_method' => 'inclusive',
                'account_type' => null,
            ];

            $attributes['items'][$key]['selectedItem'] = $selectedItem; #required
            $attributes['items'][$key]['selectedTaxes'] = []; #required
            $attributes['items'][$key]['displayTotal'] = 0; #required

            foreach ($item['taxes'] as $itemTax)
            {
                $attributes['items'][$key]['selectedTaxes'][] = $taxes[$itemTax['tax_code']];
            }

            $attributes['items'][$key]['rate'] = floatval($item['rate']);
            $attributes['items'][$key]['quantity'] = floatval($item['quantity']);
            $attributes['items'][$key]['total'] = floatval($item['total']);
            $attributes['items'][$key]['displayTotal'] = $item['total']; #required
        };

        return $attributes;
    }

    public static function store($requestInstance)
    {
        $data = ValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = ValidateService::$errors;
            return false;
        }

        //*
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = new RetainerInvoice;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            $Txn->number_prefix = $data['number_prefix'];
            $Txn->number = $data['number'];
            $Txn->number_length = $data['number_length'];
            $Txn->number_postfix = $data['number_postfix'];
            $Txn->date = $data['date'];
            $Txn->debit_financial_account_code = $data['debit_financial_account_code'];
            $Txn->credit_financial_account_code = $data['credit_financial_account_code'];
            $Txn->debit_contact_id = $data['debit_contact_id'];
            $Txn->credit_contact_id = $data['credit_contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            $Txn->reference = $data['reference'];
            $Txn->base_currency = $data['base_currency'];
            $Txn->quote_currency = $data['quote_currency'];
            $Txn->exchange_rate = $data['exchange_rate'];
            $Txn->taxable_amount = $data['taxable_amount'];
            $Txn->total = $data['total'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->due_date = $data['due_date'];
            $Txn->memo = $data['memo'];
            $Txn->terms_and_conditions = $data['terms_and_conditions'];
            $Txn->status = $data['status'];

            $Txn->save();

            $data['id'] = $Txn->id;

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            RetainerInvoiceItemService::store($data);

            //Save the ledgers >> $data['ledgers']; and update the balances
            //NOTE >> no need to update ledgers since this is not an accounting entry

            //check status and update financial account and contact balances accordingly
            ApprovalService::run($data);

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save estimate to database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to save estimate to database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to save estimate to database. Please contact Admin';
            }

            return false;
        }
        //*/

    }

    public static function update($requestInstance)
    {
        $data = ValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = ValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = RetainerInvoice::with('items', 'ledgers')->findOrFail($data['id']);

            if ($Txn->status == 'Approved')
            {
                self::$errors[] = 'Approved Transaction cannot be not be edited';
                return false;
            }

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->items()->delete();
            $Txn->item_taxes()->delete();
            $Txn->comments()->delete();

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($Txn->ledgers->toArray(), true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($Txn->ledgers->toArray(), true);

            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            $Txn->number_prefix = $data['number_prefix'];
            $Txn->number = $data['number'];
            $Txn->number_length = $data['number_length'];
            $Txn->number_postfix = $data['number_postfix'];
            $Txn->date = $data['date'];
            $Txn->debit_financial_account_code = $data['debit_financial_account_code'];
            $Txn->credit_financial_account_code = $data['credit_financial_account_code'];
            $Txn->debit_contact_id = $data['debit_contact_id'];
            $Txn->credit_contact_id = $data['credit_contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            $Txn->reference = $data['reference'];
            $Txn->base_currency = $data['base_currency'];
            $Txn->quote_currency = $data['quote_currency'];
            $Txn->exchange_rate = $data['exchange_rate'];
            $Txn->taxable_amount = $data['taxable_amount'];
            $Txn->total = $data['total'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->due_date = $data['due_date'];
            $Txn->memo = $data['memo'];
            $Txn->terms_and_conditions = $data['terms_and_conditions'];
            $Txn->status = $data['status'];

            $Txn->save();

            $data['id'] = $Txn->id;

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            RetainerInvoiceItemService::store($data);

            //Save the ledgers >> $data['ledgers']; and update the balances
            RetainerInvoiceLedgersService::store($data);

            //check status and update financial account and contact balances accordingly
            ApprovalService::run($data);

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to update estimate in database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to update estimate in database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to update estimate in database. Please contact Admin';
            }

            return false;
        }

    }

    public static function destroy($id)
    {
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = RetainerInvoice::findOrFail($id);

            if ($Txn->status == 'Approved')
            {
                self::$errors[] = 'Approved Transaction cannot be not be deleted';
                return false;
            }

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->items()->delete();
            $Txn->item_taxes()->delete();
            $Txn->comments()->delete();

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($Txn->ledgers, true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($Txn->ledgers, true);

            $Txn->delete();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to delete estimate from database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to delete estimate from database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to delete estimate from database. Please contact Admin';
            }

            return false;
        }
    }

}
