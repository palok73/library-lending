<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\RenewalException;

final class RenewalProcessTest extends LendingTestCase
{
    private function borrow(string $memberId, string $titleId, string $on): void
    {
        $this->travelTo($on);
        $this->borrowing->borrow($memberId, $titleId);
    }

    private function renew(string $memberId, string $titleId, string $on): void
    {
        $this->travelTo($on);
        $this->renewals->renewLoan($memberId, $titleId, $this->copyId($titleId));
    }

    private function giveBack(string $memberId, string $titleId, string $on): void
    {
        $this->travelTo($on);
        $this->returning->returnCopy($memberId, $titleId, $this->copyId($titleId));
    }

    /**
     * Member owes exactly €5.00: any new fine would block borrowing, so "can borrow again"
     * tells us the copy was returned in time.
     */
    private function givenMemberAtTheFineLimit(string $id): void
    {
        $this->givenMember($id)->fine(500);
    }

    public function test_a_member_can_renew_a_loan(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->borrow('member-1', 'title-1', '2026-10-01');
        $savedMembers = $this->members->saveCount;
        $savedTitles = $this->titles->saveCount;

        $this->renew('member-1', 'title-1', '2026-10-05');

        $this->assertSame($savedMembers + 1, $this->members->saveCount);
        $this->assertSame($savedTitles, $this->titles->saveCount);
    }

    public function test_a_renewed_loan_is_due_28_days_from_the_day_of_renewal(): void
    {
        $this->givenMemberAtTheFineLimit('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrow('member-1', 'title-1', '2026-10-01'); // originally due 2026-10-29
        $this->renew('member-1', 'title-1', '2026-10-05');  // now due 2026-11-02

        $this->giveBack('member-1', 'title-1', '2026-11-02');

        $this->borrow('member-1', 'title-2', '2026-11-02');
        $this->addToAssertionCount(1);
    }

    public function test_a_renewed_loan_is_overdue_the_day_after_the_new_due_date(): void
    {
        $this->givenMemberAtTheFineLimit('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrow('member-1', 'title-1', '2026-10-01');
        $this->renew('member-1', 'title-1', '2026-10-05'); // due 2026-11-02

        $this->giveBack('member-1', 'title-1', '2026-11-03');

        $this->expectException(BorrowingException::class);
        $this->borrow('member-1', 'title-2', '2026-11-03');
    }

    public function test_a_loan_can_be_renewed_twice_but_not_a_third_time(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->borrow('member-1', 'title-1', '2026-10-01');
        $this->renew('member-1', 'title-1', '2026-10-02');
        $this->renew('member-1', 'title-1', '2026-10-03');

        $this->expectException(RenewalException::class);
        $this->renew('member-1', 'title-1', '2026-10-04');
    }

    public function test_a_loan_can_be_renewed_on_its_due_date(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->borrow('member-1', 'title-1', '2026-09-01'); // due 2026-09-29

        $this->renew('member-1', 'title-1', '2026-09-29');

        $this->addToAssertionCount(1);
    }

    public function test_an_overdue_loan_cannot_be_renewed(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->borrow('member-1', 'title-1', '2026-09-01'); // due 2026-09-29

        $this->expectException(RenewalException::class);
        $this->renew('member-1', 'title-1', '2026-09-30');
    }

    public function test_a_member_with_another_overdue_loan_cannot_renew(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrow('member-1', 'title-1', '2026-09-01'); // overdue since 2026-09-30
        $this->borrow('member-1', 'title-2', '2026-10-05'); // not overdue

        $this->expectException(RenewalException::class);
        $this->renew('member-1', 'title-2', '2026-10-11');
    }

    public function test_a_loan_that_was_returned_late_in_the_past_does_not_block_renewal(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->givenTitle('title-2');
        $this->borrow('member-1', 'title-1', '2026-09-01');
        $this->giveBack('member-1', 'title-1', '2026-10-01'); // two days late, already settled
        $this->borrow('member-1', 'title-2', '2026-10-05');

        $this->renew('member-1', 'title-2', '2026-10-06');

        $this->addToAssertionCount(1);
    }

    public function test_a_returned_loan_cannot_be_renewed(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');
        $this->borrow('member-1', 'title-1', '2026-10-01');
        $this->giveBack('member-1', 'title-1', '2026-10-02');

        $this->expectException(RenewalException::class);
        $this->renew('member-1', 'title-1', '2026-10-03');
    }

    public function test_a_loan_cannot_be_renewed_when_somebody_reserved_the_title(): void
    {
        $this->givenMember('member-1');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 1);
        $this->borrow('member-1', 'title-1', '2026-10-01');
        $this->travelTo('2026-10-02');
        $this->reservations->reserve('title-1', 'member-2');
        $savedMembers = $this->members->saveCount;

        try {
            $this->renew('member-1', 'title-1', '2026-10-03');
            $this->fail('A reserved title must not be renewed.');
        } catch (RenewalException) {
        }

        $this->assertSame($savedMembers, $this->members->saveCount);
    }

    public function test_a_new_release_cannot_be_renewed(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1, '2026-09-11'); // less than 6 months in the collection
        $this->borrow('member-1', 'title-1', '2026-10-01');

        $this->expectException(RenewalException::class);
        $this->renew('member-1', 'title-1', '2026-10-05');
    }

    public function test_a_title_that_is_no_longer_a_new_release_can_be_renewed(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1, '2026-04-01');
        $this->borrow('member-1', 'title-1', '2026-10-01');

        $this->renew('member-1', 'title-1', '2026-10-05');

        $this->addToAssertionCount(1);
    }

    public function test_a_copy_the_member_has_not_borrowed_cannot_be_renewed(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1');

        $this->expectException(RenewalException::class);
        $this->renew('member-1', 'title-1', '2026-10-05');
    }

    public function test_renewing_for_an_unknown_member_fails(): void
    {
        $this->givenTitle('title-1');

        $this->expectException(RenewalException::class);
        $this->renew('nobody', 'title-1', '2026-10-05');
    }

    public function test_renewing_an_unknown_title_fails(): void
    {
        $this->givenMember('member-1');

        $this->expectException(RenewalException::class);
        $this->travelTo('2026-10-05');
        $this->renewals->renewLoan('member-1', 'no-such-title', 'copy-x');
    }
}
