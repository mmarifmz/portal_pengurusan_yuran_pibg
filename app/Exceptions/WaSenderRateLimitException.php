<?php

namespace App\Exceptions;

use RuntimeException;

class WaSenderRateLimitException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $providerResponse
     * @param  array<string, string>  $responseHeaders
     */
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds,
        public readonly array $providerResponse = [],
        public readonly array $responseHeaders = []
    ) {
        parent::__construct($message);
    }
}
