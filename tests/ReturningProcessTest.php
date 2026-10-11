<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\LibraryPolicy;
use LibraryLanding\Shared\Domain\ReturningException;

final class ReturningProcessTest extends LendingTestCase
{
    public function test_a_returned_copy_can_be_lent_again(): void
    {
        $this->givenMember('member-1');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 1);
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt(self::TODAY));

        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt(self::TODAY));

        $this->borrowing->borrow('member-2', 'title-1', $this->clockAt(self::TODAY));
        $this->addToAssertionCount(1);
    }

    public function test_returning_a_copy_frees_a_loan_slot_of_the_member(): void
    {
        $this->givenMember('member-1');
        $max = LibraryPolicy::MAX_LOAN_PER_MEMBER;
        for ($i = 0; $i <= $max; $i++) {
            $this->givenTitle('title-' . $i);
        }
        for ($i = 0; $i < $max; $i++) {
            $this->borrowing->borrow('member-1', 'title-' . $i, $this->clockAt(self::TODAY));
        }

        $this->returning->returnCopy('member-1', 'title-0', $this->copyId('title-0'), $this->clockAt(self::TODAY));

        $this->borrowing->borrow('member-1', 'title-' . $max, $this->clockAt(self::TODAY));
        $this->addToAssertionCount(1);
    }

    public function test_a_copy_returned_on_the_due_date_is_not_fined(): void
    {
        $member = $this->givenMember('member-1');
        $member->fine(500); // right at the borrowing limit, any new fine would block the member
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt('2026-09-01'));

        // standard loan: 28 days
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt('2026-09-29'));

        $this->borrowing->borrow('member-1', 'title-2', $this->clockAt('2026-09-29'));
        $this->addToAssertionCount(1);
    }

    public function test_a_copy_returned_one_day_late_is_fined_and_the_member_at_the_limit_is_blocked(): void
    {
        $member = $this->givenMember('member-1');
        $member->fine(500);
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt('2026-09-01'));

        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt('2026-09-30'));

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-1', 'title-2', $this->clockAt('2026-09-30'));
    }

    public function test_a_fine_of_exactly_five_euro_does_not_block_borrowing(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt('2026-09-01'));

        // due 2026-09-29, returned 25 days late: 25 * 0.20 = 5.00
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt('2026-10-24'));

        $this->borrowing->borrow('member-1', 'title-2', $this->clockAt('2026-10-24'));
        $this->addToAssertionCount(1);
    }

    public function test_a_fine_above_five_euro_blocks_borrowing(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt('2026-09-01'));

        // 26 days late: 5.20
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt('2026-10-25'));

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-1', 'title-2', $this->clockAt('2026-10-25'));
    }

    public function test_returning_a_copy_that_is_not_on_loan_is_an_error(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');

        $this->expectException(ReturningException::class);
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt(self::TODAY));
    }

    public function test_a_copy_cannot_be_returned_twice(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt(self::TODAY));
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt(self::TODAY));

        $this->expectException(ReturningException::class);
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $this->clockAt(self::TODAY));
    }

    public function test_a_copy_that_was_borrowed_again_after_a_return_can_be_returned_again(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $clock = $this->clockAt(self::TODAY);
        $this->borrowing->borrow('member-1', 'title-1', $clock);
        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $clock);
        $this->borrowing->borrow('member-1', 'title-1', $clock);

        $this->returning->returnCopy('member-1', 'title-1', $this->copyId('title-1'), $clock);
        $this->addToAssertionCount(1);
    }

    public function test_returning_a_copy_that_belongs_to_another_title_changes_nothing(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrowing->borrow('member-1', 'title-1', $this->clockAt(self::TODAY));
        $savedMembers = $this->members->saveCount;
        $savedTitles = $this->titles->saveCount;

        try {
            $this->returning->returnCopy('member-1', 'title-2', $this->copyId('title-1'), $this->clockAt(self::TODAY));
            $this->fail('Returning to the wrong title must fail.');
        } catch (ReturningException) {
        }

        $this->assertSame($savedMembers, $this->members->saveCount);
        $this->assertSame($savedTitles, $this->titles->saveCount);
    }

    public function test_returning_for_an_unknown_member_or_title_fails(): void
    {
        $this->givenTitle('title-1');

        $this->expectException(ReturningException::class);
        $this->returning->returnCopy('nobody', 'title-1', $this->copyId('title-1'), $this->clockAt(self::TODAY));
    }
}
