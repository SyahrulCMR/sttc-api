<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domain-level exception for service-layer rule violations.
 * Rendered as a JSON error by the global exception handler.
 */
class BusinessException extends Exception
{
    public function __construct(
        string $message = 'Business rule violation.',
        protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        protected mixed $errors = null,
    ) {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrors(): mixed
    {
        return $this->errors;
    }
}
