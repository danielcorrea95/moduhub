<?php

use App\Modules\Invoices\Http\Controllers\Api\InvoiceController as ApiInvoiceController;
use App\Modules\Invoices\Http\Controllers\Api\IssuerController as ApiIssuerController;
use App\Modules\Signatures\Http\Controllers\Api\DocumentController as ApiDocumentController;
use App\Modules\Signatures\Http\Controllers\Api\SignerController as ApiSignerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.key', 'throttle:api'])->group(function () {
    Route::get('/me', function (Request $request) {
        $tenant = $request->attributes->get('tenant');
        $apiKey = $request->attributes->get('api_key');

        return response()->json([
            'tenant' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'modules' => $tenant->activeModules()->pluck('key'),
            ],
            'api_key' => [
                'id' => $apiKey->getKey(),
                'name' => $apiKey->name,
                'environment' => $apiKey->environment,
                'scopes' => $apiKey->scopes,
            ],
        ]);
    });

    Route::prefix('signatures')->middleware('module:signatures')->name('api.signatures.')->group(function () {
        Route::middleware('api.scope:signatures:read')->group(function () {
            Route::get('/documents', [ApiDocumentController::class, 'index'])->name('documents.index');
            Route::get('/documents/{document}', [ApiDocumentController::class, 'show'])->name('documents.show');
            Route::get('/documents/{document}/download', [ApiDocumentController::class, 'download'])->name('documents.download');
        });

        Route::middleware('api.scope:signatures:write')->group(function () {
            Route::post('/documents', [ApiDocumentController::class, 'store'])->name('documents.store');
            Route::delete('/documents/{document}', [ApiDocumentController::class, 'destroy'])->name('documents.destroy');
            Route::post('/documents/{document}/send', [ApiDocumentController::class, 'send'])->name('documents.send');
            Route::post('/documents/{document}/signers', [ApiSignerController::class, 'store'])->name('signers.store');
            Route::delete('/documents/{document}/signers/{signer}', [ApiSignerController::class, 'destroy'])->name('signers.destroy');
            Route::post('/documents/{document}/signers/{signer}/remind', [ApiSignerController::class, 'remind'])->name('signers.remind');
        });
    });

    Route::prefix('invoices')->middleware('module:invoices')->name('api.invoices.')->group(function () {
        Route::middleware('api.scope:invoices:read')->group(function () {
            Route::get('/issuer', [ApiIssuerController::class, 'show'])->name('issuer.show');
            Route::get('/', [ApiInvoiceController::class, 'index'])->name('index');
            Route::get('/{invoice}', [ApiInvoiceController::class, 'show'])->whereUlid('invoice')->name('show');
            Route::get('/{invoice}/xml', [ApiInvoiceController::class, 'xml'])->whereUlid('invoice')->name('xml');
            Route::get('/{invoice}/pdf', [ApiInvoiceController::class, 'pdf'])->whereUlid('invoice')->name('pdf');
        });

        Route::middleware('api.scope:invoices:write')->group(function () {
            Route::put('/issuer', [ApiIssuerController::class, 'update'])->name('issuer.update');
            Route::post('/issuer/certificate', [ApiIssuerController::class, 'storeCertificate'])->name('issuer.certificate.store');
            Route::delete('/issuer/certificate', [ApiIssuerController::class, 'destroyCertificate'])->name('issuer.certificate.destroy');
            Route::post('/', [ApiInvoiceController::class, 'store'])->name('store');
            Route::post('/{invoice}/sync', [ApiInvoiceController::class, 'sync'])->whereUlid('invoice')->name('sync');
            Route::post('/{invoice}/cancel', [ApiInvoiceController::class, 'cancel'])->whereUlid('invoice')->name('cancel');
        });
    });
});
