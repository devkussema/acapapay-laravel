<?php

namespace AcapaPay\Laravel\Exceptions;

/**
 * O AcapaPay rejeitou os dados enviados (HTTP 422), ou o SDK detetou o
 * problema antes de fazer o pedido.
 *
 * @since 1.2.0
 */
class ValidationException extends ApiException
{
    /** @var array<string, mixed> */
    protected array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message,
        array $errors = [],
        int $status = 422,
        string $responseBody = '',
        ?string $apiError = null,
        string $httpMethod = '',
        string $uri = ''
    ) {
        parent::__construct($message, $status, $responseBody, $apiError, $httpMethod, $uri);

        $this->errors = $errors;
    }

    /**
     * Erros por campo, tal como devolvidos pelo Laravel do lado do servidor.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
