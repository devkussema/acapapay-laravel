<?php

namespace AcapaPay\Laravel\Exceptions;

/**
 * Excepção base de todo o SDK.
 *
 * Estende \RuntimeException (e portanto \Exception), pelo que qualquer
 * `catch (\Exception $e)` já existente na tua app continua a apanhá-la.
 *
 * @since 1.2.0
 */
class AcapaPayException extends \RuntimeException
{
}
