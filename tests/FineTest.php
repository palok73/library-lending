<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use LibraryLanding\Shared\Domain\Fine;
use LibraryLanding\Shared\Domain\FineException;
use PHPUnit\Framework\TestCase;

final class FineTest extends TestCase
{
    public function test_a_fine_is_20_cents_per_day_late(): void
    {
        $this->assertSame(20, Fine::computeFine(1));
        $this->assertSame(500, Fine::computeFine(25));
    }

    public function test_a_fine_is_capped_at_10_euro_per_loan(): void
    {
        $this->assertSame(1000, Fine::computeFine(50));
        $this->assertSame(1000, Fine::computeFine(51));
        $this->assertSame(1000, Fine::computeFine(365));
    }

    private function fineOf(int $cents, string $dateFined = '2026-10-01', int $paid = 0, bool $writtenOff = false): Fine
    {
        return new Fine('fine-1', 'loan-1', 'member-1', new DateTimeImmutable($dateFined), $cents, $paid, $writtenOff);
    }

    private function clockAt(string $date): FixedClock
    {
        return new FixedClock(new DateTimeImmutable($date));
    }

    public function test_a_fine_for_an_overdue_loan_is_computed_from_the_days_late(): void
    {
        $fine = Fine::forOverdueLoan('fine-1', 'loan-1', 'member-1', new DateTimeImmutable('2026-10-01'), 3);

        $this->assertSame(60, $fine->getAmount());
        $this->assertSame(60, $fine->getOutstanding());
    }

    public function test_a_partial_payment_reduces_the_outstanding_amount(): void
    {
        $fine = $this->fineOf(500);

        $remainder = $fine->applyPayment(200);

        $this->assertSame(0, $remainder);
        $this->assertSame(200, $fine->getAmountPaid());
        $this->assertSame(300, $fine->getOutstanding());
        $this->assertFalse($fine->isPaid());
    }

    public function test_paying_the_full_amount_settles_the_fine(): void
    {
        $fine = $this->fineOf(500);

        $fine->applyPayment(500);

        $this->assertTrue($fine->isPaid());
        $this->assertSame(0, $fine->getOutstanding());
    }

    public function test_a_payment_above_the_outstanding_amount_returns_the_rest(): void
    {
        $fine = $this->fineOf(500, paid: 300);

        $remainder = $fine->applyPayment(500);

        $this->assertSame(300, $remainder);
        $this->assertTrue($fine->isPaid());
    }

    public function test_a_payment_must_be_positive(): void
    {
        $fine = $this->fineOf(500);

        $this->expectException(FineException::class);
        $fine->applyPayment(0);
    }

    public function test_a_written_off_fine_has_nothing_outstanding_and_takes_no_payment(): void
    {
        $fine = $this->fineOf(500, writtenOff: true);

        $this->assertSame(0, $fine->getOutstanding());
        $this->assertSame(100, $fine->applyPayment(100));
        $this->assertSame(0, $fine->getAmountPaid());
    }

    public function test_a_fine_is_written_off_after_two_years(): void
    {
        $fine = $this->fineOf(500, '2024-10-01');

        $fine->writeOffIfOld($this->clockAt('2026-10-12'));

        $this->assertTrue($fine->isWrittenOff());
        $this->assertSame(0, $fine->getOutstanding());
    }

    public function test_a_fine_is_not_written_off_before_two_years(): void
    {
        $fine = $this->fineOf(500, '2024-10-12');

        $fine->writeOffIfOld($this->clockAt('2026-10-11'));

        $this->assertFalse($fine->isWrittenOff());
        $this->assertSame(500, $fine->getOutstanding());
    }

    public function test_a_paid_fine_is_never_written_off(): void
    {
        $fine = $this->fineOf(500, '2020-01-01', paid: 500);

        $fine->writeOffIfOld($this->clockAt('2026-10-11'));

        $this->assertFalse($fine->isWrittenOff());
    }
}
