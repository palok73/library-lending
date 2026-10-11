<?php
declare(strict_types=1);

namespace LibraryLanding\Returning;

use LibraryLanding\Shared\Domain\ReturningException;
use LibraryLanding\Shared\Repository\MemberRepository;
use LibraryLanding\Shared\Repository\TitleRepository;
use Psr\Clock\ClockInterface;

class ReturningService {
    public function __construct(
        private MemberRepository $memberRepository, 
        private TitleRepository $titleRepository,
        private ClockInterface $clock) {}

    public function returnCopy(string $memberId,string $titleId,string $copyId): void {
        $member = $this->memberRepository->findById($memberId);
        $title = $this->titleRepository->findById($titleId);
        
        if ($member === null) {
            throw new ReturningException("Member not found: {$memberId}");
        }
        if ($title === null) {
            throw new ReturningException("Title not found: {$titleId}");
        }

        $member->returnCopy($copyId, $this->clock);
        $title->returnCopy($copyId);
        
        // Save both aggregates (within same transaction)
        $this->memberRepository->save($member);
        $this->titleRepository->save($title);
    }
}