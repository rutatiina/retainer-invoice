<?php

namespace Rutatiina\RetainerInvoice\Http\Controllers;

use Rutatiina\RetainerInvoice\Models\Setting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Rutatiina\RetainerInvoice\Services\RetainerInvoiceService;
use Rutatiina\RetainerInvoice\Models\RetainerInvoice;
use Rutatiina\Item\Traits\ItemsSelect2DataTrait;
use Rutatiina\Contact\Traits\ContactTrait;
use Yajra\DataTables\Facades\DataTables;

class RetainerInvoiceController extends Controller
{
    use ContactTrait;
    use ItemsSelect2DataTrait;

    // >> get the item attributes template << !!important

    public function __construct()
    {
        $this->middleware('permission:retainer-invoices.view');
        $this->middleware('permission:retainer-invoices.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:retainer-invoices.update', ['only' => ['edit', 'update']]);
        $this->middleware('permission:retainer-invoices.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $query = RetainerInvoice::query();

        if ($request->contact)
        {
            $query->where(function ($q) use ($request)
            {
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

        return $settings->number_prefix . (str_pad((optional($txn)->number + 1), $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix;
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
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
        $storeService = RetainerInvoiceService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => RetainerInvoiceService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Retainer Invoice saved'],
            'number' => 0,
            'callback' => URL::route('retainer-invoices.show', [$storeService->id], false)
        ];

    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txn = RetainerInvoice::findOrFail($id);
        $txn->load('contact', 'items.taxes');
        $txn->setAppends([
            'taxes',
            'number_string',
            'total_in_words',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txnAttributes = RetainerInvoiceService::edit($id);

        $data = [
            'pageTitle' => 'Edit Retainer Invoice', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/retainer-invoices/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function update(Request $request)
    {
        //print_r($request->all()); exit;

        $storeService = RetainerInvoiceService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => RetainerInvoiceService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Retainer invoice updated'],
            'number' => 0,
            'callback' => URL::route('retainer-invoices.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
    {
        $destroy = RetainerInvoiceService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Retainer Invoice deleted'],
                'callback' => URL::route('retainer-invoices.index', [], false)
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => RetainerInvoiceService::$errors
            ];
        }
    }

    #-----------------------------------------------------------------------------------

    public function approve($id)
    {
        $approve = RetainerInvoiceService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => RetainerInvoiceService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Retainer Invoice Approved'],
        ];
    }

    public function copy($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('l-limitless-bs4.layout_2-ltr-default.appVue');
        }

        $txnAttributes = RetainerInvoiceService::copy($id);

        $data = [
            'pageTitle' => 'Copy Retainer Invoice', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/retainer-invoices', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function datatables(Request $request)
    {

        $txns = Transaction::setRoute('show', route('accounting.sales.retainer-invoices.show', '_id_'))
            ->setRoute('edit', route('accounting.sales.retainer-invoices.edit', '_id_'))
            ->setSortBy($request->sort_by)
            ->paginate(false);

        return Datatables::of($txns)->make(true);
    }

    public function exportToExcel(Request $request)
    {

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

        foreach (array_reverse($request->ids) as $id)
        {
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
            'maccounts-retainer-invoices-export-' . date('Y-m-d-H-m-s') . '.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

}
