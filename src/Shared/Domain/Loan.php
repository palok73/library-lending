<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Loan extends Entity
{
    private ?\DateTimeImmutable $returnedDate;
    private int $renewCount=0;

    public function __construct(
        string $id,
        private string $memberId,
        private string $titleId,
        private string $copyId,
        private \DateTimeImmutable $borrowedDate,
        private int $duePeriod,
        ?\DateTimeInterface $returnedDate = null,
    ) {
        parent::__construct($id);
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

    public function getRenewCount():int {
        return $this->renewCount;
    }

    public function getBorrowedDate():\DateTimeImmutable {
        return $this->borrowedDate;
    }

    public function getReturnedDate():?\DateTimeImmutable {
        return $this->returnedDate;
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
        if($this->returnedDate!=null) {
            throw new ReturningException("Loan already returned on: ".$this->returnedDate->format('d.m.Y'));
        }
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
        $borrowedUntil = ($this->returnedDate !== null) ? $this->returnedDate : $clock->now();
        $diff = $dueDate->diff($borrowedUntil);
        if ( $dueDate > $borrowedUntil)
            return 0;
        return $diff->days;
    }
}