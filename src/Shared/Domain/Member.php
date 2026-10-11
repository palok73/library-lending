<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Member extends Entity {
    
       
    public function __construct(
        private string $name, 
        private string $email,
        private LibraryCard $libraryCard,
        private array $loans = [],
        private array $reservations = [],
        private array $fines = [],
        private int $amountOwnedInCents=0) {}
   

    // Borrowing

    public function canBorrow(ClockInterface $clock): bool {
        return (
            $this->countActiveLoans() < LibraryPolicy::MAX_LOAN_PER_MEMBER
            && !$this->libraryCard->isExpired($clock)
            && $this->amountOwnedInCents <= LibraryPolicy::MAX_NEGATIVE_BALANCE_IN_CENTS
        );
    }

    public function whyCantBorrow(ClockInterface $clock):array {
        $reasons = [];
        if($this->countActiveLoans()>= LibraryPolicy::MAX_LOAN_PER_MEMBER) 
            $reasons[] = "Max. number of loans per member achieved,
                cant borrow next book, until member returns at least one book.";
            
        if ($this->libraryCard->isExpired($clock))
            $reasons[] = "Member's card has expired, cant make new loan!";
            
        if ($this->amountOwnedInCents > LibraryPolicy::MAX_NEGATIVE_BALANCE_IN_CENTS)
            $reasons[] ="Member has outstanding negative balance lower then {LibraryPolicy::MAX_NEGATIVE_BALANCE_IN_CENTS} Cents.
                Until he pay his debts.";
            
        return $reasons;
    }

    public function borrowCopy(Copy $copy, Title $title, ClockInterface $clock): Loan {
        $this->clearMembersReservationForTitle($title);
        $loan = $this->createLoan($title,$copy,$clock);
        $this->loans[] = $loan;
        return $loan;
    }

    // Loans 

     public function renewLoan(Title $title,string $copyId, ClockInterface $clock):void {
        if ($title->isReserved())
            throw new RenewalException("Title: {$title->getTitle()} is reserved, can't renew;");
        $loan = $this->findLoanForCopyId($copyId);
        $loan->renew($clock);
    }

    protected function countActiveLoans():int {
        $countLoans = 0;
        foreach($this->loans as $loan) {
            /* @var $loan Loan */
            if($loan->isActive())
                $countLoans++;
        }
        return $countLoans;
    }

    public function createLoan(Title $title,Copy $copyToBorrow,ClockInterface $clock): Loan {
        $loan = new Loan(
            $this->getId(),
            $title->getId(),
            $copyToBorrow->getId(),
            $clock->now(),
            $title->duePeriod($clock)
        );
        return $loan;
    }

    protected function findLoanForCopyId(string $copyId):?Loan {
        foreach($this->loans as $loan) {
            /* @var $loan Loan */
            if ($copyId===$loan->getCopyId())
                return $loan;
        }
        return null;
    }

    protected function removeLoanByCopyId(string $copyId):bool {
        $index=0;
        foreach($this->loans as $loan) {
            /* @var $loan Loan */
            if ($copyId===$loan->getCopyId()) {
                unset($this->loans[$index]);
                return true;
            }
            $index++;
        }
        return false;
    }

    // Returning

    public function returnCopy(Copy $copy,ClockInterface $clock): void {
        $loan = $this->findLoanForCopyId($copy->getId());
        if ($loan===null)
                throw new ReturningException("No loan for this copyId: {$copy->getId()} found.");
        if ($loan->isOverdue($clock)) {
            $fine = $this->createFineForLoan($loan,$clock);
            $this->fines[] = $fine;
            $this->fine($fine->getAmount());
        }
        $copy->markAsAvailable();
        $loan->return($clock);
    }

    // Reservations

    public function reserveTitle(Title $title, ClockInterface $clock) {
        $this->checkCanReserve($title);
        $reservation = $title->makeReservationForMember($this->getId(),$clock);
        $this->reservations[] = $reservation;
    }
    
    public function checkCanReserve(Title $title) {
        if($title->availableCopiesForBorrowing()) {
            throw new ReservationException("Cant reserve when copies are available");
        }
        if(\count($this->reservations)> LibraryPolicy::MAX_RESERVATIONS_PER_MEMBER) {
            throw new ReservationException("Cant make new reservation, limit for reservations per member exceeded.");
        }

    }

    public function clearMembersReservationForTitle(Title $title):void {
        $index=0;
        foreach($this->reservations as $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->titleId===$title->getId()) {
                unset($this->reservations[$index]);
            }
            $index++;
        }
    }

    // Fines & Payments

    protected function createFineForLoan(Loan $loan,ClockInterface $clock): Fine {
        if(!$loan->isOverdue($clock)) 
            throw new FineException("Loan is not in overdue.");
        $fine = new Fine($loan->getId(),$this->getId(),$clock->now(),$loan->daysInOverdue($clock));
        return $fine;
    }

    public function fine(int $amountInCents): void {
        $this->amountOwnedInCents += $amountInCents;
    }

    public function pay(int $amountInCents): void {
        $this->amountOwnedInCents -= $amountInCents;
    }

}