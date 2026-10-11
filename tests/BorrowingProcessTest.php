<?php

declare(strict_types=1);

namespace LibraryLanding\Tests;

use DateTimeImmutable;
use LibraryLanding\Borrowing\BorrowingException;
use LibraryLanding\Borrowing\BorrowingService;
use LibraryLanding\Shared\Domain\Copy;
use LibraryLanding\Shared\Domain\LibraryCard;
use LibraryLanding\Shared\Domain\LibraryPolicy;
use LibraryLanding\Shared\Domain\Member;
use LibraryLanding\Shared\Domain\Title;
use LibraryLanding\Shared\Repository\MemberRepository;
use LibraryLanding\Shared\Repository\TitleRepository;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class BorrowingProcessTest extends TestCase
{
    public function test_a_member_can_borrow_an_available_copy_when_all_rules_allow_it(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-10-11'));
        $member = $this->createMember('member-1', new DateTimeImmutable('2027-10-11'));
        $title = $this->createTitle('title-1', 1);

        $memberRepository = $this->createMock(MemberRepository::class);
        $memberRepository->method('findById')->with('member-1')->willReturn($member);
        $memberRepository->expects($this->once())->method('save')->with($member);

        $titleRepository = $this->createMock(TitleRepository::class);
        $titleRepository->method('findById')->with('title-1')->willReturn($title);
        $titleRepository->expects($this->once())->method('save')->with($title);

        $service = new BorrowingService($memberRepository, $titleRepository);

        $service->borrow('member-1', 'title-1', $clock);

        $this->assertSame([], $member->whyCantBorrow($clock));
        $this->assertSame(0, $title->availableCopiesForBorrowing());
    }

    public function test_a_member_with_five_active_loans_cannot_borrow_a_sixth(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-10-11'));
        $member = $this->createMember('member-1', new DateTimeImmutable('2027-10-11'));

        for ($i = 0; $i < LibraryPolicy::MAX_LOAN_PER_MEMBER; $i++) {
            $title = $this->createTitle('title-' . $i, 1);
            $copy = $title->getFirstAvailableCopy();
            $this->assertNotNull($copy);
            $member->borrowCopy($copy, $title, $clock);
        }

        $this->assertFalse($member->canBorrow($clock));

        $memberRepository = $this->createMock(MemberRepository::class);
        $memberRepository->method('findById')->with('member-1')->willReturn($member);

        $titleRepository = $this->createMock(TitleRepository::class);
        $titleRepository->method('findById')->with('title-5')->willReturn($this->createTitle('title-5', 1));

        $service = new BorrowingService($memberRepository, $titleRepository);

        $this->expectException(BorrowingException::class);
        $service->borrow('member-1', 'title-5', $clock);
    }

    public function test_a_member_with_an_expired_library_card_cannot_borrow(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-10-11'));
        $member = $this->createMember('member-2', new DateTimeImmutable('2026-10-10'));
        $title = $this->createTitle('title-2', 1);

        $memberRepository = $this->createMock(MemberRepository::class);
        $memberRepository->method('findById')->with('member-2')->willReturn($member);

        $titleRepository = $this->createMock(TitleRepository::class);
        $titleRepository->method('findById')->with('title-2')->willReturn($title);

        $service = new BorrowingService($memberRepository, $titleRepository);

        $this->assertFalse($member->canBorrow($clock));
        $this->expectException(BorrowingException::class);
        $service->borrow('member-2', 'title-2', $clock);
    }

    public function test_a_member_with_too_much_debt_cannot_borrow(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-10-11'));
        $member = $this->createMember('member-3', new DateTimeImmutable('2027-10-11'));
        $member->fine(LibraryPolicy::MAX_NEGATIVE_BALANCE_IN_CENTS + 1);
        $title = $this->createTitle('title-3', 1);

        $memberRepository = $this->createMock(MemberRepository::class);
        $memberRepository->method('findById')->with('member-3')->willReturn($member);

        $titleRepository = $this->createMock(TitleRepository::class);
        $titleRepository->method('findById')->with('title-3')->willReturn($title);

        $service = new BorrowingService($memberRepository, $titleRepository);

        $this->assertFalse($member->canBorrow($clock));
        $this->expectException(BorrowingException::class);
        $service->borrow('member-3', 'title-3', $clock);
    }

    public function test_a_copy_cannot_be_lent_twice(): void
    {
        $copy = new Copy('copy-1', 'title-4');

        $copy->markAsBorrowed();

        $this->expectException(BorrowingException::class);
        $copy->markAsBorrowed();
    }

    private function createMember(string $memberId, DateTimeImmutable $expiryDate): Member
    {
        $member = new Member(
            'Member ' . $memberId,
            $memberId . '@example.com',
            new LibraryCard('CARD-' . $memberId, $memberId, $expiryDate)
        );
        $member->setId($memberId);

        return $member;
    }

    private function createTitle(string $titleId, int $copyCount): Title
    {
        $copies = [];
        for ($i = 0; $i < $copyCount; $i++) {
            $copy = new Copy($titleId . '-copy-' . $i, $titleId, true);
            $copy->setId($titleId . '-copy-' . $i);
            $copies[] = $copy;
        }

        $title = new Title(
            '978-' . $titleId,
            'Title ' . $titleId,
            'Author',
            new DateTimeImmutable('-1 month'),
            $copies,
            []
        );
        $title->setId($titleId);

        return $title;
    }
}

final class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
