<?php

declare(strict_types=1);

namespace Keboola\TableBackendUtils;

use Keboola\CommonExceptions\ApplicationExceptionInterface;
use RuntimeException;
use Throwable;

class ColumnException extends RuntimeException implements ApplicationExceptionInterface
{
    public const STRING_CODE_TO_MANY_COLUMNS = 'tooManyColumns';

    private string $stringCode;

    public function __construct(
        string $message,
        string $stringCode,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->stringCode = $stringCode;
    }

    public function getStringCode(): string
    {
        return $this->stringCode;
    }
}
