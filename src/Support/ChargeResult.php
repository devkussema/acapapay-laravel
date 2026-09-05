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
     * As carteiras de criptomoeda suportadas pela RedotPay para esta cobrança
     * (Phantom, Rainbow, TronLink, etc.), tal como a RedotPay as devolveu —
     * cada uma com o seu nome, logótipo e os links de pagamento/QR.
     *
     * Só faz sentido chamar isto num ChargeResult de um método RDP
     * (RedotPay). Nos outros métodos (REF, GPO, EKZ) devolve um array vazio.
     *
     * Cada item tem, tipicamente, esta forma (nem todos os campos vêm sempre
     * preenchidos — depende da carteira):
     * [
     *     'id'       => 'phantom',
     *     'name'     => 'Phantom',
     *     'logo'     => 'https://.../phantom.svg',
     *     'webUrl'   => 'https://phantom.app/ul/browse/...',   // abrir num browser desktop
     *     'webQrCode'=> 'https://phantom.app/ul/browse/...',   // converter em QR (desktop)
     *     'h5Url'    => 'https://phantom.app/ul/browse/...',   // abrir num browser mobile
     *     'h5QrCode' => 'https://phantom.app/ul/browse/...',   // converter em QR (mobile/H5)
     *     'appUrl'   => 'https://phantom.app/ul/browse/...',   // deep-link direto para a app
     *     'appQrCode'=> null,
     * ]
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 1.2.0
     */
    public function paymentMethods(): array
    {
        return $this->payload['data']['paymentMethods'] ?? [];
    }

    /**
     * Atalho sobre paymentMethods(): só os links a converter em QR code,
     * indexados pelo id da carteira (ex: 'phantom', 'rainbow', 'tronlink').
     *
     * ⚠️ IMPORTANTE: estes valores são LINKS (deep-links), não imagens de QR
     * code prontas. A RedotPay não gera a imagem — quem gera é a tua
     * aplicação, a partir destes links. Ver a secção "Renderizar o QR code"
     * no README para os pacotes recomendados (PHP e JavaScript).
     *
     * Usa 'web' quando o utilizador está num ecrã de desktop (vai ver o QR e
     * digitalizá-lo com o telemóvel) e 'h5' quando está a navegar já a partir
     * do telemóvel (nesse caso normalmente nem mostras QR — usas antes um
     * botão que abre o link directamente, ver appUrl() abaixo).
     *
     * @return array<string, array{name: ?string, logo: ?string, web: ?string, h5: ?string}>
     *
     * @since 1.2.0
     */
    public function qrCodeUrls(): array
    {
        $urls = [];

        foreach ($this->paymentMethods() as $method) {
            $id = $method['id'] ?? null;

            if (!$id) {
                continue;
            }

            $urls[$id] = [
                'name' => $method['name'] ?? null,
                'logo' => $method['logo'] ?? null,
                'web' => $method['webQrCode'] ?? null,
                'h5' => $method['h5QrCode'] ?? null,
            ];
        }

        return $urls;
    }

    /**
     * O deep-link para abrir directamente a carteira $walletId (ex: 'phantom'),
     * a usar num botão "Abrir na app" quando o utilizador já está no telemóvel.
     *
     * @since 1.2.0
     */
    public function appUrl(string $walletId): ?string
    {
        foreach ($this->paymentMethods() as $method) {
            if (($method['id'] ?? null) === $walletId) {
                return $method['appUrl'] ?? null;
            }
        }

        return null;
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
