<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class Reservation extends Entity
{
    private readonly \DateTimeImmutable $reservedDate;

    public function __construct(
        string $id,
        private readonly string $memberId,
        private readonly string $titleId,
        \DateTimeImmutable $reservedDate,
    ) {
        parent::__construct($id);
        $this->reservedDate = \DateTimeImmutable::createFromInterface($reservedDate);
    }

    public function getMemberId():string {
        return $this->memberId;
    }

    public function getTitleId():string {
        return $this->titleId;
    }

    public function getReservedDate():\DateTimeImmutable {
        return $this->reservedDate;
    }
}
