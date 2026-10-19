<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Domain\Fine;
use LibraryLanding\Shared\Domain\FineException;
use LibraryLanding\Shared\Domain\LibraryCard;
use LibraryLanding\Shared\Domain\Member;
use PHPUnit\Framework\TestCase;

final class MemberFinesTest extends TestCase
{
    private function clockAt(string $date): FixedClock
    {
        return new FixedClock(new DateTimeImmutable($date));
    }

    private function fine(string $id, int $cents, string $dateFined = '2026-10-01'): Fine
    {
        return new Fine($id, 'loan-' . $id, 'member-1', new DateTimeImmutable($dateFined), $cents);
    }

    private function memberOwing(Fine ...$fines): Member
    {
        return new Member(
            'member-1',
            'Member',
            'member@example.com',
            new LibraryCard('CARD-1', 'member-1', new DateTimeImmutable('2027-12-31')),
            [],
            $fines
        );
    }

    private function assertCanBorrow(Member $member): void
    {
        $member->checkCanBorrow($this->clockAt('2026-10-11'));
        $this->addToAssertionCount(1);
    }

    private function assertCannotBorrow(Member $member): void
    {
        try {
            $member->checkCanBorrow($this->clockAt('2026-10-11'));
        } catch (BorrowingException) {
            $this->addToAssertionCount(1);
            return;
        }
        $this->fail('Member should not be able to borrow.');
    }

    public function test_a_member_owing_more_than_five_euro_in_total_cannot_borrow(): void
    {
        $member = $this->memberOwing($this->fine('a', 300), $this->fine('b', 201));

        $this->assertCannotBorrow($member);
    }

    public function test_a_partial_payment_can_bring_the_member_below_the_limit(): void
    {
        $member = $this->memberOwing($this->fine('a', 1000));
        $this->assertCannotBorrow($member);

        $member->payFines(500);

        $this->assertCanBorrow($member);
    }

    public function test_a_payment_is_spread_over_several_fines(): void
    {
        $member = $this->memberOwing($this->fine('a', 300), $this->fine('b', 300), $this->fine('c', 300)); // 9.00
        $member->payFines(400); // a settled, b has 2.00 left: 5.00 owed in total

        $this->assertCanBorrow($member);
    }

    public function test_a_payment_that_does_not_reach_the_limit_keeps_the_member_blocked(): void
    {
        $member = $this->memberOwing($this->fine('a', 1000));

        $member->payFines(499); // 5.01 left

        $this->assertCannotBorrow($member);
    }

    public function test_a_payment_larger_than_what_is_owed_is_refused(): void
    {
        $member = $this->memberOwing($this->fine('a', 300));

        $this->expectException(FineException::class);
        $member->payFines(301);
    }

    public function test_a_member_can_pay_everything_in_one_payment(): void
    {
        $member = $this->memberOwing($this->fine('a', 700), $this->fine('b', 700));

        $member->payFines(1400);

        $this->assertCanBorrow($member);
        $this->expectException(FineException::class); // nothing left to pay
        $member->payFines(1);
    }

    public function test_a_payment_must_be_positive(): void
    {
        $member = $this->memberOwing($this->fine('a', 300));

        $this->expectException(FineException::class);
        $member->payFines(0);
    }

    public function test_fines_older_than_two_years_are_written_off_and_no_longer_block_borrowing(): void
    {
        $member = $this->memberOwing($this->fine('old', 1000, '2024-01-01'));
        $this->assertCannotBorrow($member);

        $member->writeOffOldFines($this->clockAt('2026-10-11'));

        $this->assertCanBorrow($member);
    }

    public function test_writing_off_leaves_recent_fines_untouched(): void
    {
        $member = $this->memberOwing($this->fine('old', 300, '2024-01-01'), $this->fine('new', 600, '2026-09-01'));

        $member->writeOffOldFines($this->clockAt('2026-10-11'));

        $this->assertCannotBorrow($member); // 6.00 still owed
    }
}
