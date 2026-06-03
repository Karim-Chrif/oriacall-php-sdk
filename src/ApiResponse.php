<?php

namespace Oriacall;

class ApiResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly int $status,
        public readonly ?string $requestId = null,
    ) {}
}
