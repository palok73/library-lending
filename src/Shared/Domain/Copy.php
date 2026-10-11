<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use LibraryLanding\Borrowing\BorrowingException;

// every copy has its loan and reservation
class Copy extends Entity {
    
    
    public function __construct(
        private string $id,
        private string $titleId,
        private bool $available=true) {
    }

    
    public function isAvailable() {
        return $this->available;
    }

    public function markAsBorrowed() {
        if (!$this->available) {
            throw new BorrowingException("Copy already lent, can't lend again!");
        }
        $this->available = false;
    }

    public function markAsAvailable() {
        if ($this->available)
            throw new BorrowingException("Copy not lent, can't return!");
        $this->available = true;
    }

}