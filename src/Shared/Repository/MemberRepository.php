<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

use LibraryLanding\Shared\Domain\Member;

interface MemberRepository {
    /**
     * Load a Member without lock (read-only access).
     */
    public function findById(string $memberId): ?Member;
    
    /**
     * Load a Member with pessimistic lock for update.
     * Requires active transaction.
     * Throws PessimisticLockException if lock cannot be acquired.
     */
    public function findByIdForUpdate(string $memberId): ?Member;
    
    /**
     * Save a Member within current transaction.
     * Must have acquired lock via findByIdForUpdate.
     */
    public function save(Member $member): void;
}