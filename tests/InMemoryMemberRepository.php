<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Shared\Domain\Member;
use LibraryLanding\Shared\Repository\MemberRepository;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    private array $members = [];
    public int $saveCount = 0;

    public function findById(string $memberId): ?Member
    {
        return $this->members[$memberId] ?? null;
    }

    public function save(Member $member): void
    {
        $this->members[$member->getId()] = $member;
        $this->saveCount++;
    }
}
