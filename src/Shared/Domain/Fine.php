<?php
declare(strict_types=1);


namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Fine {
    private readonly int $amount;
    private bool $writtenOff=false;
    
    public function __construct(
        private readonly string $loanId,
        private readonly string $memberId,
        private readonly \DateTimeImmutable $dateFined,
        int $daysInOverdue,

        
    ) {
        $this->amount = self::computeFine($daysInOverdue);
    }

    public static function computeFine(int $daysInOverdue):int {
        $fine = $daysInOverdue* LibraryPolicy::DAILY_FINE_RATE_IN_CENTS;
        if ($fine>LibraryPolicy::MAX_FINE_AMOUNT_IN_CENTS)
            return LibraryPolicy::MAX_FINE_AMOUNT_IN_CENTS;
        return $fine;
    }

    public function writeOff(ClockInterface $clock):void {
        if($this->writtenOff)
            throw new FineException("Fine already written off");
        if ($this->shouldWriteOff($clock))
            $this->writtenOff= true;
    }

    public function getAmount():int {
        return $this->amount;
    }

    protected function shouldWriteOff(ClockInterface $clock):bool {
        $diff = $clock->now()->diff($this->dateFined);
        if ($diff->days > LibraryPolicy::WRITE_OFF_PERIOD_IN_DAYS)
            return true;
        return false;
    }
}