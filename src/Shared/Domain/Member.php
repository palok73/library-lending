<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Member extends Entity {
    
       
    public function __construct(
        string $id,
        private string $name, 
        private string $email,
        private LibraryCard $libraryCard,
        private array $loans = [],
        private array $fines = []) 
    {
        parent::__construct($id);
    }
   

    // Borrowing

    public function checkCanBorrow(ClockInterface $clock): void {
        if($this->countActiveLoans()>= LibraryPolicy::MAX_LOAN_PER_MEMBER) 
            throw new BorrowingException("Max. number of loans per member achieved,
                cant borrow next book, until member returns at least one book.");
            
        if ($this->libraryCard->isExpired($clock))
            throw new BorrowingException("Member's card has expired, can't make a new loan!");
            
        if ($this->totalFineBalance() > LibraryPolicy::MAX_UNPAID_FINES_IN_CENTS)
            throw new BorrowingException("Member owing on fees more then ".LibraryPolicy::MAX_UNPAID_FINES_IN_CENTS." Cents.
                Until he pay his debts, he can't make a new loan.");
            
    }
   
    public function borrowCopy(string $copyId, string $titleId,ClockInterface $clock,int $duePeriod): Loan {
        $this->checkCanBorrow($clock);
        $loan = $this->createLoan($titleId,$copyId,$clock,$duePeriod);
        $this->loans[] = $loan;
        return $loan;
    }

    // Loans 

     public function renewLoan(string $copyId, bool $titleReserved, bool $newRelease, ClockInterface $clock):void {
        if ($this->hasOverdueLoans($clock)) {
            throw new RenewalException("Member cant renew loan, because of loans in overdue.");
        }
        $loan = $this->findActiveLoanForCopyId($copyId);
        if ($loan===null)
                throw new RenewalException("No loan for this copyId: {$copyId} found.");
        $loan->renew($titleReserved,$newRelease,$clock);
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

    protected function createLoan(string $titleId,string $copyId,ClockInterface $clock,int $duePeriod): Loan {
        $loan = new Loan(
            Uuid::generate(),
            $this->getId(),
            $titleId,
            $copyId,
            $clock->now(),
            $clock->now()->modify("+{$duePeriod} days"),
        );
        return $loan;
    }

    protected function findActiveLoanForCopyId(string $copyId):?Loan {
        foreach($this->loans as $loan) {
            /* @var $loan Loan */
            if ($copyId === $loan->getCopyId() && $loan->isActive())
                return $loan;
        }
        return null;
    }

    protected function hasOverdueLoans(ClockInterface $clock): bool {
        foreach($this->loans as $loan) {
            if ($loan->isOverdue($clock) && $loan->isActive())
                return true;
        }
        return false;
    }

    // Returning

    public function returnCopy(string $copyId,ClockInterface $clock): void {
        $loan = $this->findActiveLoanForCopyId($copyId);
        if ($loan===null)
                throw new ReturningException("No loan for this copyId: {$copyId} found.");
        if ($loan->isOverdue($clock)) {
            $fine = $this->createFineForLoan($loan,$clock);
            $this->fines[] = $fine;
        }
        $loan->return($clock);
    }

    // Fines & Payments

    protected function createFineForLoan(Loan $loan,ClockInterface $clock): Fine {
        if(!$loan->isOverdue($clock)) 
            throw new FineException("Loan is not in overdue.");
        $fine =  Fine::forOverdueLoan(
            Uuid::generate(),
            $loan->getId(),
            $this->getId(),
            $clock->now(),
            $loan->daysInOverdue($clock)
        );
        return $fine;
    }

    public function totalFineBalance(): int {
        $amount = 0;
        foreach($this->fines as $fine) {
            $amount+=$fine->getOutstanding();
        }
        return $amount;
    }

    public function writeOffOldFines(ClockInterface $clock):void {
        foreach($this->fines as $fine) {
            $fine->writeOffIfOld($clock);
        }
    }

    public function payFines(int $amount): void {
        if ($amount > $this->totalFineBalance()) {
            throw new FineException("Payment is larger then total fine amount.");
        }
        if($amount===0) {
            throw new FineException("Zero payment?");
        }
        $remainder = $amount;
        foreach($this->fines as $fine) {
            if ($fine->isWrittenOff() || $fine->isPaid())
                continue;
            $remainder = $fine->applyPayment($remainder);
            if ($remainder==0) break;
        }
    }

}