<?php

namespace AcapaPay\Laravel\Exceptions;

/**
 * Falha de rede/DNS/SSL a contactar o AcapaPay — nada chegou ao servidor.
 * É seguro voltar a tentar.
 *
 * @since 1.2.0
 */
class ConnectionException extends AcapaPayException
{
}
