<?php

namespace AcapaPay\Laravel\Exceptions;

/**
 * O AcapaPay respondeu com um erro HTTP.
 *
 * Ao contrário da versão anterior do SDK — que colapsava tudo numa mensagem
 * genérica "Falha na comunicação... Status: 422" — esta excepção guarda o
 * estado HTTP, o corpo da resposta e a mensagem de erro do servidor, para a
 * tua app poder reagir de forma diferenciada.
 *
 * @since 1.2.0
 */
class ApiException extends AcapaPayException
{
    protected int $status;
    protected string $responseBody;
    protected ?string $apiError;
    protected string $uri;
    protected string $httpMethod;

    public function __construct(
        string $message,
        int $status,
        string $responseBody = '',
        ?string $apiError = null,
        string $httpMethod = '',
        string $uri = ''
    ) {
        parent::__construct($message);

        $this->status = $status;
        $this->responseBody = $responseBody;
        $this->apiError = $apiError;
        $this->httpMethod = $httpMethod;
        $this->uri = $uri;
    }

    /** Código HTTP devolvido pelo AcapaPay. */
    public function status(): int
    {
        return $this->status;
    }

    /** Corpo bruto da resposta. */
    public function body(): string
    {
        return $this->responseBody;
    }

    /** A mensagem do campo "error" devolvido pela API, quando existe. */
    public function apiError(): ?string
    {
        return $this->apiError;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function httpMethod(): string
    {
        return $this->httpMethod;
    }

    /** O erro é potencialmente transitório (vale a pena repetir)? */
    public function isRetryable(): bool
    {
        return $this->status >= 500;
    }
}
