<?php

use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PlatformCertificateController as AdminPlatformCertificateController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProductPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoFilesController;
use App\Http\Controllers\WebhookEndpointController;
use App\Http\Controllers\WorkspaceController;
use App\Modules\Billing\Http\Controllers\AsaasWebhookController;
use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Invoices\Http\Controllers\InvoiceController as InvoicesController;
use App\Modules\Invoices\Http\Controllers\IssuerController as InvoicesIssuerController;
use App\Modules\Invoices\Http\Controllers\MunicipalAgreementController as InvoicesAgreementController;
use App\Modules\Invoices\Http\Controllers\MunicipalityController as InvoicesMunicipalityController;
use App\Modules\Invoices\Http\Controllers\ReceivedInvoiceController as InvoicesReceivedController;
use App\Modules\Mailing\Http\Controllers\EmailLogController;
use App\Modules\Mailing\Http\Controllers\SendingDomainController;
use App\Modules\Mailing\Http\Controllers\SesWebhookController;
use App\Modules\Mailing\Http\Controllers\SmtpCredentialController;
use App\Modules\Signatures\Http\Controllers\DocumentController as SignaturesDocumentController;
use App\Modules\Signatures\Http\Controllers\PublicSignController as SignaturesPublicController;
use App\Modules\Signatures\Http\Controllers\SignerController as SignaturesSignerController;
use App\Modules\Signatures\Http\Controllers\VerificationController as SignaturesVerificationController;
use App\Modules\Signatures\Http\Middleware\EnterSignerTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/docs/api', ApiDocsController::class)->name('docs.api');

// Páginas de produto, para quem chega pela busca. O endereço é o termo que as pessoas procuram.
Route::get('/assinatura-eletronica', ProductPageController::class)->defaults('product', 'signatures')->name('products.signatures');
Route::get('/emissor-nfse', ProductPageController::class)->defaults('product', 'invoices')->name('products.invoices');
Route::get('/disparo-de-email', ProductPageController::class)->defaults('product', 'mailing')->name('products.mailing');

// Para buscadores e robôs de IA. Gerados a cada acesso: os preços vêm dos planos do admin.
Route::get('/sitemap.xml', [SeoFilesController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoFilesController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SeoFilesController::class, 'llms'])->name('llms');

// Eventos do SES via SNS. Sem CSRF nem sessão de tenant: a assinatura da AWS autentica.
Route::post('/webhooks/ses', SesWebhookController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('webhooks.ses');

// Eventos de cobrança do Asaas. Sem CSRF nem sessão de tenant: o token do header autentica.
Route::post('/webhooks/asaas', AsaasWebhookController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('webhooks.asaas');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::prefix('v')->name('verify.')->group(function () {
    Route::get('/{short}', [SignaturesVerificationController::class, 'show'])->name('show');
    Route::get('/{short}/download', [SignaturesVerificationController::class, 'download'])->name('download');
});

Route::prefix('sign')->name('public-sign.')->middleware(EnterSignerTenant::class)->group(function () {
    Route::get('/{token}', [SignaturesPublicController::class, 'show'])->name('show');
    Route::get('/{token}/pdf', [SignaturesPublicController::class, 'pdf'])->name('pdf');
    Route::post('/{token}/code', [SignaturesPublicController::class, 'sendCode'])->name('send-code');
    Route::post('/{token}', [SignaturesPublicController::class, 'verifyAndSign'])->name('sign');
    Route::post('/{token}/reject', [SignaturesPublicController::class, 'reject'])->name('reject');
    Route::get('/{token}/thanks', [SignaturesPublicController::class, 'thanks'])->name('thanks');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{tenant}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    Route::get('/workspace/settings', [WorkspaceController::class, 'edit'])->name('workspace.settings');
    Route::patch('/workspace/settings', [WorkspaceController::class, 'update'])->name('workspace.update');

    Route::prefix('workspace/sending-domain')->name('sending-domain.')->controller(SendingDomainController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'store')->name('store');
        Route::patch('/', 'update')->name('update');
        Route::post('/verify', 'verify')->name('verify');
        Route::post('/test', 'test')->middleware('throttle:5,10')->name('test');
        Route::delete('/', 'destroy')->name('destroy');
    });

    Route::prefix('workspace/emails')->name('emails.')->controller(EmailLogController::class)->group(function () {
        Route::get('/', 'overview')->name('overview');
        Route::get('/mensagens', 'messages')->name('messages');
        Route::get('/bloqueados', 'suppressions')->name('suppressions');
        Route::delete('/bloqueados/{suppression}', 'release')->name('suppressions.release');
    });

    Route::post('/workspace/smtp-credentials', [SmtpCredentialController::class, 'store'])->name('smtp-credentials.store');
    Route::delete('/workspace/smtp-credentials/{credential}', [SmtpCredentialController::class, 'destroy'])->name('smtp-credentials.destroy');

    Route::prefix('workspace/billing')->name('billing.')->controller(BillingController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        // Cartão: poucas tentativas, para o formulário não virar teste de cartão roubado.
        Route::post('/subscription', 'subscribe')->middleware('throttle:5,10')->name('subscribe');
        Route::patch('/payment-method', 'changePaymentMethod')->middleware('throttle:5,10')->name('payment-method');
        Route::patch('/plan', 'changePlan')->name('plan');
        Route::delete('/subscription', 'cancel')->name('cancel');
        Route::get('/payments/{payment}/pix', 'pix')->whereUlid('payment')->middleware('throttle:20,1')->name('pix');
    });

    Route::get('/workspace/members', [MemberController::class, 'index'])->name('workspace.members');
    Route::post('/workspace/invitations', [MemberController::class, 'invite'])->name('workspace.invitations.store');
    Route::delete('/workspace/invitations/{invitation}', [MemberController::class, 'revokeInvitation'])->name('workspace.invitations.destroy');
    Route::delete('/workspace/members/{user}', [MemberController::class, 'removeMember'])->name('workspace.members.destroy');
    Route::patch('/workspace/members/{user}', [MemberController::class, 'updateRole'])->name('workspace.members.update');

    Route::get('/workspace/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/workspace/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/workspace/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('/workspace/webhooks', [WebhookEndpointController::class, 'index'])->name('webhooks.index');
    Route::post('/workspace/webhooks', [WebhookEndpointController::class, 'store'])->name('webhooks.store');
    Route::get('/workspace/webhooks/{endpoint}', [WebhookEndpointController::class, 'show'])->name('webhooks.show');
    Route::patch('/workspace/webhooks/{endpoint}', [WebhookEndpointController::class, 'update'])->name('webhooks.update');
    Route::delete('/workspace/webhooks/{endpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
    Route::post('/workspace/webhooks/{endpoint}/ping', [WebhookEndpointController::class, 'ping'])->name('webhooks.ping');

    Route::middleware('module.web:signatures')->prefix('signatures')->name('signatures.')->group(function () {
        Route::get('/', [SignaturesDocumentController::class, 'index'])->name('index');
        Route::get('/new', [SignaturesDocumentController::class, 'create'])->name('create');
        Route::post('/', [SignaturesDocumentController::class, 'store'])->name('store');
        Route::get('/{document}', [SignaturesDocumentController::class, 'show'])->name('show');
        Route::get('/{document}/download', [SignaturesDocumentController::class, 'download'])->name('download');
        Route::get('/{document}/download-working', [SignaturesDocumentController::class, 'downloadWorking'])->name('download-working');
        Route::post('/{document}/send', [SignaturesDocumentController::class, 'send'])->name('send');
        Route::delete('/{document}', [SignaturesDocumentController::class, 'destroy'])->name('destroy');

        Route::post('/{document}/signers', [SignaturesSignerController::class, 'store'])->name('signers.store');
        Route::patch('/{document}/signers/reorder', [SignaturesSignerController::class, 'reorder'])->name('signers.reorder');
        Route::patch('/{document}/signers/{signer}', [SignaturesSignerController::class, 'update'])->name('signers.update');
        Route::delete('/{document}/signers/{signer}', [SignaturesSignerController::class, 'destroy'])->name('signers.destroy');
        Route::post('/{document}/signers/{signer}/remind', [SignaturesSignerController::class, 'remind'])
            ->middleware('throttle:10,1')
            ->name('signers.remind');
    });

    Route::middleware('module.web:invoices')->prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/issuer', [InvoicesIssuerController::class, 'edit'])->name('issuer.edit');
        Route::put('/issuer', [InvoicesIssuerController::class, 'update'])->name('issuer.update');
        Route::post('/issuer/certificate', [InvoicesIssuerController::class, 'storeCertificate'])->name('issuer.certificate.store');
        Route::delete('/issuer/certificate', [InvoicesIssuerController::class, 'destroyCertificate'])->name('issuer.certificate.destroy');

        Route::get('/', [InvoicesController::class, 'index'])->name('index');
        Route::get('/create', [InvoicesController::class, 'create'])->name('create');
        Route::post('/', [InvoicesController::class, 'store'])->name('store');
        Route::get('/service-code-check', [InvoicesController::class, 'checkServiceCode'])->name('service-code-check');
        Route::get('/municipalities', [InvoicesMunicipalityController::class, 'index'])->name('municipalities');
        Route::get('/agreements', [InvoicesAgreementController::class, 'index'])->name('agreements');
        Route::get('/received', [InvoicesReceivedController::class, 'index'])->name('received.index');
        Route::post('/received/sync', [InvoicesReceivedController::class, 'sync'])->middleware('throttle:6,1')->name('received.sync');
        Route::whereUlid('receivedInvoice')->group(function () {
            Route::get('/received/{receivedInvoice}/xml', [InvoicesReceivedController::class, 'xml'])->name('received.xml');
            Route::get('/received/{receivedInvoice}/pdf', [InvoicesReceivedController::class, 'pdf'])->name('received.pdf');
            Route::post('/received/{receivedInvoice}/manifest', [InvoicesReceivedController::class, 'manifest'])->name('received.manifest');
        });
        // ULID na restrição para /invoices/issuer e /invoices/create nunca caírem aqui.
        Route::whereUlid('invoice')->group(function () {
            Route::get('/{invoice}', [InvoicesController::class, 'show'])->name('show');
            Route::get('/{invoice}/xml', [InvoicesController::class, 'xml'])->name('xml');
            Route::get('/{invoice}/pdf', [InvoicesController::class, 'pdf'])->name('pdf');
            Route::post('/{invoice}/sync', [InvoicesController::class, 'sync'])->name('sync');
            Route::post('/{invoice}/cancel', [InvoicesController::class, 'cancel'])->name('cancel');
            Route::post('/{invoice}/replace', [InvoicesController::class, 'replace'])->name('replace');
            Route::post('/{invoice}/cancellation-review', [InvoicesController::class, 'cancellationReview'])->name('cancellation-review');
            Route::post('/{invoice}/email', [InvoicesController::class, 'email'])->middleware('throttle:10,1')->name('email');
        });
    });
});

// Admin da plataforma (equipe da Moduhub). Fora de workspace: os planos valem para todos.
Route::middleware(['auth', 'can:access-admin-panel'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/planos', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::get('/planos/novo', [AdminPlanController::class, 'create'])->name('plans.create');
    Route::post('/planos', [AdminPlanController::class, 'store'])->name('plans.store');
    Route::get('/planos/{plan}/editar', [AdminPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/planos/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

    Route::get('/certificado', [AdminPlatformCertificateController::class, 'index'])->name('platform-certificate.index');
    // Limite baixo: as duas pedem a senha da conta, e ninguém troca o certificado mais que uma vez por ano.
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/certificado', [AdminPlatformCertificateController::class, 'store'])->name('platform-certificate.store');
        Route::delete('/certificado', [AdminPlatformCertificateController::class, 'destroy'])->name('platform-certificate.destroy');
    });
});

require __DIR__.'/auth.php';
