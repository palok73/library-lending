<?php
declare(strict_types=1);

namespace LibraryLanding\Tests;

use LibraryLanding\Reserving\ReservationQuery;

final class InMemoryReservationQuery implements ReservationQuery
{
    /** @var array<string, int> */
    private array $counts = [];

    public function givenMemberHasReservations(string $memberId, int $count): void
    {
        $this->counts[$memberId] = $count;
    }

    public function countForMember(string $memberId): int
    {
        return $this->counts[$memberId] ?? 0;
    }
}
