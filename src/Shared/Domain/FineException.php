<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class FineException extends \RuntimeException {
    public function __construct(string $message = "")
    {
        parent::__construct("Fine error: ".$message);
    }
}