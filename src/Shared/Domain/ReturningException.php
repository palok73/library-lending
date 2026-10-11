<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class ReturningException extends \RuntimeException {

    public function __construct(string $message = "") {
        parent::__construct("Error returning book: ".$message);
    }
}