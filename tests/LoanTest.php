<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use LibraryLanding\Shared\Domain\Loan;
use LibraryLanding\Shared\Domain\ReturningException;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    private function loanBorrowedOn(string $date, int $duePeriod = 28): Loan
    {
        return new Loan('loan-1', 'member-1', 'title-1', 'copy-1', new DateTimeImmutable($date), $duePeriod);
    }

    private function clockAt(string $date): FixedClock
    {
        return new FixedClock(new DateTimeImmutable($date));
    }

    public function test_a_loan_is_not_overdue_before_its_due_date(): void
    {
        $loan = $this->loanBorrowedOn('2026-10-10');

        $this->assertFalse($loan->isOverdue($this->clockAt('2026-10-11')));
        $this->assertSame(0, $loan->daysInOverdue($this->clockAt('2026-10-11')));
    }

    public function test_a_loan_is_not_overdue_on_its_due_date(): void
    {
        $loan = $this->loanBorrowedOn('2026-09-01');

        $this->assertFalse($loan->isOverdue($this->clockAt('2026-09-29')));
    }

    public function test_a_loan_is_one_day_overdue_the_day_after_its_due_date(): void
    {
        $loan = $this->loanBorrowedOn('2026-09-01');

        $this->assertSame(1, $loan->daysInOverdue($this->clockAt('2026-09-30')));
    }

    public function test_a_returned_loan_counts_overdue_days_up_to_its_return_date(): void
    {
        $loan = $this->loanBorrowedOn('2026-09-01');
        $loan->return($this->clockAt('2026-10-01'));

        $this->assertSame(2, $loan->daysInOverdue($this->clockAt('2027-01-01')));
    }

    public function test_a_returned_loan_is_not_active(): void
    {
        $loan = $this->loanBorrowedOn('2026-09-01');
        $this->assertTrue($loan->isActive());

        $loan->return($this->clockAt('2026-09-10'));

        $this->assertTrue($loan->isReturned());
        $this->assertFalse($loan->isActive());
    }

    public function test_a_loan_cannot_be_returned_twice(): void
    {
        $loan = $this->loanBorrowedOn('2026-09-01');
        $loan->return($this->clockAt('2026-09-10'));

        $this->expectException(ReturningException::class);
        $loan->return($this->clockAt('2026-09-11'));
    }
}
