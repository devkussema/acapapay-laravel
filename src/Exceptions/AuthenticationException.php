<?php

namespace AcapaPay\Laravel\Exceptions;

/**
 * Falha ao obter (ou renovar) o token OAuth2 junto do SSO.
 *
 * Normalmente significa ACAPAPAY_CLIENT_ID/ACAPAPAY_CLIENT_SECRET errados
 * ou a App Satélite desativada no painel de Developer.
 *
 * @since 1.2.0
 */
class AuthenticationException extends AcapaPayException
{
}
