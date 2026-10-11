<?php
declare(strict_types=1);

namespace LibraryLanding\Reserving;

use LibraryLanding\Shared\Domain\LibraryPolicy;
use LibraryLanding\Shared\Domain\ReservationException;
use LibraryLanding\Shared\Repository\TitleRepository;
use Psr\Clock\ClockInterface;


class ReservationService {
    public function __construct(
        private readonly TitleRepository $titleRepository, 
        private readonly ReservationQuery $reservationQuery
    ) {}

    public function reserve(string $titleId,string $memberId, ClockInterface $clock):void {
        $title = $this->titleRepository->findById($titleId);
        if ($title === null) {
            throw new ReservationException("Title not found: {$titleId}");
        }
        $countMemberReservations = $this->reservationQuery->countForMember($memberId);
        if ($countMemberReservations>=LibraryPolicy::MAX_RESERVATIONS_PER_MEMBER) {
            throw new ReservationException("Maximum number of reservations for member exceeded.");
        }
        $title->makeReservationForMember($memberId,$clock);
        $this->titleRepository->save($title);
    }
}