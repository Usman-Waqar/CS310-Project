<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class LedgerException extends RuntimeException implements ShouldntReport
{
    public readonly string $errorCode;

    public readonly int $httpStatus;

    public function __construct(
        ErrorCode|string $errorCode,
        string $message,
        ?int $httpStatus = null,
    ) {
        $this->errorCode = $errorCode instanceof ErrorCode ? $errorCode->value : $errorCode;
        $this->httpStatus = $httpStatus ?? ($errorCode instanceof ErrorCode ? $errorCode->defaultHttpStatus() : 422);

        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ErrorCode::tryFrom($this->errorCode)?->toResponse($this->getMessage(), $this->httpStatus)
            ?? response()->json([
                'error_code' => $this->errorCode,
                'message' => $this->getMessage(),
            ], $this->httpStatus);
    }
}
