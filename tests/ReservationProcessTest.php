<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\ReservationException;

final class ReservationProcessTest extends LendingTestCase
{
    public function test_a_member_can_reserve_a_title_when_all_copies_are_lent(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->titles->saveCount = 0;

        $this->reservations->reserve('title-1', 'member-1');

        $this->assertSame(1, $this->titles->saveCount);
    }

    public function test_a_member_cannot_reserve_a_title_when_a_copy_is_available(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 2);
        $this->givenAllCopiesAreLent('title-1', 1);

        $this->expectException(ReservationException::class);
        $this->reservations->reserve('title-1', 'member-1');
    }

    public function test_a_member_cannot_reserve_the_same_title_twice(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->reservations->reserve('title-1', 'member-1');

        $this->expectException(ReservationException::class);
        $this->reservations->reserve('title-1', 'member-1');
    }

    public function test_a_member_with_three_reservations_cannot_make_a_fourth(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->reservationQuery->givenMemberHasReservations('member-1', 3);

        $this->expectException(ReservationException::class);
        $this->reservations->reserve('title-1', 'member-1');
    }

    public function test_a_member_with_two_reservations_can_make_a_third(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->reservationQuery->givenMemberHasReservations('member-1', 2);
        $this->titles->saveCount = 0;

        $this->reservations->reserve('title-1', 'member-1');

        $this->assertSame(1, $this->titles->saveCount);
    }

    public function test_reserving_an_unknown_title_fails(): void
    {
        $this->givenMember('member-1');

        $this->expectException(ReservationException::class);
        $this->reservations->reserve('no-such-title', 'member-1');
    }

    public function test_a_returned_copy_is_kept_for_the_member_who_reserved_it(): void
    {
        $this->givenMember('member-1');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->reservations->reserve('title-1', 'member-1');
        $this->returnCopyNumber('title-1', 0);

        try {
            $this->borrowing->borrow('member-2', 'title-1');
            $this->fail('A member without a reservation must not get the copy.');
        } catch (BorrowingException) {
        }

        $this->borrowing->borrow('member-1', 'title-1');
        $this->addToAssertionCount(1);
    }

    public function test_reservations_are_served_first_come_first_served(): void
    {
        $this->givenMember('first');
        $this->givenMember('second');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->travelTo('2026-10-11');
        $this->reservations->reserve('title-1', 'first');
        $this->travelTo('2026-10-12');
        $this->reservations->reserve('title-1', 'second');
        $this->travelTo('2026-10-13');
        $this->returnCopyNumber('title-1', 0);

        try {
            $this->borrowing->borrow('second', 'title-1');
            $this->fail('Second in the queue must wait for the first one.');
        } catch (BorrowingException) {
        }

        $this->borrowing->borrow('first', 'title-1');
        $this->addToAssertionCount(1);
    }

    public function test_when_two_copies_come_back_both_members_in_the_queue_can_borrow(): void
    {
        $this->givenMember('first');
        $this->givenMember('second');
        $this->givenTitle('title-1', 2);
        $this->givenAllCopiesAreLent('title-1', 2);
        $this->travelTo('2026-10-11');
        $this->reservations->reserve('title-1', 'first');
        $this->travelTo('2026-10-12');
        $this->reservations->reserve('title-1', 'second');
        $this->returnCopyNumber('title-1', 0);
        $this->returnCopyNumber('title-1', 1);

        $this->travelTo(self::TODAY);
        $this->borrowing->borrow('second', 'title-1');
        $this->borrowing->borrow('first', 'title-1');
        $this->addToAssertionCount(1);
    }

    public function test_a_member_without_reservation_can_borrow_when_there_are_more_copies_than_reservations(): void
    {
        $this->givenMember('reserver');
        $this->givenMember('outsider');
        $this->givenMember('late-comer');
        $this->givenTitle('title-1', 3);
        $this->givenAllCopiesAreLent('title-1', 3);
        $this->reservations->reserve('title-1', 'reserver');
        $this->returnCopyNumber('title-1', 0);
        $this->returnCopyNumber('title-1', 1);

        // 2 copies on the shelf, 1 reservation: one copy is free for anybody
        $this->borrowing->borrow('outsider', 'title-1');

        // now 1 copy, 1 reservation: it belongs to the reserver
        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('late-comer', 'title-1');
    }

    public function test_borrowing_the_reserved_copy_removes_the_reservation(): void
    {
        $this->givenMember('member-1');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 1);
        $this->givenAllCopiesAreLent('title-1', 1);
        $this->reservations->reserve('title-1', 'member-1');
        $this->returnCopyNumber('title-1', 0);
        $this->borrowing->borrow('member-1', 'title-1');
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'));

        // nobody is waiting any more
        $this->borrowing->borrow('member-2', 'title-1');
        $this->addToAssertionCount(1);
    }
}
