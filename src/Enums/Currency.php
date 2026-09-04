<?php

namespace AcapaPay\Laravel\Enums;

/**
 * Moedas suportadas pelo AcapaPay.
 *
 * @since 1.2.0
 */
final class Currency
{
    /** Kwanza angolano — Multicaixa (REF/GPO) e E-Kwanza (EKZ). */
    public const AOA = 'AOA';

    /** Dólar americano — obrigatório para pagamentos em cripto via RedotPay (RDP). */
    public const USD = 'USD';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::AOA, self::USD];
    }

    public static function isValid(string $currency): bool
    {
        return in_array(strtoupper($currency), self::all(), true);
    }
}
