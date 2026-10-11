<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

use LibraryLanding\Borrowing\BorrowingException;
use Psr\Clock\ClockInterface;

class Title extends Entity
{
    private string $isbn;
    private string $title;
    private string $author;
    private \DateTimeImmutable $addedDate;

    private array $copies;

    private array $reservations;

    public function __construct(
        string $isbn, string $title, string $author, 
        \DateTimeInterface $addedDate, array $copies, array $reservations) {
        //apply some validation here if needed
        $this->isbn = $isbn;
        $this->title = trim($title);
        $this->author = trim($author);
        $this->addedDate = \DateTimeImmutable::createFromInterface($addedDate);
        $this->copies = $copies;
        $this->reservations = empty($reservations) ? [] : $reservations;
        usort($reservations, fn($a, $b) => $a->reservedDate > $b->reservedDate);
    }   
     public function getTitle():string {
        return $this->title;
    }


    // Borrowing

    public function allocateCopyForBorrowing(string $memberId,ClockInterface $clock): Copy
    {
        $numberOfAvailableCopies = $this->availableCopiesForBorrowing();
        
        if ($numberOfAvailableCopies<1) {
            throw new BorrowingException("No available copy to borrow.");
        }
        
        if($this->isReserved()) {
            if ($this->hasReservationForMember($memberId)) {
                $positionInReservationQueue = $this->reservationQueuePosition($memberId);
                if ($numberOfAvailableCopies<($positionInReservationQueue+1)) {
                    throw new BorrowingException("Cant borrow, not enough available copies.");
                }
            }
            else {
                throw new BorrowingException("Cant borrow, title is reserved by other member/s.");
            }
        }
        $copyToBorrow = $this->getFirstAvailableCopy();
        $copyToBorrow->markAsBorrowed();
        $this->clearReservationForMember($memberId);
        return $copyToBorrow;
    }


    public function availableCopiesForBorrowing():int {
        $countvailableCopies = 0;
        foreach($this->copies as $copy) {
            /* @var $copy Copy */
            if($copy->isAvailable()) {
                $countvailableCopies++;
            }
        }
        return $countvailableCopies;
    }

    public function getFirstAvailableCopy():?Copy {
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
                $copy->markAsAvailable();
                return;
            }
        }
        throw new ReturningException("Copy: $copyId was not marked as borrowed, can't return");
    }

    // Reservations

    public function isReserved():bool {
        return \count($this->reservations)>0;
    }

    public function makeReservationForMember(string $memberId,ClockInterface $clock):Reservation {
        if ($this->hasReservationForMember($memberId)) {
            throw new ReservationException("Member already has reservation for this title");
        }
        $reservation = new Reservation(
            uniqid(),
            $memberId,
            $this->getId(),
            $clock->now()
        );
        $this->addReservation($reservation);
        return $reservation;
    }

    public function addReservation(Reservation $reservation) {
        $this->reservations[] = $reservation;
    }


    public function hasReservationForMember($memberId):bool {
        foreach($this->reservations as $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->memberId===$memberId)
                return true;
        }
        return false;
    }

    public function clearReservationForMember($memberId):bool {
        $index=0;
        $removed = false;
        foreach($this->reservations as $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->memberId===$memberId) {
                unset($this->reservations[$index]);
                $removed=true;
            }
            $index++;
        }
        return $removed;
    }

    public function reservationQueuePosition(string $memberId): ?int {
        $index=0;
        foreach($this->reservations as $reservation) {
            /* @var $reservation Reservation */
            if ($reservation->memberId===$memberId) {
                return $index;
            }
            $index++;
        }
        return null;
    }
}