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
        private array $fines = [],
        private int $amountOwnedInCents=0) 
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
            
        if ($this->amountOwnedInCents > LibraryPolicy::MAX_NEGATIVE_BALANCE_IN_CENTS)
            throw new BorrowingException("Member has outstanding negative balance lower then ".LibraryPolicy::MAX_NEGATIVE_BALANCE_IN_CENTS." Cents.
                Until he pay his debts, he can't make new loan.");
            
    }
   
    public function borrowCopy(string $copyId, string $titleId,ClockInterface $clock,int $duePeriod): Loan {
        $this->checkCanBorrow($clock);
        $loan = $this->createLoan($titleId,$copyId,$clock,$duePeriod);
        $this->loans[] = $loan;
        return $loan;
    }

    // Loans 

     public function renewLoan(string $titleId,bool $titleReserved,string $copyId, ClockInterface $clock):void {
        if ($titleReserved)
            throw new RenewalException("Title: $titleId is reserved, can't renew;");
        $loan = $this->findActiveLoanForCopyId($copyId);
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

    protected function createLoan(string $titleId,string $copyId,ClockInterface $clock,int $duePeriod): Loan {
        $loan = new Loan(
            Uuid::generate(),
            $this->getId(),
            $titleId,
            $copyId,
            $clock->now(),
            $duePeriod,
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

    // Returning

    public function returnCopy(string $copyId,ClockInterface $clock): void {
        $loan = $this->findActiveLoanForCopyId($copyId);
        if ($loan===null)
                throw new ReturningException("No loan for this copyId: {$copyId} found.");
        if ($loan->isOverdue($clock)) {
            $fine = $this->createFineForLoan($loan,$clock);
            $this->fines[] = $fine;
            $this->fine($fine->getAmount());
        }
        $loan->return($clock);
    }

    // Fines & Payments

    protected function createFineForLoan(Loan $loan,ClockInterface $clock): Fine {
        if(!$loan->isOverdue($clock)) 
            throw new FineException("Loan is not in overdue.");
        $fine = new Fine(
            Uuid::generate(),
            $loan->getId(),
            $this->getId(),
            $clock->now(),
            $loan->daysInOverdue($clock)
        );
        return $fine;
    }

    public function fine(int $amountInCents): void {
        $this->amountOwnedInCents += $amountInCents;
    }

    public function pay(int $amountInCents): void {
        $this->amountOwnedInCents -= $amountInCents;
    }

}