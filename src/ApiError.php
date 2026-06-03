<?php

namespace Oriacall;

use RuntimeException;

class ApiError extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $code,
        string $message,
        public readonly ?array $response = null,
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message, $status);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
