<?php
declare(strict_types=1);


namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Fine extends Entity {
    private readonly int $amount;
    private int $amountPaid;
    private bool $writtenOff;
    
    public function __construct(
        string $id,
        private readonly string $loanId,
        private readonly string $memberId,
        private readonly \DateTimeImmutable $dateFined,
        int $amount,
        int $amountPaid = 0,
        bool $writtenOff = false
    ) {
        parent::__construct($id);
        $this->amount = $amount;
        $this->amountPaid = $amountPaid;
        $this->writtenOff = $writtenOff;
    }

    public static function forOverdueLoan(
        string $id,string $loanId,string $memberId,\DateTimeImmutable $dateFined,int $daysInOverdue):self {
        $computedFine = self::computeFine($daysInOverdue);
        $fine = new self($id,$loanId,$memberId,$dateFined,$computedFine);
        return $fine;
    }

    public function getAmount():int {
        return $this->amount;
    }

    public function getAmountPaid():int {
        return $this->amountPaid;
    }

    public function getOutstanding(): int {
        if($this->writtenOff) return 0;
        return $this->amount - $this->amountPaid;
    }

    public function isWrittenOff(): bool {
        return $this->writtenOff;
    }

    public function applyPayment(int $amount):int {
        if($amount<=0) {
            throw new FineException("Payment must be positive amount in cents.");
        }
        if ($this->writtenOff || $this->isPaid()) {
            return $amount;
        }
        if($this->getOutstanding() >= $amount) {
            $this->amountPaid +=$amount;
            return 0;
        }
        $remainder = $amount - $this->getOutstanding();
        $this->amountPaid = $this->amount;
        return $remainder;
    }

    public function isPaid():bool {
        return ($this->amount===$this->amountPaid);
    }

    public static function computeFine(int $daysInOverdue):int {
        $fine = $daysInOverdue* LibraryPolicy::DAILY_FINE_RATE_IN_CENTS;
        if ($fine>LibraryPolicy::MAX_FINE_AMOUNT_IN_CENTS)
            return LibraryPolicy::MAX_FINE_AMOUNT_IN_CENTS;
        return $fine;
    }

    public function writeOffIfOld(ClockInterface $clock):void {
        if ($this->shouldWriteOff($clock))
            $this->writtenOff= true;
    }

    
    public function shouldWriteOff(ClockInterface $clock):bool {
        if ($this->writtenOff || $this->isPaid()) return false;
        return ($this->dateFined->modify("+".LibraryPolicy::WRITE_OFF_PERIOD_IN_YEARS." years") < $clock->now());
        
    }
}