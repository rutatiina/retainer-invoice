<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('retainer-invoices')->group(function () {

        //Route::get('summary', 'Rutatiina\RetainerInvoice\Http\Controllers\RetainerInvoiceController@summary');
        Route::post('export-to-excel', 'Rutatiina\RetainerInvoice\Http\Controllers\RetainerInvoiceController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\RetainerInvoice\Http\Controllers\RetainerInvoiceController@approve');
        Route::get('{id}/copy', 'Rutatiina\RetainerInvoice\Http\Controllers\RetainerInvoiceController@copy');

    });

    Route::resource('retainer-invoices/settings', 'Rutatiina\RetainerInvoice\Http\Controllers\RetainerInvoiceSettingsController');
    Route::resource('retainer-invoices', 'Rutatiina\RetainerInvoice\Http\Controllers\RetainerInvoiceController');

});
