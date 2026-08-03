<?php

namespace AcapaPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string checkoutSession(string|int $userId, string $planReference, array $metadata = [], string $successUrl = null, string $cancelUrl = null)
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
