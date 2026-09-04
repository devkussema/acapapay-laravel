<?php

namespace AcapaPay\Laravel\Exceptions;

/**
 * Operação não permitida (HTTP 403) — por exemplo, simular um pagamento
 * fora do ambiente sandbox.
 *
 * @since 1.2.0
 */
class ForbiddenException extends ApiException
{
}
