<?php
declare(strict_types=1);

namespace LibraryLanding\Renewing;

use LibraryLanding\Shared\Domain\RenewalException;
use LibraryLanding\Shared\Repository\MemberRepository;
use LibraryLanding\Shared\Repository\TitleRepository;
use Psr\Clock\ClockInterface;

class RenewalService {
    public function __construct(
        private MemberRepository $memberRepository, 
        private TitleRepository $titleRepository) {}

    public function renewLoan(string $memberId,string $titleId,string $copyId,ClockInterface $clock): void {
        $member = $this->memberRepository->findById($memberId);
        $title = $this->titleRepository->findById($titleId);
        
        if ($member === null) {
            throw new RenewalException("Member not found: {$memberId}");
        }
        if ($title === null) {
            throw new RenewalException("Title not found: {$titleId}");
        }

        $member->renewLoan(
            $copyId,
            $title->isReserved(),
            $title->isNewRelease($clock),
            $clock
        );
        
        $this->memberRepository->save($member);
       
    }
}