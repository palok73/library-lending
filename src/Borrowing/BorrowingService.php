<?php
declare(strict_types=1);

namespace LibraryLanding\Borrowing;

use LibraryLanding\Shared\Repository\MemberRepository;
use LibraryLanding\Shared\Repository\TitleRepository;
use Psr\Clock\ClockInterface;

class BorrowingService {
    
    public function __construct(
        private MemberRepository $memberRepository, 
        private TitleRepository $titleRepository) {}

    /**
     * Borrow a copy for a member.
     * Uses pessimistic locking to prevent concurrent modifications.
     */
    public function borrow(
        string $memberId,
        string $titleId,
        ClockInterface $clock): void 
    {
         
        $member = $this->memberRepository->findById($memberId);
        $title = $this->titleRepository->findById($titleId);
            
        if ($member === null) {
            throw new \RuntimeException("Member not found: {$memberId}");
        }
        if ($title === null) {
            throw new \RuntimeException("Title not found: {$titleId}");
        }

        // Domain logic
        $borrowedCopy = $title->allocateCopyForBorrowing($member->getId(), $clock);
        $member->borrowCopy($borrowedCopy, $title, $clock);
        
        // Save both aggregates (within same transaction)
        $this->memberRepository->save($member);
        $this->titleRepository->save($title);
        
    }
}