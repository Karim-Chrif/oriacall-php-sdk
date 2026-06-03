<?php

namespace Oriacall;

use RuntimeException;

class ApiError extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly int $status,
        string $code,
        string $message,
        public readonly ?array $response = null,
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
        public readonly ?array $details = null,
    ) {
        $this->errorCode = $code;
        parent::__construct($message, $status);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
