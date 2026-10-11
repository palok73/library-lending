<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class LibraryCard {
    private \DateTimeImmutable $expiryDate;
    public function __construct(
        private string $cardNumber,
        private string $memberId,
        \DateTimeInterface $expiryDate) {
        $this->expiryDate = \DateTimeImmutable::createFromInterface($expiryDate);
    }
    
    public function getCardNumber(): string {
        return $this->cardNumber;
    }

    
    public function getMemberId(): string {
        return $this->memberId;
    }

    public function getExpiryDate(): \DateTimeInterface {
        return $this->expiryDate;
    }

    public function isExpired(ClockInterface $clock): bool {
        
        return $this->expiryDate < $clock->now();
    }   
    
}