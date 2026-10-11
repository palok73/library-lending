<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class LibraryPolicyException extends \RuntimeException {

    public function __construct(string $message = "") {
        parent::__construct("Library policy violation: ".$message);
    }
}