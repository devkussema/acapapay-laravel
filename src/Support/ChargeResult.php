<?php

namespace AcapaPay\Laravel\Support;

/**
 * Resultado de uma cobrança criada pela API de pagamento direta.
 *
 * Implementa ArrayAccess, por isso podes tratá-lo exactamente como o array
 * que a API devolve (`$result['data']['pay_url']`) ou usar os métodos, que
 * são mais legíveis (`$result->payUrl()`).
 *
 * @since 1.2.0
 */
final class ChargeResult implements \ArrayAccess, \JsonSerializable
{
    /** @var array<string, mixed> */
    private array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /**
     * ID da fatura no AcapaPay (quando conhecido).
     */
    public function invoiceId(): ?string
    {
        return $this->payload['invoice_id'] ?? null;
    }

    /**
     * URL de pagamento — no caso da RedotPay, a página de checkout em cripto
     * para onde deves enviar o utilizador.
     */
    public function payUrl(): ?string
    {
        return $this->payload['data']['pay_url'] ?? $this->payload['pay_url'] ?? null;
    }

    /**
     * Método usado: 'REF', 'GPO', 'EKZ' ou 'RDP'.
     */
    public function paymentMethod(): ?string
    {
        return $this->payload['payment_method'] ?? null;
    }

    /**
     * A cobrança foi simulada (ambiente sandbox), sem tocar no gateway real?
     */
    public function isMock(): bool
    {
        return (bool) ($this->payload['is_mock'] ?? false);
    }

    /**
     * Prazo de validade da cobrança, em ISO 8601.
     *
     * Varia com o método: Referência ~3 dias, Multicaixa Express 60 segundos,
     * E-Kwanza 5 minutos, RedotPay 1 hora.
     */
    public function expiresAt(): ?string
    {
        return $this->payload['expires_at'] ?? null;
    }

    /**
     * Referência Multicaixa a mostrar ao utilizador (métodos REF).
     */
    public function reference(): ?string
    {
        return $this->payload['data']['reference'] ?? null;
    }

    /**
     * Entidade Multicaixa (métodos REF).
     */
    public function entity(): ?string
    {
        return $this->payload['data']['entity'] ?? null;
    }

    /**
     * Payload bruto devolvido pelo gateway.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->payload['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function jsonSerialize(): mixed
    {
        return $this->payload;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->payload[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->payload[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->payload[] = $value;

            return;
        }

        $this->payload[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->payload[$offset]);
    }
}
