<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\LibraryPolicy;

final class BorrowingProcessTest extends LendingTestCase
{
    public function test_a_member_can_borrow_an_available_copy(): void
    {
        $this->givenMember('member-1');
        $this->givenTitle('title-1', 1);

        $this->borrowing->borrow('member-1', 'title-1');

        $this->assertSame(1, $this->members->saveCount);
        $this->assertSame(1, $this->titles->saveCount);
    }

    public function test_a_copy_cannot_be_lent_to_two_members_at_the_same_time(): void
    {
        $this->givenMember('member-1');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 1);
        $this->borrowing->borrow('member-1', 'title-1');

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-2', 'title-1');
    }

    public function test_two_copies_of_a_title_can_be_lent_to_two_members(): void
    {
        $this->givenMember('member-1');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 2);

        $this->borrowing->borrow('member-1', 'title-1');
        $this->borrowing->borrow('member-2', 'title-1');

        $this->assertSame(2, $this->members->saveCount);
    }

    public function test_a_member_can_have_five_loans_but_not_a_sixth(): void
    {
        $this->givenMember('member-1');
        $max = LibraryPolicy::MAX_LOAN_PER_MEMBER;
        for ($i = 0; $i <= $max; $i++) {
            $this->givenTitle('title-' . $i);
        }

        for ($i = 0; $i < $max; $i++) {
            $this->borrowing->borrow('member-1', 'title-' . $i);
        }

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-1', 'title-' . $max);
    }

    public function test_a_member_with_an_expired_library_card_cannot_borrow(): void
    {
        $this->givenMember('member-1', '2026-10-10');
        $this->givenTitle('title-1');

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-1', 'title-1');
    }

    public function test_a_refused_borrowing_leaves_the_copy_available_for_others(): void
    {
        $this->givenMember('expired', '2026-10-10');
        $this->givenMember('member-2');
        $this->givenTitle('title-1', 1);

        try {
            $this->borrowing->borrow('expired', 'title-1');
            $this->fail('Expired card should not be able to borrow.');
        } catch (BorrowingException) {
        }

        $this->borrowing->borrow('member-2', 'title-1');
        $this->assertSame(1, $this->titles->saveCount);
    }

    public function test_a_member_who_owes_more_than_five_euro_cannot_borrow(): void
    {
        $member = $this->givenMember('member-1');
        $member->fine(501);
        $this->givenTitle('title-1');

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-1', 'title-1');
    }

    public function test_a_member_who_owes_exactly_five_euro_can_still_borrow(): void
    {
        $member = $this->givenMember('member-1');
        $member->fine(500);
        $this->givenTitle('title-1');

        $this->borrowing->borrow('member-1', 'title-1');

        $this->assertSame(1, $this->members->saveCount);
    }

    public function test_borrowing_for_an_unknown_member_fails(): void
    {
        $this->givenTitle('title-1');

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('nobody', 'title-1');
    }

    public function test_borrowing_an_unknown_title_fails(): void
    {
        $this->givenMember('member-1');

        $this->expectException(BorrowingException::class);
        $this->borrowing->borrow('member-1', 'no-such-title');
    }
}
