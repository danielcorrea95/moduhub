<?php

namespace App\Modules\Billing\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Dados de cobrança do workspace e o cliente correspondente no Asaas. Um por workspace. */
class BillingAccount extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'tenant_id',
        'gateway_customer_id',
        'name',
        'tax_id',
        'email',
        'phone',
        'postal_code',
        'address_number',
        'address_complement',
    ];
}
