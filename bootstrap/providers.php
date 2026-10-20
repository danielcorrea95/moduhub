<?php

use App\Modules\Invoices\Providers\InvoicesServiceProvider;
use App\Modules\Mailing\Providers\MailingServiceProvider;
use App\Modules\Signatures\Providers\SignaturesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SignaturesServiceProvider::class,
    InvoicesServiceProvider::class,
    MailingServiceProvider::class,
];
