<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use Psr\Clock\ClockInterface;

class Title extends Entity
{
    private readonly string $title;
    private readonly string $author;
    private readonly \DateTimeImmutable $addedDate;

    private array $copies;

    private array $reservations;

    public function __construct(
        string $id,
        string $title, 
        string $author, 
        \DateTimeInterface $addedDate, 
        array $copies, 
        array $reservations
    ) {
        parent::__construct($id);
        $this->title = trim($title);
        $this->author = trim($author);
        $this->addedDate = \DateTimeImmutable::createFromInterface($addedDate);
        $this->copies = \is_array($copies) ? $copies: [];
        $this->reservations = \is_array($reservations) ? $reservations : [];
        usort($this->reservations, fn($a, $b) => $a->getReservedDate() <=> $b->getReservedDate());
    }   
    public function getTitle():string {
        return $this->title;
    }

    public function getAuthor():string {
        return $this->author;
    }

    public function getAddedDate(): \DateTimeImmutable {
        return $this->addedDate;
    }

    public function getCopy(string $id):?Copy {
        foreach($this->copies as $copy) {
            if ($copy->getId() === $id)
                return $copy;
        }
        return null;
    }

    // Borrowing

    public function allocateCopyForBorrowing(string $memberId): string
    {
        $numberOfAvailableCopies = $this->availableCopiesForBorrowing();
        
        if ($numberOfAvailableCopies<1) {
            throw new BorrowingException("No available copy to borrow.");
        }
        
        $totalReservationCount = \count($this->reservations);
        
        if ($this->hasReservationForMember($memberId)) {
            $positionInReservationQueue = $this->reservationQueuePosition($memberId);
            if ($numberOfAvailableCopies<($positionInReservationQueue+1)) {
                throw new BorrowingException("Cant borrow, because of other reservations.");
            }
        }
        else if ($totalReservationCount >= $numberOfAvailableCopies) {
            throw new BorrowingException("Cant borrow, all copies are reserved by other members.");
        }
       
        $copyToBorrow = $this->getFirstAvailableCopy();
        $copyToBorrow->markAsBorrowed();
        $this->clearReservationForMember($memberId);
        return $copyToBorrow->getId();
    }


    protected function availableCopiesForBorrowing():int {
        $countvailableCopies = 0;
        foreach($this->copies as $copy) {
            /* @var $copy Copy */
            if($copy->isAvailable()) {
                $countvailableCopies++;
            }
        }
        return $countvailableCopies;
    }

    protected function getFirstAvailableCopy():?Copy {
        foreach($this->copies as $copy) {
            /* @var $copy Copy */
            if($copy->isAvailable()) {
                return $copy;
            }
        }
        return null;
    }

        
    public function duePeriod(ClockInterface $clock):int {
        $diff = $clock->now()->diff($this->addedDate);
        $releasedMonthsAgo = 12*$diff->y + $diff->m;
        if ($releasedMonthsAgo < LibraryPolicy::NEW_RELEASE_MAX_AGE_IN_MONTHS) {
            return LibraryPolicy::DUE_DAYS_NEW_RELEASE;
        }
        return LibraryPolicy::DUE_DAYS_STANDARD;
    }

    // Returning
    public function returnCopy(string $copyId):void {
        foreach($this->copies as $copy) {
            if ($copy->getId() === $copyId) {
                if($copy->isAvailable()) {
                    throw new ReturningException("Copy: $copyId was not marked as borrowed, can't return.");
                }
                $copy->markAsAvailable();
                return;
            }
        }
        throw new ReturningException("Copy: $copyId was not found int this Title.");
    }

    // Reservations

    public function isReserved():bool {
        return \count($this->reservations)>0;
    }

    public function makeReservationForMember(string $memberId,ClockInterface $clock):Reservation {
        if ($this->hasReservationForMember($memberId)) {
            throw new ReservationException("Member already has reservation for this title");
        }
        if($this->availableCopiesForBorrowing()) {
            throw new ReservationException("Cant reserve when copies are available");
        }
        $reservation = new Reservation(
            Uuid::generate(),
            $memberId,
            $this->getId(),
            $clock->now()
        );
        $this->reservations[] = $reservation;
        return $reservation;
    }

    
    protected function hasReservationForMember($memberId):bool {
        foreach($this->reservations as $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->getMemberId() === $memberId)
                return true;
        }
        return false;
    }

    protected function clearReservationForMember($memberId):bool {
        $removed = false;
        foreach($this->reservations as $key=> $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->getMemberId() === $memberId) {
                unset($this->reservations[$key]);
                $removed=true;
            }
        }
        $this->reservations = array_values($this->reservations);
        return $removed;
    }

    protected function reservationQueuePosition(string $memberId): ?int {
        $index=0;
        foreach($this->reservations as $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->getMemberId() === $memberId) {
                return $index;
            }
            $index++;
        }
        return null;
    }
}