<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Repository;

use LibraryLanding\Shared\Domain\Member;

/**
 * In-memory Member repository with pessimistic locking.
 * Acquires locks when loading aggregates for update.
 */
class InMemoryMemberRepository implements MemberRepository {
    /** @var array<string, Member> */
    private array $store = [];
    
    /** @var array<string, bool> Track which aggregates are locked */
    private array $lockedByOtherTransaction = [];

    public function __construct(
        private UnitOfWork $unitOfWork
    ) {}

    /**
     * Load a Member without lock (read-only).
     * Returns null if not found.
     */
    public function findById(string $memberId): ?Member {
        return $this->store[$memberId] ?? null;
    }

    /**
     * Load a Member FOR UPDATE (acquire pessimistic lock).
     * Used when the aggregate will be modified in a transaction.
     * Throws if lock cannot be acquired.
     */
    public function findByIdForUpdate(string $memberId): ?Member {
        if (!$this->unitOfWork->isInTransaction()) {
            throw new \RuntimeException(
                "findByIdForUpdate() requires an active transaction"
            );
        }

        // Check if lock is already held by another transaction (simulated conflict)
        if (isset($this->lockedByOtherTransaction[$memberId])) {
            throw new PessimisticLockException(
                "Member {$memberId} is locked by another transaction",
                $memberId
            );
        }

        // Acquire lock in current transaction
        $this->unitOfWork->acquireLock($memberId);
        $this->unitOfWork->recordChange($memberId, 'LOCK_ACQUIRED');

        return $this->store[$memberId] ?? null;
    }

    /**
     * Save a Member (must be called within transaction).
     * Lock is released when transaction commits.
     */
    public function save(Member $member): void {
        if (!$this->unitOfWork->isInTransaction()) {
            throw new \RuntimeException(
                "save() must be called within a transaction"
            );
        }

        $id = $member->getId();

        // Verify lock is held
        if (!$this->unitOfWork->isLocked($id)) {
            throw new \RuntimeException(
                "Cannot save Member {$id}: no lock held in current transaction"
            );
        }

        // Update store
        $this->store[$id] = $member;
        $this->unitOfWork->recordChange($id, 'SAVE');
    }

    /**
     * Load all members (for testing/admin, no locks)
     */
    public function findAll(): array {
        return $this->store;
    }

    /**
     * Clear repository (for testing)
     */
    public function clear(): void {
        $this->store = [];
        $this->lockedByOtherTransaction = [];
    }
}
