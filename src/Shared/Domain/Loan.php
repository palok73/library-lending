<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Loan extends Entity
{
    public ?\DateTimeImmutable $returnedDate;
    public int $renewCount=0;

    public function __construct(
        private string $memberId,
        private string $titleId,
        private string $copyId,
        public \DateTimeImmutable $borrowedDate,
        public int $duePeriod,
        ?\DateTimeInterface $returnedDate = null,
    ) {
        $this->borrowedDate = \DateTimeImmutable::createFromInterface($borrowedDate);
        $this->returnedDate = $returnedDate ? \DateTimeImmutable::createFromInterface($returnedDate) : null;
    }

    public function getMemberId():string {
        return $this->memberId;
    }

    public function getTitleId():string {
        return $this->titleId;
    }

    public function getCopyId():string {
        return $this->copyId;
    }

    public function isOverdue(ClockInterface $clock): bool
    {
        return ($this->daysInOverdue($clock)>0);
    }

    public function isActive() {
        return $this->returnedDate === null;
    }

    public function isReturned() {
        return !$this->isActive();
    }

    public function return(ClockInterface $clock) {
        $this->returnedDate = $clock->now();
    }
   
    public function renew(ClockInterface $clock) {
        if ($this->isReturned())
            throw new RenewalException("Copy already returned.");
        if ($this->isOverdue($clock))
            throw new RenewalException("Loan in overdue.");
        if ($this->renewCount>=LibraryPolicy::MAX_RENEW_COUNT)
            throw new RenewalException("Loan already renewed {$this->renewCount} times");
        $this->duePeriod+=LibraryPolicy::DUE_DAYS_STANDARD;
        $this->renewCount+=1;
    }

    public function daysInOverdue(ClockInterface $clock): int {
        $dueDate = $this->borrowedDate->modify("+{$this->duePeriod} days");
        $diff = ($this->returnedDate !== null) ? 
            $this->returnedDate->diff($dueDate) 
            : $clock->now()->diff($dueDate);
        
        if ($diff->days < 0)
            return 0;
        return $diff->days;
    }
}