<?php

namespace App\Services\Payments\Comgate;

/** A Comgate call or notification was refused. The message is a short code, never a vendor payload. */
final class ComgateException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus = 422, public readonly int $vendorCode = 0)
    {
        parent::__construct('comgate_'.$reason);
    }
}
