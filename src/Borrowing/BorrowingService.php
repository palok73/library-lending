<?php
declare(strict_types=1);

namespace LibraryLanding\Borrowing;

use LibraryLanding\Shared\Domain\BorrowingException;
use LibraryLanding\Shared\Repository\MemberRepository;
use LibraryLanding\Shared\Repository\TitleRepository;
use Psr\Clock\ClockInterface;

class BorrowingService {
    
    public function __construct(
        private MemberRepository $memberRepository, 
        private TitleRepository $titleRepository,
        private ClockInterface $clock) {}

    /**
     * Borrow a copy for a member.
     * Uses pessimistic locking to prevent concurrent modifications.
     */
    public function borrow(
        string $memberId,
        string $titleId): void 
    {
         
        $member = $this->memberRepository->findById($memberId);
        $title = $this->titleRepository->findById($titleId);
            
        if ($member === null) {
            throw new BorrowingException("Member not found: {$memberId}");
        }
        if ($title === null) {
            throw new BorrowingException("Title not found: {$titleId}");
        }
	
        // Domain logic
	// TODO fee management
        $member->checkCanBorrow($this->clock);
        $borrowedCopyId = $title->allocateCopyForBorrowing($member->getId());
        $duePeriod = $title->duePeriod($this->clock);
        $member->borrowCopy(
            $borrowedCopyId, 
            $title->getId(), 
            $this->clock,
            $duePeriod
        );
        
        // Save both aggregates (within same transaction)
        $this->memberRepository->save($member);
        $this->titleRepository->save($title);
        
    }
}
