<?php

namespace AcapaPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string checkoutSession(string|int $userId, string $planReference, array $metadata = [], ?string $successUrl = null, ?string $cancelUrl = null, ?string $currency = null, ?string $preferredMethod = null)
 * @method static string createInvoice(string|int $userId, float $amount, string $description, string $currency = 'USD', ?string $preferredMethod = null, array $metadata = [], ?string $successUrl = null, ?string $cancelUrl = null)
 * @method static array getInvoiceStatus(string $invoiceId)
 * @method static array syncPlans(array $plans)
 * @method static \AcapaPay\Laravel\Http\DirectPaymentApi direct()
 * @method static \AcapaPay\Laravel\Http\DirectPaymentApi invoices()
 * @method static \AcapaPay\Laravel\Support\ChargeResult createCryptoCharge(array $invoiceAttributes)
 * @method static array ping()
 *
 * @see \AcapaPay\Laravel\AcapaPayManager
 */
class AcapaPay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'acapapay';
    }
}
