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

    /** Multicaixa Express via Deeplink (Angola, AOA). Abre a app Multicaixa Express. Validade: 15 minutos. @since 1.6.0 */
    public const GPO_DEEPLINK = 'GPO_DEEPLINK';

    /** Multicaixa Express via Código QR EMIS (Angola, AOA). Validade: 3 dias. @since 1.6.0 */
    public const GPO_QR = 'GPO_QR';

    /** E-Kwanza (Angola, AOA). Validade: 5 minutos. */
    public const EKZ = 'EKZ';

    /** RedotPay — criptomoedas/stablecoins liquidadas em USD. Validade: 1 hora. */
    public const RDP = 'RDP';

    /**
     * WiPay — checkout hospedado (Angola, AOA). Sem QR code e sem endpoint de
     * consulta de estado: só o webhook `invoice.paid` confirma o pagamento.
     *
     * @since 1.4.0
     */
    public const WIP = 'WIP';

    /**
     * Todos os métodos suportados.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::REF, self::GPO, self::GPO_DEEPLINK, self::GPO_QR, self::EKZ, self::RDP, self::WIP];
    }

    public static function isValid(string $method): bool
    {
        return in_array($method, self::all(), true);
    }

    /**
     * Métodos que exigem o número de telemóvel do cliente.
     * Nota: GPO_DEEPLINK e GPO_QR não necessitam de número de telemóvel prévio.
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
            self::GPO => 'Multicaixa Express (Telefone)',
            self::GPO_DEEPLINK => 'Multicaixa Express (Deeplink)',
            self::GPO_QR => 'Multicaixa Express (Código QR EMIS)',
            self::EKZ => 'E-Kwanza',
            self::RDP => 'Criptomoeda (RedotPay)',
            self::WIP => 'WiPay',
            default => $method,
        };
    }

    /**
     * Indica se o método suporta estorno/reembolso programático direto via gateway.
     *
     * @since 1.5.0
     */
    public static function supportsRefund(string $method): bool
    {
        return in_array($method, [self::GPO, self::GPO_DEEPLINK, self::GPO_QR], true);
    }
}
