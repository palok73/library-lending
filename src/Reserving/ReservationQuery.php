<?php
declare(strict_types=1);

namespace LibraryLanding\Reserving;

interface ReservationQuery {
    public function countForMember(string $memberId):int;
}