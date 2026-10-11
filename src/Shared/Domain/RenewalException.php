<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class RenewalException extends \RuntimeException {
    public function __construct(string $message = "") {
        parent::__construct("Can't renew: ".$message);
    }
}