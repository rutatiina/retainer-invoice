<?php

namespace Rutatiina\RetainerInvoice\Http\Controllers;

use Rutatiina\RetainerInvoice\Models\Setting;
use URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Facades\View;
use Rutatiina\Tax\Models\Tax;
use Rutatiina\RetainerInvoice\Models\RetainerInvoice;
use Rutatiina\FinancialAccounting\Classes\Transaction;
use Rutatiina\Item\Traits\ItemsSelect2DataTrait;
use Rutatiina\Tenant\Traits\TenantTrait;
use Rutatiina\Contact\Traits\ContactTrait;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;
use Yajra\DataTables\Facades\DataTables;

use Rutatiina\RetainerInvoice\Classes\Store as TxnStore;
use Rutatiina\RetainerInvoice\Classes\Approve as TxnApprove;
use Rutatiina\RetainerInvoice\Classes\Read as TxnRead;
use Rutatiina\RetainerInvoice\Classes\Copy as TxnCopy;
use Rutatiina\RetainerInvoice\Classes\Number as TxnNumber;
use Rutatiina\RetainerInvoice\Traits\Item as TxnItem;
use Rutatiina\RetainerInvoice\Classes\Edit as TxnEdit;
use Rutatiina\RetainerInvoice\Classes\Update as TxnUpdate;

class RetainerInvoiceController extends Controller
{
    use ContactTrait;
    use ItemsSelect2DataTrait; //calls AccountingTrait
    use TxnItem; // >> get the item attributes template << !!important

    private  $txnEntreeSlug = 'retainer-invoice';

    public function __construct()
    {
        $this->middleware('permission:retainer-invoices.view');
		$this->middleware('permission:retainer-invoices.create', ['only' => ['create','store']]);
		$this->middleware('permission:retainer-invoices.update', ['only' => ['edit','update']]);
		$this->middleware('permission:retainer-invoices.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $query = RetainerInvoice::query();

        if ($request->contact)
        {
            $query->where(function($q) use ($request) {
                $q->where('debit_contact_id', $request->contact);
                $q->orWhere('credit_contact_id', $request->contact);
            });
        }

        $txns = $query->latest()->paginate($request->input('per_page', 20));

        return [
            'tableData' => $txns
        ];
    }

    private function nextNumber()
    {
        $txn = RetainerInvoice::latest()->first();
        $settings = Setting::first();

        return $settings->number_prefix.(str_pad((optional($txn)->number+1), $settings->minimum_number_length, "0", STR_PAD_LEFT)).$settings->number_postfix;
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new RetainerInvoice)->rgGetAttributes();

        $txnAttributes['number'] = $this->nextNumber();
        $txnAttributes['status'] = 'Approved';
        $txnAttributes['contact_id'] = '';
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');
        $txnAttributes['base_currency'] = $tenant->base_currency;
        $txnAttributes['quote_currency'] = $tenant->base_currency;
        $txnAttributes['taxes'] = json_decode('{}');
        $txnAttributes['isRecurring'] = false;
        $txnAttributes['recurring'] = [
            'date_range' => [],
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '*',
        ];
        $txnAttributes['contact_notes'] = null;
        $txnAttributes['terms_and_conditions'] = null;
        $txnAttributes['items'] = [$this->itemCreate()];

        unset($txnAttributes['txn_entree_id']); //!important
        unset($txnAttributes['txn_type_id']); //!important
        unset($txnAttributes['debit_contact_id']); //!important
        unset($txnAttributes['credit_contact_id']); //!important

        $data = [
            'pageTitle' => 'Create Retainer Invoice', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/retainer-invoices', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function store(Request $request)
    {
        $TxnStore = new TxnStore();
        $TxnStore->txnEntreeSlug = $this->txnEntreeSlug;
        $TxnStore->txnInsertData = $request->all();
        $insert = $TxnStore->run();

        if ($insert == false) {
            return [
                'status'    => false,
                'messages'   => $TxnStore->errors
            ];
        }

        return [
            'status'    => true,
            'messages'   => ['Retainer Invoice saved'],
            'number'    => 0,
            'callback'  => URL::route('retainer-invoices.show', [$insert->id], false)
        ];

    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        if (FacadesRequest::wantsJson()) {
            $TxnRead = new TxnRead();
            return $TxnRead->run($id);
        }
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $TxnEdit = new TxnEdit();
        $txnAttributes = $TxnEdit->run($id);

        $data = [
            'pageTitle' => 'Edit Estimate', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/retainer-invoices/'.$id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        if (FacadesRequest::wantsJson()) {
            return $data;
        }
    }

    public function update($id, Request $request)
    {
        $TxnStore = new TxnUpdate();
        $TxnStore->txnEntreeSlug = $this->txnEntreeSlug;
        $TxnStore->txnInsertData = $request->all();
        $insert = $TxnStore->run();

        if ($insert == false) {
            return [
                'status'    => false,
                'messages'  => $TxnStore->errors
            ];
        }

        return [
            'status'    => true,
            'messages'  => ['Retainer invoice updated'],
            'number'    => 0,
            'callback'  => URL::route('retainer-invoices.show', [$insert->id], false)
        ];
    }

    public function destroy($id)
	{
		$delete = Transaction::delete($id);

		if ($delete) {
			return [
				'status' => true,
				'message' => 'Retainer Invoice deleted',
			];
		} else {
			return [
				'status' => false,
				'message' => implode('<br>', array_values(Transaction::$rg_errors))
			];
		}
	}

	#-----------------------------------------------------------------------------------

    public function approve($id)
    {
        $TxnApprove = new TxnApprove();
        $approve = $TxnApprove->run($id);

        if ($approve == false) {
            return [
                'status'    => false,
                'messages'   => $TxnApprove->errors
            ];
        }

        return [
            'status'    => true,
            'messages'   => ['Retainer Invoice Approved'],
        ];

    }

    public function copy($id)
	{
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $TxnCopy = new TxnCopy();
        $txnAttributes = $TxnCopy->run($id);

        $TxnNumber = new TxnNumber();
        $txnAttributes['number'] = $TxnNumber->run($this->txnEntreeSlug);

        $data = [
            'pageTitle' => 'Copy Retainer Invoice', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/retainer-invoices', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        if (FacadesRequest::wantsJson()) {
            return $data;
        }

        $txn = Transaction::transaction($id);
        $txn->number = Transaction::entreeNextNumber($this->txnEntreeSlug);
        return view('accounting::sales.retainer-invoices.copy')->with([
            'txn'       => $txn,
            'contacts'  => static::contactsByTypes(['customer']),
            'taxes'     => self::taxes()
        ]);
    }

    public function datatables(Request $request)
	{

        $txns = Transaction::setRoute('show', route('accounting.sales.retainer-invoices.show', '_id_'))
			->setRoute('edit', route('accounting.sales.retainer-invoices.edit', '_id_'))
			->setSortBy($request->sort_by)
			->paginate(false)
			->findByEntree($this->txnEntreeSlug);

        return Datatables::of($txns)->make(true);
    }

    public function exportToExcel(Request $request) {

        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT#',
            'REFERENCE',
            'CUSTOMER',
            'EXPIRY DATE',
            'TOTAL',
            ' ', //Currency
        ]);

        foreach (array_reverse($request->ids) as $id) {
            $txn = Transaction::transaction($id);

            $txns->push([
                $txn->date,
                $txn->number,
                $txn->reference,
                $txn->contact_name,
                $txn->expiry_date,
                $txn->total,
                $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-retainer-invoices-export-'.date('Y-m-d-H-m-s').'.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

}
