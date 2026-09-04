<?php

namespace AcapaPay\Laravel\Enums;

/**
 * Métodos de pagamento suportados pelo AcapaPay.
 *
 * São constantes de string (e não um enum nativo) de propósito: continuam a ser
 * strings simples, pelo que podem ser passadas a qualquer método do SDK que já
 * aceite `?string $preferredMethod` — quem hoje escreve 'RDP' à mão não precisa
 * de mudar nada.
 *
 * @since 1.2.0
 */
final class PaymentMethod
{
    /** Referência Multicaixa (Angola, AOA). Validade: 3 dias. */
    public const REF = 'REF';

    /** Multicaixa Express (Angola, AOA). Validade: 60 segundos. */
    public const GPO = 'GPO';

    /** E-Kwanza (Angola, AOA). Validade: 5 minutos. */
    public const EKZ = 'EKZ';

    /** RedotPay — criptomoedas/stablecoins liquidadas em USD. Validade: 1 hora. */
    public const RDP = 'RDP';

    /**
     * Todos os métodos suportados.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::REF, self::GPO, self::EKZ, self::RDP];
    }

    public static function isValid(string $method): bool
    {
        return in_array($method, self::all(), true);
    }

    /**
     * Métodos que exigem o número de telemóvel do cliente.
     */
    public static function requiresPhoneNumber(string $method): bool
    {
        return in_array($method, [self::GPO, self::EKZ], true);
    }

    /**
     * Métodos que exigem que a fatura esteja em USD.
     */
    public static function requiresUsd(string $method): bool
    {
        return $method === self::RDP;
    }

    /**
     * Nome legível para apresentar ao utilizador.
     */
    public static function label(string $method): string
    {
        return match ($method) {
            self::REF => 'Referência Multicaixa',
            self::GPO => 'Multicaixa Express',
            self::EKZ => 'E-Kwanza',
            self::RDP => 'Criptomoeda (RedotPay)',
            default => $method,
        };
    }
}
