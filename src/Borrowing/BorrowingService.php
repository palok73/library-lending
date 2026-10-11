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
            throw new BorrowingException("Member not found: {$memberId}");
        }
        if ($title === null) {
            throw new BorrowingException("Title not found: {$titleId}");
        }

        if (!$member->canBorrow($clock)) {
            $reasons = $member->whyCantBorrow($clock);
            throw new BorrowingException($reasons[0] ?? 'Member cannot borrow at this time.');
        }

        // Domain logic
        $borrowedCopy = $title->allocateCopyForBorrowing($member->getId(), $clock);
        $member->borrowCopy($borrowedCopy, $title, $clock);
        
        // Save both aggregates (within same transaction)
        $this->memberRepository->save($member);
        $this->titleRepository->save($title);
        
    }
}