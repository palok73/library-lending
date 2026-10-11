<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class Reservation
{
    public \DateTimeImmutable $reservedDate;

    public function __construct(
        public string $id,
        public string $memberId,
        public string $titleId,
        \DateTimeImmutable $reservedDate,
    ) {
        $this->id = $id;
        $this->memberId = $memberId;
        $this->titleId = $titleId;
        $this->reservedDate = \DateTimeImmutable::createFromInterface($reservedDate);
    }

}
