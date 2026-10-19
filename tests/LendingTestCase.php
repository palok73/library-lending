<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use LibraryLanding\Borrowing\BorrowingService;
use LibraryLanding\Renewing\RenewalService;
use LibraryLanding\Reserving\ReservationService;
use LibraryLanding\Returning\ReturningService;
use LibraryLanding\Shared\Domain\Copy;
use LibraryLanding\Shared\Domain\Fine;
use LibraryLanding\Shared\Domain\LibraryCard;
use LibraryLanding\Shared\Domain\Member;
use LibraryLanding\Shared\Domain\Title;
use LibraryLanding\Shared\Domain\Uuid;
use PHPUnit\Framework\TestCase;

/**
 * Shared "library" for process tests: in-memory ports, the three use cases
 * and small helpers that read like the owner's sentences.
 */
abstract class LendingTestCase extends TestCase
{
    protected const TODAY = '2026-10-11';

    protected TestClock $clock;
    protected InMemoryMemberRepository $members;
    protected InMemoryTitleRepository $titles;
    protected InMemoryReservationQuery $reservationQuery;
    protected BorrowingService $borrowing;
    protected ReturningService $returning;
    protected ReservationService $reservations;
    protected RenewalService $renewals;

    protected function setUp(): void
    {
        $this->clock = new TestClock(new DateTimeImmutable(self::TODAY));
        $this->members = new InMemoryMemberRepository();
        $this->titles = new InMemoryTitleRepository();
        $this->reservationQuery = new InMemoryReservationQuery();

        $this->borrowing = new BorrowingService($this->members, $this->titles, $this->clock);
        $this->returning = new ReturningService($this->members, $this->titles, $this->clock);
        $this->reservations = new ReservationService($this->titles, $this->reservationQuery, $this->clock);
        $this->renewals = new RenewalService($this->members, $this->titles, $this->clock);
    }

    /**
     * Moves the clock used by all services. Everything after this call happens on that date.
     */
    protected function travelTo(string $date): void
    {
        $this->clock->travelTo($date);
    }

    protected function givenMember(string $id, string $cardExpiresOn = '2027-12-31'): Member
    {
        $member = new Member(
            $id,
            'Member ' . $id,
            $id . '@example.com',
            new LibraryCard('CARD-' . $id, $id, new DateTimeImmutable($cardExpiresOn))
        );
        $this->members->save($member);
        $this->members->saveCount = 0;

        return $member;
    }

    /**
     * Member with one unpaid, recent fine of the given amount.
     */
    protected function givenMemberOwing(string $id, int $cents, string $cardExpiresOn = '2027-12-31'): Member
    {
        $fine = new Fine(Uuid::generate(), 'old-loan-' . $id, $id, new DateTimeImmutable(self::TODAY), $cents);
        $member = new Member(
            $id,
            'Member ' . $id,
            $id . '@example.com',
            new LibraryCard('CARD-' . $id, $id, new DateTimeImmutable($cardExpiresOn)),
            [],
            [$fine]
        );
        $this->members->save($member);
        $this->members->saveCount = 0;

        return $member;
    }

    /**
     * Title with copies "<titleId>-copy-0" .. "<titleId>-copy-N". By default an old title (28 days loans).
     */
    protected function givenTitle(string $id, int $copyCount = 1, string $addedOn = '2020-01-01'): Title
    {
        $copies = [];
        for ($i = 0; $i < $copyCount; $i++) {
            $copies[] = new Copy($this->copyId($id, $i), $id);
        }

        $title = new Title($id, 'Title ' . $id, 'Author', new DateTimeImmutable($addedOn), $copies, []);
        $this->titles->save($title);
        $this->titles->saveCount = 0;

        return $title;
    }

    protected function copyId(string $titleId, int $number = 0): string
    {
        return $titleId . '-copy-' . $number;
    }

    /**
     * Every copy of the title is on loan, by "holder-0", "holder-1", ... (on the current date of the clock).
     */
    protected function givenAllCopiesAreLent(string $titleId, int $copyCount): void
    {
        for ($i = 0; $i < $copyCount; $i++) {
            $holder = 'holder-' . $i;
            $this->givenMember($holder);
            $this->borrowing->borrow($holder, $titleId);
        }
    }

    protected function returnCopyNumber(string $titleId, int $copyNumber): void
    {
        $this->returning->returnCopy('holder-' . $copyNumber, $titleId, $this->copyId($titleId, $copyNumber));
    }
}
