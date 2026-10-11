<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Loan extends Entity
{
    private string $memberId;
    private string $titleId;
    private string $copyId;
    private \DateTimeImmutable $dueDate;
    private ?\DateTimeImmutable $returnedDate;
    private \DateTimeImmutable $borrowedDate;
    private int $renewCount;

    public function __construct(
        string $id,
        string $memberId,
        string $titleId,
        string $copyId,
        \DateTimeInterface $borrowedDate,
        \DateTimeInterface $dueDate,
        int $renewCount=0,
        ?\DateTimeInterface $returnedDate = null,
    ) {
        parent::__construct($id);
        $this->memberId = $memberId;
        $this->titleId = $titleId;
        $this->copyId = $copyId;
        $this->renewCount = $renewCount;
        $this->borrowedDate = \DateTimeImmutable::createFromInterface($borrowedDate);
        $this->dueDate = \DateTimeImmutable::createFromInterface($dueDate);
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


    public function getDueDate():\DateTimeImmutable {
        return $this->dueDate;
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

    // Returning

    public function return(ClockInterface $clock) {
        if($this->returnedDate!=null) {
            throw new ReturningException("Loan already returned on: ".$this->returnedDate->format('d.m.Y').".");
        }
        $this->returnedDate = $clock->now();
    }
   
    //Renewal

    public function renew(bool $reserved, bool $newRelease,ClockInterface $clock) {
        if ($this->isReturned())
            throw new RenewalException("Copy already returned.");
        if ($reserved)
            throw new RenewalException("Title is reserved, can't renew.");
        if ($newRelease) 
            throw new RenewalException("Cant renew new relase Title!");
        if ($this->isOverdue($clock))
            throw new RenewalException("Loan in overdue.");
        if ($this->renewCount >= LibraryPolicy::MAX_RENEW_COUNT)
            throw new RenewalException("Loan already renewed {$this->renewCount} times.");

        $this->dueDate = $clock->now()->modify("+".LibraryPolicy::DUE_DAYS_STANDARD." days");
        $this->renewCount+=1;
    }

    public function daysInOverdue(ClockInterface $clock): int {
        $borrowedUntil = ($this->returnedDate !== null) ? $this->returnedDate : $clock->now();
        $diff = $this->dueDate->diff($borrowedUntil);
        if ( $this->dueDate > $borrowedUntil)
            return 0;
        return $diff->days;
    }
}