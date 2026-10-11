<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\Copy;
use LibraryLanding\Shared\Domain\LibraryPolicy;
use LibraryLanding\Shared\Domain\Reservation;
use LibraryLanding\Shared\Domain\Title;
use PHPUnit\Framework\TestCase;

final class TitleTest extends TestCase
{
    private function titleAddedOn(string $date, array $reservations = [], int $availableCopies = 1): Title
    {
        $copies = [new Copy('copy-0', 'title-1', false)];
        for ($i = 1; $i <= $availableCopies; $i++) {
            $copies[] = new Copy('copy-' . $i, 'title-1', true);
        }
        return new Title('title-1', 'Animal Farm', 'Orwell', new DateTimeImmutable($date), $copies, $reservations);
    }

    private function clockAt(string $date): FixedClock
    {
        return new FixedClock(new DateTimeImmutable($date));
    }

    public function test_a_loan_of_an_old_title_lasts_28_days(): void
    {
        $title = $this->titleAddedOn('2020-01-01');

        $this->assertSame(LibraryPolicy::DUE_DAYS_STANDARD, $title->duePeriod($this->clockAt('2026-10-11')));
    }

    public function test_a_loan_of_a_new_release_lasts_14_days(): void
    {
        $title = $this->titleAddedOn('2026-09-11');

        $this->assertSame(LibraryPolicy::DUE_DAYS_NEW_RELEASE, $title->duePeriod($this->clockAt('2026-10-11')));
    }

    public function test_a_title_added_just_under_six_months_ago_is_still_a_new_release(): void
    {
        $title = $this->titleAddedOn('2026-04-12');

        $this->assertSame(LibraryPolicy::DUE_DAYS_NEW_RELEASE, $title->duePeriod($this->clockAt('2026-10-11')));
    }

    public function test_a_title_added_six_months_ago_is_no_longer_a_new_release(): void
    {
        $title = $this->titleAddedOn('2026-04-11');

        $this->assertSame(LibraryPolicy::DUE_DAYS_STANDARD, $title->duePeriod($this->clockAt('2026-10-11')));
    }

    public function test_the_reservation_queue_is_ordered_by_reservation_date_whatever_the_loading_order(): void
    {
        $later = new Reservation('r-2', 'later', 'title-1', new DateTimeImmutable('2026-10-02'));
        $earlier = new Reservation('r-1', 'earlier', 'title-1', new DateTimeImmutable('2026-10-01'));
        $title = $this->titleAddedOn('2020-01-01', [$later, $earlier], 1);

        try {
            $title->allocateCopyForBorrowing('later');
            $this->fail('Second in the queue must not get the only copy.');
        } catch (BorrowingException) {
        }

        $this->assertSame('copy-1', $title->allocateCopyForBorrowing('earlier'));
    }

    public function test_a_title_without_available_copy_cannot_allocate_one(): void
    {
        $title = $this->titleAddedOn('2020-01-01', [], 0);

        $this->expectException(BorrowingException::class);
        $title->allocateCopyForBorrowing('member-1');
    }
}
